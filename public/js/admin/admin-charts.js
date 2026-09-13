(function () {
    'use strict';

    var COLORS = {
        indigo: { stroke: 'rgb(79, 70, 229)', fill: 'rgba(79, 70, 229, 0.14)' },
        emerald: { stroke: 'rgb(5, 150, 105)', fill: 'rgba(5, 150, 105, 0.14)' },
        amber: { stroke: 'rgb(217, 119, 6)', fill: 'rgba(217, 119, 6, 0.14)' },
    };

    function parseJsonAttr(el, name) {
        try {
            var raw = el.getAttribute(name);

            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function formatValue(value, format) {
        var n = Number(value);

        if (format === 'money') {
            return (n / 100).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        }

        return n.toLocaleString();
    }

    function initChart(canvas) {
        if (!window.Chart || !(canvas instanceof HTMLCanvasElement) || canvas.getAttribute('data-chart-ready') === '1') {
            return;
        }

        var labels = parseJsonAttr(canvas, 'data-labels');
        var values = parseJsonAttr(canvas, 'data-values');

        if (labels.length < 2 || values.length < 2) {
            return;
        }

        var format = canvas.getAttribute('data-format') || 'number';
        var color = COLORS[canvas.getAttribute('data-color') || 'indigo'] || COLORS.indigo;
        var currency = canvas.getAttribute('data-currency') || '';
        var beginZero = canvas.getAttribute('data-begin-zero') === '1';

        canvas.setAttribute('data-chart-ready', '1');

        new window.Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    borderColor: color.stroke,
                    backgroundColor: color.fill,
                    fill: true,
                    tension: 0.3,
                    pointRadius: values.length > 20 ? 0 : 3,
                    pointHoverRadius: 5,
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var text = formatValue(ctx.parsed.y, format);

                                return currency && format === 'money' ? text + ' ' + currency : text;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 8,
                            font: { size: 10 },
                            color: '#64748b',
                        },
                        grid: { display: false },
                    },
                    y: {
                        beginAtZero: beginZero,
                        ticks: {
                            font: { size: 10 },
                            color: '#64748b',
                            callback: function (v) {
                                return formatValue(v, format);
                            },
                        },
                        grid: { color: 'rgba(148, 163, 184, 0.18)' },
                    },
                },
            },
        });
    }

    function initAll(root) {
        (root || document).querySelectorAll('[data-admin-chart]').forEach(initChart);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAll(document);
        });
    } else {
        initAll(document);
    }
})();
