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

// Fetch valid leave types
$types = $conn->query("SELECT * FROM Leave_Types WHERE leave_type_id IN (1,2,3)");

function countWeekdays($start, $end)
{
  $start = new DateTime($start);
  $end = new DateTime($end);
  $count = 0;
  while ($start <= $end) {
    if (!in_array($start->format('N'), [6, 7])) {
      $count++;
    }
    $start->modify('+1 day');
  }
  return $count;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $leave_type_id = (int)$_POST['leave_type'];
  $range = explode(' to ', $_POST['leave_range']);
  $start_date = trim($range[0] ?? '');
  $end_date   = isset($range[1]) ? trim($range[1]) : $start_date; // fallback to start_date if only one date selected
  $reason        = $_POST['reason'];
  $status        = in_array($_POST['action'], ['draft', 'pending']) ? $_POST['action'] : 'draft';

  if (countWeekdays($start_date, $end_date) > 3) {
    $statusMessage = 'You can only apply for a maximum of 3 working (non-weekend) days.';
    $redirectTo = 'user_dashboard.php';
  } else {
    $stmt = $conn->prepare("
      INSERT INTO Leave_Requests
        (employee_id, leave_type_id, start_date, end_date, reason, status, requested_at)
      VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iissss", $userId, $leave_type_id, $start_date, $end_date, $reason, $status);

    if ($stmt->execute()) {
      $statusMessage = $status === 'pending' ? 'Leave submitted successfully.' : 'Leave saved as draft successfully.';
      $redirectTo = $status === 'pending' ? 'user_dashboard.php' : 'drafts.php';
    } else {
      $statusMessage = 'Error: ' . $stmt->error;
      $redirectTo = 'user_dashboard.php';
    }
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
              <label class="form-label">Leave Date Range</label>
              <input type="text" name="leave_range" id="leave_range" class="form-control" required placeholder="Select date range">
              <small class="text-muted">Note: Max 3 working days (Mon–Fri). Weekends are excluded automatically.</small>
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

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  flatpickr("#leave_range", {
    mode: "range",
    dateFormat: "Y-m-d",
    onClose: function(selectedDates, dateStr, instance) {
      if (selectedDates.length === 1) {
        const onlyDate = selectedDates[0];
        instance.setDate([onlyDate, onlyDate], true);
      }
    },
    onChange: function(selectedDates, dateStr, instance) {
      if (selectedDates.length === 2) {
        const start = selectedDates[0];
        const end = selectedDates[1];

        let count = 0;
        const current = new Date(start);
        while (current <= end) {
          const day = current.getDay();
          if (day !== 0 && day !== 6) {
            count++;
          }
          current.setDate(current.getDate() + 1);
        }

        if (count > 3) {
          alert("You can only apply for a maximum of 3 working (Mon–Fri) days.");
          instance.clear();
        }
      }
    }
  });
</script>