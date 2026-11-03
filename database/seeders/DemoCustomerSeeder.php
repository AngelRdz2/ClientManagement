<?php

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Enums\CustomerStatus;
use App\Enums\PaymentMethod;
use App\Enums\StatusChangeSource;
use App\Models\Customer;
use App\Models\CustomerArticle;
use App\Models\CustomerGroup;
use App\Models\CustomerPayment;
use App\Models\User;
use App\Services\DelinquencyService;
use App\Support\Dui;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Datos de DEMOSTRACIÓN: clientes, artículos y pagos coherentes entre sí (saldos = cuotas pendientes × valor de cuota,
 * días de mora calculados desde la próxima fecha de pago). Solo para desarrollo y pruebas.
 */
class DemoCustomerSeeder extends Seeder
{
    private const FIRST_NAMES = ['José', 'María', 'Juan', 'Ana', 'Carlos', 'Rosa', 'Luis', 'Carmen', 'Jorge', 'Marta', 'Miguel', 'Gloria', 'Francisco', 'Sonia', 'Mario', 'Claudia', 'Óscar', 'Patricia', 'Roberto', 'Karla', 'Manuel', 'Silvia', 'Ricardo', 'Verónica', 'Fernando', 'Yesenia', 'Alexander', 'Beatriz', 'Edwin', 'Marisol', 'Nelson', 'Iris', 'Rafael', 'Lorena', 'William', 'Jessica', 'Douglas', 'Evelyn', 'Héctor', 'Fátima'];

    private const SECOND_NAMES = ['Antonio', 'Elena', 'Alberto', 'Isabel', 'Ernesto', 'Guadalupe', 'Enrique', 'Beatriz', 'Arturo', 'del Carmen', 'Alfredo', 'Margarita', 'Eduardo', 'Esperanza', 'Armando', 'Lucía'];

    private const LAST_NAMES = ['Hernández', 'García', 'Martínez', 'López', 'Rodríguez', 'Pérez', 'Ramírez', 'Flores', 'Cruz', 'Rivera', 'Gómez', 'Díaz', 'Reyes', 'Morales', 'Ramos', 'Aguilar', 'Mejía', 'Castillo', 'Romero', 'Ortiz', 'Alvarado', 'Rivas', 'Guevara', 'Portillo', 'Menjívar', 'Escobar', 'Orellana', 'Chávez', 'Quintanilla', 'Argueta', 'Henríquez', 'Ayala', 'Velásquez', 'Bonilla', 'Mendoza'];

    private const CITIES = ['San Salvador', 'Santa Tecla', 'Soyapango', 'San Miguel', 'Santa Ana', 'Mejicanos', 'Apopa', 'Ilopango', 'Antiguo Cuscatlán', 'Usulután', 'Sonsonate', 'La Libertad', 'Ahuachapán', 'Chalatenango', 'Zacatecoluca'];

    /** [código, descripción, precio mínimo, precio máximo] */
    private const PRODUCTS = [
        ['TV-SAM55', 'Televisor Samsung 55" 4K', 520, 780],
        ['TV-LG43', 'Televisor LG 43" Smart', 330, 450],
        ['REF-LG14', 'Refrigeradora LG 14 pies', 650, 900],
        ['REF-MAB10', 'Refrigeradora Mabe 10 pies', 420, 560],
        ['LAV-WHR18', 'Lavadora Whirlpool 18 kg', 480, 690],
        ['COC-MAB4', 'Cocina Mabe 4 quemadores', 280, 420],
        ['LAP-HP15', 'Laptop HP 15" Core i5', 620, 880],
        ['CEL-A55', 'Celular Samsung Galaxy A55', 380, 520],
        ['CEL-IP13', 'iPhone 13 128 GB', 690, 850],
        ['SAL-3P', 'Juego de sala 3 piezas', 590, 1100],
        ['CAM-MAT', 'Cama matrimonial con colchón', 390, 720],
        ['MOT-HON125', 'Motocicleta Honda 125cc', 1450, 2200],
        ['AC-12K', 'Aire acondicionado 12,000 BTU', 430, 640],
        ['MIC-PAN', 'Microondas Panasonic', 110, 180],
        ['COM-6S', 'Comedor 6 sillas', 480, 850],
        ['SON-LG', 'Equipo de sonido LG XBOOM', 260, 410],
    ];

    /** Distribución objetivo de días de atraso: [probabilidad acumulada %, mínimo, máximo] */
    private const OVERDUE_BUCKETS = [[55, 0, 0], [65, 2, 9], [76, 10, 29], [85, 30, 59], [91, 60, 89], [95, 90, 119], [98, 120, 179], [100, 180, 260]];

    public function run(int $count = 150): void
    {
        $service = app(DelinquencyService::class);
        $service->flushCache();
        $today = CarbonImmutable::today();
        $admin = User::where('email', 'admin@crm.test')->first();
        $usedPhones = [];

        $groups = collect([
            ['Clientes VIP', 'Clientes con excelente historial de pago', '#6366f1'],
            ['Zona Oriente', 'Clientes de San Miguel y Usulután', '#0ea5e9'],
            ['Cartera 2025', 'Créditos otorgados durante 2025', '#f59e0b'],
        ])->map(fn ($g) => CustomerGroup::firstOrCreate(['name' => $g[0]], ['description' => $g[1], 'color' => $g[2]]));

        // Cliente fijo usado en el ejemplo del chatbot (DUI 01234567-8).
        $this->createCustomer(1, [
            'full_name' => 'María Fernanda López Hernández',
            'dui' => '01234567-8',
            'phone_primary' => '7012-3456',
            'accepts_promotions' => true,
        ], [
            ['TV-SAM55', 'Televisor Samsung 55" 4K', 600, 12, 50.00, 5, 12],
            ['REF-LG14', 'Refrigeradora LG 14 pies', 720, 12, 60.00, 3, 12],
        ], $today, $service, $admin, $groups, $usedPhones);

        for ($i = 2; $i <= $count; $i++) {
            $first = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
            $name = $first.(random_int(0, 1) ? ' '.self::SECOND_NAMES[array_rand(self::SECOND_NAMES)] : '')
                .' '.self::LAST_NAMES[array_rand(self::LAST_NAMES)].' '.self::LAST_NAMES[array_rand(self::LAST_NAMES)];

            $this->createCustomer($i, ['full_name' => $name], null, $today, $service, $admin, $groups, $usedPhones);
        }

        // Algunos clientes inactivos y uno con número de prueba que siempre falla en el simulador.
        Customer::inRandomOrder()->where('id', '>', 1)->limit(6)->update(['status' => CustomerStatus::Inactive]);
        Customer::where('id', 2)->first()?->update(['phone_primary' => '7777-0000', 'notes' => 'Número de prueba: el simulador siempre falla al enviar a números terminados en 0000.']);
    }

    /**
     * @param  list<array{0:string,1:string,2:float,3:int,4:float,5:int,6:int}>|null  $fixedArticles
     *                                                                                                [código, descripción, precio, cuotas totales, valor cuota, cuotas pendientes, días de vencida la próxima cuota]
     */
    private function createCustomer(int $i, array $attributes, ?array $fixedArticles, CarbonImmutable $today, DelinquencyService $service, ?User $admin, $groups, array &$usedPhones): void
    {
        do {
            $phone = (random_int(0, 3) ? '7' : '6').random_int(100, 999).'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (isset($usedPhones[$phone]) || str_ends_with($phone, '0000'));
        $usedPhones[$phone] = true;

        $registered = $today->subDays(random_int(60, 900));
        $acceptsPromotions = $attributes['accepts_promotions'] ?? (random_int(1, 100) <= 62);
        $slug = mb_strtolower(str_replace(' ', '.', implode(' ', array_slice(explode(' ', $attributes['full_name']), 0, 2))));

        $customer = Customer::create(array_merge([
            'code' => 'CLI-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
            'dui' => Dui::fromBase(str_pad((string) random_int(1_000_000, 69_999_999), 8, '0', STR_PAD_LEFT)),
            'phone_primary' => $phone,
            'phone_secondary' => random_int(1, 100) <= 30 ? '2'.random_int(200, 899).'-'.random_int(1000, 9999) : null,
            'email' => random_int(1, 100) <= 55 ? iconv('UTF-8', 'ASCII//TRANSLIT', $slug).random_int(1, 99).'@correo.test' : null,
            'address' => 'Col. '.self::LAST_NAMES[array_rand(self::LAST_NAMES)].', calle principal #'.random_int(1, 120).', '.self::CITIES[array_rand(self::CITIES)],
            'registered_at' => $registered,
            'status' => CustomerStatus::Active,
            'accepts_collection_messages' => true,
            'accepts_promotions' => $acceptsPromotions,
            'promotions_consent_at' => $acceptsPromotions ? $registered : null,
            'promotions_consent_source' => $acceptsPromotions ? 'contrato' : null,
            'whatsapp_opted_out_at' => random_int(1, 100) <= 3 ? $today->subDays(random_int(5, 60)) : null,
            'created_by' => $admin?->id,
        ], $attributes));

        $articles = $fixedArticles ?? $this->randomArticles($today);
        foreach ($articles as $n => [$code, $description, $price, $count, $installment, $pending, $overdueDays]) {
            $paid = $count - $pending;
            $nextPayment = $pending > 0 ? $today->subDays($overdueDays) : null;
            $acquired = ($nextPayment ?? $today)->subMonths($paid + 1);

            $article = CustomerArticle::create([
                'customer_id' => $customer->id,
                'article_code' => $code,
                'description' => $description,
                'contract_number' => 'CT-'.$acquired->format('Y').'-'.str_pad((string) ($customer->id * 10 + $n), 6, '0', STR_PAD_LEFT),
                'acquired_at' => $acquired,
                'original_price' => $price,
                'down_payment' => round($price - ($installment * $count) / 1.18, 2) > 0 ? round($price - ($installment * $count) / 1.18, 2) : 0,
                'installments_count' => $count,
                'installment_amount' => $installment,
                'installments_paid' => $paid,
                'balance' => round($pending * $installment, 2),
                'next_payment_date' => $nextPayment,
                'days_overdue' => max(0, $overdueDays),
                'status' => $pending > 0 ? ($overdueDays >= 90 ? ArticleStatus::InCollection : ArticleStatus::Active) : ArticleStatus::PaidOff,
            ]);

            // Pagos de los últimos meses (uno por cuota pagada, en fechas mensuales).
            for ($p = 1; $p <= min($paid, 8); $p++) {
                $paidAt = ($nextPayment ?? $today)->subMonths($p)->addDays(random_int(-3, 4));
                if ($paidAt->gt($today)) {
                    continue;
                }
                CustomerPayment::create([
                    'customer_id' => $customer->id,
                    'customer_article_id' => $article->id,
                    'amount' => $installment,
                    'installments_covered' => 1,
                    'paid_at' => $paidAt,
                    'method' => collect(PaymentMethod::cases())->random(),
                    'reference' => 'REC-'.random_int(100000, 999999),
                    'registered_by' => $admin?->id,
                ]);
            }
        }

        $customer->refreshFinancialSummary();
        $days = $service->daysOverdueFromArticles($customer);
        $service->changeStatus($customer, $service->statusForDays($days), $days, StatusChangeSource::System, 'Carga inicial de datos de demostración');

        if (random_int(1, 100) <= 25) {
            $customer->groups()->attach($groups->random()->id);
        }
    }

    private function randomArticles(CarbonImmutable $today): array
    {
        $roll = random_int(1, 100);
        $bucket = collect(self::OVERDUE_BUCKETS)->first(fn ($b) => $roll <= $b[0]);
        $customerOverdue = random_int($bucket[1], $bucket[2]);

        $articles = [];
        $amount = random_int(1, 100) <= 60 ? 1 : (random_int(1, 100) <= 75 ? 2 : 3);
        foreach (collect(self::PRODUCTS)->random($amount) as $index => [$code, $description, $min, $max]) {
            $price = random_int($min, $max);
            $count = [12, 18, 24, 36][array_rand([12, 18, 24, 36])];
            $installment = round($price * 1.18 / $count, 2);
            $fullyPaid = $customerOverdue === 0 && random_int(1, 100) <= 15;
            $pending = $fullyPaid ? 0 : random_int(1, $count - 1);
            // El primer artículo define la mora del cliente; los demás pueden estar al día.
            $overdue = $index === 0 ? $customerOverdue : (random_int(0, 1) ? $customerOverdue : -random_int(1, 25));
            $articles[] = [$code, $description, $price, $count, $installment, $pending, $overdue];
        }

        return $articles;
    }
}
