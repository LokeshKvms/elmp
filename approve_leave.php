<?php
session_start();
include 'includes/db.php';
include 'includes/header.php';

// Redirect if not admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$manager_id = $_SESSION['user_id'];

// Handle approve/reject actions
if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $req_id = (int)$_GET['id'];

    // Fetch the specific request
    $reqRes = $conn->query("SELECT * FROM Leave_Requests WHERE request_id = $req_id");
    if ($reqRes && $reqRes->num_rows === 1) {
        $req = $reqRes->fetch_assoc();
        if ($req['status'] === 'pending') {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $reviewed_at = date("Y-m-d H:i:s");

            $upd = $conn->query(
                "UPDATE Leave_Requests
                 SET status='$status', reviewed_at='$reviewed_at', reviewed_by=$manager_id
                 WHERE request_id=$req_id"
            );

            // After the approval/rejection logic:
            if ($upd) {
                if ($status === 'approved') {
                    $days = (strtotime($req['end_date']) - strtotime($req['start_date'])) / (60 * 60 * 24) + 1;
                    $balUpd = $conn->query(
                        "UPDATE Leave_Balances
                         SET used = used + $days
                         WHERE employee_id={$req['employee_id']} 
                           AND leave_type_id={$req['leave_type_id']}"
                    );
                    if (!$balUpd) {
                        $_SESSION['toast_message'] = ['message' => 'Leave approved, but balance update failed.', 'type' => 'danger'];
                    } else {
                        $_SESSION['toast_message'] = ['message' => 'Leave request approved!', 'type' => 'success'];
                    }
                } else {
                    $_SESSION['toast_message'] = ['message' => 'Leave request rejected!', 'type' => 'danger'];
                }
            } else {
                $_SESSION['toast_message'] = ['message' => 'Failed to update leave request.', 'type' => 'danger'];
            }

            // Redirect to avoid resubmission of data on page refresh
            header("Location: approve_leave.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Leave Requests</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css" rel="stylesheet">
    <style>
        .dataTables_filter {
            margin-bottom: 1rem !important;
        }

        thead th {
            text-align: center !important;
        }
    </style>
</head>

<body>

    <!-- Toast Message -->
    <?php if (isset($_SESSION['toast_message'])): ?>
        <div id="toast" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;">
            <div class="toast align-items-center text-white bg-<?= $_SESSION['toast_message']['type']; ?> border-0 show" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <?= $_SESSION['toast_message']['message']; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['toast_message']); ?>
    <?php endif; ?>

    <h5 class="mb-3">Pending Leave Requests</h5>

    <?php
    $sql = "
    SELECT r.*, e.name AS emp_name, l.type_name
    FROM Leave_Requests r
    JOIN Employees e ON r.employee_id = e.employee_id
    JOIN Leave_Types l ON r.leave_type_id = l.leave_type_id
    WHERE r.status = 'pending'
";
    $result = $conn->query($sql);
    if (!$result) {
        die("<div class='alert alert-danger'>Query failed: " . $conn->error . "</div>");
    }
    ?>

    <table id="theTable" class="table table-bordered text-center">
        <thead>
            <tr class="table-dark">
                <th>Employee</th>
                <th>Leave Type</th>
                <th>From</th>
                <th>To</th>
                <th>Reason</th>
                <th>Requested At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['emp_name']; ?></td>
                        <td><?= $row['type_name']; ?></td>
                        <td><?= $row['start_date']; ?></td>
                        <td><?= $row['end_date']; ?></td>
                        <td><?= $row['reason']; ?></td>
                        <td><?= $row['requested_at']; ?></td>
                        <td>
                            <a href="approve_leave.php?action=approve&id=<?= $row['request_id']; ?>" class="btn btn-success btn-sm">Approve</a>
                            <a href="approve_leave.php?action=reject&id=<?= $row['request_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Reject this leave?');">Reject</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php
    $sqlFetch = "
    SELECT r.*, e.name AS emp_name, l.type_name
    FROM Leave_Requests r
    JOIN Employees e ON r.employee_id = e.employee_id
    JOIN Leave_Types l ON r.leave_type_id = l.leave_type_id
    WHERE r.status != 'draft' AND r.status != 'pending' AND e.employee_id != 1
";
    $result = $conn->query($sqlFetch);
    if (!$result) {
        die("<div class='alert alert-danger'>Query failed: " . $conn->error . "</div>");
    }
    ?>

    <h5 class="mb-3 mt-5">Leave Requests</h5>

    <table id="leaveTable" class="table table-bordered text-center">
        <thead>
            <tr class="table-dark">
                <th>Employee</th>
                <th>Leave Type</th>
                <th>From</th>
                <th>To</th>
                <th>Reason</th>
                <th>Requested At</th>
                <th>Reviewed At</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['emp_name']; ?></td>
                        <td><?= $row['type_name']; ?></td>
                        <td><?= $row['start_date']; ?></td>
                        <td><?= $row['end_date']; ?></td>
                        <td><?= $row['reason']; ?></td>
                        <td><?= date("F j, Y", strtotime($row['requested_at'])) . "<br>" . date("g:i A", strtotime($row['requested_at'])); ?></td>
                        <td><?= date("F j, Y", strtotime($row['reviewed_at'])) . "<br>" . date("g:i A", strtotime($row['reviewed_at'])); ?></td>
                        <td>
                            <?php
                            $status = strtolower($row['status']);
                            $badgeClass = $status === 'approved' ? 'success' : 'danger';
                            echo "<span class='badge bg-{$badgeClass} p-2 text-capitalize'>{$status}</span>";
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan='8' class='text-center'>No leave requests found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <footer class="text-center mt-auto py-3 text-muted small bottom-0">
        &copy; <?= date("Y") ?> Employee Leave Portal
    </footer>

    <!-- Bootstrap & DataTables Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script>
        $('#leaveTable').DataTable({
            lengthChange: false
        });
        $('#theTable').DataTable({
            lengthChange: false
        });

        document.addEventListener("DOMContentLoaded", function() {
            const toastEl = document.querySelector('.toast');
            if (toastEl) {
                const toast = new bootstrap.Toast(toastEl, {
                    delay: 2000
                });
                toast.show();
            }
        });
    </script>
</body>

</html>