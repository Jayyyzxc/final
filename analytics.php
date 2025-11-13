<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user']);
$user_role = $_SESSION['user']['role'] ?? null;
$user_barangay_id = $_SESSION['user']['barangay_id'] ?? null;

// Get analytics data with proper barangay filtering
function getAnalyticsData($barangay_id = null, $purok_id = null) {
    global $conn;
    
    $data = [];
    
    // Build WHERE clause based on filters
    $whereClause = "WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($barangay_id) {
        $whereClause .= " AND r.barangay_id = ?";
        $params[] = $barangay_id;
        $types .= "i";
    }
    
    if ($purok_id) {
        $whereClause .= " AND r.purok_id = ?";
        $params[] = $purok_id;
        $types .= "i";
    }
    
    // Age distribution
    $ageQuery = "SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(YEAR, r.birthdate, CURDATE()) < 18 THEN '0-17'
            WHEN TIMESTAMPDIFF(YEAR, r.birthdate, CURDATE()) BETWEEN 18 AND 24 THEN '18-24'
            WHEN TIMESTAMPDIFF(YEAR, r.birthdate, CURDATE()) BETWEEN 25 AND 34 THEN '25-34'
            WHEN TIMESTAMPDIFF(YEAR, r.birthdate, CURDATE()) BETWEEN 35 AND 44 THEN '35-44'
            WHEN TIMESTAMPDIFF(YEAR, r.birthdate, CURDATE()) BETWEEN 45 AND 59 THEN '45-59'
            ELSE '60+'
        END AS age_group,
        COUNT(*) AS count
        FROM residents r
        $whereClause
        GROUP BY age_group ORDER BY age_group";
    
    $stmt = $conn->prepare($ageQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data['age_distribution'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Employment status
    $employmentQuery = "SELECT employment_status, COUNT(*) AS count FROM residents r $whereClause GROUP BY employment_status";
    
    $stmt = $conn->prepare($employmentQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data['employment_status'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Gender ratio
    $genderQuery = "SELECT gender, COUNT(*) AS count FROM residents r $whereClause GROUP BY gender";
    
    $stmt = $conn->prepare($genderQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data['gender_ratio'] = $result->fetch_all(MYSQLI_ASSOC);
    
    return $data;
}

// Get filters
$selected_barangay = isset($_GET['barangay']) ? intval($_GET['barangay']) : null;
$selected_purok = isset($_GET['purok']) ? intval($_GET['purok']) : null;

// Determine which barangay to show data for
$current_barangay_id = null;

if ($user_role === 'super_admin') {
    $current_barangay_id = $selected_barangay;
} elseif (in_array($user_role, ['official', 'captain'])) {
    $current_barangay_id = $user_barangay_id;
}

// Get all barangays for dropdown (only for super admin)
$barangays = [];
if ($user_role === 'super_admin') {
    $barangayQuery = "SELECT id, barangay_name FROM barangay_registration ORDER BY barangay_name";
    $result = $conn->query($barangayQuery);
    if ($result) {
        $barangays = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Get puroks for dropdown
$puroks = [];
$purokQuery = "SELECT * FROM puroks WHERE 1=1";
$purokParams = [];
$purokTypes = "";

if ($current_barangay_id) {
    $purokQuery .= " AND barangay_id = ?";
    $purokParams[] = $current_barangay_id;
    $purokTypes = "i";
}

$purokQuery .= " ORDER BY purok_name";

if (!empty($purokParams)) {
    $stmt = $conn->prepare($purokQuery);
    $stmt->bind_param($purokTypes, ...$purokParams);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($purokQuery);
}

if ($result) {
    $puroks = $result->fetch_all(MYSQLI_ASSOC);
}

// Get analytics data
$analyticsData = getAnalyticsData($current_barangay_id, $selected_purok);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demographic Analytics - Barangay Profiling System</title>
        <link rel="stylesheet" href="analytics.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-600: #6c757d;
            --primary-blue: #007bff;
            --shadow-sm: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .analytics-container {
            padding: 20px;
            margin-left: var(--sidebar-width);
        }
        
        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .filter-section {
            background-color: var(--white);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-row select, .filter-row button {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid var(--gray-300);
        }
        
        .filter-row button {
            background-color: var(--primary-blue);
            color: white;
            border: none;
            cursor: pointer;
        }
        
        .current-view {
            background-color: var(--gray-100);
            padding: 10px 15px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        
        .analytics-card {
            background-color: var(--white);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow-sm);
        }
        
        .card-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .stat-item {
            background-color: var(--gray-100);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-blue);
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--gray-600);
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background: #2c3e50;
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #34495e;
        }
        
        .sidebar-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-nav li a {
            display: block;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            border-left: 4px solid transparent;
        }
        
        .sidebar-nav li a:hover,
        .sidebar-nav li a.active {
            background: #34495e;
            border-left-color: var(--primary-blue);
        }
        
        .sidebar-nav li a i {
            margin-right: 10px;
            width: 20px;
        }
        
        .welcome {
            margin-top: 10px;
            font-size: 14px;
        }
        
        .welcome a {
            color: #3498db;
            text-decoration: none;
        }
        
        .welcome a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Barangay System</h2>
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
        <nav class="sidebar-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-house-user"></i> Dashboard</a></li>
                <li><a href="resident.php"><i class="fas fa-users"></i> Residents</a></li>
                <li><a href="analytics.php" class="active"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                <li><a href="predictive.php"><i class="fas fa-brain"></i> Predictive Models</a></li>
                <li><a href="events.php"><i class="fas fa-calendar-alt"></i> Events</a></li>
                <li><a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a></li>
                <?php if ($is_logged_in): ?>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="analytics-container">
        <div class="analytics-header">
            <h1><i class="fas fa-chart-bar"></i> Demographic Analytics</h1>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="get" action="analytics.php">
                <div class="filter-row">
                    <?php if ($user_role === 'super_admin'): ?>
                        <label for="barangay">Filter by Barangay:</label>
                        <select name="barangay" id="barangay">
                            <option value="">All Barangays</option>
                            <?php foreach ($barangays as $barangay): ?>
                                <option value="<?php echo $barangay['id']; ?>" <?php echo $selected_barangay == $barangay['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    
                    <label for="purok">Filter by Purok:</label>
                    <select name="purok" id="purok">
                        <option value="">All Puroks</option>
                        <?php foreach ($puroks as $purok): ?>
                            <option value="<?php echo $purok['purok_id']; ?>" <?php echo $selected_purok == $purok['purok_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($purok['purok_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Apply Filter</button>
                </div>
            </form>
            
            <!-- Current View Info -->
            <div class="current-view">
                <strong>Currently Viewing:</strong> 
                <?php
                if ($user_role === 'super_admin') {
                    if ($selected_barangay) {
                        $barangay_name = "Selected Barangay";
                        foreach ($barangays as $barangay) {
                            if ($barangay['id'] == $selected_barangay) {
                                $barangay_name = $barangay['barangay_name'];
                                break;
                            }
                        }
                        echo htmlspecialchars($barangay_name);
                    } else {
                        echo "All Barangays (Overall)";
                    }
                } else {
                    echo "Your Barangay";
                }
                
                if ($selected_purok) {
                    $purok_name = "";
                    foreach ($puroks as $purok) {
                        if ($purok['purok_id'] == $selected_purok) {
                            $purok_name = $purok['purok_name'];
                            break;
                        }
                    }
                    echo " > " . htmlspecialchars($purok_name);
                } else {
                    echo " > All Puroks";
                }
                ?>
            </div>
        </div>
        
        <!-- Analytics Grid -->
        <div class="analytics-grid">
            <!-- Age Distribution Card -->
            <div class="analytics-card">
                <div class="card-header">
                    <h3>Age Distribution</h3>
                </div>
                <div class="chart-container">
                    <canvas id="ageChart"></canvas>
                </div>
            </div>
            
            <!-- Employment Status Card -->
            <div class="analytics-card">
                <div class="card-header">
                    <h3>Employment Status</h3>
                </div>
                <div class="chart-container">
                    <canvas id="employmentChart"></canvas>
                </div>
            </div>
            
            <!-- Gender Ratio Card -->
            <div class="analytics-card">
                <div class="card-header">
                    <h3>Gender Ratio</h3>
                </div>
                <div class="chart-container">
                    <canvas id="genderChart"></canvas>
                </div>
            </div>
            
            <!-- Quick Stats Card -->
            <div class="analytics-card">
                <div class="card-header">
                    <h3>Quick Statistics</h3>
                </div>
                <div class="stats-grid">
                    <?php
                    // Calculate total residents
                    $totalResidents = array_sum(array_column($analyticsData['gender_ratio'], 'count'));
                    
                    // Calculate gender percentages
                    $maleCount = 0;
                    $femaleCount = 0;
                    $otherCount = 0;
                    foreach ($analyticsData['gender_ratio'] as $gender) {
                        if ($gender['gender'] == 'Male') $maleCount = $gender['count'];
                        if ($gender['gender'] == 'Female') $femaleCount = $gender['count'];
                        if ($gender['gender'] == 'Other') $otherCount = $gender['count'];
                    }
                    $malePercent = $totalResidents > 0 ? round(($maleCount / $totalResidents) * 100, 1) : 0;
                    $femalePercent = $totalResidents > 0 ? round(($femaleCount / $totalResidents) * 100, 1) : 0;
                    
                    // Employment stats
                    $employedCount = 0;
                    $unemployedCount = 0;
                    $studentCount = 0;
                    $retiredCount = 0;
                    foreach ($analyticsData['employment_status'] as $employment) {
                        if ($employment['employment_status'] == 'Employed') $employedCount = $employment['count'];
                        if ($employment['employment_status'] == 'Unemployed') $unemployedCount = $employment['count'];
                        if ($employment['employment_status'] == 'Student') $studentCount = $employment['count'];
                        if ($employment['employment_status'] == 'Retired') $retiredCount = $employment['count'];
                    }
                    ?>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $totalResidents; ?></div>
                        <div class="stat-label">Total Residents</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $malePercent; ?>%</div>
                        <div class="stat-label">Male</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $femalePercent; ?>%</div>
                        <div class="stat-label">Female</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $employedCount; ?></div>
                        <div class="stat-label">Employed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $unemployedCount; ?></div>
                        <div class="stat-label">Unemployed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $studentCount; ?></div>
                        <div class="stat-label">Students</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script>
    // Prepare data for charts
    const ageData = {
        labels: <?php echo json_encode(array_column($analyticsData['age_distribution'] ?? [], 'age_group')); ?>,
        values: <?php echo json_encode(array_column($analyticsData['age_distribution'] ?? [], 'count')); ?>
    };
    
    const employmentData = {
        labels: <?php echo json_encode(array_column($analyticsData['employment_status'] ?? [], 'employment_status')); ?>,
        values: <?php echo json_encode(array_column($analyticsData['employment_status'] ?? [], 'count')); ?>
    };
    
    const genderData = {
        labels: <?php echo json_encode(array_column($analyticsData['gender_ratio'] ?? [], 'gender')); ?>,
        values: <?php echo json_encode(array_column($analyticsData['gender_ratio'] ?? [], 'count')); ?>
    };
    
    // Colors
    const chartColors = {
        blue: 'rgba(54, 162, 235, 0.7)',
        red: 'rgba(255, 99, 132, 0.7)',
        yellow: 'rgba(255, 206, 86, 0.7)',
        green: 'rgba(75, 192, 192, 0.7)',
        purple: 'rgba(153, 102, 255, 0.7)',
        orange: 'rgba(255, 159, 64, 0.7)',
        gray: 'rgba(201, 203, 207, 0.7)'
    };

    // Initialize charts when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Age Distribution Chart (Bar)
        const ageCtx = document.getElementById('ageChart').getContext('2d');
        new Chart(ageCtx, {
            type: 'bar',
            data: {
                labels: ageData.labels,
                datasets: [{
                    label: 'Number of Residents',
                    data: ageData.values,
                    backgroundColor: [
                        chartColors.blue,
                        chartColors.red,
                        chartColors.yellow,
                        chartColors.green,
                        chartColors.purple,
                        chartColors.orange
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Residents'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Age Group'
                        }
                    }
                }
            }
        });
        
        // Employment Status Chart (Doughnut)
        const employmentCtx = document.getElementById('employmentChart').getContext('2d');
        new Chart(employmentCtx, {
            type: 'doughnut',
            data: {
                labels: employmentData.labels,
                datasets: [{
                    data: employmentData.values,
                    backgroundColor: [
                        chartColors.green,
                        chartColors.red,
                        chartColors.blue,
                        chartColors.orange
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        
        // Gender Ratio Chart (Pie)
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        new Chart(genderCtx, {
            type: 'pie',
            data: {
                labels: genderData.labels,
                datasets: [{
                    data: genderData.values,
                    backgroundColor: [
                        chartColors.blue,
                        chartColors.red,
                        chartColors.purple
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    });
</script>
</body>
</html>
