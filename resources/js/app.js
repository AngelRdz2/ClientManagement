import './bootstrap'
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm'
import Swal from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'
import { registerCharts } from './charts'
import { registerTheme } from './theme'

window.Swal = Swal

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    },
})

window.toast = (message, icon = 'success') => Toast.fire({ icon, title: message })

/**
 * Confirmación con SweetAlert2.
 * Uso en Blade: x-on:click="$confirm({ title: '¿Eliminar?', text: '...' }, () => $wire.delete(1))"
 */
const confirmDialog = (options, onConfirm) => {
    let opts = typeof options === 'string' ? { title: options } : options

    Swal.fire({
        title: opts.title ?? '¿Confirmar acción?',
        text: opts.text ?? '',
        icon: opts.icon ?? 'warning',
        showCancelButton: true,
        confirmButtonText: opts.confirmText ?? 'Sí, continuar',
        cancelButtonText: opts.cancelText ?? 'Cancelar',
        reverseButtons: true,
        focusCancel: opts.danger ?? false,
        confirmButtonColor: opts.danger ? '#e11d48' : '#4f46e5',
    }).then((result) => {
        if (result.isConfirmed) onConfirm()
    })
}

Alpine.magic('confirm', () => confirmDialog)

document.addEventListener('livewire:init', () => {
    Livewire.on('toast', (event) => {
        let payload = Array.isArray(event) ? event[0] : event
        Toast.fire({ icon: payload.type ?? 'success', title: payload.message })
    })

    Livewire.on('alert', (event) => {
        let payload = Array.isArray(event) ? event[0] : event
        Swal.fire({ icon: payload.type ?? 'info', title: payload.title ?? '', text: payload.message ?? '' })
    })
})

registerTheme(Alpine)
registerCharts(Alpine)

Livewire.start()
