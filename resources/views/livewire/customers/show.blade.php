<div>
    {{-- Encabezado del cliente --}}
    <a href="{{ route('customers.index') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-indigo-600">
        <x-icon name="chevron-left" class="size-3.5" /> Clientes
    </a>

    <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200/80 bg-surface shadow-card">
        <div class="h-1.5" style="background: linear-gradient(90deg, {{ $customer->delinquencyStatus?->color ?? '#94a3b8' }}, #6366f1);"></div>
        <div class="flex flex-col gap-5 p-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-4">
                <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500 to-navy-700 text-lg font-bold text-white shadow-lg shadow-indigo-500/20">
                    {{ collect(explode(' ', $customer->full_name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
                </span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-bold tracking-tight text-slate-900">{{ $customer->full_name }}</h1>
                        <x-badge :color="$customer->status->color()" dot>{{ $customer->status->label() }}</x-badge>
                        <x-status-pill :status="$customer->delinquencyStatus" />
                    </div>
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500">
                        <span class="inline-flex items-center gap-1.5 font-mono text-xs"><x-icon name="tag" class="size-3.5" /> {{ $customer->code }}</span>
                        <span class="inline-flex items-center gap-1.5"><x-icon name="credit-card" class="size-3.5" /> DUI {{ $customer->dui }}</span>
                        <span class="inline-flex items-center gap-1.5"><x-icon name="phone" class="size-3.5" /> {{ $customer->phone_primary }}</span>
                        @if ($customer->email)<span class="inline-flex items-center gap-1.5"><x-icon name="mail" class="size-3.5" /> {{ $customer->email }}</span>@endif
                    </div>
                    @if ($customer->groups->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($customer->groups as $group)
                                <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600"><span class="size-1.5 rounded-full" style="background: {{ $group->color }}"></span>{{ $group->name }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('messages.send')
                    <x-button size="sm" icon="send" wire:click="openMessage">Enviar mensaje</x-button>
                @endcan
            @can('customers.manage')
                    <x-button variant="secondary" size="sm" icon="refresh-cw" wire:click="openStatusModal">Cambiar estado</x-button>
                    <x-button variant="secondary" size="sm" icon="pencil" :href="route('customers.edit', $customer)">Editar</x-button>
                    <x-button :variant="$customer->isActive() ? 'ghost' : 'success'" size="sm" :icon="$customer->isActive() ? 'user-x' : 'user-check'"
                              x-on:click="$confirm({ title: {{ \Illuminate\Support\Js::from($customer->isActive() ? '¿Desactivar cliente?' : '¿Reactivar cliente?') }}, text: {{ \Illuminate\Support\Js::from($customer->isActive() ? 'No recibirá mensajes ni aparecerá en campañas.' : 'Volverá a estar disponible.') }}, danger: {{ \Illuminate\Support\Js::from($customer->isActive()) }} }, () => $wire.toggleActive())">
                        {{ $customer->isActive() ? 'Desactivar' : 'Reactivar' }}
                    </x-button>
            @endcan
            </div>
        </div>
    </div>

    {{-- Resumen financiero --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div class="col-span-2 rounded-2xl bg-gradient-to-br from-navy-900 to-indigo-800 p-5 text-white shadow-elevated lg:col-span-1">
            <p class="text-xs font-medium text-indigo-200">Saldo pendiente total</p>
            <p class="mt-2 text-3xl font-bold tracking-tight tabular-nums">{{ money($customer->outstanding_balance) }}</p>
            <p class="mt-1 text-xs text-indigo-200">{{ $summary['active_articles'] }} {{ $summary['active_articles'] === 1 ? 'cuenta activa' : 'cuentas activas' }}</p>
        </div>
        <x-stat-card label="Cuotas pendientes" :value="$customer->pending_installments" icon="list-checks" color="amber" :hint="'Cuota mensual: '.money($summary['monthly'])" />
        <x-stat-card label="Próxima fecha de pago" :value="$customer->next_payment_date?->format('d/m/Y') ?? '—'" icon="calendar" color="blue"
                     :hint="$customer->days_overdue > 0 ? $customer->days_overdue.' días de mora' : 'Al día'" :alert="$customer->days_overdue >= 90" />
        <x-stat-card label="Total pagado" :value="money($summary['paid'])" icon="banknote" color="green" :hint="$counts['payments'].' pagos registrados'" />
        <x-stat-card label="Monto financiado" :value="money($summary['original'])" icon="wallet" color="slate" :hint="'Cliente desde '.$customer->registered_at->format('d/m/Y')" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-4">
        {{-- Pestañas --}}
        <div class="min-w-0 xl:col-span-3">
            <div class="mb-4 flex gap-1 overflow-x-auto rounded-xl bg-slate-200/50 p-1 scrollbar-thin">
                @foreach ([
                    'articles' => ['Artículos', 'package', $articles->count()],
                    'payments' => ['Pagos', 'banknote', $counts['payments']],
                    'history' => ['Historial de mora', 'history', $counts['history']],
                    'messages' => ['Mensajes', 'send', $counts['messages']],
                    'conversations' => ['Conversaciones', 'messages-square', $counts['conversations']],
                ] as $key => [$label, $icon, $count])
                    <button wire:click="setTab('{{ $key }}')" @class(['flex shrink-0 items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition', 'bg-surface text-slate-900 shadow-sm' => $tab === $key, 'text-slate-500 hover:text-slate-800' => $tab !== $key])>
                        <x-icon :name="$icon" class="size-4" /> {{ $label }}
                        <span class="rounded-full bg-slate-100 px-1.5 text-[11px] text-slate-500">{{ $count }}</span>
                    </button>
                @endforeach
            </div>

            <div wire:loading.class="opacity-60" wire:target="setTab">
                @if ($tab === 'articles')
                    <div class="space-y-4">
                        @can('customers.manage')
                            <div class="flex justify-end"><x-button size="sm" icon="plus" wire:click="createArticle">Agregar artículo</x-button></div>
                        @endcan
                        @forelse ($articles as $a)
                            <div wire:key="art-{{ $a->id }}" class="rounded-2xl border border-slate-200/80 bg-surface p-5 shadow-card">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex items-start gap-3">
                                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-indigo-50 text-indigo-600"><x-icon name="package" class="size-5" /></span>
                                        <div>
                                            <p class="font-semibold text-slate-900">{{ $a->description }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500">
                                                <span class="font-mono">{{ $a->article_code }}</span> · Contrato <span class="font-mono">{{ $a->contract_number }}</span>
                                                @if ($a->acquired_at) · Adquirido {{ $a->acquired_at->format('d/m/Y') }} @endif
                                            </p>
                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                <x-badge :color="$a->status->color()">{{ $a->status->label() }}</x-badge>
                                                @if ($a->days_overdue > 0)
                                                    <x-badge :color="$a->days_overdue >= 60 ? 'red' : 'amber'">{{ $a->days_overdue }} días de atraso</x-badge>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-left sm:text-right">
                                        <p class="text-xs text-slate-500">Saldo pendiente</p>
                                        <p class="text-xl font-bold text-slate-900 tabular-nums">{{ money($a->balance) }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 text-sm sm:grid-cols-3 lg:grid-cols-6">
                                    <div><p class="text-[11px] text-slate-500">Precio original</p><p class="font-semibold text-slate-800">{{ money($a->original_price) }}</p></div>
                                    <div><p class="text-[11px] text-slate-500">Prima pagada</p><p class="font-semibold text-slate-800">{{ money($a->down_payment) }}</p></div>
                                    <div><p class="text-[11px] text-slate-500">Valor de cuota</p><p class="font-semibold text-slate-800">{{ money($a->installment_amount) }}</p></div>
                                    <div><p class="text-[11px] text-slate-500">Cuotas pagadas</p><p class="font-semibold text-slate-800">{{ $a->installments_paid }} / {{ $a->installments_count }}</p></div>
                                    <div><p class="text-[11px] text-slate-500">Cuotas pendientes</p><p class="font-semibold text-slate-800">{{ $a->installments_pending }}</p></div>
                                    <div><p class="text-[11px] text-slate-500">Próximo pago</p><p @class(['font-semibold', 'text-rose-600' => $a->days_overdue > 0, 'text-slate-800' => $a->days_overdue === 0])>{{ $a->next_payment_date?->format('d/m/Y') ?? '—' }}</p></div>
                                </div>

                                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <div class="flex-1">
                                        <div class="mb-1 flex justify-between text-[11px] text-slate-500"><span>Avance del crédito</span><span>{{ $a->progressPercent() }}%</span></div>
                                        <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-emerald-500" style="width: {{ $a->progressPercent() }}%"></div></div>
                                    </div>
                                    @can('customers.manage')
                                        <div class="flex gap-1.5">
                                            @if ((float) $a->balance > 0)
                                                <x-button size="sm" variant="success" icon="banknote" wire:click="openPayment({{ $a->id }})">Registrar pago</x-button>
                                            @endif
                                            <x-button size="sm" variant="secondary" icon="pencil" wire:click="editArticle({{ $a->id }})">Editar</x-button>
                                            <button type="button" title="Eliminar" class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                                    x-on:click="$confirm({ title: '¿Eliminar artículo?', text: @js($a->description), danger: true, confirmText: 'Eliminar' }, () => $wire.deleteArticle({{ $a->id }}))">
                                                <x-icon name="trash-2" class="size-4" />
                                            </button>
                                        </div>
                                    @endcan
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-surface">
                                <x-empty-state icon="package" title="Sin artículos registrados" description="Agrega los artículos o productos adquiridos por el cliente." />
                            </div>
                        @endforelse
                    </div>
                @else
                    <x-table-wrapper>
                        @if ($tab === 'payments')
                            <table class="table-base">
                                <thead><tr><th>Fecha</th><th>Artículo</th><th>Monto</th><th>Cuotas</th><th>Forma</th><th>Referencia</th><th>Registró</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($tabData as $p)
                                        <tr wire:key="p-{{ $p->id }}">
                                            <td class="whitespace-nowrap">{{ $p->paid_at->format('d/m/Y') }}</td>
                                            <td><span class="block max-w-[220px] truncate">{{ $p->article?->description }}</span><span class="font-mono text-xs text-slate-400">{{ $p->article?->contract_number }}</span></td>
                                            <td class="font-semibold text-emerald-700 tabular-nums">{{ money($p->amount) }}</td>
                                            <td>{{ $p->installments_covered }}</td>
                                            <td><x-badge :color="$p->method->color()">{{ $p->method->label() }}</x-badge></td>
                                            <td class="font-mono text-xs">{{ $p->reference ?? '—' }}</td>
                                            <td class="text-xs text-slate-500">{{ $p->registeredBy?->name ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7"><x-empty-state icon="banknote" title="Sin pagos registrados" /></td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        @elseif ($tab === 'history')
                            <table class="table-base">
                                <thead><tr><th>Fecha</th><th>Estado anterior</th><th>Estado nuevo</th><th>Días</th><th>Origen</th><th>Motivo</th><th>Usuario</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($tabData as $h)
                                        <tr wire:key="h-{{ $h->id }}">
                                            <td class="whitespace-nowrap">{{ $h->created_at->format('d/m/Y h:i a') }}</td>
                                            <td><x-status-pill :status="$h->fromStatus" size="xs" /></td>
                                            <td><x-status-pill :status="$h->toStatus" size="xs" /></td>
                                            <td class="text-xs whitespace-nowrap text-slate-500">{{ $h->from_days_overdue ?? '—' }} → {{ $h->to_days_overdue ?? '—' }}</td>
                                            <td><x-badge :color="$h->source->color()">{{ $h->source->label() }}</x-badge></td>
                                            <td class="max-w-[260px] text-xs text-slate-600">{{ $h->reason ?? '—' }}</td>
                                            <td class="text-xs text-slate-500">{{ $h->user?->name ?? 'Sistema' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7"><x-empty-state icon="history" title="Sin cambios de estado" /></td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        @elseif ($tab === 'messages')
                            <table class="table-base">
                                <thead><tr><th>Fecha</th><th>Dirección</th><th>Mensaje</th><th>Origen</th><th>Estado</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($tabData as $m)
                                        <tr wire:key="m-{{ $m->id }}">
                                            <td class="whitespace-nowrap text-xs">{{ $m->created_at->format('d/m/Y h:i a') }}</td>
                                            <td><x-badge :color="$m->direction->color()">{{ $m->direction->label() }}</x-badge></td>
                                            <td class="max-w-md"><p class="line-clamp-2 text-xs text-slate-600">{{ $m->body }}</p>@if ($m->error_message)<p class="mt-0.5 text-[11px] text-rose-600">{{ $m->error_message }}</p>@endif</td>
                                            <td class="text-xs">{{ $m->sender_type->label() }}@if ($m->campaign)<span class="block text-slate-400">{{ $m->campaign->name }}</span>@endif</td>
                                            <td><x-badge :color="$m->status->color()">{{ $m->status->label() }}</x-badge></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5"><x-empty-state icon="send" title="Sin mensajes" description="Aquí aparecerán los mensajes de WhatsApp enviados y recibidos." /></td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        @elseif ($tab === 'conversations')
                            <table class="table-base">
                                <thead><tr><th>Último mensaje</th><th>Vista previa</th><th>Estado</th><th>Asesor</th><th>Mensajes</th><th></th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($tabData as $c)
                                        <tr wire:key="cv-{{ $c->id }}">
                                            <td class="text-xs whitespace-nowrap">{{ $c->last_message_at?->format('d/m/Y h:i a') ?? '—' }}</td>
                                            <td class="max-w-xs truncate text-xs text-slate-600">{{ $c->last_message_preview }}</td>
                                            <td><x-badge :color="$c->status->color()">{{ $c->status->label() }}</x-badge></td>
                                            <td class="text-xs">{{ $c->assignee?->name ?? '—' }}</td>
                                            <td>{{ $c->entries_count }}</td>
                                            <td class="text-right">@if (Route::has('conversations.index'))<x-button size="xs" variant="soft" :href="route('conversations.index', ['c' => $c->id])">Abrir</x-button>@endif</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6"><x-empty-state icon="messages-square" title="Sin conversaciones" /></td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        @endif
                        @if ($tabData && $tabData->hasPages())
                            <x-slot:footer>{{ $tabData->links() }}</x-slot:footer>
                        @endif
                    </x-table-wrapper>
                @endif
            </div>
        </div>

        {{-- Información lateral --}}
        <div class="space-y-6">
            <x-card title="Información" icon="info">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs text-slate-500">Dirección</dt><dd class="text-slate-800">{{ $customer->address ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Teléfono secundario</dt><dd class="text-slate-800">{{ $customer->phone_secondary ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">WhatsApp (normalizado)</dt><dd class="font-mono text-slate-800">+{{ $customer->whatsapp_number }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Fecha de registro</dt><dd class="text-slate-800">{{ $customer->registered_at->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Días de mora</dt><dd class="text-slate-800">{{ $customer->days_overdue }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Registrado por</dt><dd class="text-slate-800">{{ $customer->creator?->name ?? 'Importación / sistema' }}</dd></div>
                </dl>
                @if ($customer->notes)
                    <div class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-900 ring-1 ring-amber-200"><p class="mb-1 font-semibold">Observaciones</p>{{ $customer->notes }}</div>
                @endif
            </x-card>

            <x-card title="Comunicación" icon="message-circle">
                <ul class="space-y-2.5 text-sm">
                    <li class="flex items-center justify-between"><span class="text-slate-600">Mensajes de cobranza</span>
                        @if ($customer->canReceiveCollectionMessages())<x-badge color="green">Sí recibe</x-badge>@else<x-badge color="slate" title="Solvente, excluido, inactivo o sin consentimiento">No recibe</x-badge>@endif
                    </li>
                    <li class="flex items-center justify-between"><span class="text-slate-600">Promociones</span>
                        @if ($customer->canReceivePromotions())<x-badge color="green">Autorizado</x-badge>@else<x-badge color="slate">No autorizado</x-badge>@endif
                    </li>
                    @if ($customer->promotions_consent_at)
                        <li class="text-xs text-slate-500">Consentimiento: {{ $customer->promotions_consent_at->format('d/m/Y') }} ({{ $customer->promotions_consent_source }})</li>
                    @endif
                    @if ($customer->whatsapp_opted_out_at)
                        <li class="rounded-lg bg-rose-50 p-2 text-xs text-rose-700">Solicitó la baja de WhatsApp el {{ $customer->whatsapp_opted_out_at->format('d/m/Y') }}.</li>
                    @endif
                </ul>
            </x-card>

            @can('customers.manage')
                <x-card title="Cobranza" icon="gauge">
                    <p class="text-xs text-slate-500">Recalcula días y estado de mora a partir de las próximas fechas de pago de los artículos.</p>
                    <x-button variant="soft" size="sm" icon="refresh-cw" class="mt-3 w-full" wire:click="recalculateStatus">Recalcular estado</x-button>
                </x-card>
            @endcan
        </div>
    </div>

    {{-- Modal: artículo --}}
    <x-modal wire:model="showArticleModal" :title="$editingArticleId ? 'Editar artículo' : 'Agregar artículo'" description="Datos del crédito asociado al artículo" icon="package" maxWidth="3xl">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
            <x-field label="Código de artículo" error="article.article_code" required class="sm:col-span-2"><x-input wire:model="article.article_code" class="font-mono" /></x-field>
            <x-field label="Descripción" error="article.description" required class="sm:col-span-4"><x-input wire:model="article.description" placeholder="Ej. Televisor Samsung 55&quot;" /></x-field>
            <x-field label="Número de contrato" error="article.contract_number" required class="sm:col-span-2"><x-input wire:model="article.contract_number" class="font-mono" /></x-field>
            <x-field label="Fecha de adquisición" error="article.acquired_at" class="sm:col-span-2"><x-input type="date" wire:model="article.acquired_at" /></x-field>
            <x-field label="Estado del artículo" error="article.status" class="sm:col-span-2"><x-select wire:model="article.status" :options="\App\Enums\ArticleStatus::options()" /></x-field>
            <x-field label="Precio original ($)" error="article.original_price" required class="sm:col-span-2"><x-input type="number" step="0.01" min="0" wire:model="article.original_price" /></x-field>
            <x-field label="Prima pagada ($)" error="article.down_payment" required class="sm:col-span-2"><x-input type="number" step="0.01" min="0" wire:model="article.down_payment" /></x-field>
            <x-field label="Valor de cuota ($)" error="article.installment_amount" required class="sm:col-span-2"><x-input type="number" step="0.01" min="0" wire:model="article.installment_amount" /></x-field>
            <x-field label="Cantidad de cuotas" error="article.installments_count" required class="sm:col-span-2"><x-input type="number" min="0" wire:model="article.installments_count" /></x-field>
            <x-field label="Cuotas pagadas" error="article.installments_paid" required class="sm:col-span-2"><x-input type="number" min="0" wire:model="article.installments_paid" /></x-field>
            <x-field label="Próxima fecha de pago" error="article.next_payment_date" class="sm:col-span-2"><x-input type="date" wire:model="article.next_payment_date" /></x-field>
            <x-field label="Saldo pendiente ($)" error="article.balance" required class="sm:col-span-3" hint="Cuotas pendientes se calculan automáticamente (total − pagadas).">
                <div class="flex gap-2">
                    <x-input type="number" step="0.01" min="0" wire:model="article.balance" />
                    <x-button variant="soft" size="sm" wire:click="suggestBalance" title="Calcular: cuotas pendientes × valor de cuota">Calcular</x-button>
                </div>
            </x-field>
        </div>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="show = false">Cancelar</x-button>
            <x-button icon="check" wire:click="saveArticle">Guardar artículo</x-button>
        </x-slot:footer>
    </x-modal>

    {{-- Modal: pago --}}
    <x-modal wire:model="showPaymentModal" title="Registrar pago" description="El saldo, las cuotas pagadas y la próxima fecha se actualizan automáticamente." icon="banknote" maxWidth="lg">
        @php $payArticle = $paymentArticleId ? $articles->firstWhere('id', $paymentArticleId) : null; @endphp
        @if ($payArticle)
            <div class="mb-4 rounded-xl bg-slate-50 p-3 text-sm">
                <p class="font-medium text-slate-800">{{ $payArticle->description }}</p>
                <p class="text-xs text-slate-500">Saldo: <strong>{{ money($payArticle->balance) }}</strong> · Cuota: {{ money($payArticle->installment_amount) }} · Pendientes: {{ $payArticle->installments_pending }}</p>
            </div>
        @endif
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-field label="Monto ($)" error="payment.amount" required><x-input type="number" step="0.01" min="0.01" wire:model="payment.amount" /></x-field>
            <x-field label="Fecha de pago" error="payment.paid_at" required><x-input type="date" wire:model="payment.paid_at" /></x-field>
            <x-field label="Forma de pago" error="payment.method"><x-select wire:model="payment.method" :options="\App\Enums\PaymentMethod::options()" /></x-field>
            <x-field label="Referencia / recibo" error="payment.reference"><x-input wire:model="payment.reference" /></x-field>
            <x-field label="Notas" error="payment.notes" class="sm:col-span-2"><x-textarea wire:model="payment.notes" rows="2" /></x-field>
        </div>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="show = false">Cancelar</x-button>
            <x-button variant="success" icon="check" wire:click="savePayment">Registrar pago</x-button>
        </x-slot:footer>
    </x-modal>

    {{-- Modal: cambio de estado --}}
    <x-modal wire:model="showStatusModal" title="Cambiar estado de mora" description="El cambio queda registrado en el historial con el motivo y tu usuario." icon="refresh-cw" maxWidth="lg">
        <div class="space-y-4">
            <x-field label="Nuevo estado" error="newStatusId" required>
                <x-select wire:model="newStatusId" placeholder="Selecciona…">
                    @foreach ($statuses as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}{{ $s->is_auto_assignable ? '' : ' (manual)' }}</option>
                    @endforeach
                </x-select>
            </x-field>
            <x-field label="Días de mora" error="newDays" required><x-input type="number" min="0" wire:model="newDays" /></x-field>
            <x-field label="Motivo del cambio" error="statusReason" required><x-textarea wire:model="statusReason" rows="3" placeholder="Ej. Cliente presentó comprobante de pago / acuerdo de pago firmado…" /></x-field>
        </div>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="show = false">Cancelar</x-button>
            <x-button icon="check" wire:click="saveStatus">Aplicar cambio</x-button>
        </x-slot:footer>
    </x-modal>

    {{-- Modal: mensaje individual (no masivo) --}}
    @can('messages.send')
    <x-modal wire:model="showMessageModal" title="Enviar mensaje por WhatsApp" :description="'Mensaje individual para '.$customer->full_name.' · +'.$customer->whatsapp_number" icon="send" maxWidth="4xl">
        @if ($messageContext)
            <div class="mb-4 space-y-2">
                @if ($messageContext['simulation'])
                    <p class="flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 ring-1 ring-amber-200"><x-icon name="flask-conical" class="size-4 shrink-0" /> Modo simulación: el mensaje se registra pero no se envía a WhatsApp real.</p>
                @elseif (! $messageContext['conversation']?->is_simulated && ! ($messageContext['conversation'] && $messageContext['conversation']->last_inbound_at?->gt(now()->subDay())))
                    <p class="flex items-center gap-2 rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-900 ring-1 ring-sky-200"><x-icon name="info" class="size-4 shrink-0" /> El cliente no ha escrito en las últimas 24 h: WhatsApp exige una plantilla aprobada (marcadas con ✓) para iniciar la conversación.</p>
                @endif
                @unless ($customer->isActive())
                    <p class="flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-700"><x-icon name="user-x" class="size-4 shrink-0" /> El cliente está inactivo.</p>
                @endunless
                @if ($customer->whatsapp_opted_out_at)
                    <p class="flex items-center gap-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-800 ring-1 ring-rose-200"><x-icon name="triangle-alert" class="size-4 shrink-0" /> Solicitó la baja de mensajes masivos el {{ $customer->whatsapp_opted_out_at->format('d/m/Y') }}. Envía solo mensajes de atención necesarios.</p>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            <div class="space-y-4 lg:col-span-3">
                <x-field label="Plantilla (opcional)" error="messageTemplateId" hint="Al elegirla se rellena con los datos del cliente; puedes editar el texto antes de enviar.">
                    <x-select wire:model.live="messageTemplateId" placeholder="Mensaje libre (sin plantilla)">
                        @foreach ($messageTemplates->groupBy(fn ($t) => $t->category->label()) as $category => $items)
                            <optgroup label="{{ $category }}">
                                @foreach ($items as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}{{ $t->approvedIdFor($messageContext['provider'] ?? '') ? ' ✓ aprobada' : '' }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </x-select>
                </x-field>
                <x-field label="Mensaje" error="messageBody" required>
                    <x-textarea wire:model.live.debounce.400ms="messageBody" rows="9" placeholder="Escribe el mensaje para el cliente…" class="text-[13px]" />
                </x-field>
                <x-toggle wire:model="assignToMe" label="Asignarme la conversación"
                          description="Las respuestas del cliente llegarán a tu bandeja y el bot no responderá automáticamente." />
            </div>
            <div class="lg:col-span-2">
                <p class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Vista previa</p>
                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <div class="flex items-center gap-2 bg-[#075e54] px-4 py-2.5 text-white">
                        <span class="grid size-8 place-items-center rounded-full bg-white/20 text-xs font-bold">{{ mb_substr($customer->full_name, 0, 1) }}</span>
                        <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ $customer->full_name }}</p><p class="text-[10px] opacity-80">+{{ $customer->whatsapp_number }}</p></div>
                    </div>
                    <div class="chat-pattern min-h-[240px] p-4">
                        @if (trim($messageBody) !== '')
                            <x-wa-bubble :text="$messageBody" :time="now()->format('h:i a')" />
                        @else
                            <p class="pt-16 text-center text-xs text-slate-500">El mensaje aparecerá aquí.</p>
                        @endif
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-500">Se guarda en la conversación del cliente y en el historial de mensajes.</p>
            </div>
        </div>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="show = false">Cancelar</x-button>
            <x-button icon="send" wire:click="sendMessage" wire:loading.attr="disabled">Enviar mensaje</x-button>
        </x-slot:footer>
    </x-modal>
    @endcan
</div>
