<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
  header("Location: index.php");
  exit;
}
include 'includes/db.php';
include 'includes/header.php';
unset($_SESSION['isOk']);
$userId = $_SESSION['user_id'];
$name = $_SESSION['name'];

// Fetch holidays from the database
$holidays = [];
$holidayQuery = "SELECT holiday_date FROM holidays"; // Assuming the table name is `holidays` and the column is `holiday_date`
$holidayResult = $conn->query($holidayQuery);

if ($holidayResult) {
  while ($row = $holidayResult->fetch_assoc()) {
    $holidays[] = $row['holiday_date']; // Populate the holidays array with holiday dates
  }
}

// Fetch leave balances
$balanceQuery = $conn->prepare("
  SELECT 
    LT.leave_type_id,
    LT.type_name,
    LB.total_allocated,
    LR.start_date,
    LR.end_date
  FROM Leave_Balances LB
  JOIN Leave_Types LT ON LB.leave_type_id = LT.leave_type_id
  LEFT JOIN Leave_Requests LR ON 
    LB.employee_id = LR.employee_id AND 
    LB.leave_type_id = LR.leave_type_id AND 
    LR.status = 'approved'
  WHERE LB.employee_id = ?
  ORDER BY LT.leave_type_id
");
$balanceQuery->bind_param("i", $userId);
$balanceQuery->execute();
$balanceResult = $balanceQuery->get_result();

// Fetch leave history
$historyQuery = $conn->prepare("
    SELECT LR.start_date, LR.end_date, LT.type_name, LR.status, LR.reason
    FROM Leave_Requests LR
    JOIN Leave_Types LT ON LR.leave_type_id = LT.leave_type_id
    WHERE LR.employee_id = ?
    ORDER BY LR.requested_at DESC
");
$historyQuery->bind_param("i", $userId);
$historyQuery->execute();
$history = $historyQuery->get_result();

$leaveData = [];
while ($row = $balanceResult->fetch_assoc()) {
  $typeId = $row['leave_type_id'];
  if (!isset($leaveData[$typeId])) {
    $leaveData[$typeId] = [
      'type_name'       => $row['type_name'],
      'total_allocated' => $row['total_allocated'],
      'used'            => 0
    ];
  }

  // If start_date and end_date exist, count weekdays excluding weekends and holidays
  if ($row['start_date'] && $row['end_date']) {
    $start = new DateTime($row['start_date']);
    $end = new DateTime($row['end_date']);
    while ($start <= $end) {
      $dayOfWeek = $start->format('N'); // 1 = Monday, 7 = Sunday
      $dateStr = $start->format('Y-m-d');

      // Count only weekdays (Mon-Fri) that are not holidays
      if ($dayOfWeek < 6 && !in_array($dateStr, $holidays)) {
        $leaveData[$typeId]['used']++;
      }
      $start->modify('+1 day');
    }
  }
}

// Now the $leaveData array will have the 'used' leave days excluding weekends and holidays.
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>User Dashboard - Leave Portal</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

  <!-- DataTables Core CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">

  <!-- DataTables Buttons Extension CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <!-- Bootstrap 5 JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <!-- DataTables Core JS -->
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

  <!-- DataTables Buttons Extension JS -->
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

  <!-- JSZip (required for Excel export) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <style>
    .dataTables_filter {
      margin-bottom: 1rem !important;
    }


    #holidaysTable thead th {
      text-align: center !important;
    }
  </style>
</head>

<body class="container mt-5">

  <!-- Leave Balances as Cards -->
  <div class="mb-4">
    <h4 class="mb-4">Leave Balances</h4>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
      <?php foreach ($leaveData as $data): ?>
        <!-- Allocated -->
        <div class="col">
          <div class="card text-center border-dark border-3 shadow-sm">
            <div class="card-body py-3">
              <h6 class="card-title mb-2"><?= htmlspecialchars($data['type_name']) ?> - Allocated</h6>
              <p class="display-6 fw-semibold mb-1"><?= $data['total_allocated'] ?></p>
              <p class="fw-bold text-primary mb-0">Allocated</p>
            </div>
          </div>
        </div>

        <!-- Used -->
        <div class="col">
          <div class="card text-center border-dark border-3 shadow-sm">
            <div class="card-body py-3">
              <h6 class="card-title mb-2"><?= htmlspecialchars($data['type_name']) ?> - Used</h6>
              <p class="display-6 fw-semibold mb-1"><?= $data['used'] ?></p>
              <p class="fw-bold text-warning mb-0">Used</p>
            </div>
          </div>
        </div>

        <!-- Remaining -->
        <div class="col">
          <div class="card text-center border-dark border-3 shadow-sm">
            <div class="card-body py-3">
              <h6 class="card-title mb-2"><?= htmlspecialchars($data['type_name']) ?> - Remaining</h6>
              <p class="display-6 fw-semibold mb-1"><?= $data['total_allocated'] - $data['used'] ?></p>
              <p class="fw-bold text-success mb-0">Remaining</p>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

    </div>
  </div>

  <!-- Leave History -->
  <div class="mb-4">
    <h4 class="mb-4">Leave History</h4>
    <table id="theTable" class="table table-striped table-bordered">
      <thead>
        <tr class="table-dark">
          <th>Leave Type</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Status</th>
          <th>Reason</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $history->fetch_assoc()): ?>
          <?php
          // Choose badge color based on status
          switch ($row['status']) {
            case 'draft':
              $badge = 'warning';
              break;
            case 'submitted':
              $badge = 'primary';
              break;
            case 'approved':
              $badge = 'success';
              break;
            case 'rejected':
              $badge = 'danger';
              break;
            default:
              $badge = 'secondary';
          }
          ?>
          <tr>
            <td><?= htmlspecialchars($row['type_name']) ?></td>
            <td><?= $row['start_date'] ?></td>
            <td><?= $row['end_date'] ?></td>
            <td>
              <span class="badge bg-<?= $badge ?>">
                <?= ucfirst($row['status']) ?>
              </span>
            </td>
            <td><?= htmlspecialchars($row['reason']) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <footer class="text-center mt-auto py-3 text-muted small bottom-0">
    &copy; <?= date("Y") ?> Employee Leave Portal
  </footer>
  <script>
    $(document).ready(function() {
      $('#theTable').DataTable({
        lengthChange: false,
        dom: 'Bfrtip',
        buttons: [{
          extend: 'excel',
          text: 'Export to Excel'
        }]
      });
    });
  </script>
</body>

</html>