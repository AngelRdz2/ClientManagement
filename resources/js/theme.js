/**
 * Tema claro / oscuro. La preferencia ('light' | 'dark' | 'system') se guarda en localStorage y se refleja como la
 * clase .dark en <html>. El script en línea de resources/views/partials/theme.blade.php la aplica antes de pintar
 * para evitar el parpadeo; aquí se mantiene al cambiar de preferencia, de tema del sistema o de página (wire:navigate).
 *
 * Uso en Blade: x-on:click="$store.theme.set('dark')" · $store.theme.preference
 */
const STORAGE_KEY = 'theme'
const media = window.matchMedia('(prefers-color-scheme: dark)')

const storedPreference = () => {
    try {
        return localStorage.getItem(STORAGE_KEY) ?? 'system'
    } catch {
        return 'system'
    }
}

/** Aplica la preferencia y avisa con el evento 'theme-changed' si el tema visible cambió (p. ej. para las gráficas). */
const apply = (preference) => {
    let dark = preference === 'dark' || (preference === 'system' && media.matches)
    let root = document.documentElement

    if (root.classList.contains('dark') !== dark) {
        root.classList.toggle('dark', dark)
        window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark } }))
    }
}

export function registerTheme(Alpine) {
    Alpine.store('theme', {
        preference: storedPreference(),

        set(preference) {
            this.preference = preference
            try {
                localStorage.setItem(STORAGE_KEY, preference)
            } catch {
                // Sin almacenamiento (modo privado estricto): el tema aplica solo a esta página
            }
            apply(preference)
        },
    })

    media.addEventListener('change', () => apply(Alpine.store('theme').preference))

    // wire:navigate reemplaza el documento: reaplicar el tema justo al intercambiar la página
    document.addEventListener('livewire:navigating', (event) => event.detail?.onSwap?.(() => apply(Alpine.store('theme').preference)))
    document.addEventListener('livewire:navigated', () => apply(Alpine.store('theme').preference))
}
