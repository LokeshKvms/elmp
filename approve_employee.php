<?php
session_start();
require 'includes/db.php';
require 'includes/header.php';
require 'includes/mail.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['employee_id'] ?? '';
    $name = $_POST['name'];
    $email = $_POST['email'];
    $dept = $_POST['department_id'];
    $pos = $_POST['position'];
    $date = $_POST['hire_date'];
    $password = $_POST['password'];

    if ($id) {
        $stmt = $conn->prepare("UPDATE Employees SET name=?, email=?, department_id=?, position=?, hire_date=? WHERE employee_id=?");
        $stmt->bind_param("ssissi", $name, $email, $dept, $pos, $date, $id);
        $_SESSION['toast'] = ['msg' => 'Employee updated successfully.', 'class' => 'bg-info'];
    } else {
        $stmt = $conn->prepare("INSERT INTO Employees (name, email, department_id, position, hire_date,password, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("ssisss", $name, $email, $dept, $pos, $date,$password);
        $_SESSION['toast'] = ['msg' => 'Employee added successfully.', 'class' => 'bg-success'];
    }

    $stmt->execute();
    $stmt->close();
    header("Location: approve_employee.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM leave_requests WHERE employee_id = $id");
    $conn->query("DELETE FROM leave_balances WHERE employee_id = $id");
    $conn->query("DELETE FROM Employees WHERE employee_id = $id");

    $_SESSION['toast'] = ['msg' => 'Employee deleted.', 'class' => 'bg-danger'];
    header("Location: approve_employee.php");
    exit;
}

// Handle Approve
if (isset($_GET['approve'])) {
    $emp_id = $_GET['approve'];

    $emp = $conn->query("SELECT * FROM Employees WHERE employee_id = $emp_id")->fetch_assoc();
    $conn->query("UPDATE Employees SET status = 'active' WHERE employee_id = $emp_id");

    $subject = "Welcome to the Company!";
    $body = "
        <h4>Hi {$emp['name']},</h4>
        <p>You have been approved as an employee at our company.</p>
        <p><strong>Email:</strong> {$emp['email']}<br>
           <strong>Position:</strong> {$emp['position']}<br>
           <strong>Hire Date:</strong> {$emp['hire_date']}</p>
        <p>Please log in to the portal using your registered email and password. An OTP will be sent to your email for login verification.</p>
        <br><p>Regards,<br>Admin</p>";

    sendMail($emp['email'], $subject, $body);

    $year = date("Y");
    $default_used = 0;
    $leaveTypes = $conn->query("SELECT leave_type_id, type_name FROM leave_types");
    $balanceStmt = $conn->prepare("INSERT INTO leave_balances (employee_id, leave_type_id, year, total_allocated, used) VALUES (?, ?, ?, ?, ?)");

    while ($type = $leaveTypes->fetch_assoc()) {
        $leave_type_id = $type['leave_type_id'];
        $type_name = strtolower($type['type_name']);
        $default_allocated = ($type_name === 'casual leave') ? 12 : 6;
        $balanceStmt->bind_param("iisii", $emp_id, $leave_type_id, $year, $default_allocated, $default_used);
        $balanceStmt->execute();
    }

    $_SESSION['toast'] = ['msg' => 'Employee approved.', 'class' => 'bg-success'];
    header("Location: approve_employee.php");
    exit;
}

// Handle Reject
if (isset($_GET['reject'])) {
    $emp_id = $_GET['reject'];
    $emp = $conn->query("SELECT * FROM Employees WHERE employee_id = $emp_id")->fetch_assoc();
    $conn->query("DELETE FROM Employees WHERE employee_id = $emp_id");

    $subject = "Application Rejected";
    $body = "<h4>Dear {$emp['name']},</h4><p>Your employment application has been rejected. We wish you all the best.</p>";
    sendMail($emp['email'], $subject, $body);

    $_SESSION['toast'] = ['msg' => 'Employee rejected.', 'class' => 'bg-danger'];
    header("Location: approve_employee.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Approve Users</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .dataTables_filter {
            margin-bottom: 1rem !important;
        }

        #employeeTable thead th {
            text-align: center !important;
        }

        .d-flex.justify-content-between {
            align-items: center;
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">

    <!-- Add/Edit Employee Modal -->
    <!-- <div class="modal fade" id="employeeModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" id="employeeForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add / Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employee_id" id="employee_id">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department ID</label>
                        <input type="number" name="department_id" id="department_id" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" id="position" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hire Date</label>
                        <input type="date" name="hire_date" id="hire_date" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div> -->
    <!-- Add/Edit Employee Modal -->
    <div class="modal fade" id="employeeModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" id="employeeForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add / Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employee_id" id="employee_id">

                    <!-- Name Field -->
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>

                    <!-- Email Field -->
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>

                    <!-- Department Field -->
                    <div class="mb-3">
                        <label class="form-label">Department ID</label>
                        <input type="number" name="department_id" id="department_id" class="form-control" required>
                    </div>

                    <!-- Position Field -->
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" id="position" class="form-control" required>
                    </div>

                    <!-- Hire Date Field -->
                    <div class="mb-3">
                        <label class="form-label">Hire Date</label>
                        <input type="date" name="hire_date" id="hire_date" class="form-control" required>
                    </div>

                    <!-- Password Field (only shown when adding a new employee) -->
                    <div class="mb-3" id="passwordField" style="display: none;">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>


    <main class="flex-grow-1">
        <div class="container mt-1">
            <div class="d-flex justify-content-between">
                <h3>List of Employees</h3>
                <button id="addBtn" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#employeeModal">Add Employee</button>
            </div>

            <table id="employeeTable" class="table table-bordered table-striped my-3">
                <thead class="table-dark text-center">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Hire Date</th>
                        <th>Status</th>
                        <th>Actions</th>
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
                <td>
                    <button class='btn btn-primary btn-sm editBtn'
                            data-id='{$emp['employee_id']}'
                            data-name='{$emp['name']}'
                            data-email='{$emp['email']}'
                            data-department='{$emp['department_id']}'
                            data-position='{$emp['position']}'
                            data-date='{$emp['hire_date']}'>
                        Edit
                    </button>
                    <a href='?delete={$emp['employee_id']}' class='btn btn-danger btn-sm' onclick='return confirm('Delete this employee?');'>Delete</a>
                </td>
            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center'>No pending approvals</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Toast Notification -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
        <div id="toastMsg" class="toast align-items-center text-white bg-success border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body" id="toastBody">Action successful.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <footer class="text-center mt-auto py-3 text-muted small bottom-0">
        &copy; <?= date("Y") ?> Employee Leave Portal
    </footer>

    <script>
        $(document).ready(function() {
            $('#employeeTable').DataTable({
                lengthChange: false,
                order: [
                    [5, 'asc']
                ],
                pageLength: 5
            });

            // Show Toast after adding/editing employee
            const toastMessage = '<?= $_SESSION['toast']['msg'] ?? '' ?>';
            const toastClass = '<?= $_SESSION['toast']['class'] ?? '' ?>';

            if (toastMessage) {
                $('#toastBody').text(toastMessage);
                $('#toastMsg').removeClass('bg-success bg-info bg-danger')
                    .addClass(toastClass);

                const toast = new bootstrap.Toast(document.getElementById('toastMsg'), {
                    delay: 3000
                });
                toast.show();

                <?php unset($_SESSION['toast']); ?>
            }

            // When the edit button is clicked
            $('.editBtn').click(function() {
                // Hide password field (since it's not needed for editing)
                $('#passwordField').hide();
                // Set modal fields with the current employee data
                $('#employee_id').val($(this).data('id'));
                $('#name').val($(this).data('name'));
                $('#email').val($(this).data('email'));
                $('#department_id').val($(this).data('department'));
                $('#position').val($(this).data('position'));
                $('#hire_date').val($(this).data('date'));
                // Show the modal
                $('#employeeModal').modal('show');
            });

            // When the "Add Employee" button is clicked
            $('#addBtn').click(function() {
                // Clear the modal fields (if any)
                $('#employeeForm')[0].reset();
                // Show password field for adding a new employee
                $('#passwordField').attr('required');
                $('#passwordField').show();
                // Make sure to hide the password field in the modal (if any)
                $('#employee_id').val('');
                // Show the modal
                $('#employeeModal').modal('show');
            });
        });
    </script>

</body>

</html>