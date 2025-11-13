<?php
require_once 'config.php';

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Check if public access is enabled
$public_access = $settings['public_access'] ?? 1;
$is_logged_in = isLoggedIn();

// Check if user is super admin
$is_super_admin = false;
if ($is_logged_in && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'super_admin') {
    $is_super_admin = true;
}

// Get data from database using MySQLi
$total_residents = getResidentCount();
$total_households = getHouseholdCount();
$upcoming_events_count = getUpcomingEventsCount();
$age_distribution = getAgeDistribution();
$gender_distribution = getGenderDistribution();
$employment_status = getEmploymentStatus();
$upcoming_events = getUpcomingEvents(5);

// Prepare data for charts
$age_labels = [];
$age_data = [];
foreach ($age_distribution as $age) {
    $age_labels[] = $age['age_group'];
    $age_data[] = $age['count'];
}

$gender_labels = [];
$gender_data = [];
foreach ($gender_distribution as $gender) {
    $gender_labels[] = $gender['gender'];
    $gender_data[] = $gender['count'];
}

$employment_labels = [];
$employment_data = [];
foreach ($employment_status as $status) {
    $employment_labels[] = $status['employment_status'];
    $employment_data[] = $status['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(APP_NAME); ?></title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
</head>
<body>
<div class="dashboard-container">
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo-link">
                <img src="logo.png" alt="Admin & Staff Logo" class="header-logo">
            </a>
            <h2>A Web-based Barangay Demographic Profiling System</h2>
            <?php if ($is_logged_in): ?>
                <div class="welcome">
                    <p>Welcome, <?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? 'User'); ?></p>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            <?php else: ?>
                <div class="welcome">
                    <p> </p>
                    <a href="login.php" class="login-btn">Staff Login</a>
                </div>
            <?php endif; ?>
        </div>
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

    <div class="main-content">
        <div class="dashboard-header">
            <h2><i class="fas fa-house-user"></i> Dashboard</h2>
            <?php if ($is_super_admin): ?>
                <span class="admin-badge"><i class="fas fa-crown"></i> Super Admin</span>
            <?php endif; ?>
        </div>
        <?php if (!$is_logged_in && !$public_access): ?>
            <div class="access-denied">
                <i class="fas fa-lock"></i>
                <h2>Public Access Disabled</h2>
                <p>Please login to view the dashboard</p>
                <a href="login.php" class="login-btn">Login</a>
            </div>
        <?php else: ?>
            <div class="dashboard-header">
                <h4>Overview - <?php echo date('F j, Y'); ?></h4>
                <?php if ($is_super_admin): ?>
                    <p class="admin-notice">You are viewing the system as Super Administrator</p>
                <?php endif; ?>
            </div>

            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Residents</h3>
                        <p><?php echo number_format($total_residents); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Households</h3>
                        <p><?php echo number_format($total_households); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Upcoming Events</h3>
                        <p><?php echo number_format($upcoming_events_count); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Population Growth</h3>
                        <p>+5.2%</p>
                    </div>
                </div>
            </div>

            <div class="dashboard-charts-grid dashboard-charts-grid-vertical">
                <!-- Column 1: Residents by Age Group (top), Event Calendar (bottom) -->
                <div class="card" style="grid-column: 1; grid-row: 1;">
                    <div class="card-header">
                        <h3>Residents by Age Group</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="ageChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="card" style="grid-column: 1; grid-row: 2;">
                    <div class="card-header">
                        <h3>Event Calendar</h3>
                    </div>
                    <div class="card-body">
                        <div class="calendar-month">
                            <button class="btn-icon"><i class="fas fa-chevron-left"></i></button>
                            <span><?php echo date('F Y'); ?></span>
                            <button class="btn-icon"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <div class="calendar-days">
                            <div class="day-header">Sun</div>
                            <div class="day-header">Mon</div>
                            <div class="day-header">Tue</div>
                            <div class="day-header">Wed</div>
                            <div class="day-header">Thu</div>
                            <div class="day-header">Fri</div>
                            <div class="day-header">Sat</div>
                            <?php
                            $first_day = date('w', strtotime(date('Y-m-01')));
                            $days_in_month = date('t');
                            for ($i = 0; $i < $first_day; $i++) {
                                echo "<div class='calendar-day'></div>";
                            }
                            for ($i = 1; $i <= $days_in_month; $i++) {
                                $dayClass = '';
                                $current_date = date('Y-m') . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM events WHERE event_date = ?");
                                $stmt->bind_param("s", $current_date);
                                $stmt->execute();
                                $stmt->bind_result($has_event);
                                $stmt->fetch();
                                $stmt->close();
                                if ($has_event) {
                                    $dayClass = 'event-day';
                                }
                                echo "<div class='calendar-day $dayClass'>$i</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <!-- Column 2: Gender Distribution (top), Upcoming Events (bottom) -->
                <div class="card" style="grid-column: 2; grid-row: 1;">
                    <div class="card-header">
                        <h3>Gender Distribution</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="genderChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="card" style="grid-column: 2; grid-row: 2;">
                    <div class="card-header">
                        <h3>Upcoming Events</h3>
                    </div>
                    <div class="card-body">
                        <ul class="events-list">
                            <?php foreach ($upcoming_events as $event): ?>
                                <li>
                                    <div class="event-icon">
                                        <i class="fas fa-calendar-day"></i>
                                    </div>
                                    <div class="event-details">
                                        <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                        <span><?php echo date('M j, Y', strtotime($event['event_date'])); ?> • <?php echo htmlspecialchars($event['location']); ?></span>
                                    </div>
                                    <div class="event-time">
    <?php 
        if (!empty($event['start_time']) && $event['start_time'] !== "00:00:00") {
            echo date('g:i A', strtotime($event['start_time']));
        } else {
            echo "TBA";
        }
    ?>
</div>
                                </li>
                            <?php endforeach; ?>
                            <?php if (empty($upcoming_events)): ?>
                                <li class="no-events">
                                    <i class="fas fa-calendar-times"></i>
                                    <span>No upcoming events</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <!-- Column 3: Employment Status (spans both rows) -->
                <div class="card" style="grid-column: 3; grid-row: 1 / span 2;">
                    <div class="card-header">
                        <h3>Employment Status</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="employmentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions card: visible to any logged-in user -->
            <?php if ($is_logged_in): ?>
           
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Chart colors
    const chartColors = {
        blue: 'rgba(54, 162, 235, 0.7)',
        red: 'rgba(255, 99, 132, 0.7)',
        yellow: 'rgba(255, 206, 86, 0.7)',
        green: 'rgba(75, 192, 192, 0.7)',
        purple: 'rgba(153, 102, 255, 0.7)',
        orange: 'rgba(255, 159, 64, 0.7)',
        gray: 'rgba(201, 203, 207, 0.7)'
    };

    // Age Distribution Chart
    const ageCtx = document.getElementById('ageChart').getContext('2d');
    const ageChart = new Chart(ageCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($age_labels); ?>,
            datasets: [{
                label: 'Residents by Age Group',
                data: <?php echo json_encode($age_data); ?>,
                backgroundColor: chartColors.blue,
                borderColor: chartColors.blue.replace('0.7', '1'),
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.raw} residents`;
                        }
                    }
                },
                datalabels: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    },
                    grid: {
                        display: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        },
        plugins: [ChartDataLabels]
    });

    // Gender Distribution Chart
    const genderCtx = document.getElementById('genderChart').getContext('2d');
    const genderChart = new Chart(genderCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($gender_labels); ?>,
            datasets: [{
                label: 'Gender Distribution',
                data: <?php echo json_encode($gender_data); ?>,
                backgroundColor: [
                    chartColors.red,
                    chartColors.blue,
                    chartColors.yellow
                ],
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12,
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${context.label}: ${context.raw} (${percentage}%)`;
                        }
                    }
                },
                datalabels: {
                    formatter: (value, ctx) => {
                        const total = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((value / total) * 100);
                        return `${percentage}%`;
                    },
                    color: '#fff',
                    font: {
                        weight: 'bold'
                    }
                }
            },
            cutout: '65%'
        },
        plugins: [ChartDataLabels]
    });

    // Employment Status Chart
    const employmentCtx = document.getElementById('employmentChart').getContext('2d');
    const employmentChart = new Chart(employmentCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($employment_labels); ?>,
            datasets: [{
                label: 'Employment Status',
                data: <?php echo json_encode($employment_data); ?>,
                backgroundColor: [
                    chartColors.green,
                    chartColors.red,
                    chartColors.purple,
                    chartColors.orange,
                    chartColors.gray
                ],
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12,
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${context.label}: ${context.raw} (${percentage}%)`;
                        }
                    }
                },
                datalabels: {
                    formatter: (value, ctx) => {
                        const total = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((value / total) * 100);
                        return percentage >= 10 ? `${percentage}%` : '';
                    },
                    color: '#fff',
                    font: {
                        weight: 'bold'
                    }
                }
            },
            cutout: '65%'
        },
        plugins: [ChartDataLabels]
    });

    // Make charts responsive to window resize
    window.addEventListener('resize', function() {
        ageChart.resize();
        genderChart.resize();
        employmentChart.resize();
    });

    // Generate Report function
    function generateReport() {
        // Show loading state
        const btn = event.target.closest('.action-btn');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        btn.disabled = true;
        
        // Simulate report generation (replace with actual AJAX call)
        setTimeout(() => {
            alert('Report generated successfully!');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }, 1500);
    }

    // Calendar navigation (basic implementation)
    document.querySelectorAll('.calendar-month button').forEach(btn => {
        btn.addEventListener('click', function() {
            // In a real implementation, this would fetch new month data
            alert('Calendar navigation would load new month data here');
        });
    });
</script>
</body>
</html>