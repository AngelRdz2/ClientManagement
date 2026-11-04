import {
    Chart,
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js'

Chart.register(
    ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Filler, Legend,
    LinearScale, LineController, LineElement, PointElement, Tooltip,
)

Chart.defaults.font.family = "'Inter Variable', ui-sans-serif, system-ui, sans-serif"
Chart.defaults.font.size = 12
Chart.defaults.plugins.legend.labels.usePointStyle = true
Chart.defaults.plugins.legend.labels.boxWidth = 8
Chart.defaults.plugins.tooltip.padding = 10
Chart.defaults.plugins.tooltip.cornerRadius = 8

/** Colores de textos, cuadrícula y separadores según el tema (surface coincide con --color-surface de app.css). */
const palette = () => document.documentElement.classList.contains('dark')
    ? { text: '#94a3b8', grid: '#1f2a3d', surface: '#131c2e', tooltip: '#020617' }
    : { text: '#64748b', grid: '#eef2f7', surface: '#ffffff', tooltip: '#0f172a' }

const money = (v) => '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const buildConfig = (cfg) => {
    let isCircular = cfg.type === 'doughnut'
    let formatter = cfg.money ? money : (v) => Number(v).toLocaleString('en-US')
    let colors = palette()

    // Los segmentos de la dona se separan con el color del fondo de la tarjeta
    let datasets = isCircular ? cfg.datasets.map((ds) => ({ ...ds, borderColor: colors.surface })) : cfg.datasets

    return {
        type: cfg.type,
        data: { labels: cfg.labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            color: colors.text,
            cutout: isCircular ? '68%' : undefined,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: cfg.legend ?? isCircular, position: isCircular ? 'bottom' : 'top', align: 'start' },
                tooltip: {
                    backgroundColor: colors.tooltip,
                    callbacks: {
                        label: (ctx) => {
                            let value = isCircular ? ctx.parsed : ctx.parsed.y
                            return ` ${ctx.dataset.label ?? ctx.label}: ${formatter(value)}`
                        },
                    },
                },
            },
            scales: isCircular ? {} : {
                x: { grid: { display: false }, border: { display: false }, stacked: cfg.stacked ?? false, ticks: { color: colors.text } },
                y: {
                    beginAtZero: true,
                    stacked: cfg.stacked ?? false,
                    border: { display: false },
                    grid: { color: colors.grid },
                    ticks: { color: colors.text, precision: 0, callback: (v) => (cfg.money ? '$' + Number(v).toLocaleString('en-US') : v) },
                },
            },
        },
    }
}

export function registerCharts(Alpine) {
    /**
     * Uso: <div x-data="chart(@js($config))"><canvas x-ref="canvas"></canvas></div>
     * $config = ['type' => 'bar', 'labels' => [...], 'datasets' => [...], 'money' => false]
     */
    Alpine.data('chart', (config) => ({
        instance: null,
        config: null,
        onThemeChanged: null,
        init() {
            this.render(config)
            this.onThemeChanged = () => this.render(this.config)
            window.addEventListener('theme-changed', this.onThemeChanged)
        },
        render(cfg) {
            this.config = cfg
            if (this.instance) this.instance.destroy()
            this.instance = new Chart(this.$refs.canvas, buildConfig(cfg))
        },
        update(cfg) {
            this.render(cfg)
        },
        destroy() {
            window.removeEventListener('theme-changed', this.onThemeChanged)
            this.instance?.destroy()
        },
    }))
}
