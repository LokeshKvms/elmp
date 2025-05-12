<?php
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
    background-color: #000 !important;
  }

  #sidebar {
    height: 100vh;
    width: 250px;
    position: fixed;
    top: 0;
    left: -250px;
    background-color: #191c24;
    transition: left 0.3s;
    z-index: 999;
    padding-top: 60px;
  }

  #sidebar.active {
    left: 0;
  }

  .sidebar-link {
    padding: 10px 20px;
    display: block;
    color: #333;
    text-decoration: none;
    color: white;
  }

  .sidebar-link:hover {
    background-color: #000;
    font-weight: bold;
  }

  #main-content {
    margin-left: 0;
    transition: margin-left 0.3s;
  }

  #main-content.shifted {
    margin-left: 250px;
  }

  .custom-table tbody tr:nth-child(odd) {
    background-color: #191c24 !important;
    color: #fff;
  }

  .custom-table thead tr th {
    text-align: center;
  }

  .custom-table td,
  .custom-table th {
    vertical-align: middle;
  }

  .custom-table thead {
    background-color: #343a40;
    color: #fff;
  }

  .dt-button.buttons-excel {
    background-color: #191c24 !important;
    /* Bootstrap gray */
    color: white !important;
    border: none !important;
    padding: 6px 12px;
    border-radius: 4px;
  }

  .dt-button.buttons-excel:hover {
    background-color: #191c24 !important;
    /* Darker gray on hover */
    color: white !important;
  }

  /* Pagination buttons */
  .dataTables_wrapper .dataTables_paginate .paginate_button {
    background-color: #191c24 !important;
    color: white !important;
    border: none !important;
    border-radius: 3px;
    margin: 0 2px;
    padding: 5px 10px;
  }

  .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background-color: #191c24 !important;
    color: white !important;
  }

  .dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background-color: #191c24 !important;
    color: white !important;
  }

  .dataTables_filter input {
    color: white !important;
    /* background-color: #343a40 !important; */
    border: 1px solid #6c757d;
  }
</style>


<!-- Header -->
<header class="text-white py-3 px-3 d-flex justify-content-between align-items-center fixed-top border-bottom border-dark border-3" style="z-index:1000;background-color:#191c24">
  <div class="d-flex align-items-center">
    <i class="fas fa-bars me-3 cursor-pointer" style="cursor:pointer;" onclick="toggleSidebar()"></i>
    <strong>ELMS</strong>
  </div>

  <div>
    <a href="<?= $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php' ?>" class="text-white text-decoration-none">
      <h5 class="mb-0"><?= $dashboard ?></h5>
    </a>
  </div>

  <div class="d-flex align-items-center">
    <i class="fas fa-user-circle me-2"></i>
    <?= htmlspecialchars($name) ?>
    <a href="logout.php" class="btn btn-sm btn-light ms-3">Logout</a>
  </div>
</header>


<!-- Sidebar -->
<div id="sidebar" class="shadow active border-end border-dark border-3">
  <div class="text-center text-white">
    <i class="fas fa-user-circle fa-3x mb-2 mt-3"></i>
    <h6><?= htmlspecialchars($name) ?></h6>
    <small class="text-white"><?= ucfirst($role) ?></small><br>
    <small class="text-white"><?= htmlspecialchars($email) ?></small>
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
<div id="main-content" class="pt-5 mt-2 px-3 shifted text-white">