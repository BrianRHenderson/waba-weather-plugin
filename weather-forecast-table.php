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

        .city-marker {
            width: 14px;
            height: 14px;
            background: blue;
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
            padding: 1px 2px;
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
        <div id="skyline-marker-container" class="marker-container">
            <div id="skyline-marker" class="alberta-marker" title="Click for weather"></div>
            <div class="alberta-label">Skyline</div>
        </div>
        <div id="highwood-marker-container" class="marker-container">
            <div id="highwood-marker" class="alberta-marker" title="Click for weather"></div>
            <div class="alberta-label">The Highwood</div>
        </div>
        <div id="cathedral-marker-container" class="marker-container">
            <div id="cathedral-marker" class="alberta-marker" title="Click for weather"></div>
            <div class="alberta-label">Cathedral</div>
        </div>
        <div id="bigChoss-marker-container" class="marker-container">
            <div id="bigChoss-marker" class="alberta-marker" title="Click for weather"></div>
            <div class="alberta-label">Big Choss</div>
        </div>
        <div id="calgary-marker-container" class="marker-container">
            <div id="calgary-marker" class="city-marker" title="Calgary"></div>
            <div class="alberta-label">Calgary</div>
        </div>

    </div>

    <div id="weather-output">
        <div id="weather-table-body">
            <p>Loading...</p>
        </div>
        <canvas id="weather-chart" width="800" height="300"></canvas>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const wrapper = document.getElementById('alberta-map-wrapper');
        const skylineMarkerContainer = document.getElementById('skyline-marker-container');
        const bigChossMarkerContainer = document.getElementById('bigChoss-marker-container');
        const cathedralMarkerContainer = document.getElementById('cathedral-marker-container');
        const highwoodMarkerContainer = document.getElementById('highwood-marker-container');
        const calgaryMarkerContainer = document.getElementById('calgary-marker-container');

        const skylineMarker = document.getElementById('skyline-marker');
        const bigChossMarker = document.getElementById('bigChoss-marker');
        const cathedralMarker = document.getElementById('cathedral-marker');
        const highwoodMarker = document.getElementById('highwood-marker');
        const calgaryMarker = document.getElementById('calgary-marker');

        const chartCanvas = document.getElementById('weather-chart');
        const tableBody = document.getElementById('weather-table-body');
        const weatherOutput = document.getElementById('weather-output');
        let chart;

        const skylineLat = 49.95;
        const skylineLon = -114.04;
        const highwoodLat = 50.38;
        const highwoodLon = -114.64;
        const cathedralLat = 51.43;
        const cathedralLon = -116.40;
        const bigChossLat = 51.12;
        const bigChossLon = -115.11;
        const calgaryLat = 51.05;
        const calgaryLon = -114.06;

        const viewWidth = 660;
        const viewHeight = 515;

        const minLat = 49, maxLat = 54;
        const minLon = -120, maxLon = -110;

        function project(lat, lon) {
            const x = ((lon - minLon) / (maxLon - minLon)) * viewWidth;
            const y = ((maxLat - lat) / (maxLat - minLat)) * viewHeight;
            return { x, y };
        }

        function getData(lat, lon, name) {
            const api = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&daily=precipitation_sum,temperature_2m_max,precipitation_probability_max&hourly=temperature_2m,precipitation&timezone=auto&past_days=1&forecast_days=2`;

            fetch(api)
                .then(res => res.json())
                .then(data => {
                    weatherOutput.style.display = 'block';

                    const daily = data.daily;
                    const hourly = data.hourly;

                    console.log(daily.time)
                    tableBody.innerHTML = `
                        <h2>${name}</h2>
                        <p>
                            <strong>Date: </strong> ${daily.time[1]}<br/>
                            <strong>Precipitation (mm): </strong> ${daily.precipitation_sum[1].toFixed(1)}<br/>
                            <strong>Max Temp (°C): </strong> ${daily.temperature_2m_max[1].toFixed(1)}<br/>
                        </p>
                    `;

                    const today = new Date();
                    const chartLabels = [];
                    const chartDataTemp = [];
                    const chartDataPercip = [];

                    for (let i = 0; i < hourly.time.length; i++) {
                        const date = hourly.time[i].slice(0, 10);
                            const dt = new Date(hourly.time[i]);
                            let dayString = '';
                            if (dt.getHours().toString()==="0") {
                                dayString = `${dt.toLocaleDateString('en-CA', { weekday: 'short', day: 'numeric' })} -`;
                            }
                            chartLabels.push(`${dayString} ${dt.getHours().toString().padStart(2, '0')}:00`);
                            chartDataTemp.push(hourly.temperature_2m[i]);
                            chartDataPercip.push(hourly.precipitation[i]);
                    }

                    if (chart) chart.destroy();
                    chart = new Chart(chartCanvas.getContext('2d'), {
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                type: 'line',
                                label: 'Hourly Temp (°C)',
                                data: chartDataTemp,
                                borderColor: 'rgba(255,99,132,1)',
                                backgroundColor: 'rgba(255,99,132,0)',
                                fill: true,
                                yAxisID: "y0"
                            },
                            {
                                type: 'bar',
                                label: 'Hourly Precipitation (mm)',
                                data: chartDataPercip,
                                borderColor: 'rgb(99, 135, 255)',
                                backgroundColor: 'rgb(99, 122, 255)',
                                fill: true,
                                yAxisID: "y1"

                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: `${name} Hourly Temperature and Precipitation (Yesterday, Today, Tomorrow)`
                                }
                            },
                            scales: {
                                y0: {
                                    max: 40,
                                    min: 0,
                                    position: 'left',
                                    title: {
                                        display: true,
                                        text: 'Temperature (°C)'
                                    }
                                },
                                y1: {
                                    max: 16,
                                    min: 0,
                                    position: 'right',
                                    title: {
                                        display: true,
                                        text: 'Precipitation (mm)'
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
        }

        const scale = wrapper.offsetWidth / viewWidth;

        skylineMarkerContainer.style.left = `${project(skylineLat, skylineLon).x * scale}px`;
        skylineMarkerContainer.style.top = `${project(skylineLat, skylineLon).y * scale}px`;

        bigChossMarkerContainer.style.left = `${project(bigChossLat, bigChossLon).x * scale}px`;
        bigChossMarkerContainer.style.top = `${project(bigChossLat, bigChossLon).y * scale}px`;

        cathedralMarkerContainer.style.left = `${project(cathedralLat, cathedralLon).x * scale}px`;
        cathedralMarkerContainer.style.top = `${project(cathedralLat, cathedralLon).y * scale}px`;

        highwoodMarkerContainer.style.left = `${project(highwoodLat, highwoodLon).x * scale}px`;
        highwoodMarkerContainer.style.top = `${project(highwoodLat, highwoodLon).y * scale}px`;

        calgaryMarkerContainer.style.left = `${project(calgaryLat, calgaryLon).x * scale}px`;
        calgaryMarkerContainer.style.top = `${project(calgaryLat, calgaryLon).y * scale}px`;


        skylineMarker.addEventListener('click', () => {getData(skylineLat, skylineLon, 'Skyline')});
        bigChossMarker.addEventListener('click', () => {getData(bigChossLat, bigChossLon, 'Big Choss')});
        cathedralMarker.addEventListener('click', () => {getData(cathedralLat, cathedralLon, 'Cathedral')});
        highwoodMarker.addEventListener('click', () => {getData(highwoodLat, highwoodLon, 'The Highwood')});
        calgaryMarker.addEventListener('click', () => {getData(calgaryLat, calgaryLon, 'Calgary')});
    });
    </script>
    <?php
    return ob_get_clean();
});
