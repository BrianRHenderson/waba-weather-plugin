<?php

/**
 * Plugin Name: WABA Weather Forecast (PNG Map)
 * Description: Shows clickable markers on a PNG map of Alberta and fetches weather data on click.
 * Version: 1.8.1
 * Author: Brian Henderson
 */

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
    wp_enqueue_script('Math');
});

add_shortcode('alberta_weather_map', function () {
    $upload_dir = wp_upload_dir();
    $png_path = $upload_dir['basedir'] . '/locations.png';
    $map_url = file_exists($png_path) ? $upload_dir['baseurl'] . '/locations.png' : '';

    ob_start();
?>
    <style>
        .alberta-map-wrapper {
            position: relative;
            max-width: 70%;
            margin: 20px auto;
        }

        .alberta-map-wrapper img {
            width: 100%;
            height: auto;
            display: block;
        }

        .marker-container {
            position: absolute;
        }

        .alberta-marker {
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: clamp(10px, 2vw, 30px);
            height: clamp(10px, 2vw, 30px);
            border: 2px solid green;
            background-color: rgba(0, 128, 0, 0.4);
            transform: translate(-50%, -50%);
        }

        .marker-label {
            position: absolute;
            white-space: nowrap;
            font-weight: 700;
            color: #000;
            font-size: clamp(10px, 1.2vw, 12px);
            pointer-events: none;
            top: 40%;
            transform: translateX(-50%);
        }

        .alberta-marker:focus,
        .city-marker:focus {
            outline: 2px solid white;
            outline-offset: 0;
        }

        #weather-output {
            max-width: 1200px;
            margin: 40px auto;
            display: none;
        }

        #weather-output th,
        #weather-output td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: center;
        }

        #weather-output canvas {
            margin-top: 20px;
            width: 100% !important;
            height: auto !important;
            background: #fff;
        }

        .weather-day-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px auto;
            flex-wrap: wrap;
        }

        .weather-day {
            border-radius: 16px;
            padding: 20px;
            width: 200px;
            text-align: left;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            background: #fff;
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
            width: 100% !important;
            height: auto !important;
            max-height: 400px;
        }

        .weather-license-details {
            color: #999;
        }

        #calgary-marker-container .marker-label {
            display: none;
        }

        @media (max-width: 768px) {
            #calgary-marker-container .marker-label {
                display: block;
            }
            .alberta-map-wrapper {
                max-width: 100%;
            }

            #weather-chart {
                max-height: 250px;
            }

            .marker-label {
                word-wrap: break-word;
            }

            .weather-day-container {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                gap: 10px;
                padding: 10px;
                justify-content: flex-start;
            }

            .weather-day {
                flex: 0 0 50%;
                max-width: 250px;
            }
        }
    </style>

    <div class="weather-page-instructions">
        <p>Click a location to load its weather forecast.</p>
    </div>

    <div class="alberta-map-wrapper" id="alberta-map-wrapper">
        <img id="alberta-map-img" src="<?php echo esc_url($map_url); ?>" alt="Alberta Map" draggable="false" />

        <div id="highwood-marker-container" class="marker-container">
            <div id="highwood-marker" class="alberta-marker" tabindex="0" aria-label="The Highwood weather marker" title="Click for weather"></div>
            <div class="marker-label">The Highwood</div>
        </div>
        <div id="cathedral-marker-container" class="marker-container">
            <div id="cathedral-marker" class="alberta-marker" tabindex="1" aria-label="Cathedral weather marker" title="Click for weather"></div>
            <div class="marker-label">Cathedral</div>
        </div>
        <div id="bigChoss-marker-container" class="marker-container">
            <div id="bigChoss-marker" class="alberta-marker" tabindex="2" aria-label="Big Choss weather marker" title="Click for weather"></div>
            <div class="marker-label">Big Choss</div>
        </div>
        <div id="calgary-marker-container" class="marker-container">
            <div id="calgary-marker" class="alberta-marker" tabindex="3" aria-label="Calgary weather marker" title="Click for weather"></div>
            <div class="marker-label">Calgary</div>
        </div>
        <div id="frank-marker-container" class="marker-container">
            <div id="frank-marker" class="alberta-marker" tabindex="4" aria-label="Frank weather marker" title="Click for weather"></div>
            <div class="marker-label">Frank</div>
        </div>
        <div id="skyline-marker-container" class="marker-container">
            <div id="skyline-marker" class="alberta-marker" tabindex="5" aria-label="Skyline weather marker" title="Click for weather"></div>
            <div class="marker-label">Skyline</div>
        </div>
        <div id="whiteBuddha-marker-container" class="marker-container">
            <div id="whiteBuddha-marker" class="alberta-marker" tabindex="5" aria-label="White Buddha weather marker" title="Click for weather"></div>
            <div class="marker-label">White Buddha</div>
        </div>
    </div>

    <div id="weather-output">
        <div id="weather-crag-name"></div>
        <div class="weather-day-container" id="weather-summaries"></div>
        <canvas id="weather-chart"></canvas>
        <small class="weather-license-details">
            Data Source: <a href="https://eccc-msc.github.io/open-data/licence/readme_en/">Environment and Climate Change Canada</a> and <a href="https://open-meteo.com/">Weather data by Open-Meteo.com</a>
        </small>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const locations = {
                skyline: {
                    lat: 49.9408,
                    lon: -114.0806,
                    name: "Skyline"
                },
                highwood: {
                    lat: 50.3873,
                    lon: -114.6436,
                    name: "The Highwood"
                },
                cathedral: {
                    lat: 51.4305,
                    lon: -116.4022,
                    name: "Cathedral"
                },
                bigChoss: {
                    lat: 51.1163,
                    lon: -115.1079,
                    name: "Big Choss"
                },
                calgary: {
                    lat: 51.05,
                    lon: -114.06,
                    name: "Calgary"
                },
                frank: {
                    lat: 49.5963,
                    lon: -114.3966,
                    name: "Frank"
                },
                whiteBuddha: {
                    lat: 50.8672,
                    lon: -114.8064,
                    name: "White Buddha"
                },
            };

            const wrapper = document.getElementById('alberta-map-wrapper');
            const img = document.getElementById('alberta-map-img');

            const weatherOutput = document.getElementById('weather-output');
            const weatherCragName = document.getElementById('weather-crag-name');
            const weatherSummaries = document.getElementById('weather-summaries');
            const chartCanvas = document.getElementById('weather-chart');
            let chart;

            const REF = {
                calgary: {
                    x: 1487,
                    y: 401
                },
                cathedral: {
                    x: 670,
                    y: 190
                }
            };


            function gpsToMap(lat, lon, imgWidth, imgHeight) {
                const A = {
                    ...locations.calgary,
                    ...REF.calgary
                };
                const B = {
                    ...locations.cathedral,
                    ...REF.cathedral
                };

                const fx = (lon - A.lon) / (B.lon - A.lon);
                const fy = (lat - A.lat) / (B.lat - A.lat);

                const xOriginal = A.x + fx * (B.x - A.x);
                const yOriginal = A.y + fy * (B.y - A.y);

                const scaledX = (xOriginal / 2417) * imgWidth;
                const scaledY = (yOriginal / 1380) * imgHeight;

                return {
                    x: scaledX,
                    y: scaledY
                };
            }



            function positionMarkers() {
                const w = img.clientWidth;
                const h = img.clientHeight;

                if (!w || !h) return;

                Object.entries(locations).forEach(([id, loc]) => {
                    const pos = gpsToMap(loc.lat, loc.lon, w, h);
                    const container = document.getElementById(`${id}-marker-container`);
                    if (!container) return;

                    container.style.left = `${pos.x}px`;
                    container.style.top = `${pos.y}px`;

                    const label = container.querySelector('.marker-label');
                });
            }


            if (img.complete) {
                positionMarkers();
            } else {
                img.addEventListener('load', positionMarkers);
            }

            window.addEventListener('resize', positionMarkers);

            function setWithExpiry(key, value, ttlSeconds) {
                const now = new Date();
                const item = {
                    value: value,
                    expiry: now.getTime() + ttlSeconds * 1000
                };
                localStorage.setItem(key, JSON.stringify(item));
            }

            function getWithExpiry(key) {
                const itemStr = localStorage.getItem(key);
                if (!itemStr) return null;
                const item = JSON.parse(itemStr);
                if (new Date().getTime() > item.expiry) {
                    localStorage.removeItem(key);
                    return null;
                }
                return item.value;
            }

            function getData(lat, lon, name) {
                const api = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&daily=precipitation_sum,temperature_2m_max,temperature_2m_min&hourly=temperature_2m,precipitation,precipitation_probability&past_days=2&forecast_days=6&timezone=auto`;

                const cacheKey = `${lat},${lon}`;
                const cached = getWithExpiry(cacheKey);

                weatherOutput.style.display = 'block';

                if (cached) {
                    showWeather(cached, name);
                    return;
                }

                fetch(api)
                    .then(res => res.json())
                    .then(data => {
                        setWithExpiry(cacheKey, data, 3600);
                        showWeather(data, name);
                    })
                    .catch(err => {
                        weatherOutput.innerHTML = `<p style="color:red;">Could not load weather forecast. Please try again later.</p>`;
                    });
            }

            function showWeather(data, name) {
                const todayIndex = 2;
                weatherCragName.innerHTML = `<h2>${name}</h2>`;
                weatherSummaries.innerHTML = '';
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
                    div.addEventListener('click', () => showHourlyChart(day, data));
                    weatherSummaries.appendChild(div);
                });
                showHourlyChart(days[todayIndex], data);
            }

            function showHourlyChart(dayStr, data) {
                const labels = [],
                    temps = [],
                    precips = [],
                    precipChance = [];
                let graphTempMinDefault = 0;
                let graphTempMaxDefault = 30;
                data.hourly.time.forEach((t, i) => {
                    if (t.startsWith(dayStr)) {
                        labels.push(formatAMPM(t));
                        temps.push(data.hourly.temperature_2m[i]);
                        precips.push(data.hourly.precipitation[i]);
                        precipChance.push(data.hourly.precipitation_probability[i]);
                    }
                });

                let minTemp = Math.round(Math.min(...temps));
                if (minTemp % 2 !== 0) minTemp -= 1;
                if (minTemp < 5) {
                    graphTempMinDefault = Math.round(minTemp) - 2;
                    graphTempMaxDefault = graphTempMaxDefault + minTemp - 2;
                }

                if (chart) chart.destroy();
                chart = new Chart(chartCanvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
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
                                backgroundColor: 'rgba(54, 163, 235, 0.5)',
                                borderRadius: 4,
                                yAxisID: 'y1',
                                barPercentage: 0.8
                            },
                            {
                                type: 'bar',
                                label: 'Precipitation Probability (%)',
                                data: precipChance,
                                backgroundColor: 'rgba(54, 163, 235, 0.2)',
                                yAxisID: 'y2',
                                barPercentage: 0.2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        aspectRatio: window.innerWidth < 768 ? 0.5 : 2.5,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: `Hourly Forecast for ${dayStr}`,
                                font: {
                                    size: 16
                                }
                            }
                        },
                        scales: {
                            x: {
                                stacked: true
                            },
                            y0: {
                                min: graphTempMinDefault,
                                max: graphTempMaxDefault,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Temperature (°C)'
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
                            },
                            y2: {
                                min: 0,
                                max: 100,
                                display: false
                            }
                        }
                    }
                });
            }

            function formatAMPM(date) {
                date = new Date(date);
                let hours = date.getHours();
                let minutes = date.getMinutes();
                const ampm = hours >= 12 ? 'pm' : 'am';
                hours = hours % 12 || 12;
                minutes = minutes < 10 ? '0' + minutes : minutes;
                return `${hours}:${minutes} ${ampm}`;
            }

            Object.entries(locations).forEach(([id, loc]) => {
                const marker = document.getElementById(`${id}-marker`);
                marker.addEventListener('click', () => getData(loc.lat, loc.lon, loc.name));
                if (location.hash === `#${id}`) getData(loc.lat, loc.lon, loc.name);
            });

            window.addEventListener('hashchange', () => {
                Object.entries(locations).forEach(([id, loc]) => {
                    if (location.hash === `#${id}`) getData(loc.lat, loc.lon, loc.name);
                });
            });

        });
    </script>
<?php
    return ob_get_clean();
});
