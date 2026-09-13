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

    var SLICE_FALLBACK = ['#4f46e5', '#059669', '#d97706', '#dc2626', '#2563eb', '#94a3b8', '#7c3aed', '#0d9488'];

    function initPieChart(canvas, labels, values) {
        var colors = parseJsonAttr(canvas, 'data-colors');
        var percents = parseJsonAttr(canvas, 'data-percents');

        canvas.setAttribute('data-chart-ready', '1');

        new window.Chart(canvas, {
            type: canvas.getAttribute('data-chart-type') || 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: labels.map(function (_, i) {
                        return colors[i] || SLICE_FALLBACK[i % SLICE_FALLBACK.length];
                    }),
                    borderWidth: 1,
                    borderColor: '#fff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: { font: { size: 11 }, color: '#475569', boxWidth: 10 },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var n = Number(ctx.raw);
                                var pct = percents[ctx.dataIndex];

                                if (pct === undefined || pct === null) {
                                    var sum = values.reduce(function (a, b) {
                                        return a + Number(b);
                                    }, 0);
                                    pct = sum > 0 ? Math.round((n * 1000) / sum) / 10 : 0;
                                }

                                return ctx.label + ': ' + n.toLocaleString() + ' (' + pct + '%)';
                            },
                        },
                    },
                },
            },
        });
    }

    function initChart(canvas) {
        if (!window.Chart || !(canvas instanceof HTMLCanvasElement) || canvas.getAttribute('data-chart-ready') === '1') {
            return;
        }

        var labels = parseJsonAttr(canvas, 'data-labels');
        var values = parseJsonAttr(canvas, 'data-values');
        var type = canvas.getAttribute('data-chart-type') || 'line';

        if (type === 'doughnut' || type === 'pie') {
            if (labels.length < 1 || values.length < 1) {
                return;
            }

            initPieChart(canvas, labels, values);

            return;
        }

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
