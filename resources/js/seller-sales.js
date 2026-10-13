document.addEventListener('DOMContentLoaded', async () => {
    const root = document.querySelector('[data-sales-workspace]');
    if (!root) return;
    root.querySelector('[data-sales-range]').addEventListener('change', event => {
        root.querySelectorAll('[data-sales-custom]').forEach(label => label.hidden = event.target.value !== 'custom');
    });
    if (!root.querySelector('[data-sales-chart]')) return;
    const data = JSON.parse(root.querySelector('[data-sales-data]').textContent);
    const {default: Chart} = await import('chart.js/auto');
    const palette = ['#009e93', '#ef9a45', '#29465d', '#69b9aa', '#c2a27d', '#8198ad'];
    const money = value => new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'}).format(value);
    const compact = value => '\u20b1' + new Intl.NumberFormat('en-PH', {notation: 'compact', maximumFractionDigits: 1}).format(value);
    const hasPeriodSales = data.trend.some(point => Number(point.currentOrders) > 0);
    const options = () => ({
        responsive: true, maintainAspectRatio: false, animation: false,
        interaction: {mode: 'index', intersect: false},
        plugins: {
            legend: {display: false},
            tooltip: {backgroundColor: '#20344a', padding: 10, displayColors: false, callbacks: {
                title: items => data.trend[items[0].dataIndex].label,
                label: context => money(context.parsed.y),
            }},
        },
        scales: {
            x: {grid: {display: false}, border: {display: false}, ticks: {font: {size: 10}, color: '#8794a3', maxRotation: 0, maxTicksLimit: 7}},
            y: {beginAtZero: true, max: hasPeriodSales ? undefined : 1, border: {display: false}, grid: {color: '#eff3f6'}, ticks: {font: {size: 10}, color: '#8794a3', maxTicksLimit: 5, callback: compact}},
        },
    });
    for (const type of ['overview', 'trend']) {
        const canvas = root.querySelector(`[data-sales-chart="${type}"]`);
        if (!canvas) continue; // Empty periods use the explanatory Blade state, not invented points.
        const bar = type === 'overview';
        new Chart(canvas, {
            type: bar ? 'bar' : 'line',
            data: {
                labels: data.trend.map(point => data.grouping === 'day' ? point.label : point.axisLabel || point.label),
                datasets: [{
                    label: 'Completed net sales', data: data.trend.map(point => point.current),
                    backgroundColor: bar ? palette[0] : 'rgba(239,154,69,.09)',
                    borderColor: bar ? palette[0] : palette[1], borderWidth: bar ? 0 : 2,
                    borderRadius: bar ? 5 : 0, maxBarThickness: 38,
                    fill: !bar, tension: .3, pointRadius: 0, pointHoverRadius: 4,
                }],
            },
            options: options(),
        });
        canvas.dataset.rendered = 'true';
    }
    const categories = root.querySelector('[data-sales-chart="categories"]');
    const total = data.categories.reduce((sum, row) => sum + row.value, 0);
    new Chart(categories, {
        type: 'doughnut',
        data: {
            labels: total ? data.categories.map(row => row.label) : ['No sales'],
            datasets: [{data: total ? data.categories.map(row => row.value) : [1],
                backgroundColor: total ? data.categories.map((row, index) => palette[index % palette.length]) : ['#e3eaee'],
                borderColor: '#fff', borderWidth: 3, borderRadius: total ? 4 : 0}],
        },
        options: {responsive: true, maintainAspectRatio: false, animation: false, cutout: '72%',
            plugins: {legend: {display: false}, tooltip: {enabled: total > 0, callbacks: {
                label: context => money(context.raw) + ' (' + (context.raw / total * 100).toFixed(1) + '%)',
            }}},
        },
    });
    categories.dataset.rendered = 'true';
    root.querySelectorAll('[data-category-color]').forEach(element => {
        element.style.backgroundColor = palette[Number(element.dataset.categoryColor) % palette.length];
    });
});
