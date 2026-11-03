<?php

namespace App\Services\Chatbot;

use App\Enums\ArticleStatus;
use App\Enums\ChatbotState;
use App\Enums\SenderType;
use App\Models\ChatbotFlow;
use App\Models\ChatbotSession;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerArticle;
use App\Models\CustomerVerification;
use App\Services\Conversations\ConversationService;
use App\Services\Messaging\MessageSender;
use App\Services\Messaging\TemplateRenderer;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\Dui;
use App\Support\Phone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Chatbot basado en reglas.
 *
 * Seguridad: nunca muestra información financiera sin verificar la identidad con DUI + un segundo factor configurable
 * (phone_match: el mensaje proviene del número registrado; otp: código enviado al número registrado;
 * contract_number: últimos 4 dígitos de un contrato). La verificación expira tras un tiempo configurable.
 */
class ChatbotEngine
{
    private const FINANCIAL_INTENTS = ['balance', 'articles', 'installments', 'next_payment'];

    public function __construct(
        private IntentDetector $detector,
        private MessageSender $sender,
        private TemplateRenderer $renderer,
        private ConversationService $conversations,
    ) {}

    public function handle(Conversation $conversation, string $text): void
    {
        if (! setting('chatbot.enabled', true) || ! $conversation->isBotHandling()) {
            return;
        }

        $session = ChatbotSession::firstOrCreate(['conversation_id' => $conversation->id], ['state' => ChatbotState::Idle]);
        $session->forceFill(['last_interaction_at' => now()])->save();
        $this->expireVerification($session);

        $flow = $this->detector->detect($text);

        // Solicitar asesor siempre tiene prioridad, incluso a mitad de una verificación o con la sesión bloqueada.
        if ($flow?->intent === 'agent') {
            $this->conversations->requestAgent($conversation);

            return;
        }

        if ($session->isLocked()) {
            $this->reply($conversation, 'Por seguridad, la consulta de información está bloqueada temporalmente por varios intentos fallidos. Escribe *asesor* si necesitas ayuda de una persona.');

            return;
        }

        if (in_array($flow?->intent, ['menu', 'greeting'], true) && $session->state !== ChatbotState::Idle) {
            $session->forceFill(['state' => ChatbotState::Idle, 'pending_intent' => null])->save();
        }

        match ($session->state) {
            ChatbotState::AwaitingDui => $this->handleDui($conversation, $session, $text),
            ChatbotState::AwaitingSecondFactor => $this->handleSecondFactor($conversation, $session, $text),
            default => $this->handleIntent($conversation, $session, $flow, $text),
        };
    }

    /* ============================== Intenciones ============================== */

    private function handleIntent(Conversation $conversation, ChatbotSession $session, ?ChatbotFlow $flow, string $text): void
    {
        // Selección de artículo después de un listado ("1", "2"...)
        $options = $session->context['article_options'] ?? [];
        $digit = IntentDetector::normalize($text);
        if ($options && ctype_digit($digit) && isset($options[(int) $digit - 1]) && $session->isVerified()) {
            $this->clearContext($session, 'article_options');
            $this->replyArticleDetail($conversation, $session, (int) $options[(int) $digit - 1]);

            return;
        }

        if ($flow === null) {
            $this->reply($conversation, (string) setting('chatbot.fallback_message'));

            return;
        }

        if (in_array($flow->intent, self::FINANCIAL_INTENTS, true) || $flow->requires_verification) {
            if (! $session->isVerified()) {
                $session->forceFill(['state' => ChatbotState::AwaitingDui, 'pending_intent' => $flow->intent])->save();
                $this->reply($conversation, (string) setting('chatbot.ask_dui_message'));

                return;
            }
            $this->respondFinancial($conversation, $session, $flow->intent);

            return;
        }

        match ($flow->intent) {
            'greeting', 'menu' => $this->reply($conversation, $this->render((string) setting('chatbot.welcome_message'), $conversation)),
            'logout' => $this->logout($conversation, $session, $flow),
            default => $this->reply($conversation, $this->render($flow->response ?: (string) setting('chatbot.fallback_message'), $conversation)),
        };
    }

    private function logout(Conversation $conversation, ChatbotSession $session, ChatbotFlow $flow): void
    {
        $session->forceFill(['customer_id' => null, 'verified_at' => null, 'verification_expires_at' => null, 'state' => ChatbotState::Idle, 'context' => null])->save();
        $this->reply($conversation, $this->render((string) $flow->response, $conversation));
    }

    /* =========================== Verificación de identidad =========================== */

    private function handleDui(Conversation $conversation, ChatbotSession $session, string $text): void
    {
        $dui = Dui::normalize($text);
        if ($dui === null) {
            $this->reply($conversation, 'El DUI debe tener 9 dígitos, por ejemplo: 01234567-8. Inténtalo de nuevo o escribe *menú* para cancelar.');

            return;
        }

        $customer = Dui::isValid($dui) ? Customer::active()->where('dui', $dui)->with('articles')->first() : null;
        $method = (string) setting('chatbot.verification_method', 'phone_match');

        if ($customer === null) {
            $this->fail($conversation, $session, null, $method);

            return;
        }

        $context = array_merge($session->context ?? [], ['candidate_customer_id' => $customer->id]);

        switch ($method) {
            case 'otp':
                $code = (string) random_int(100000, 999999);
                CustomerVerification::create([
                    'customer_id' => $customer->id,
                    'conversation_id' => $conversation->id,
                    'phone' => (string) $customer->whatsapp_number,
                    'method' => 'otp',
                    'code_hash' => Hash::make($code),
                    'status' => 'pending',
                    'expires_at' => now()->addMinutes(10),
                ]);
                $this->sendOtp($conversation, $customer, $code);
                $session->forceFill(['state' => ChatbotState::AwaitingSecondFactor, 'context' => $context])->save();
                $this->reply($conversation, 'Para proteger tu información enviamos un código de 6 dígitos a tu número registrado terminado en '.substr((string) $customer->whatsapp_number, -4).'. Escríbelo aquí (válido por 10 minutos).');
                break;

            case 'contract_number':
                $session->forceFill(['state' => ChatbotState::AwaitingSecondFactor, 'context' => $context])->save();
                $this->reply($conversation, 'Gracias. Como segunda verificación, escribe los *últimos 4 dígitos* de cualquiera de tus números de contrato.');
                break;

            default: // phone_match
                if ($this->samePhone($customer, $conversation->phone)) {
                    $this->verified($conversation, $session, $customer, 'phone_match');
                } else {
                    $this->fail($conversation, $session, $customer, 'phone_match', 'Por seguridad, solo podemos mostrar información financiera cuando escribes desde el número de teléfono registrado en tu cuenta. Si cambiaste de número, escribe *asesor* para actualizarlo.');
                }
        }
    }

    private function handleSecondFactor(Conversation $conversation, ChatbotSession $session, string $text): void
    {
        $customer = Customer::with('articles')->find($session->context['candidate_customer_id'] ?? 0);
        $method = (string) setting('chatbot.verification_method', 'phone_match');
        $input = preg_replace('/\D/', '', $text);

        if (! $customer) {
            $session->forceFill(['state' => ChatbotState::Idle])->save();
            $this->reply($conversation, (string) setting('chatbot.fallback_message'));

            return;
        }

        $ok = false;
        if ($method === 'otp') {
            $verification = CustomerVerification::where('conversation_id', $conversation->id)->where('customer_id', $customer->id)
                ->where('status', 'pending')->where('expires_at', '>', now())->latest('id')->first();
            if ($verification) {
                $verification->increment('attempts');
                $ok = $input !== '' && Hash::check($input, (string) $verification->code_hash);
                if ($ok) {
                    $verification->update(['status' => 'verified', 'verified_at' => now()]);
                } elseif ($verification->attempts >= (int) setting('chatbot.max_failed_attempts', 3)) {
                    $verification->update(['status' => 'failed']);
                }
            }
        } else {
            $ok = strlen($input) === 4 && $customer->articles->contains(fn (CustomerArticle $a) => str_ends_with(preg_replace('/\D/', '', $a->contract_number), $input));
        }

        if ($ok) {
            $this->verified($conversation, $session, $customer, $method);

            return;
        }

        $this->fail($conversation, $session, $customer, $method, 'El dato de verificación no es correcto. Inténtalo nuevamente.', keepState: true);
    }

    private function verified(Conversation $conversation, ChatbotSession $session, Customer $customer, string $method): void
    {
        $intent = $session->pending_intent ?? 'balance';
        $session->forceFill([
            'customer_id' => $customer->id,
            'verified_at' => now(),
            'verification_expires_at' => now()->addMinutes((int) setting('chatbot.verification_ttl_minutes', 30)),
            'state' => ChatbotState::Idle,
            'pending_intent' => null,
            'failed_attempts' => 0,
            'context' => array_diff_key($session->context ?? [], ['candidate_customer_id' => 1]),
        ])->save();

        if ($method !== 'otp') {
            CustomerVerification::create([
                'customer_id' => $customer->id, 'conversation_id' => $conversation->id, 'phone' => $conversation->phone,
                'method' => $method, 'status' => 'verified', 'verified_at' => now(), 'attempts' => 1,
            ]);
        }

        // La conversación queda identificada con el cliente verificado.
        if ($conversation->customer_id === null) {
            $conversation->forceFill(['customer_id' => $customer->id])->save();
        }

        $this->conversations->event($conversation, '🔐 Identidad verificada ('.match ($method) {
            'otp' => 'código enviado al teléfono', 'contract_number' => 'número de contrato', default => 'número registrado'
        }.').');
        $this->respondFinancial($conversation, $session->fresh(), $intent, greeting: true);
    }

    private function fail(Conversation $conversation, ChatbotSession $session, ?Customer $customer, string $method, ?string $message = null, bool $keepState = false): void
    {
        $attempts = $session->failed_attempts + 1;
        $max = (int) setting('chatbot.max_failed_attempts', 3);

        if ($customer && $method !== 'otp') {
            CustomerVerification::create([
                'customer_id' => $customer->id, 'conversation_id' => $conversation->id, 'phone' => $conversation->phone,
                'method' => $method, 'status' => 'failed', 'attempts' => 1,
            ]);
        }

        if ($attempts >= $max) {
            $session->forceFill([
                'failed_attempts' => 0, 'state' => ChatbotState::Idle, 'pending_intent' => null,
                'locked_until' => now()->addMinutes((int) setting('chatbot.lock_minutes', 30)), 'context' => null,
            ])->save();
            $this->conversations->event($conversation, '⚠️ Verificación de identidad bloqueada por intentos fallidos.');
            $this->reply($conversation, 'No fue posible verificar tu identidad. Por seguridad bloqueamos la consulta temporalmente. Escribe *asesor* para que una persona de nuestro equipo te ayude.');

            return;
        }

        $session->forceFill(['failed_attempts' => $attempts, 'state' => $keepState ? $session->state : ChatbotState::AwaitingDui])->save();
        // Mensaje genérico: no se revela si el DUI existe o no.
        $this->reply($conversation, $message ?? 'No pudimos verificar tu identidad con los datos proporcionados. Revisa tu DUI e inténtalo de nuevo, o escribe *asesor*.');
    }

    private function expireVerification(ChatbotSession $session): void
    {
        if ($session->customer_id && ! $session->isVerified()) {
            $session->forceFill(['customer_id' => null, 'verified_at' => null, 'verification_expires_at' => null, 'context' => null])->save();
        }
    }

    private function samePhone(Customer $customer, string $phone): bool
    {
        $phone = Phone::normalize($phone);

        return $phone !== null && in_array($phone, array_filter([Phone::normalize($customer->phone_primary), Phone::normalize($customer->phone_secondary)]), true);
    }

    private function sendOtp(Conversation $conversation, Customer $customer, string $code): void
    {
        $text = "Tu código de verificación de {$this->render('{{empresa}}', $conversation)} es: *{$code}*. No lo compartas con nadie.";
        // El código se envía SIEMPRE al número registrado del cliente (no al número que está escribiendo).
        $message = $this->sender->create((string) $customer->whatsapp_number, $text, SenderType::System, [
            'customer_id' => $customer->id,
            'provider' => $conversation->is_simulated ? 'simulator' : app(WhatsAppManager::class)->activeName(),
            'meta' => ['otp' => true, 'conversation_id' => $conversation->id],
        ]);
        $this->sender->deliver($message);
    }

    /* ============================ Respuestas financieras ============================ */

    private function respondFinancial(Conversation $conversation, ChatbotSession $session, string $intent, bool $greeting = false): void
    {
        $customer = Customer::with(['articles', 'delinquencyStatus'])->findOrFail($session->customer_id);
        $open = $this->openArticles($customer);
        $intro = $greeting ? "Gracias. Hemos encontrado tu información, {$this->firstName($customer)}. ✅\n\n" : '';

        if ($open->isEmpty()) {
            $this->reply($conversation, $intro.'Actualmente no tienes saldos pendientes con nosotros. ¡Gracias por estar al día! 🎉'."\n\nEscribe *menú* para ver más opciones.");

            return;
        }

        $body = match ($intent) {
            'articles' => $this->articlesText($open),
            'installments' => $this->installmentsText($open),
            'next_payment' => $this->nextPaymentText($open),
            default => $this->balanceText($open, $customer),
        };

        $session->forceFill(['context' => array_merge($session->context ?? [], ['article_options' => $open->pluck('id')->all()])])->save();
        $this->reply($conversation, $intro.$body."\n\n¿Deseas consultar el detalle de algún artículo? Escribe su número (1".($open->count() > 1 ? '–'.$open->count() : '').') o *menú* para más opciones.');
    }

    private function balanceText(Collection $open, Customer $customer): string
    {
        $lines = ['Actualmente tienes '.$open->count().' '.($open->count() === 1 ? 'artículo' : 'artículos').' con saldo pendiente.'];
        foreach ($open->values() as $i => $a) {
            $lines[] = "\n*Artículo ".($i + 1).":*\n- {$a->description}\n- Saldo pendiente: ".money($a->balance)."\n- Cuotas pendientes: {$a->installments_pending}";
        }
        $lines[] = "\n*Saldo total pendiente: ".money($open->sum(fn ($a) => (float) $a->balance)).'*';
        if ($customer->days_overdue > 0) {
            $lines[] = "Tu cuenta presenta {$customer->days_overdue} días de atraso.";
        }

        return implode("\n", $lines);
    }

    private function articlesText(Collection $open): string
    {
        $lines = ['Estos son tus artículos con saldo:'];
        foreach ($open->values() as $i => $a) {
            $lines[] = "\n*".($i + 1).". {$a->description}*\n- Contrato: {$a->contract_number}".($a->acquired_at ? "\n- Adquirido: ".$a->acquired_at->format('d/m/Y') : '')."\n- Estado: {$a->status->label()}";
        }

        return implode("\n", $lines);
    }

    private function installmentsText(Collection $open): string
    {
        $lines = ['Detalle de tus cuotas:'];
        foreach ($open->values() as $i => $a) {
            $lines[] = "\n*".($i + 1).". {$a->description}*\n- Cuotas pagadas: {$a->installments_paid} de {$a->installments_count}\n- Cuotas pendientes: {$a->installments_pending}\n- Valor de cuota: ".money($a->installment_amount);
        }
        $lines[] = "\nTotal de cuotas pendientes: *".$open->sum('installments_pending').'*';

        return implode("\n", $lines);
    }

    private function nextPaymentText(Collection $open): string
    {
        $lines = ['Tus próximas fechas de pago:'];
        foreach ($open->values() as $i => $a) {
            $date = $a->next_payment_date?->format('d/m/Y') ?? 'por confirmar';
            $late = $a->days_overdue > 0 ? " ⚠️ (vencida hace {$a->days_overdue} días)" : '';
            $lines[] = "\n*".($i + 1).". {$a->description}*\n- Fecha: {$date}{$late}\n- Monto: ".money($a->installment_amount);
        }

        return implode("\n", $lines);
    }

    private function replyArticleDetail(Conversation $conversation, ChatbotSession $session, int $articleId): void
    {
        $a = CustomerArticle::where('customer_id', $session->customer_id)->find($articleId);
        if (! $a) {
            $this->reply($conversation, (string) setting('chatbot.fallback_message'));

            return;
        }

        $this->reply($conversation, implode("\n", array_filter([
            "*{$a->description}*",
            "Código: {$a->article_code}",
            "Contrato: {$a->contract_number}",
            $a->acquired_at ? 'Fecha de adquisición: '.$a->acquired_at->format('d/m/Y') : null,
            'Precio: '.money($a->original_price).' · Prima: '.money($a->down_payment),
            "Cuotas: {$a->installments_paid} pagadas de {$a->installments_count} ({$a->installments_pending} pendientes)",
            'Valor de cuota: '.money($a->installment_amount),
            'Saldo pendiente: *'.money($a->balance).'*',
            'Próximo pago: '.($a->next_payment_date?->format('d/m/Y') ?? '—').($a->days_overdue > 0 ? " (vencido hace {$a->days_overdue} días)" : ''),
            "\n¿Necesitas algo más? Escribe *menú* o *asesor*.",
        ])));
    }

    /** @return Collection<int, CustomerArticle> */
    private function openArticles(Customer $customer): Collection
    {
        return $customer->articles
            ->filter(fn (CustomerArticle $a) => (float) $a->balance > 0 && in_array($a->status, [ArticleStatus::Active, ArticleStatus::InCollection], true))
            ->sortBy(fn (CustomerArticle $a) => $a->next_payment_date?->timestamp ?? PHP_INT_MAX)
            ->values();
    }

    /* ================================ Utilidades ================================ */

    private function reply(Conversation $conversation, string $text): void
    {
        $this->sender->replyInConversation($conversation, $text, SenderType::Bot);
    }

    private function render(string $text, Conversation $conversation): string
    {
        // Sin cliente: el bot no expone datos personales en mensajes generales.
        return $this->renderer->render($text);
    }

    private function firstName(Customer $customer): string
    {
        return strtok($customer->full_name, ' ') ?: $customer->full_name;
    }

    private function clearContext(ChatbotSession $session, string $key): void
    {
        $context = $session->context ?? [];
        unset($context[$key]);
        $session->forceFill(['context' => $context ?: null])->save();
    }
}
