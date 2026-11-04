{{-- Aplica el tema guardado antes de pintar la página (evita el parpadeo claro → oscuro). Ver resources/js/theme.js --}}
<script>
    (() => {
        try {
            let theme = localStorage.getItem('theme') ?? 'system'
            if (theme === 'dark' || (theme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark')
            }
        } catch {}
    })()
</script>
