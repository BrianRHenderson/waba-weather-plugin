<?php
/**
 * Plugin Name: WABA Weather Forecast
 * Description: Shows clickable markers on an SVG map of Alberta and fetches weather data on click.
 * Version: 1.5.1
 * Author: Brian Henderson
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
            background: #2b6cb0;
            border: 2px solid white;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 4px rgba(0, 0, 0, 0.3);
            cursor: pointer;
        }

        .alberta-label {
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-60%);
            font-size: 14px;
            background: rgba(255, 255, 255, 0.8);
            padding: 1px 2px;
            border-radius: 4px;
            white-space: nowrap;
            pointer-events: none;
        }
        .alberta-label-offset {
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-30%);
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
        <div id="frank-marker-container" class="marker-container">
            <div id="frank-marker" class="alberta-marker" tabindex="4" aria-label="Frank weather marker" title="Click for weather"></div>
            <div class="alberta-label">Frank</div>
        </div>
        <div id="skyline-marker-container" class="marker-container">
            <div id="skyline-marker" class="alberta-marker" tabindex="4" aria-label="Skyline weather marker" title="Click for weather"></div>
            <div class="alberta-label-offset">Skyline</div>
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
        const locations = {
            skyline: { lat: 49.9408, lon: -114.0806, name: "Skyline" },
            highwood: { lat: 50.3873, lon: -114.6436, name: "The Highwood" },
            cathedral: { lat: 51.4305, lon: -116.4022, name: "Cathedral" },
            bigChoss: { lat: 51.1163, lon: -115.1079, name: "Big Choss" },
            calgary: { lat: 51.05, lon: -114.06, name: "Calgary" },
            frank: { lat: 49.5963, lon: -114.3966, name: "Frank" },
        };

        const viewWidth = 660, viewHeight = 515;
        const minLat = 49, maxLat = 54, minLon = -120, maxLon = -110;

        const wrapper = document.getElementById('alberta-map-wrapper');
        const weatherOutput = document.getElementById('weather-output');
        const weatherCragName = document.getElementById('weather-crag-name');
        const weatherSummaries = document.getElementById('weather-summaries');
        const chartCanvas = document.getElementById('weather-chart');

        let chart;

        function project(lat, lon) {
            const x = ((lon - minLon) / (maxLon - minLon)) * viewWidth;
            const y = ((maxLat - lat) / (maxLat - minLat)) * viewHeight;
            return { x, y };
        }

        function setWithExpiry(key, value, ttlSeconds) {
            const now = new Date();
            const item = {
                value: value,
                expiry: now.getTime() + ttlSeconds * 1000
            };
            sessionStorage.setItem(key, JSON.stringify(item));
        }

        function getWithExpiry(key) {
            const itemStr = sessionStorage.getItem(key);
            if (!itemStr) return null;
            const item = JSON.parse(itemStr);
            if (new Date().getTime() > item.expiry) {
                sessionStorage.removeItem(key);
                return null;
            }
            return item.value;
        }

        function getData(lat, lon, name) {
            const api = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&daily=precipitation_sum,temperature_2m_max,temperature_2m_min&hourly=temperature_2m,precipitation,precipitation_probability&past_days=2&forecast_days=4&timezone=auto&models=gem_seamless`;

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
                showWeather(data, name);
            })
            .then((data) => {
                setWithExpiry(cacheKey, data, 3600);
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
        const labels = [], temps = [], precips = [], precipChance = [];
            data.hourly.time.forEach((t, i) => {
                if (t.startsWith(dayStr)) {
                    labels.push(formatAMPM(t));
                    temps.push(data.hourly.temperature_2m[i]);
                    precips.push(data.hourly.precipitation[i]);
                    precipChance.push(data.hourly.precipitation_probability[i]);
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
                            pointRadius: 2,
                            yAxisID: 'y2',
                            barPercentage: 0.2
                        }
                    ]
                },
                options: {
                responsive: true,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    plugins: {
                        title: {
                            display: true,
                        text: `Hourly Forecast for ${dayStr}`,
                        font: { size: 18 }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true
                        },
                        y0: {
                            min: 0,
                            max: 30,
                            position: 'left',
                        title: { display: true, text: 'Temperature (°C)' }
                        },
                        y1: {
                            min: 0,
                            max: 15,
                            position: 'right',
                        title: { display: true, text: 'Precipitation (mm)' },
                        grid: { drawOnChartArea: false }
                        },
                        y2: {
                            min: 0,
                            max: 100,
                            display: false,
                        title: { display: false }
                        },
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

        const scale = wrapper.offsetWidth / viewWidth;
        Object.entries(locations).forEach(([id, loc]) => {
            const pos = project(loc.lat, loc.lon);
            const container = document.getElementById(`${id}-marker-container`);
            const marker = document.getElementById(`${id}-marker`);
            container.style.left = `${pos.x * scale}px`;
            container.style.top = `${pos.y * scale}px`;
            marker.addEventListener('click', () => getData(loc.lat, loc.lon, loc.name));
            if (location.hash === `#${id}`) {
                getData(loc.lat, loc.lon, loc.name);
                container.scrollIntoView();
            }
        });
        window.addEventListener('hashchange', () => {
            Object.entries(locations).forEach(([id, loc]) => {
                if (location.hash === `#${id}`) {
                    getData(loc.lat, loc.lon, loc.name);
                }
            })
        });
    });

    </script>
    <?php
    return ob_get_clean();
});
