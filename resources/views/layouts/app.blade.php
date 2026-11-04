<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%234f46e5'/><path d='M9 17l4 4 10-10' stroke='white' stroke-width='3' fill='none' stroke-linecap='round' stroke-linejoin='round'/></svg>">
    @include('partials.theme')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full font-sans">
@php
    $user = auth()->user();
    $navigation = \App\Support\Navigation::for($user);
    $badges = [
        'pending_agent' => $user->can(\App\Support\Permissions::CONVERSATIONS_ATTEND)
            ? \App\Models\Conversation::where('status', \App\Enums\ConversationStatus::PendingAgent)->count()
            : 0,
    ];
    $whatsapp = app(\App\Services\WhatsApp\WhatsAppManager::class)->provider();
@endphp

<div x-data="{
        collapsed: JSON.parse(localStorage.getItem('sidebar-collapsed') ?? 'false'),
        mobileOpen: false,
        toggle() { this.collapsed = ! this.collapsed; localStorage.setItem('sidebar-collapsed', JSON.stringify(this.collapsed)) },
     }"
     class="min-h-screen">

    {{-- Fondo del menú móvil --}}
    <div x-show="mobileOpen" x-cloak x-transition.opacity x-on:click="mobileOpen = false" class="fixed inset-0 z-40 bg-slate-900/60 lg:hidden dark:bg-black/70"></div>

    {{-- Sidebar --}}
    <aside :class="[collapsed ? 'lg:w-[76px]' : 'lg:w-[264px]', mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']"
           class="fixed inset-y-0 left-0 z-50 flex w-[264px] flex-col bg-navy-950 text-navy-100 transition-all duration-300 ease-in-out dark:border-r dark:border-white/5">
        {{-- Marca --}}
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/5 px-5">
            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 shadow-lg shadow-indigo-900/40">
                <x-icon name="hand-coins" class="size-5 text-white" />
            </span>
            <div class="min-w-0" :class="collapsed && 'lg:hidden'">
                <p class="truncate text-[15px] font-bold tracking-tight text-white">{{ config('app.name') }}</p>
                <p class="truncate text-[11px] text-navy-300">Cobranza · Clientes · WhatsApp</p>
            </div>
            <button x-on:click="mobileOpen = false" class="ml-auto rounded-lg p-1 text-navy-300 hover:bg-white/5 hover:text-white lg:hidden">
                <x-icon name="x" class="size-5" />
            </button>
        </div>

        {{-- Navegación --}}
        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5 scrollbar-thin">
            @foreach ($navigation as $group)
                <div>
                    <p class="mb-2 px-3 text-[10.5px] font-semibold tracking-[0.12em] text-navy-400 uppercase" :class="collapsed && 'lg:invisible lg:h-0 lg:mb-0'">{{ $group['label'] }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            @php
                                $active = request()->routeIs(...(array) $item['active']);
                                $badge = isset($item['badge']) ? ($badges[$item['badge']] ?? 0) : 0;
                            @endphp
                            <li>
                                <a href="{{ route($item['route']) }}" wire:navigate
                                   title="{{ $item['label'] }}"
                                   @class([
                                       'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-[13.5px] font-medium transition',
                                       'bg-white/10 text-white shadow-inner' => $active,
                                       'text-navy-200 hover:bg-white/5 hover:text-white' => ! $active,
                                   ])
                                   :class="collapsed && 'lg:justify-center lg:px-0'">
                                    @if ($active)
                                        <span class="absolute top-1.5 bottom-1.5 left-0 w-[3px] rounded-r-full bg-indigo-400"></span>
                                    @endif
                                    <x-icon :name="$item['icon']" @class(['size-[18px] shrink-0', 'text-indigo-300' => $active, 'text-navy-300 group-hover:text-white' => ! $active]) />
                                    <span class="truncate" :class="collapsed && 'lg:hidden'">{{ $item['label'] }}</span>
                                    @if ($badge > 0)
                                        <span class="ml-auto rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] leading-none font-bold text-white"
                                              :class="collapsed && 'lg:absolute lg:top-0.5 lg:right-2 lg:ml-0'">{{ $badge }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        {{-- Estado del proveedor --}}
        <div class="border-t border-white/5 p-3">
            <a href="{{ Route::has('settings.index') && $user->can('settings.manage') ? route('settings.index', ['tab' => 'whatsapp']) : '#' }}" wire:navigate
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 transition hover:bg-white/5" :class="collapsed && 'lg:justify-center lg:px-0'"
               title="{{ $whatsapp->label() }}">
                <span @class(['relative grid size-8 shrink-0 place-items-center rounded-lg', 'bg-amber-400/15 text-amber-300' => $whatsapp->isSimulation(), 'bg-emerald-400/15 text-emerald-300' => ! $whatsapp->isSimulation()])>
                    <x-icon name="smartphone" class="size-4" />
                </span>
                <span class="min-w-0" :class="collapsed && 'lg:hidden'">
                    <span class="block text-[11px] text-navy-300">Canal WhatsApp</span>
                    <span @class(['block truncate text-xs font-semibold', 'text-amber-300' => $whatsapp->isSimulation(), 'text-emerald-300' => ! $whatsapp->isSimulation()])>
                        {{ $whatsapp->isSimulation() ? 'Modo simulación' : $whatsapp->label() }}
                    </span>
                </span>
            </a>
            <button x-on:click="toggle()" class="mt-1 hidden w-full items-center gap-3 rounded-lg px-3 py-2 text-xs font-medium text-navy-300 transition hover:bg-white/5 hover:text-white lg:flex" :class="collapsed && 'lg:justify-center lg:px-0'">
                <x-icon name="panel-left-close" class="size-4" x-show="! collapsed" />
                <x-icon name="panel-left-open" class="size-4" x-show="collapsed" x-cloak />
                <span :class="collapsed && 'lg:hidden'">Contraer menú</span>
            </button>
        </div>
    </aside>

    {{-- Contenido --}}
    <div :class="collapsed ? 'lg:pl-[76px]' : 'lg:pl-[264px]'" class="flex min-h-screen flex-col transition-all duration-300 ease-in-out">
        <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-slate-200/80 bg-surface/85 px-4 backdrop-blur-md sm:px-6">
            <button x-on:click="mobileOpen = true" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden">
                <x-icon name="menu" class="size-5" />
            </button>

            @can(\App\Support\Permissions::CUSTOMERS_VIEW)
                <livewire:global-search />
            @else
                <div class="flex-1"></div>
            @endcan

            <div class="ml-auto flex items-center gap-1.5 sm:gap-3">
                @if ($whatsapp->isSimulation())
                    <span class="hidden items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200 md:inline-flex"
                          title="Los mensajes NO se envían a WhatsApp real. Cambia el proveedor en Configuración.">
                        <x-icon name="flask-conical" class="size-3.5" /> WhatsApp simulado
                    </span>
                @endif

                <x-theme-toggle />

                <livewire:notification-bell />

                <x-dropdown width="w-60">
                    <x-slot:trigger>
                        <button class="flex items-center gap-2.5 rounded-xl p-1 pr-2 transition hover:bg-slate-100">
                            <span class="grid size-8 place-items-center rounded-lg bg-gradient-to-br from-indigo-500 to-navy-700 text-xs font-bold text-white">{{ $user->initials() }}</span>
                            <span class="hidden text-left sm:block">
                                <span class="block text-[13px] leading-tight font-semibold text-slate-800">{{ $user->name }}</span>
                                <span class="block text-[11px] leading-tight text-slate-500">{{ $user->roleLabel() }}</span>
                            </span>
                            <x-icon name="chevron-down" class="hidden size-4 text-slate-400 sm:block" />
                        </button>
                    </x-slot:trigger>
                    <div class="border-b border-slate-100 px-3.5 pt-1.5 pb-2.5">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                    </div>
                    <div class="py-1">
                        <x-dropdown-item icon="user-round" :href="route('profile')">Mi perfil</x-dropdown-item>
                        <x-dropdown-item icon="bell" :href="route('notifications.index')">Notificaciones</x-dropdown-item>
                    </div>
                    <div class="border-t border-slate-100 pt-1">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-item type="submit" icon="log-out" danger onclick="this.closest('form').submit()">Cerrar sesión</x-dropdown-item>
                        </form>
                    </div>
                </x-dropdown>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>

        @unless (request()->routeIs('conversations.*'))
            <footer class="px-4 pb-5 text-center text-xs text-slate-400 sm:px-6 lg:px-8">
                {{ config('app.name') }} · {{ setting('company.name') }} · {{ now()->year }}
            </footer>
        @endunless
    </div>
</div>

@if (session('toast'))
    <script>document.addEventListener('livewire:navigated', () => window.toast(@js(session('toast'))), { once: true })</script>
@endif
@livewireScriptConfig
</body>
</html>
