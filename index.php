<?php
require_once __DIR__ . '/includes/session.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    header("Location: /environmental_monitor/" . ($role === 'admin' ? 'admin' : 'user') . "/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoMonitor — Smart Environmental Monitoring Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/environmental_monitor/assets/css/style.css" rel="stylesheet">
    <style>
        .landing-nav {
            position: absolute;
            width: 100%;
            z-index: 10;
            background: transparent;
            padding: 16px 0;
        }
    </style>
</head>
<body>

<nav class="landing-nav">
    <div class="container flex items-center justify-between">
        <a class="navbar-brand text-white" href="/environmental_monitor/">
            <i class="bi bi-cloud-sun-fill text-success"></i> EcoMonitor
        </a>
        <div class="flex gap-2">
            <a href="/environmental_monitor/auth/login.php" class="btn btn-outline-light btn-sm">Login</a>
            <a href="/environmental_monitor/auth/register.php" class="btn btn-primary btn-sm">Register</a>
        </div>
    </div>
</nav>

<section class="hero">
    <div class="container">
        <div class="row items-center">
            <div class="col-6">
                <div class="flex flex-wrap gap-2 mb-4">
                    <span class="stat-pill"><i class="bi bi-cpu"></i> 4 Active Sensors</span>
                    <span class="stat-pill"><i class="bi bi-geo-alt"></i> 4 Stations</span>
                    <span class="stat-pill"><i class="bi bi-activity"></i> Live Monitoring</span>
                </div>
                <h1 style="font-size:41.6px; font-weight:700; margin-bottom:16px;">
                    Smart Environmental<br>
                    <span class="text-success">Monitoring Platform</span>
                </h1>
                <p class="text-white-50" style="font-size:18.4px; margin-bottom:24px;">
                    Real-time environmental data from monitoring stations worldwide.
                    Track temperature, air quality, humidity, CO₂ levels and pollution
                    through an interactive analytics dashboard.
                </p>
                <div class="flex flex-wrap gap-3">
                    <a href="/environmental_monitor/auth/register.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-person-plus"></i> Get Started
                    </a>
                    <a href="/environmental_monitor/auth/login.php" class="btn btn-outline-light btn-lg">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                    </a>
                </div>
            </div>
            <div class="col-6">
                <div class="row gap-3 mt-4">
                    <div class="col-6">
                        <div class="card text-center" style="background:rgba(255,255,255,0.08); padding:16px;">
                            <i class="bi bi-thermometer-half text-danger" style="font-size:28.8px;"></i>
                            <div class="text-white" style="font-weight:700; font-size:22.4px;">27.3°C</div>
                            <div class="text-white-50" style="font-size:13px;">Avg Temperature</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card text-center" style="background:rgba(255,255,255,0.08); padding:16px;">
                            <i class="bi bi-wind text-info" style="font-size:28.8px;"></i>
                            <div class="text-white" style="font-weight:700; font-size:22.4px;">AQI 110</div>
                            <div class="text-white-50" style="font-size:13.6px;">Air Quality Index</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card text-center" style="background:rgba(255,255,255,0.08); padding:16px;">
                            <i class="bi bi-droplet-half" style="font-size:28.8px; color:#5aa9ff;"></i>
                            <div class="text-white" style="font-weight:700; font-size:22.4px;">62.0%</div>
                            <div class="text-white-50" style="font-size:13.6px;">Avg Humidity</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card text-center" style="background:rgba(255,255,255,0.08); padding:16px;">
                            <i class="bi bi-cloud-fog2 text-warning" style="font-size:28.8px;"></i>
                            <div class="text-white" style="font-weight:700; font-size:22.4px;">795 ppm</div>
                            <div class="text-white-50" style="font-size:13.6px;">Avg CO₂</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section style="padding: 80px 0; background: var(--color-light-bg);">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="section-title">Platform Features</h2>
            <p class="text-muted">Everything you need to monitor and understand environmental data</p>
        </div>
        <div class="row gap-1">
            <div class="col-4 mb-3">
                <div class="feature-card card mb-3">
                    <div class="feature-icon" style="background:rgba(25,135,84,0.1); color:var(--color-primary);">
                        <i class="bi bi-activity"></i>
                    </div>
                    <h5 style="font-weight:700;">Live Monitoring</h5>
                    <p class="text-muted">Real-time readings from all stations including temperature, humidity, AQI, CO₂, pollution and wind speed.</p>
                </div>
            </div>
            <div class="col-4 mb-3">
                <div class="feature-card card mb-3">
                    <div class="feature-icon" style="background:rgba(13,110,253,0.1); color:#0d6efd;">
                        <i class="bi bi-bar-chart-line"></i>
                    </div>
                    <h5 style="font-weight:700;">Advanced Analytics</h5>
                    <p class="text-muted">Interactive charts and trend analysis over 30-day periods. Compare stations and track environmental changes.</p>
                </div>
            </div>
            <div class="col-4 mb-3">
                <div class="feature-card card mb-3">
                    <div class="feature-icon" style="background:rgba(255,193,7,0.15); color:#b8860b;">
                        <i class="bi bi-bell"></i>
                    </div>
                    <h5 style="font-weight:700;">Smart Alerts</h5>
                    <p class="text-muted">Automatic alerts for dangerous AQI levels, extreme temperatures, storms and high pollution readings.</p>
                </div>
            </div>
            <div class="col-4 mb-3">
                <div class="feature-card card mb-3">
                    <div class="feature-icon" style="background:rgba(13,202,240,0.15); color:#0aa2c0;">
                        <i class="bi bi-geo-alt"></i>
                    </div>
                    <h5 style="font-weight:700;">Multi-Station</h5>
                    <p class="text-muted">Monitor stations across London, New York, Tokyo and Dubai simultaneously from one dashboard.</p>
                </div>
            </div>
            <div class="col-4 mb-3">
                <div class="feature-card card mb-3">
                    <div class="feature-icon" style="background:rgba(220,53,69,0.1); color:var(--color-danger);">
                        <i class="bi bi-file-earmark-bar-graph"></i>
                    </div>
                    <h5 style="font-weight:700;">CSV Reports</h5>
                    <p class="text-muted">Export filtered environmental data and alert records to CSV for offline analysis and record keeping.</p>
                </div>
            </div>
            <div class="col-4 mb-3">
                <div class="feature-card card mb-3">
                    <div class="feature-icon" style="background:rgba(108,117,125,0.12); color:var(--color-secondary);">
                        <i class="bi bi-diagram-3"></i>
                    </div>
                    <h5 style="font-weight:700;">Database + Graph Network</h5>
                    <p class="text-muted">MySQL, MongoDB and Neo4j working together — relational data, time-series readings and a sensor/location relationship graph.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="bg-dark text-white" style="padding: 20px 0;">
    <div class="container text-center">
        <p class="mb-0 text-white-50">
            <i class="bi bi-cloud-sun-fill text-success"></i>
            EcoMonitor — Smart Environmental Monitoring Platform
        </p>
        <small class="text-white-50">Built with PHP, MySQL, MongoDB, Neo4j &amp; Chart.js</small>
    </div>
</footer>

</body>
</html>
