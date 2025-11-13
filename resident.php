<?php
require_once 'config.php';
require_once 'functions.php';

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Session checks
$public_access = $settings['public_access'] ?? 1;
$is_logged_in  = isLoggedIn();
$user_role = $_SESSION['user']['role'] ?? null;
$user_barangay_id = $_SESSION['user']['barangay_id'] ?? null;

$residents     = [];
$search_term   = $_GET['search'] ?? '';
$selected_barangay = isset($_GET['barangay']) ? intval($_GET['barangay']) : null;
$selected_purok = isset($_GET['purok']) ? intval($_GET['purok']) : null;

// Get all barangays for dropdown (only for super admin)
$barangays = [];
if ($user_role === 'super_admin') {
    $barangayQuery = "SELECT id, barangay_name FROM barangay_registration ORDER BY barangay_name";
    $result = $conn->query($barangayQuery);
    if ($result) {
        $barangays = $result->fetch_all(MYSQLI_ASSOC);
    }
} else {
    // For non-super admin users, get their specific barangay
    if ($user_barangay_id) {
        $barangayQuery = "SELECT id, barangay_name FROM barangay_registration WHERE id = ?";
        $stmt = $conn->prepare($barangayQuery);
        $stmt->bind_param("i", $user_barangay_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $barangays = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Determine which barangay to show data for
$current_barangay_id = null;

if ($user_role === 'super_admin') {
    $current_barangay_id = $selected_barangay;
} elseif (in_array($user_role, ['official', 'captain'])) {
    $current_barangay_id = $user_barangay_id;
}

// Get puroks for dropdown (filtered by current barangay if applicable)
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

// Delete resident
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && $is_logged_in) {
    $delete_id = intval($_POST['delete_id']);
    $stmt = $conn->prepare("DELETE FROM residents WHERE id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        echo "<script>alert('Resident deleted successfully.'); window.location.href='resident.php';</script>";
        exit();
    } else {
        echo "<script>alert('Failed to delete resident.');</script>";
    }
    $stmt->close();
}

// Search + filter
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query  = "SELECT r.*, p.purok_name, p.purok_id AS actual_purok_id, 
               br.barangay_name, br.id AS barangay_id
               FROM residents r
               LEFT JOIN puroks p ON r.purok_id = p.purok_id
               LEFT JOIN barangay_registration br ON r.barangay_id = br.id
               WHERE 1=1";

    $params = [];
    $types  = '';

    if (!empty($search_term)) {
        $query .= " AND (r.first_name LIKE ? OR r.last_name LIKE ? OR r.address LIKE ?)";
        $search_param = "%" . $conn->real_escape_string($search_term) . "%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
        $types  .= 'sss';
    }

    // Apply barangay filter
    if ($current_barangay_id) {
        $query .= " AND r.barangay_id = ?";
        $params[] = $current_barangay_id;
        $types   .= 'i';
    }

    if ($selected_purok) {
        $query .= " AND r.purok_id = ?";
        $params[] = $selected_purok;
        $types   .= 'i';
    }

    $query .= " ORDER BY r.last_name, r.first_name";
    $stmt = $conn->prepare($query);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result    = $stmt->get_result();
    $residents = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Resident Information</title>
    <link rel="stylesheet" href="resident.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        .filter-container select {
            padding: 10px 15px;
            border-radius: 4px;
            border: 1px solid #ddd;
            background-color: white;
            font-size: 14px;
            color: #333;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 10px;
        }
        .filter-container select:hover { border-color: #1d3b71; }
        .filter-container select:focus {
            outline: none; border-color: #1d3b71;
            box-shadow: 0 0 0 2px rgba(29, 59, 113, 0.2);
        }
        .action-buttons { display: flex; gap: 10px; margin-bottom: 20px; align-items: center; }
        .action-buttons .add-resident-btn {
            background-color: #1d3b71; color: white; border: none;
            padding: 10px 15px; border-radius: 4px; cursor: pointer;
            display: flex; align-items: center; gap: 5px; font-size: 14px;
            transition: background-color 0.3s;
        }
        .action-buttons .add-resident-btn:hover { background-color: #2c4d8a; }
        .fill-census-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .fill-census-btn:hover {
            background-color: #218838;
        }
        .purok-badge {
            display: inline-block; padding: 3px 8px;
            border-radius: 12px; background-color: #e0e0e0;
            color: #333; font-size: 12px; font-weight: 500;
        }
        .barangay-badge {
            display: inline-block; padding: 3px 8px;
            border-radius: 12px; background-color: #1d3b71;
            color: white; font-size: 11px; font-weight: 500;
            margin-left: 5px;
        }
        .no-results { text-align: center; padding: 20px; color: #666; }
        /* Modal styles */
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0;
                 width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content {
            background: #fff; margin: 10% auto; padding: 20px;
            border-radius: 8px; width: 500px; max-width: 90%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .close-btn { float: right; font-size: 20px; cursor: pointer; }
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
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

    <!-- Main Content -->
    <main class="main-content" style="margin-left: 280px; padding: 20px;">
        <div class="resident-header">
            <h1><i class="fas fa-users"></i> Residents</h1>
        </div>

        <!-- Search -->
        <div class="search-container">
            <form method="GET" action="resident.php">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search residents..."
                           value="<?php echo htmlspecialchars($search_term); ?>" />
                    <button type="submit">Search</button>
                </div>
            </form>
        </div>

        <!-- Filters and Actions -->
        <div class="action-buttons">
            <div class="filter-container">
                <form method="GET" action="resident.php" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <?php if ($user_role === 'super_admin'): ?>
                        <select name="barangay" onchange="this.form.submit()">
                            <option value="">All Barangays</option>
                            <?php foreach ($barangays as $barangay): ?>
                                <option value="<?php echo $barangay['id']; ?>" 
                                    <?php echo $selected_barangay == $barangay['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    
                    <select name="purok" onchange="this.form.submit()">
                        <option value="">All Puroks</option>
                        <?php foreach ($puroks as $purok): ?>
                            <option value="<?php echo $purok['purok_id']; ?>"
                                <?php echo $selected_purok == $purok['purok_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($purok['purok_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>" />
                    <?php if ($user_role === 'super_admin'): ?>
                        <input type="hidden" name="barangay" value="<?php echo $selected_barangay; ?>" />
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($is_logged_in): ?>
                <button class="add-resident-btn" onclick="window.location.href='census.php'">
                    <i class="fas fa-user-plus"></i> Fill out Census
                </button>
            <?php endif; ?>
        </div>

        <!-- Residents Table -->
        <div class="resident-table-container">
            <table class="resident-table">
                <thead>
                    <tr>
                        <th>Resident ID</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Purok</th>
                        <?php if ($user_role === 'super_admin'): ?><th>Barangay</th><?php endif; ?>
                        <?php if ($is_logged_in): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($residents)): ?>
                        <?php foreach ($residents as $resident): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($resident['id']); ?></td>
                                <td><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></td>
                                <td><?php echo calculateAge($resident['birthdate']); ?></td>
                                <td><?php echo htmlspecialchars($resident['gender']); ?></td>
                                <td>
                                    <span class="purok-badge"><?php echo htmlspecialchars($resident['purok_name'] ?? 'N/A'); ?></span>
                                </td>
                                <?php if ($user_role === 'super_admin'): ?>
                                    <td>
                                        <span class="barangay-badge"><?php echo htmlspecialchars($resident['barangay_name'] ?? 'N/A'); ?></span>
                                    </td>
                                <?php endif; ?>
                                <?php if ($is_logged_in): ?>
                                    <td class="actions">
                                        <a href="edit-resident.php?id=<?php echo $resident['id']; ?>" class="action-btn edit" title="Update"><i class="fas fa-edit"></i></a>
                                        <a href="javascript:void(0);" class="action-btn view" title="View Census Answers"
                                           onclick="showAnswers(<?php echo $resident['id']; ?>, '<?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?>')">
                                           <i class="fas fa-eye"></i></a>
                                        <form method="POST" action="resident.php" onsubmit="return confirm('Are you sure you want to delete this resident?');" style="display:inline;">
                                            <input type="hidden" name="delete_id" value="<?php echo $resident['id']; ?>">
                                            <button type="submit" class="action-btn delete" title="Delete Resident"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo ($user_role === 'super_admin' ? 7 : 6) + ($is_logged_in ? 1 : 0); ?>" class="no-results">
                                <?php echo empty($search_term) && empty($selected_purok) && empty($selected_barangay) ? 'No residents found in database' : 'No matching residents found'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Census Answers Modal -->
<div id="answersModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeModal()">&times;</span>
    <h4 id="residentName"></h4>
    <div id="answersContainer"><!-- Answers will load here --></div>
  </div>
</div>

<script>
function showAnswers(id, name) {
    document.getElementById("residentName").innerText = name;
    document.getElementById("answersContainer").innerHTML = "<p>Loading answers...</p>";
    fetch("get_answers.php?id=" + id)
        .then(response => response.text())
        .then(data => { document.getElementById("answersContainer").innerHTML = data; })
        .catch(() => { document.getElementById("answersContainer").innerHTML = "<p>Failed to load answers.</p>"; });
    document.getElementById("answersModal").style.display = "block";
}
function closeModal() {
    document.getElementById("answersModal").style.display = "none";
}
</script>
</body>
</html>