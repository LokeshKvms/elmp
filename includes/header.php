<?php
require 'session_check.php';
if (!isset($_SESSION)) session_start();
$name = $_SESSION['name'] ?? 'User';
$role = $_SESSION['role'] ?? '';
$email = $_SESSION['email'] ?? '';
$employee_id = $_SESSION['user_id'];
$password = $department = $position = $hire_date = '';
$stmt = $conn->prepare("SELECT e.position, e.hire_date,e.password, d.name 
                            FROM employees e 
                            JOIN departments d ON e.department_id = d.department_id 
                            WHERE e.employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$stmt->bind_result($position, $hire_date, $password, $department);
$stmt->fetch();
$stmt->close();
$dashboard = $role === 'admin' ? 'Admin Portal' : 'Employee Portal';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_password') {
  require 'db.php'; // make sure $conn is defined

  $newPassword = $_POST['new_password'] ?? '';
  $employee_id = $_SESSION['user_id'] ?? 0;

  if (strlen($newPassword) < 3) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 3 characters.']);
    exit;
  }

  $stmt = $conn->prepare("UPDATE employees SET password = ? WHERE employee_id = ?");
  $stmt->bind_param("si", $newPassword, $employee_id);

  if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
  } else {
    echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
  }
  $stmt->close();
  exit;
}

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
  #holidaysTable tbody tr:nth-child(odd),
  #employeeTable tbody tr:nth-child(odd),
  #leaveTable tbody tr:nth-child(odd) {
    background-color: white !important;
    color: #fff;
  }

  #theTable tbody tr:nth-child(even),
  #holidaysTable tbody tr:nth-child(even),
  #employeeTable tbody tr:nth-child(even) {
    background-color: #F5F7FA !important;
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
  <div class="d-flex align-items-center gap-2">
    <?php if ($role !== 'admin'): ?>
      <div style="cursor:pointer;" data-bs-toggle="modal" data-bs-target="#profileModal">
        <i class="fas fa-user-circle fs-5"></i>
        <span class="fw-medium"><?= htmlspecialchars($name) ?></span>
      </div>
    <?php else: ?>
      <i class="fas fa-user-circle fs-5"></i>
      <span class="fw-medium"><?= htmlspecialchars($name) ?></span>
    <?php endif; ?>
    <a href="logout.php" class="btn btn-sm btn-outline-dark ms-3">Logout</a>
  </div>
</header>

<!-- Sidebar -->
<div id="sidebar" class="shadow active border-end border-dark border-3">
  <div class="text-center">
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
<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true" style="z-index:2001;height:200px;">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-dark">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title">Message</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="feedbackMessage">
      </div>
    </div>
  </div>
</div>

<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-dark">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title" id="profileModalLabel">Employee Profile</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="profileForm" method="POST" action="includes/update_profile.php">
        <div class="modal-body">
          <div class="row">

            <div class="mb-3 col-6">
              <label class="form-label">Name</label>
              <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" class="form-control" readonly>
            </div>
            <div class="mb-3 col-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" class="form-control" readonly>
            </div>
            <div class="mb-3 col-6">
              <label class="form-label">Password</label>
              <input type="text" name="password" value="<?= htmlspecialchars($password) ?>" class="form-control" readonly>
            </div>
            <div class="mb-3 col-6 d-none" id="newPasswordGroup">
              <label class="form-label">New Password</label>
              <input type="password" name="new_password" class="form-control">
            </div>
            <div class="mb-3 col-6 d-none" id="confirmPasswordGroup">
              <label class="form-label">Confirm Password</label>
              <input type="password" name="confirm_password" class="form-control">
            </div>

            <div class="mb-3 col-6">
              <label class="form-label">Department</label>
              <input type="text" name="department" value="<?= htmlspecialchars($department) ?>" class="form-control" readonly>
            </div>
            <div class="mb-3 col-6">
              <label class="form-label">Position</label>
              <input type="text" name="position" value="<?= htmlspecialchars($position) ?>" class="form-control" readonly>
            </div>
            <div class="mb-3 col-6">
              <label class="form-label">Hire Date</label>
              <input type="date" name="hire_date" value="<?= htmlspecialchars($hire_date) ?>" class="form-control" readonly>
            </div>
          </div>
          <div class="modal-footer d-flex justify-content-center">
            <button type="button" class="btn btn-warning fw-semibold" id="editPasswordBtn">Edit Password</button>
            <button type="button" class="btn btn-dark d-none" id="savePasswordBtn">Save</button>
          </div>

        </div>
      </form>
    </div>
  </div>
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

<script>
  $(document).ready(function() {
    $('#editPasswordBtn').on('click', function() {
      // Hide readonly fields
      $('input[name="password"], input[name="name"], input[name="email"], input[name="department"], input[name="position"], input[name="hire_date"]').closest('.mb-3').addClass('d-none');

      // Show new password fields and save button
      $('#newPasswordGroup, #confirmPasswordGroup').removeClass('d-none');
      $('#savePasswordBtn').removeClass('d-none');
      $('#editPasswordBtn').addClass('d-none');
    });

    $('#savePasswordBtn').on('click', function() {
      const newPassword = $('input[name="new_password"]').val().trim();
      const confirmPassword = $('input[name="confirm_password"]').val().trim();

      if (newPassword === '' || confirmPassword === '') {
        showFeedback("Both fields are required.", false);
        return;
      }
      if (newPassword !== confirmPassword) {
        showFeedback("Passwords do not match.", false);
        return;
      }

      $.ajax({
        url: '',
        method: 'POST',
        data: {
          action: 'update_password',
          new_password: newPassword
        },
        success: function(response) {
          let res;
          try {
            res = JSON.parse(response);
          } catch {
            res = {
              success: false,
              message: "Unexpected server error."
            };
          }
          showFeedback(res.message, res.success);
          if (res.success) {
            setTimeout(() => location.reload(), 1500);
          }
        },
        error: function() {
          showFeedback("AJAX request failed.", false);
        }
      });
      $('#profileModal').modal('hide');
    });

    function showFeedback(message, success) {
      $('#feedbackMessage').html(`<div class="alert ${success ? 'alert-success' : 'alert-danger'} mb-0">${message}</div>`);
      $('#feedbackModal').modal('show');
    }
  });
</script>


<!-- Wrapper for Page Content -->
<div id="main-content" class="pt-5 mt-2 px-3 shifted ">