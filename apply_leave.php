<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
  header("Location: ../index.php");
  exit;
}
include 'includes/db.php';
include 'includes/header.php';

$userId = $_SESSION['user_id'];
$statusMessage = '';
$redirectTo = '';

// Fetch only valid leave types
$types = $conn->query("SELECT * FROM Leave_Types WHERE leave_type_id IN (1,2,3)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $leave_type_id = (int)$_POST['leave_type'];
  $start_date    = $_POST['start_date'];
  $end_date      = $_POST['end_date'];
  $reason        = $_POST['reason'];
  // Read action rather than separate button names
  $status = in_array($_POST['action'], ['draft', 'pending']) ? $_POST['action'] : 'draft';

  $stmt = $conn->prepare("
      INSERT INTO Leave_Requests
        (employee_id, leave_type_id, start_date, end_date, reason, status, requested_at)
      VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
  $stmt->bind_param("iissss", $userId, $leave_type_id, $start_date, $end_date, $reason, $status);

  if ($stmt->execute()) {
    if ($status === 'pending') {
      $statusMessage = 'Leave submitted successfully.';
      $redirectTo = 'user_dashboard.php';
    } else {
      $statusMessage = 'Leave saved as draft successfully.';
      $redirectTo = 'drafts.php';
    }
  } else {
    $statusMessage = 'Error: ' . $stmt->error;
    $redirectTo = 'user_dashboard.php'; // In case of error, we can redirect to the dashboard or error page.
  }
}
?>

<main class="flex-grow-1 container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-md-10 col-lg-8">
      <div class="card shadow-sm border-1 p-4">
        <div class="card-body">
          <h2 class="card-title mb-4">Apply for Leave</h2>

          <?php if (!empty($statusMessage)): ?>
            <div class="position-fixed top-0 end-0 p-3 m-3" style="z-index: 1100;">
              <div class="toast align-items-center text-white bg-success border-0 show" role="alert">
                <div class="d-flex">
                  <div class="toast-body"><?= htmlspecialchars($statusMessage) ?></div>
                </div>
              </div>
            </div>

            <script>
              setTimeout(function() {
                window.location.href = '<?= $redirectTo ?>';
              }, 2000);
            </script>
          <?php endif; ?>

          <form method="post">
            <div class="mb-3">
              <label class="form-label">Leave Type</label>
              <select name="leave_type" class="form-select" required>
                <option value="">-- Select --</option>
                <?php while ($type = $types->fetch_assoc()): ?>
                  <option value="<?= $type['leave_type_id'] ?>">
                    <?= htmlspecialchars($type['type_name']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Start Date</label>
              <input type="date" name="start_date" class="form-control" required>
            </div>

            <div class="mb-3">
              <label class="form-label">End Date</label>
              <input type="date" name="end_date" class="form-control" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Reason</label>
              <textarea name="reason" class="form-control" rows="3" required></textarea>
            </div>

            <div class="d-flex justify-content-between">
              <button type="submit" name="action" value="draft" class="btn btn-secondary">Save as Draft</button>
              <button type="submit" name="action" value="pending" class="btn btn-primary">Submit for Approval</button>
            </div>
          </form>
        </div>
      </div>

      <footer class="text-center mt-4 text-muted small">
        &copy; <?= date("Y") ?> Employee Leave Portal
      </footer>
    </div>
  </div>
</main>