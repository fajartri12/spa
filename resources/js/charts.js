import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';

Chart.register(
    LineController,
    BarController,
    DoughnutController,
    LineElement,
    PointElement,
    BarElement,
    ArcElement,
    CategoryScale,
    LinearScale,
    Title,
    Tooltip,
    Legend,
    Filler,
);

const chartInstances = new Map();

const rupiahCompact = (val) => {
    if (val >= 1_000_000_000) return (val / 1_000_000_000).toFixed(1) + ' M';
    if (val >= 1_000_000) return (val / 1_000_000).toFixed(1) + ' Jt';
    if (val >= 1_000) return (val / 1_000).toFixed(0) + ' Rb';
    return val;
};

export const initDashboardCharts = () => {
    // Line chart
    const elMonthly = document.getElementById('chart-monthly');
    if (elMonthly) {
        chartInstances.get('monthly')?.destroy();
        const labels = JSON.parse(elMonthly.dataset.labels || '[]');
        const values = JSON.parse(elMonthly.dataset.values || '[]');

        const chart = new Chart(elMonthly, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Pemasukan',
                        data: values,
                        borderColor: '#2872C8',
                        backgroundColor: 'rgba(40, 114, 200, 0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#2872C8',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ' Rp ' + Number(ctx.parsed.y).toLocaleString('id-ID'),
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11, family: 'Inter' }, color: '#8994A3' },
                    },
                    y: {
                        beginAtZero: true,
                        min: 0,
                        grid: { color: '#EEF2F7' },
                        ticks: {
                            font: { size: 11, family: 'Inter' },
                            color: '#8994A3',
                            callback: rupiahCompact,
                        },
                    },
                },
            },
        });
        chartInstances.set('monthly', chart);
    }

    // Doughnut chart
    const elStatus = document.getElementById('chart-billing-status');
    if (elStatus) {
        chartInstances.get('status')?.destroy();
        const labels = JSON.parse(elStatus.dataset.labels || '[]');
        const values = JSON.parse(elStatus.dataset.values || '[]');
        const colors = JSON.parse(elStatus.dataset.colors || '[]');

        const allZero = values.every((v) => Number(v) === 0);
        const chart = new Chart(elStatus, {
            type: 'doughnut',
            data: {
                labels: allZero ? ['Belum ada tagihan'] : labels,
                datasets: [
                    {
                        data: allZero ? [1] : values,
                        backgroundColor: allZero ? ['#E2E8F0'] : colors,
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            padding: 12,
                            font: { size: 11, family: 'Inter' },
                            color: '#475569',
                        },
                    },
                },
            },
        });
        chartInstances.set('status', chart);
    }

    // Bar chart
    const elCategory = document.getElementById('chart-category');
    if (elCategory) {
        chartInstances.get('category')?.destroy();
        const labels = JSON.parse(elCategory.dataset.labels || '[]');
        const values = JSON.parse(elCategory.dataset.values || '[]');

        const chart = new Chart(elCategory, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Total',
                        data: values,
                        backgroundColor: '#3997D5',
                        borderRadius: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ' Rp ' + Number(ctx.parsed.y).toLocaleString('id-ID'),
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11, family: 'Inter' }, color: '#8994A3' },
                    },
                    y: {
                        beginAtZero: true,
                        min: 0,
                        grid: { color: '#EEF2F7' },
                        ticks: {
                            font: { size: 11, family: 'Inter' },
                            color: '#8994A3',
                            callback: rupiahCompact,
                        },
                    },
                },
            },
        });
        chartInstances.set('category', chart);
    }
};

document.addEventListener('DOMContentLoaded', initDashboardCharts);
document.addEventListener('livewire:navigated', initDashboardCharts);
