<!DOCTYPE html>
<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
  header("Location: ../index.php");
  exit;
}
include 'includes/db.php';

$userId = $_SESSION['user_id'];
$statusMessage = '';
$redirectTo = '';

// Fetch valid leave types
$types = $conn->query("SELECT * FROM Leave_Types WHERE leave_type_id IN (1,2,3)");

// Fetch holidays from the database
$holidays = [];
$holidayQuery = "SELECT holiday_date FROM holidays"; // Assuming the table name is `holidays` and the column is `holiday_date`
$holidayResult = $conn->query($holidayQuery);

if ($holidayResult) {
  while ($row = $holidayResult->fetch_assoc()) {
    $holidays[] = $row['holiday_date']; // Populate the holidays array with holiday dates
  }
}

// Function to count weekdays excluding weekends
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

  // Count weekdays excluding weekends and holidays
  $workingDays = 0;
  $current = new DateTime($start_date);

  while ($current <= new DateTime($end_date)) {
    $day = $current->format('N'); // 1 = Monday, 7 = Sunday
    $dateStr = $current->format('Y-m-d');
    if ($day < 6 && !in_array($dateStr, $holidays)) {
      $workingDays++;
    }
    $current->modify('+1 day');
  }

  // Check if working days count is 0
  if ($workingDays == 0) {
    $statusMessage = 'You have selected 0 working days.';
    $redirectTo = 'user_dashboard.php'; // Or any other page you want
  } else {
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
}
include 'includes/header.php';
?>
<html>

<head>
  <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
  <script src="https://cdn.tiny.cloud/1/3g4qn6x3hnpmu6lcwk8usodwmm9zjtgi4ppblgvjg2si6egn/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      tinymce.init({
        selector: 'textarea',
        plugins: ['link', 'table', 'emoticons', 'image'],
        toolbar: 'undo redo | bold italic underline | blocks fontfamily fontsize',
        skin: 'oxide-dark', // UI dark skin
        content_css: false, // Disable default styles
        content_style: `
        body {
          background-color: #212529;
          color: #ffffff;
          font-family: Arial, sans-serif;
        }
        a { color: #212529; }
        table, th, td {
          border: 1px solid #444;
        }
      `,
        height: 300,
        menubar: false,
        setup: function(editor) {
          editor.on('change', function() {
            editor.save();
          });
        }
      });
    });

    function syncEditor() {
      tinymce.triggerSave();
      const content = document.getElementById("reason").value.trim();
      if (content === "") {
        alert("Please enter a reason for your leave.");
        return false;
      }
      return true;
    }
  </script>
  <style>
    .holiday {
      background-color: #f8d7da !important;
      /* Light red background */
      color: #721c24 !important;
    }
  </style>

</head>

<body>



  <main class="flex-grow-1 container py-4">
    <div class="row justify-content-center">
      <div class="col-12 col-md-10 col-lg-8 w-100">
        <div class="card shadow-sm border-1 p-4 px-5" style="background-color: #191c24;">
          <div class="card-body text-white">
            <h2 class="card-title text-white mb-4">Apply for Leave</h2>

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

            <form method="post" onsubmit="return syncEditor();">
              <div class="mb-3">
                <label class="form-label">Leave Type</label>
                <select name="leave_type" class="form-select text-white bg-dark" required>
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
                <input type="text" name="leave_range" id="leave_range" class="form-control mb-1 text-white bg-dark" required placeholder="Select date range">
                <small class="text-secondary form-text">Note: Max 3 working days (Mon–Fri). Weekends and holidays are excluded automatically.</small>
              </div>

              <div class="mb-3">
                <label class="form-label">Reason</label>
                <textarea id="reason" name="reason" class="form-control mb-4 text-white bg-dark" rows="3"></textarea>
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
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    // Pass holidays from PHP to JavaScript
    const holidays = <?= json_encode($holidays) ?>;

    flatpickr("#leave_range", {
      mode: "range",
      dateFormat: "Y-m-d",
      minDate: "today", // Prevent selection of past dates
      maxDate: "2025-12-31", // Optional: Set maximum range limit

      // Highlight holidays
      onDayCreate: function(dObj, dStr, fp, dayElem) {
        const date = dayElem.dateObj.toISOString().split('T')[0];
        if (holidays.includes(date)) {
          dayElem.classList.add('holiday');
          dayElem.title = "Holiday";
        }
      },

      // Validate on date range change
      onChange: function(selectedDates, dateStr, instance) {
        if (selectedDates.length === 2) {
          const start = selectedDates[0];
          const end = selectedDates[1];

          let count = 0;
          const current = new Date(start);

          while (current <= end) {
            const day = current.getDay(); // 0 = Sun, 6 = Sat
            const dateStr = current.toISOString().split('T')[0];
            if (day !== 0 && day !== 6 && !holidays.includes(dateStr)) {
              count++;
            }
            current.setDate(current.getDate() + 1);
          }

          if (count == 0) {
            alert("You have selected 0 working days.");
            instance.clear();
          }

          if (count > 3) {
            alert("You can only apply for a maximum of 3 working days excluding weekends and holidays.");
            instance.clear();
          }
        }
      }
    });
  </script>
</body>

</html>