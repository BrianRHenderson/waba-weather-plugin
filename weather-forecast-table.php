<?php
/**
 * Plugin Name: Alberta Weather Map with Skyline Label
 * Description: Shows a clickable marker labeled "Skyline" on an SVG map of Alberta and fetches weather data.
 * Version: 1.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

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
            background: #2b6cb0;
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
            background: rgba(255, 255, 255, 0.8);
            padding: 1px 2px;
            border-radius: 4px;
            white-space: nowrap;
            pointer-events: none;
        }

        #weather-output {
            max-width: 1200px;
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
            width: 100%;
            background: #fff;
        }
        .weather-day-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px auto;
        }
        .weather-day {
            border-radius: 16px;
            padding: 20px;
            width: 200px;
            text-align: left;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .weather-day:hover {
            transform: translateY(-5px);
        }
        .weather-day h3 {
            margin: 0 0 10px;
            font-size: 16px;
        }
        .weather-day p {
            margin: 4px 0;
        }
        #weather-chart {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
            display: block;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .weather-license-details {
              color: #999;
        }
    </style>

    <div class="weather-page-instructions">
        <p>Click a location to load its weather forecast.</p>
    </div>

    <div class="alberta-map-wrapper" id="alberta-map-wrapper">
        <?php echo $svg_content; ?>
        <div id="highwood-marker-container" class="marker-container">
            <div id="highwood-marker" class="alberta-marker" tabindex="0" aria-label="The Highwood weather marker" title="Click for weather"></div>
            <div class="alberta-label">The Highwood</div>
        </div>
        <div id="cathedral-marker-container" class="marker-container">
            <div id="cathedral-marker" class="alberta-marker" tabindex="1" aria-label="Cathedral weather marker" title="Click for weather"></div>
            <div class="alberta-label">Cathedral</div>
        </div>
        <div id="bigChoss-marker-container" class="marker-container">
            <div id="bigChoss-marker" class="alberta-marker" tabindex="2" aria-label="Big Choss weather marker" title="Click for weather"></div>
            <div class="alberta-label">Big Choss</div>
        </div>
        <div id="calgary-marker-container" class="marker-container">
            <div id="calgary-marker" class="city-marker" tabindex="3" aria-label="Calgary weather marker" title="Click for weather"></div>
            <div class="alberta-label">Calgary</div>
        </div>
        <div id="skyline-marker-container" class="marker-container">
            <div id="skyline-marker" class="alberta-marker" tabindex="4" aria-label="Skyline weather marker" title="Click for weather"></div>
            <div class="alberta-label">Skyline</div>
        </div>
    </div>

    <div id="weather-output">
        <div id="weather-crag-name"></div>
        <div class="weather-day-container" id="weather-summaries"></div>
        <canvas id="weather-chart" width="800" height="300"></canvas>
        <small class="weather-license-details">
            Data Source: <a href="https://eccc-msc.github.io/open-data/licence/readme_en/">Environment and Climate Change Canada</a> and <a href="https://open-meteo.com/">Weather data by Open-Meteo.com</a>
        </small>
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
        const weatherSummaries = document.getElementById('weather-summaries');
        const weatherCragName = document.getElementById('weather-crag-name');

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
            const api = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&daily=precipitation_sum,temperature_2m_max,temperature_2m_min,precipitation_probability_max&hourly=temperature_2m,precipitation&past_days=2&forecast_days=4&timezone=auto&models=gem_seamless`;

            const cacheKey = `${lat},${lon}`;
            if (sessionStorage.getItem(cacheKey)) {
                showWeather(JSON.parse(sessionStorage.getItem(cacheKey)));
            } else {
                fetch(api)
                    .then(res => res.json())
                    .then(data => {
                        sessionStorage.setItem(cacheKey, JSON.stringify(data));
                        showWeather(data);
                    })
                    .catch(err => {
                        weatherOutput.innerHTML = `<p style="color:red;">Could not load weather forecast. Please try again later.</p>`;
                    });
            }

            function showWeather(data) {
                const todayIndex = 2;
                weatherOutput.style.display = 'block';
                weatherCragName.innerHTML = `<h2>${name}</h2>`;
                weatherSummaries.innerHTML = ``;

                const days = data.daily.time;

                days.forEach((day, index) => {
                    const todayDate = new Date(`${day}T12:00`);
                    const todayWeekday = todayDate.toString().slice(0, 3);
                    const todayDayMonth = todayDate.toString().slice(4, 10);
                    const istoday = index === todayIndex;
                    const div = document.createElement('div');
                    div.className = 'weather-day';
                    div.innerHTML = `
                        ${istoday ? `<strong>${todayWeekday} (Today)</strong>` : `<strong>${todayWeekday}</strong>`}
                        <h4>${todayDayMonth}</h4>
                        <p><strong>High:</strong> ${data.daily.temperature_2m_max[index]} °C</p>
                        <p><strong>Low:</strong> ${data.daily.temperature_2m_min[index]} °C</p>
                        <p><strong>Rain:</strong> ${data.daily.precipitation_sum[index]} mm</p>
                    `;
                    div.addEventListener('click', () => {
                        showHourlyChart(day, data);
                    });
                    weatherSummaries.appendChild(div);
                });

                // Default to current day
                showHourlyChart(days[todayIndex], data);
            }
           
            function showHourlyChart(dayStr, data) {
                const labels = [];
                const temps = [];
                const precips = [];

                data.hourly.time.forEach((t, i) => {
                    if (t.startsWith(dayStr)) {
                        const date = new Date(t);
                        labels.push(formatAMPM(t));
                        temps.push(data.hourly.temperature_2m[i]);
                        precips.push(data.hourly.precipitation[i]);
                    }
                });

                if (chart) chart.destroy();
                chart = new Chart(chartCanvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                type: 'line',
                                label: 'Temperature (°C)',
                                data: temps,
                                borderColor: 'rgba(255, 99, 132, 1)',
                                yAxisID: 'y0',
                                tension: 0.4,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                borderWidth: 2
                            },
                            {
                                type: 'bar',
                                label: 'Precipitation (mm)',
                                data: precips,
                                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                borderRadius: 4,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        interaction: {
                            intersect: false,
                            mode: 'index',
                            axis: 'x'
                        },
                        responsive: true,
                        plugins: {
                            legend: {
                                labels: {
                                    font: {
                                        size: 14,
                                        family: 'Arial'
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: '#333',
                                titleFont: { size: 14 },
                                bodyFont: { size: 13 },
                                padding: 10
                            },
                            title: {
                                display: true,
                                text: `Hourly Forecast for ${dayStr.slice(0, 10)}`,
                                font: {
                                    size: 18
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                }
                            },
                            y0: {
                                min: 0,
                                max: 30,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Temperature (°C)'
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                }
                            },
                            y1: {
                                min: 0,
                                max: 15,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Precipitation (mm)'
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        }
                    }
                });
            }
        }

        function formatAMPM(date) {
            date = new Date(date);
            var hours = date.getHours();
            var minutes = date.getMinutes();
            var ampm = hours >= 12 ? 'pm' : 'am';
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            minutes = minutes < 10 ? '0'+minutes : minutes;
            var strTime = hours + ':' + minutes + ' ' + ampm;
            return strTime;
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
