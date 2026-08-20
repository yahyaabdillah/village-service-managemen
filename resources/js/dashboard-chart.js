import Chart from 'chart.js/auto';

export function initDashboardChart() {
    const canvas = document.getElementById('request-trend-chart');
    const dataNode = document.getElementById('dashboard-chart-data');
    if (!canvas || !dataNode) return;

    const data = JSON.parse(dataNode.textContent);
    const context = canvas.getContext('2d');
    const gradient = context.createLinearGradient(0, 0, 0, 260);
    gradient.addColorStop(0, 'rgba(45, 122, 100, .28)');
    gradient.addColorStop(1, 'rgba(45, 122, 100, 0)');

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Pengajuan',
                data: data.values,
                borderColor: '#2d7a64',
                backgroundColor: gradient,
                fill: true,
                borderWidth: 2.5,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#fffdf8',
                pointBorderColor: '#2d7a64',
                pointBorderWidth: 2,
                tension: .38,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    displayColors: false,
                    backgroundColor: '#0b2a24',
                    padding: 10,
                    callbacks: { label: (item) => `${item.parsed.y} pengajuan` },
                },
            },
            scales: {
                x: { grid: { display: false }, border: { display: false }, ticks: { color: '#65726d', font: { size: 11 } } },
                y: { beginAtZero: true, suggestedMax: 4, ticks: { precision: 0, color: '#65726d', font: { size: 11 } }, border: { display: false }, grid: { color: '#e8ede9' } },
            },
        },
    });
}
