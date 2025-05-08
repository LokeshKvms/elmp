<?php
session_start();
include 'includes/db.php';
include 'includes/header.php';

// Redirect if not admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Approve employee
if (isset($_GET['approve'])) {
    $emp_id = $_GET['approve'];
    $conn->query("UPDATE Employees SET status = 'active' WHERE employee_id = $emp_id");

    $new_employee_id = $emp_id;

    $year = date("Y");
    $default_used = 0;

    // Fetch leave types with their names
    $leaveTypes = $conn->query("SELECT leave_type_id, type_name FROM leave_types");

    // Prepare the insert for leave balances
    $balanceStmt = $conn->prepare("INSERT INTO leave_balances (employee_id, leave_type_id, year, total_allocated, used) VALUES (?, ?, ?, ?, ?)");

    while ($type = $leaveTypes->fetch_assoc()) {
        $leave_type_id = $type['leave_type_id'];
        $type_name = strtolower($type['type_name']); // Normalize for comparison

        // Assign default based on type
        $default_allocated = ($type_name === 'casual leave') ? 12 : 6;

        $balanceStmt->bind_param("iisii", $new_employee_id, $leave_type_id, $year, $default_allocated, $default_used);
        $balanceStmt->execute();
    }

    header("Location: approve_employee.php");
    exit;
}

// Reject (delete) employee
if (isset($_GET['reject'])) {
    $emp_id = $_GET['reject'];
    $conn->query("DELETE FROM Employees WHERE employee_id = $emp_id");
    header("Location: approve_employee.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Approve Users</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <style>
        .dataTables_filter {
            margin-bottom: 1rem !important;
        }

        #employeeTable thead th {
            text-align: center !important;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>

<body class="d-flex flex-column min-vh-100">
    <main class="flex-grow-1">
        <div class="container mt-1">
            <h3>List of Employees</h3>

            <table id="employeeTable" class="table table-bordered table-striped my-3">
                <thead class="table-dark text-center">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Hire Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT e.*, d.name AS dept_name FROM Employees e 
                                JOIN Departments d ON e.department_id = d.department_id
                                WHERE e.position!='Manager' 
                                ORDER BY e.status DESC");

                    if ($result->num_rows > 0) {
                        while ($emp = $result->fetch_assoc()) {
                            $isActive = ($emp['status'] === 'active');
                            $approveBtn = $isActive
                                ? "<label class='form-label'>Approved</label>"
                                : "<a href='?approve={$emp['employee_id']}' class='btn btn-success btn-sm'>Approve</a>";

                            $rejectBtn = $isActive
                                ? ""
                                : "<a href='?reject={$emp['employee_id']}' class='btn btn-danger btn-sm' onclick=\"return confirm('Reject and delete this employee?');\">Reject</a>";

                            echo "<tr style='text-align:center'>
                <td>{$emp['name']}</td>
                <td>{$emp['email']}</td>
                <td>{$emp['dept_name']}</td>
                <td>{$emp['position']}</td>
                <td>{$emp['hire_date']}</td>
                <td>
                    $approveBtn
                    $rejectBtn
                </td>
            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center'>No pending approvals</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <!-- <a href="admin_dashboard.php" class="btn btn-outline-secondary">← Back to Dashboard</a> -->
        </div>

    </main>
    <footer class="text-center mt-auto py-3 text-muted small bottom-0">
        &copy; <?= date("Y") ?> Employee Leave Portal
    </footer>

    <div class="position-fixed top-0 end-0 p-3" style="z-index: 11">
        <div id="statusToast" class="toast align-items-center text-bg-success border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body" id="toastMsg">Employee approved</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#employeeTable').DataTable({
                lengthChange: false,
                order: [
                    [5, 'asc']
                ],
                pageLength: 5
            });

            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            if (status) {
                const toastMsg = status === 'approved' ? 'Employee approved successfully' : 'Employee rejected successfully';
                $('#toastMsg').text(toastMsg);
                $('#statusToast').removeClass('text-bg-success text-bg-danger')
                    .addClass(status === 'approved' ? 'text-bg-success' : 'text-bg-danger');

                const toast = new bootstrap.Toast(document.getElementById('statusToast'), {
                    delay: 2000
                });
                toast.show();

                // Remove the status param to prevent repeated toast on refresh
                window.history.replaceState(null, null, window.location.pathname);
            }
        });
    </script>



</body>

</html>