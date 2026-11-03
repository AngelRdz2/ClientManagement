<div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
    {{-- Panel de marca --}}
    <div class="relative hidden overflow-hidden bg-navy-950 lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div class="pointer-events-none absolute -top-40 -right-40 size-[520px] rounded-full bg-indigo-600/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-48 -left-32 size-[460px] rounded-full bg-sky-500/10 blur-3xl"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(white 1px, transparent 1px); background-size: 22px 22px;"></div>

        <div class="relative flex items-center gap-3">
            <span class="grid size-11 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-700 shadow-lg shadow-indigo-900/50">
                <x-icon name="hand-coins" class="size-6 text-white" />
            </span>
            <div>
                <p class="text-lg font-bold text-white">{{ config('app.name') }}</p>
                <p class="text-xs text-navy-300">{{ setting('company.name') }}</p>
            </div>
        </div>

        <div class="relative max-w-lg">
            <h1 class="text-4xl leading-tight font-bold tracking-tight text-white">
                Cobranza inteligente y atención por WhatsApp, <span class="text-indigo-300">en un solo lugar.</span>
            </h1>
            <p class="mt-5 text-base leading-relaxed text-navy-200">
                Administra clientes, controla estados de mora, lanza campañas segmentadas y deja que el chatbot
                responda consultas de saldo mientras tu equipo atiende los casos importantes.
            </p>

            <div class="mt-10 grid grid-cols-3 gap-4">
                @foreach ([['users', 'Clientes y artículos'], ['megaphone', 'Campañas masivas'], ['bot', 'Chatbot 24/7']] as [$icon, $text])
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                        <x-icon :name="$icon" class="size-5 text-indigo-300" />
                        <p class="mt-3 text-sm font-medium text-white">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <p class="relative text-xs text-navy-400">© {{ now()->year }} {{ setting('company.name') }}. Acceso restringido a personal autorizado.</p>
    </div>

    {{-- Formulario --}}
    <div class="flex items-center justify-center px-6 py-12 sm:px-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <span class="grid size-10 place-items-center rounded-xl bg-indigo-600"><x-icon name="hand-coins" class="size-5 text-white" /></span>
                <p class="text-lg font-bold text-slate-900">{{ config('app.name') }}</p>
            </div>

            <h2 class="text-2xl font-bold tracking-tight text-slate-900">Bienvenido de nuevo</h2>
            <p class="mt-1.5 text-sm text-slate-500">Ingresa tus credenciales para acceder al sistema.</p>

            @if ($errors->has('email') && ! $errors->has('password'))
                <div class="mt-6 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                    <x-icon name="circle-alert" class="mt-0.5 size-4 shrink-0" />
                    <span>{{ $errors->first('email') }}</span>
                </div>
            @endif

            <form wire:submit="login" class="mt-8 space-y-5">
                <x-field label="Correo electrónico" for="email">
                    <x-input id="email" type="email" icon="mail" wire:model="email" autocomplete="email" autofocus placeholder="usuario@empresa.com" />
                </x-field>

                <x-field label="Contraseña" for="password" error="password">
                    <div x-data="{ visible: false }" class="relative">
                        <x-icon name="lock" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                        <input id="password" :type="visible ? 'text' : 'password'" wire:model="password" autocomplete="current-password" placeholder="••••••••"
                               class="form-input pr-10 pl-9 @error('password') has-error @enderror" />
                        <button type="button" x-on:click="visible = ! visible" class="absolute top-1/2 right-2.5 -translate-y-1/2 rounded p-1 text-slate-400 hover:text-slate-600" tabindex="-1">
                            <x-icon name="eye" class="size-4" />
                        </button>
                    </div>
                </x-field>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" wire:model="remember" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Mantener sesión iniciada
                </label>

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition hover:bg-indigo-500 disabled:opacity-60"
                        wire:loading.attr="disabled">
                    <x-icon name="loader-circle" class="size-4 animate-spin" wire:loading wire:target="login" />
                    <x-icon name="log-in" class="size-4" wire:loading.remove wire:target="login" />
                    Iniciar sesión
                </button>
            </form>

            @if (app()->isLocal())
                <div class="mt-8 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-xs text-slate-600">
                    <p class="font-semibold text-slate-700">Usuarios de demostración (entorno local)</p>
                    <ul class="mt-2 space-y-1 font-mono">
                        <li>admin@crm.test · supervisor@crm.test</li>
                        <li>asesor@crm.test · cobranza@crm.test</li>
                        <li class="text-slate-500">Contraseña: <span class="font-semibold">password</span></li>
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>
