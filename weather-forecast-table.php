<?php
/**
 * Plugin Name: Alberta Weather Map with Skyline Label
 * Description: Shows a clickable marker labeled "Skyline" on an SVG map of Alberta and fetches weather data.
 * Version: 1.2
 */

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
});

add_shortcode('alberta_weather_map', function () {
    $upload_dir = wp_upload_dir();
    $svg_path = $upload_dir['basedir'] . '/Canada_Alberta_location_map.svg';
    $svg_content = file_exists($svg_path) ? file_get_contents($svg_path) : '<svg><text x="10" y="20">Map not found</text></svg>';

    ob_start();
    ?>
    <style>
        .alberta-map-wrapper {
            position: relative;
            max-width: 600px;
            margin: 30px auto;
        }

        .alberta-map-wrapper svg {
            width: 100%;
            height: auto;
            display: block;
        }

        .marker-container {
            position: absolute;
        }

        .alberta-marker {
            width: 14px;
            height: 14px;
            background: red;
            border: 2px solid white;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 4px rgba(0, 0, 0, 0.3);
            cursor: pointer;
        }

        .alberta-label {
            position: absolute;
            top: 16px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 14px;
            color: black;
            background: rgba(255, 255, 255, 0.8);
            padding: 2px 6px;
            border-radius: 4px;
            white-space: nowrap;
            pointer-events: none;
        }

        #weather-output {
            max-width: 800px;
            margin: 40px auto;
            display: none;
        }

        #weather-output table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        #weather-output th, #weather-output td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: center;
        }

        #weather-output canvas {
            margin-top: 20px;
            background: #fff;
        }
    </style>

    <div class="alberta-map-wrapper" id="alberta-map-wrapper">
        <?php echo $svg_content; ?>
        <div class="marker-container" id="marker-container">
            <div id="alberta-marker" class="alberta-marker" title="Click for weather"></div>
            <div class="alberta-label">Skyline</div>
        </div>
    </div>

    <div id="weather-output">
        <canvas id="weather-chart" width="800" height="300"></canvas>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Precipitation (mm)</th>
                    <th>Max Temp (°C)</th>
                    <th>Precip Probability (%)</th>
                </tr>
            </thead>
            <tbody id="weather-table-body">
                <tr><td colspan="4">Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const wrapper = document.getElementById('alberta-map-wrapper');
        const markerContainer = document.getElementById('marker-container');
        const marker = document.getElementById('alberta-marker');
        const chartCanvas = document.getElementById('weather-chart');
        const tableBody = document.getElementById('weather-table-body');
        const weatherOutput = document.getElementById('weather-output');
        let chart;

        const lat = 50.9215;
        const lon = -113.9573;

        const viewWidth = 1060;
        const viewHeight = 1324;

        const minLat = 49, maxLat = 60;
        const minLon = -120, maxLon = -110;

        function project(lat, lon) {
            const x = ((lon - minLon) / (maxLon - minLon)) * viewWidth;
            const y = ((maxLat - lat) / (maxLat - minLat)) * viewHeight;
            return { x, y };
        }

        const coords = project(lat, lon);
        const scale = wrapper.offsetWidth / viewWidth;

        markerContainer.style.left = `${coords.x * scale}px`;
        markerContainer.style.top = `${coords.y * scale}px`;

        marker.addEventListener('click', function () {
            const api = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&daily=precipitation_sum,temperature_2m_max,precipitation_probability_max&hourly=temperature_2m,precipitation&timezone=America%2FDenver&past_days=2&forecast_days=3`;

            fetch(api)
                .then(res => res.json())
                .then(data => {
                    weatherOutput.style.display = 'block';

                    const daily = data.daily;
                    const hourly = data.hourly;

                    tableBody.innerHTML = '';
                    for (let i = 0; i < daily.time.length; i++) {
                        tableBody.innerHTML += `
                            <tr>
                                <td>${new Date(daily.time[i]).toLocaleDateString()}</td>
                                <td>${daily.precipitation_sum[i].toFixed(1)}</td>
                                <td>${daily.temperature_2m_max[i].toFixed(1)}</td>
                                <td>${daily.precipitation_probability_max[i]}</td>
                            </tr>
                        `;
                    }

                    const today = new Date();
                    const showDates = [
                        new Date(today.getTime() - 86400000).toISOString().slice(0, 10),
                        today.toISOString().slice(0, 10),
                        new Date(today.getTime() + 86400000).toISOString().slice(0, 10)
                    ];

                    const chartLabels = [];
                    const chartDataTemp = [];
                    const chartDataPercip = [];

                    for (let i = 0; i < hourly.time.length; i++) {
                        const date = hourly.time[i].slice(0, 10);
                        if (showDates.includes(date)) {
                            const dt = new Date(hourly.time[i]);
                            chartLabels.push(`${dt.getDate().toString().padStart(2, '0')} - ${dt.getHours().toString().padStart(2, '0')}:00`);
                            chartDataTemp.push(hourly.temperature_2m[i]);
                            chartDataPercip.push(hourly.precipitation[i]);
                        }
                    }

                    if (chart) chart.destroy();
                    chart = new Chart(chartCanvas.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                label: 'Hourly Temp (°C)',
                                data: chartDataTemp,
                                borderColor: 'rgba(255,99,132,1)',
                                backgroundColor: 'rgba(255,99,132,0.2)',
                                fill: true,
                                yAxisID: "y0"
                            },
                            {
                                label: 'Hourly Precipitation (mm)',
                                data: chartDataPercip,
                                borderColor: 'rgb(99, 135, 255)',
                                backgroundColor: 'rgba(99, 122, 255, 0.2)',
                                fill: true,
                                yAxisID: "y1"

                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Skyline Hourly Temperature and Precipitation (Yesterday, Today, Tomorrow)'
                                }
                            },
                            scales: {
                                y0: {
                                    title: {
                                        display: true,
                                        text: '°C'
                                    }
                                },
                                y1: {
                                    title: {
                                        display: true,
                                        text: 'mm'
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(err => {
                    tableBody.innerHTML = `<tr><td colspan="4">Error loading data</td></tr>`;
                    console.error('API error:', err);
                });
        });
    });
    </script>
    <?php
    return ob_get_clean();
});
