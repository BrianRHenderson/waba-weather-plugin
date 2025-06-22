<?php
/**
 * Plugin Name: Weather Forecast Table + Chart (Past + Future) - Unique Prefix Fix
 * Description: Displays weather data (2 days past, 3 days ahead) with Chart.js and a table.
 * Version: 1.6
 * Author: Your Name
 */

function wft_custom_enqueue_assets() {
    wp_enqueue_style('wft_custom-style', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
    wp_add_inline_style('wft_custom-style', '
        #wft_custom-chart, #wft_custom-table {
            max-width: 800px;
            margin: 0 auto 20px auto;
        }
        #wft_custom-table {
            border-collapse: collapse;
            width: 800px;
        }
        #wft_custom-table th, #wft_custom-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        #wft_custom-table th {
            background-color: #f2f2f2;
        }
    ');
}
add_action('wp_enqueue_scripts', 'wft_custom_enqueue_assets');

function wft_custom_forecast_shortcode() {
    ob_start(); ?>

    <div id="wft_custom-weather">
        <h3>Weather History + Forecast (High River, AB)</h3>

        <canvas id="wft_custom-chart" width="800" height="400" style="display: block; margin: 0 auto 20px auto;"></canvas>

        <table id="wft_custom-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Precipitation (mm)</th>
                    <th>Max Temp (°C)</th>
                    <th>Precip. Prob. (%)</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="4">Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const API_URL = "https://api.open-meteo.com/v1/forecast?latitude=50.9215&longitude=-113.9573&daily=weather_code,precipitation_sum,temperature_2m_min,temperature_2m_max,precipitation_probability_max&timezone=America%2FDenver&past_days=2&forecast_days=5";

        fetch(API_URL)
            .then(response => response.json())
            .then(data => {
                const rawDates = data.daily.time;
                const precip = data.daily.precipitation_sum;
                const tmax = data.daily.temperature_2m_max;
                const prob = data.daily.precipitation_probability_max;

                const formatDate = dateStr => {
                    const options = { weekday: 'short', month: 'short', day: 'numeric' };
                    return new Date(dateStr).toLocaleDateString(undefined, options);
                };

                const formattedDates = rawDates.map(formatDate);
                const todayISO = new Date().toLocaleDateString('en-CA', { timeZone: 'America/Denver' });
                const todayIndex = rawDates.indexOf(todayISO);

                const tbody = document.querySelector("#wft_custom-table tbody");
                tbody.innerHTML = "";
                for (let i = 0; i < rawDates.length; i++) {
                    tbody.innerHTML += `
                        <tr>
                            <td>${formattedDates[i]}</td>
                            <td>${precip[i]} mm</td>
                            <td>${tmax[i]}°C</td>
                            <td>${prob[i] ?? '-'}%</td>
                        </tr>
                    `;
                }

                const todayLinePlugin = {
                    id: 'todayLine',
                    afterDraw: chart => {
                        if (todayIndex === -1) return;
                        const ctx = chart.ctx;
                        const xAxis = chart.scales.x;
                        const x = xAxis.getPixelForTick(todayIndex);
                        ctx.save();
                        ctx.beginPath();
                        ctx.moveTo(x, chart.chartArea.top);
                        ctx.lineTo(x, chart.chartArea.bottom);
                        ctx.lineWidth = 2;
                        ctx.strokeStyle = 'rgba(255, 0, 0, 0.7)';
                        ctx.stroke();
                        ctx.font = '12px sans-serif';
                        ctx.fillStyle = 'red';
                        ctx.fillText('Today', x + 4, chart.chartArea.top + 12);
                        ctx.restore();
                    }
                };

                const ctx = document.getElementById('wft_custom-chart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: formattedDates,
                        datasets: [
                            {
                                label: 'Max Temp (°C)',
                                data: tmax,
                                borderColor: 'rgba(255, 99, 132, 1)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                fill: false,
                                tension: 0.3
                            },
                            {
                                label: 'Precipitation (mm)',
                                data: precip,
                                borderColor: 'rgba(75, 192, 192, 1)',
                                backgroundColor: 'rgba(75, 192, 192, 0.3)',
                                type: 'bar',
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                type: 'linear',
                                position: 'left',
                                title: { display: true, text: 'Temperature (°C)' }
                            },
                            y1: {
                                type: 'linear',
                                position: 'right',
                                grid: { drawOnChartArea: false },
                                title: { display: true, text: 'Precipitation (mm)' }
                            }
                        },
                        plugins: {
                            legend: { position: 'top' },
                            title: {
                                display: true,
                                text: 'Weather (2 Past Days + Today + 2 Forecast Days)'
                            }
                        }
                    },
                    plugins: [todayLinePlugin]
                });
            })
            .catch(err => {
                document.querySelector("#wft_custom-table tbody").innerHTML = `<tr><td colspan="4">Failed to load data.</td></tr>`;
                console.error("Weather fetch error:", err);
            });
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('weather_forecast_table', 'wft_custom_forecast_shortcode');
