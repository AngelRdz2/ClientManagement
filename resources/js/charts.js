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
Chart.defaults.color = '#64748b'
Chart.defaults.plugins.legend.labels.usePointStyle = true
Chart.defaults.plugins.legend.labels.boxWidth = 8
Chart.defaults.plugins.tooltip.backgroundColor = '#0f172a'
Chart.defaults.plugins.tooltip.padding = 10
Chart.defaults.plugins.tooltip.cornerRadius = 8

const money = (v) => '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const buildConfig = (cfg) => {
    let isCircular = cfg.type === 'doughnut'
    let formatter = cfg.money ? money : (v) => Number(v).toLocaleString('en-US')

    return {
        type: cfg.type,
        data: { labels: cfg.labels, datasets: cfg.datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: isCircular ? '68%' : undefined,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: cfg.legend ?? isCircular, position: isCircular ? 'bottom' : 'top', align: 'start' },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            let value = isCircular ? ctx.parsed : ctx.parsed.y
                            return ` ${ctx.dataset.label ?? ctx.label}: ${formatter(value)}`
                        },
                    },
                },
            },
            scales: isCircular ? {} : {
                x: { grid: { display: false }, border: { display: false }, stacked: cfg.stacked ?? false },
                y: {
                    beginAtZero: true,
                    stacked: cfg.stacked ?? false,
                    border: { display: false },
                    grid: { color: '#eef2f7' },
                    ticks: { precision: 0, callback: (v) => (cfg.money ? '$' + Number(v).toLocaleString('en-US') : v) },
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
        init() {
            this.render(config)
        },
        render(cfg) {
            if (this.instance) this.instance.destroy()
            this.instance = new Chart(this.$refs.canvas, buildConfig(cfg))
        },
        update(cfg) {
            this.render(cfg)
        },
        destroy() {
            this.instance?.destroy()
        },
    }))
}
