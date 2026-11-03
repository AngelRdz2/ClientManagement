<div>
    <x-page-header title="Plantillas de mensajes" description="Mensajes reutilizables de WhatsApp con variables dinámicas. Las de cobranza se asocian a cada estado de mora.">
        <x-slot:actions><x-button icon="plus" wire:click="create">Nueva plantilla</x-button></x-slot:actions>
    </x-page-header>

    <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex gap-1 overflow-x-auto rounded-xl bg-slate-200/50 p-1 scrollbar-thin">
            <button wire:click="$set('category', '')" @class(['shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium', 'bg-white text-slate-900 shadow-sm' => $category === '', 'text-slate-500 hover:text-slate-800' => $category !== ''])>Todas <span class="text-xs text-slate-400">{{ $counts->sum() }}</span></button>
            @foreach (\App\Enums\TemplateCategory::cases() as $cat)
                <button wire:click="$set('category', '{{ $cat->value }}')" @class(['shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium', 'bg-white text-slate-900 shadow-sm' => $category === $cat->value, 'text-slate-500 hover:text-slate-800' => $category !== $cat->value])>
                    {{ $cat->label() }} <span class="text-xs text-slate-400">{{ $counts[$cat->value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>
        <div class="lg:w-72"><x-input icon="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar plantilla…" /></div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
        @forelse ($templates as $template)
            <div wire:key="t-{{ $template->id }}" @class(['flex flex-col rounded-2xl border border-slate-200/80 bg-white shadow-card', 'opacity-60' => ! $template->is_active])>
                <div class="flex items-start justify-between gap-3 p-5 pb-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <x-badge :color="$template->category->color()">{{ $template->category->label() }}</x-badge>
                            @unless ($template->is_active)<x-badge color="slate">Inactiva</x-badge>@endunless
                            @if ($template->provider_template_name)<x-badge color="green" title="Plantilla aprobada en Meta: {{ $template->provider_template_name }}">Meta ✓</x-badge>@endif
                            @if ($template->twilio_content_sid)<x-badge color="green" title="Content SID de Twilio: {{ $template->twilio_content_sid }}">Twilio ✓</x-badge>@endif
                        </div>
                        <h3 class="mt-2 font-semibold text-slate-900">{{ $template->name }}</h3>
                        <p class="text-xs text-slate-500">{{ $template->description }}</p>
                    </div>
                    <x-dropdown width="w-44">
                        <x-slot:trigger><button class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><x-icon name="ellipsis-vertical" class="size-4" /></button></x-slot:trigger>
                        <x-dropdown-item icon="pencil" wire:click="edit({{ $template->id }})">Editar</x-dropdown-item>
                        <x-dropdown-item icon="copy" wire:click="duplicate({{ $template->id }})">Duplicar</x-dropdown-item>
                        <x-dropdown-item :icon="$template->is_active ? 'pause' : 'play'" wire:click="toggle({{ $template->id }})">{{ $template->is_active ? 'Desactivar' : 'Activar' }}</x-dropdown-item>
                        <x-dropdown-item icon="trash-2" danger x-on:click="$confirm({ title: '¿Eliminar plantilla?', text: {{ \Illuminate\Support\Js::from($template->name) }}, danger: true }, () => $wire.delete({{ $template->id }}))">Eliminar</x-dropdown-item>
                    </x-dropdown>
                </div>
                <div class="chat-pattern mx-5 flex-1 rounded-xl p-3">
                    <x-wa-bubble :text="\Illuminate\Support\Str::limit($template->body, 320)" highlight class="max-w-full" />
                </div>
                <div class="flex flex-wrap items-center gap-1.5 px-5 py-3 text-xs text-slate-500">
                    @foreach ($template->delinquencyStatuses as $s)
                        <x-status-pill :status="$s" size="xs" />
                    @endforeach
                    <span class="ml-auto">{{ $template->campaigns_count }} campañas</span>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white"><x-empty-state icon="file-text" title="Sin plantillas" description="Crea una plantilla para usarla en campañas." /></div>
        @endforelse
    </div>

    <x-modal wire:model="showModal" :title="$editingId ? 'Editar plantilla' : 'Nueva plantilla'" icon="file-text" maxWidth="5xl">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            <div class="space-y-4 lg:col-span-3">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="Nombre" error="form.name" required><x-input wire:model="form.name" /></x-field>
                    <x-field label="Categoría" error="form.category" required><x-select wire:model="form.category" :options="\App\Enums\TemplateCategory::options()" /></x-field>
                    <x-field label="Asunto o descripción" error="form.description" class="sm:col-span-2"><x-input wire:model="form.description" /></x-field>
                </div>
                <x-field label="Contenido del mensaje" error="form.body" required hint="Formato WhatsApp: *negrita*, _cursiva_, ~tachado~. Haz clic en una variable para insertarla.">
                    <x-textarea wire:model.live.debounce.400ms="form.body" rows="9" class="font-mono text-[13px]" />
                </x-field>
                <div class="rounded-xl border border-slate-200 p-3">
                    @foreach ($variables as $group => $vars)
                        <p class="mt-2 mb-1.5 text-[11px] font-semibold tracking-wide text-slate-500 uppercase first:mt-0">{{ $group }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($vars as $var => $desc)
                                <button type="button" wire:click="insertVariable('{{ $var }}')" title="{{ $desc }}" class="rounded-md bg-indigo-50 px-2 py-0.5 font-mono text-[11px] text-indigo-700 hover:bg-indigo-100">&#123;&#123;{{ $var }}&#125;&#125;</button>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <details class="rounded-xl border border-slate-200 p-3 text-sm" @if (filled($form['provider_template_name'] ?? null) || filled($form['twilio_content_sid'] ?? null) || $errors->hasAny(['form.provider_template_name', 'form.twilio_content_sid'])) open @endif>
                    <summary class="font-medium text-slate-700">Plantilla aprobada por WhatsApp (Meta / Twilio) — opcional</summary>
                    <p class="mt-2 text-xs text-slate-500">
                        Si el cliente no ha escrito en las últimas 24 h, WhatsApp solo permite enviarle plantillas aprobadas.
                        Registra aquí el identificador de esta plantilla en el proveedor que uses; el sistema usará el del proveedor activo.
                    </p>

                    @php $vars = $renderer->variablesIn($form['body'] ?? ''); @endphp
                    @if ($vars)
                        <div class="mt-3 rounded-lg bg-slate-50 p-2.5 text-xs">
                            <p class="mb-1.5 font-medium text-slate-600">Variables que se envían al proveedor (deben coincidir con la plantilla aprobada):</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($vars as $i => $var)
                                    <span class="rounded-md bg-white px-2 py-0.5 font-mono text-[11px] text-slate-700 ring-1 ring-slate-200">&#123;&#123;{{ $i + 1 }}&#125;&#125; = {{ $var }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-4 rounded-lg border border-slate-200 p-3">
                        <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-slate-700"><x-icon name="smartphone" class="size-3.5" /> WhatsApp Cloud API (Meta)</p>
                        <div class="grid grid-cols-3 gap-3">
                            <x-field label="Nombre de plantilla aprobada" error="form.provider_template_name" class="col-span-2"><x-input wire:model="form.provider_template_name" class="font-mono" placeholder="recordatorio_pago_v1" /></x-field>
                            <x-field label="Idioma" error="form.provider_template_language"><x-input wire:model="form.provider_template_language" placeholder="es" /></x-field>
                        </div>
                    </div>

                    <div class="mt-3 rounded-lg border border-slate-200 p-3">
                        <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-slate-700"><x-icon name="smartphone" class="size-3.5" /> Twilio</p>
                        <x-field label="Content SID" error="form.twilio_content_sid" hint="Twilio Console › Messaging › Content Template Builder. La plantilla debe estar aprobada para WhatsApp.">
                            <x-input wire:model.blur="form.twilio_content_sid" class="font-mono" placeholder="HX8bd92f91d571d5d819df4b64f2602ec6" />
                        </x-field>
                    </div>
                </details>
                <x-toggle wire:model="form.is_active" label="Plantilla activa" />
            </div>

            <div class="lg:col-span-2">
                <p class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Previsualización con datos reales</p>
                <div class="relative mb-3">
                    <x-input icon="search" type="search" wire:model.live.debounce.300ms="previewSearch" placeholder="Elegir cliente de prueba…" />
                    @if ($previewCandidates->isNotEmpty())
                        <ul class="absolute z-10 mt-1 w-full divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white shadow-elevated">
                            @foreach ($previewCandidates as $c)
                                <li><button type="button" wire:click="selectPreviewCustomer({{ $c->id }})" class="w-full px-3 py-2 text-left text-sm hover:bg-indigo-50">{{ $c->full_name }} <span class="text-xs text-slate-400">{{ $c->code }}</span></button></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @if ($previewCustomer)
                    <div class="mb-3 flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-xs">
                        <x-icon name="user-round" class="size-4 text-slate-400" />
                        <span class="font-medium text-slate-700">{{ $previewCustomer->full_name }}</span>
                        <x-status-pill :status="$previewCustomer->delinquencyStatus" size="xs" class="ml-auto" />
                    </div>
                @endif
                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <div class="flex items-center gap-2 bg-[#075e54] px-4 py-2.5 text-white">
                        <span class="grid size-8 place-items-center rounded-full bg-white/20 text-xs font-bold">{{ mb_substr(setting('company.name'), 0, 1) }}</span>
                        <div><p class="text-sm font-semibold">{{ setting('company.name') }}</p><p class="text-[10px] opacity-80">Vista previa · no se envía</p></div>
                    </div>
                    <div class="chat-pattern min-h-[260px] p-4">
                        @if ($preview)
                            <x-wa-bubble :text="$preview" :time="now()->format('h:i a')" status="read" />
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="show = false">Cancelar</x-button>
            <x-button icon="check" wire:click="save">Guardar plantilla</x-button>
        </x-slot:footer>
    </x-modal>
</div>
