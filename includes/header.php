<?php
require 'session_check.php';
if (!isset($_SESSION)) session_start();
$name = $_SESSION['name'] ?? 'User';
$role = $_SESSION['role'] ?? '';
$email = $_SESSION['email'] ?? '';
$dashboard = $role === 'admin' ? 'Admin Portal' : 'Employee Portal';
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;700&display=swap" rel="stylesheet">

<style>
  body {
    font-family: 'Rubik', sans-serif !important;
    background-color: #F5F7FA !important;
  }

  #sidebar {
    height: 100vh;
    width: 250px;
    position: fixed;
    top: 0;
    left: -250px;
    background-color: white;
    transition: left 0.3s;
    z-index: 999;
    padding-top: 60px;
  }

  #sidebar.active {
    left: 0;
  }

  .sidebar-link {
    color: black;
    padding: 10px 20px;
    display: block;
    text-decoration: none;
  }

  .sidebar-link:hover {
    background-color: #F5F7FA;
    font-weight: bold;
  }

  #main-content {
    margin-left: 0;
    transition: margin-left 0.3s;
  }

  #main-content.shifted {
    margin-left: 250px;
  }

  #theTable tbody tr:nth-child(odd),
  #holidaysTable tbody tr:nth-child(odd) {
    background-color: white !important;
    color: #fff;
  }
</style>
<script>
  // Set same timeout as PHP (in milliseconds)
  const timeout = 3600 * 1000;

  setTimeout(() => {
    alert("Session expired. You will be logged out.");
    window.location.href = "logout.php";
  }, timeout);
</script>



<!-- Header -->
<header class="py-3 px-4 d-flex justify-content-between align-items-center fixed-top border-bottom border-dark border-3 bg-white" style="z-index:1000;">
  <!-- Left: Sidebar Toggle & Logo -->
  <div class="d-flex align-items-center gap-3">
    <i class="fas fa-bars fs-5 cursor-pointer" onclick="toggleSidebar()" style="cursor:pointer;"></i>
    <strong class="fs-5 ms-2">ELMS</strong>
  </div>

  <!-- Center: Dashboard Link -->
  <div>
    <a href="<?= $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php' ?>" class="text-decoration-none text-dark">
      <h5 class="mb-0 fw-bolder fs-4"><?= $dashboard ?></h5>
    </a>
  </div>

  <!-- Right: User Info -->
  <div class="d-flex align-items-center gap-2">
    <i class="fas fa-user-circle fs-5"></i>
    <span class="fw-medium"><?= htmlspecialchars($name) ?></span>
    <a href="logout.php" class="btn btn-sm btn-outline-dark ms-3">Logout</a>
  </div>
</header>



<!-- Sidebar -->
<div id="sidebar" class="shadow active border-end border-dark border-3">
  <div class="text-center ">
    <i class="fas fa-user-circle fa-3x mb-2 mt-3"></i>
    <h6><?= htmlspecialchars($name) ?></h6>
    <small class=""><?= ucfirst($role) ?></small><br>
    <small class=""><?= htmlspecialchars($email) ?></small>
  </div>
  <hr>

  <?php if ($role === 'admin'): ?>
    <!-- Admin Dashboard -->
    <a href="admin_dashboard.php" class="sidebar-link">
      <i class="fas fa-home me-2"></i>Dashboard
    </a>
    <!-- New: Approve Employee -->
    <a href="approve_employee.php" class="sidebar-link">
      <i class="fas fa-user-check me-2"></i>Employees
    </a>

    <a href="manage_department.php" class="sidebar-link">
      <i class="fas fa-building me-2"></i>Manage Departments
    </a>

    <!-- Admin’s leave‑review link -->
    <a href="approve_leave.php" class="sidebar-link">
      <i class="fas fa-check-circle me-2"></i>Manage Leaves
    </a>

  <?php else: ?>
    <!-- Employee Dashboard -->
    <a href="user_dashboard.php" class="sidebar-link">
      <i class="fas fa-home me-2"></i>Dashboard
    </a>

    <!-- Employee’s apply‑leave link -->
    <a href="apply_leave.php" class="sidebar-link">
      <i class="fas fa-paper-plane me-2"></i>Apply Leave
    </a>
    <!-- New: Drafts -->
    <a href="drafts.php" class="sidebar-link">
      <i class="fas fa-file-alt me-2"></i>Drafts
    </a>

  <?php endif; ?>
  <a href="holidays.php" class="sidebar-link">
    <i class="fas fa-calendar-alt me-2"></i>Manage Holidays
  </a>
</div>


<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
  function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('main-content').classList.toggle('shifted');
  }
</script>

<!-- Wrapper for Page Content -->
<div id="main-content" class="pt-5 mt-2 px-3 shifted ">