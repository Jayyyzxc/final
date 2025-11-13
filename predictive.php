<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$is_logged_in = isLoggedIn();

// Try to fetch PAGASA real data
$pagasa_url = "https://api.pagasa.dost.gov.ph/weather/pampanga";
$weather_data = @json_decode(file_get_contents($pagasa_url), true);

// ===== Fallback simulated data if API not reachable =====
if (!$weather_data || !isset($weather_data['rainfall'])) {
    $weather_data = [
        "rainfall" => [200, 250, 300, 400, 450, 500, 550, 480, 350, 250, 200, 150],
        "temperature" => [30, 31, 33, 34, 35, 36, 35, 34, 33, 32, 31, 30]
    ];
}
$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];

// ===== Calculate risks =====
$risk_data = [];
for ($i=0; $i<12; $i++) {
    $month = $months[$i];
    $rain = $weather_data['rainfall'][$i] ?? 0;
    $temp = $weather_data['temperature'][$i] ?? 0;

    $dengue_risk  = min(100, ($rain / 500) * 100);
    $flood_risk   = min(100, ($rain / 550) * 100);
    $heat_risk    = max(0, (($temp - 30) / 10) * 100);
    $drought_risk = max(0, (1 - ($rain / 500)) * 100);
    $overall = round(($dengue_risk + $flood_risk + $heat_risk + $drought_risk) / 4);

    $risk_data[] = [
        'month' => $month,
        'rainfall' => $rain,
        'temperature' => $temp,
        'dengue' => round($dengue_risk, 1),
        'flood' => round($flood_risk, 1),
        'heat' => round($heat_risk, 1),
        'drought' => round($drought_risk, 1),
        'overall' => $overall
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Predictive Forecast - Barangay Profiling System</title>
<link rel="stylesheet" href="predictive.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* Center containers */
.graph-container {
    max-width: 900px;
    margin: 40px auto;
    padding: 20px;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.graph-container h2 {
    text-align: center;
    margin-bottom: 10px;
    color: #1d3b71;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    justify-content: center; align-items: center;
}
.modal-content {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    width: 400px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    animation: zoomIn 0.3s ease;
}
@keyframes zoomIn {
    from { transform: scale(0.8); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
.modal-header { font-size: 20px; font-weight: bold; margin-bottom: 10px; }
.close-btn { float: right; cursor: pointer; color: #666; font-size: 20px; }
.close-btn:hover { color: red; }
.risk-item { margin: 8px 0; }
.risk-item span { font-weight: bold; }
</style>
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Barangay Event And Program Planning System</h2>
            <?php if ($is_logged_in): ?>
                <div class="welcome">
                    <p>Welcome, <?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? 'User'); ?></p>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            <?php else: ?>
                <div class="welcome">
                    <a href="login.php" class="login-btn">Login</a>
                </div>
            <?php endif; ?>
        </div>
        <?php
        if (!isset($is_super_admin)) {
            $is_super_admin = (isset($_SESSION['user']['role']) && in_array($_SESSION['user']['role'], ['super_admin','superadmin'], true));
        }
        ?>
        <nav class="sidebar-nav">
            <ul>
               <li><a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-house-user"></i> Dashboard</a></li>
                <li><a href="resident.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'resident.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Residents</a></li>
                <li><a href="analytics.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                <li><a href="predictive.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'predictive.php' ? 'active' : ''; ?>"><i class="fas fa-brain"></i> Predictive Models</a></li>
                <li><a href="events.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'events.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> Events</a></li>
               
                <!-- Super Admin Only Links -->
                <?php if ($is_super_admin): ?>
                    <li><a href="superadmin.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'superadmin.php' ? 'active' : ''; ?>"><i class="fas fa-inbox"></i> Requests</a></li>
                <?php endif; ?>
                
                <?php if ($is_logged_in): ?>
                    <li><a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> Settings</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <!-- === Main Content === -->
    <div class="main-content">
        <h1><i class="fas fa-brain"></i> Predictive Models</h1>

        <!-- ===== Weather Risk Forecast Graph ===== -->
        <div class="graph-container">
            <h2>🌦 Weather Risk Forecast</h2>
            <canvas id="riskChart"></canvas>
        </div>

        <!-- Modal for weather risk -->
        <div id="riskModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" id="closeRiskModal">&times;</span>
                <div id="riskModalBody"></div>
            </div>
        </div>
    </div>
</div>

<script>
const months = <?= json_encode(array_column($risk_data, 'month')); ?>;
const dengue = <?= json_encode(array_column($risk_data, 'dengue')); ?>;
const flood = <?= json_encode(array_column($risk_data, 'flood')); ?>;
const heat = <?= json_encode(array_column($risk_data, 'heat')); ?>;
const drought = <?= json_encode(array_column($risk_data, 'drought')); ?>;
const rainfall = <?= json_encode(array_column($risk_data, 'rainfall')); ?>;
const temp = <?= json_encode(array_column($risk_data, 'temperature')); ?>;

// ===== WEATHER RISK CHART =====
const ctx1 = document.getElementById('riskChart').getContext('2d');
const riskChart = new Chart(ctx1, {
    type: 'line',
    data: {
        labels: months,
        datasets: [
            { label: 'Dengue/Flood Risk', data: dengue, borderColor: '#FF6384', backgroundColor: 'rgba(255,99,132,0.2)', tension: 0.4, fill: true },
            { label: 'Heat Risk', data: heat, borderColor: '#FFCE56', backgroundColor: 'rgba(255,206,86,0.2)', tension: 0.4, fill: true },
            { label: 'Drought Risk', data: drought, borderColor: '#4BC0C0', backgroundColor: 'rgba(75,192,192,0.2)', tension: 0.4, fill: true }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' }, title: { display: true, text: 'Weather-Based Monthly Risk Forecast' } },
        scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'Risk Level (%)' } } },
        onClick: (e) => {
            const points = riskChart.getElementsAtEventForMode(e, 'nearest', { intersect: true }, true);
            if (points.length) showRiskModal(points[0].index);
        }
    }
});

// ===== WEATHER MODAL =====
const riskModal = document.getElementById('riskModal');
const closeRiskModal = document.getElementById('closeRiskModal');
const riskModalBody = document.getElementById('riskModalBody');
closeRiskModal.onclick = () => riskModal.style.display = 'none';
window.onclick = (e) => { if (e.target === riskModal) riskModal.style.display = 'none'; };

function showRiskModal(i) {
    const rain = rainfall[i], t = temp[i];
    let risk = "Stable", rec = "Normal monitoring.";
    if (rain > 450) { risk = "🚨 Flood / Dengue Risk"; rec = "Clean drainage and prepare flood kits."; }
    else if (t > 35) { risk = "🔥 Heat Stroke Risk"; rec = "Advise hydration, avoid long outdoor exposure."; }
    else if (rain < 150 && t > 33) { risk = "🌾 Drought Risk"; rec = "Encourage water conservation and planting."; }
    riskModalBody.innerHTML = `
        <div class="modal-header">${months[i]} Risk Details</div>
        <div class="risk-item"><span>Rainfall:</span> ${rain} mm</div>
        <div class="risk-item"><span>Temperature:</span> ${t} °C</div>
        <div class="risk-item"><span>Main Risk:</span> ${risk}</div>
        <div class="risk-item"><span>Recommendation:</span> ${rec}</div>
    `;
    riskModal.style.display = 'flex';
}
</script>
</body>
</html>