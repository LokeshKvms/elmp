<?php
session_start();
require 'includes/db.php';
require 'includes/mail.php';


if (isset($_SESSION['role'])) {
  if ($_SESSION['role'] === 'admin') {
    header("Location: admin_dashboard.php");
    exit;
  } else if ($_SESSION['role'] === 'employee') {
    header("Location: user_dashboard.php");
    exit;
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Login - Leave Portal</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <script>
    function switchRole(role) {
      document.getElementById('role').value = role;
      document.getElementById('roleLabel').innerText = role.charAt(0).toUpperCase() + role.slice(1) + " Login";
      const emailInput = document.getElementById('email');
      // Toggle active class
      document.getElementById('btn-employee').classList.remove('active');
      document.getElementById('btn-admin').classList.remove('active');

      if (role === 'employee') {
        document.getElementById('btn-employee').classList.add('active');
        emailInput.value = "";
        emailInput.readOnly = false;
        emailInput.style = "background-color:#fff";
      } else {
        document.getElementById('btn-admin').classList.add('active');
        emailInput.value = "admin@gmail.com";
        emailInput.style = "background-color:#cccccc";
        emailInput.readOnly = true;
      }
    }



    function showToast(message, type) {
      const toastElement = document.createElement('div');
      toastElement.classList.add('toast', 'position-fixed', 'top-0', 'end-0', 'm-2', 'fade', 'show');
      if (type === 'success') {
        toastElement.classList.add('bg-success');
      } else if (type === 'warning') {
        toastElement.classList.add('bg-warning');
      } else {
        toastElement.classList.add('bg-danger');
      }
      toastElement.innerHTML = `
                <div class="toast-body fw-semibold border border-1 rounded">${message}</div>
            `;
      document.body.appendChild(toastElement);

      setTimeout(() => {
        toastElement.classList.remove('show');
        setTimeout(() => toastElement.remove(), 300);
      }, 3000);
    }
  </script>
  <style>
    body,
    html {
      height: 100%;
      margin: 0;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .card {
      width: 100%;
      max-width: 400px;
    }

    button.active {
      box-shadow: 0 0 0 0.1rem rgba(255, 255, 255, 0.8);
      font-weight: bold;
    }
  </style>
</head>

<body style="background-image: radial-gradient(circle, white, #707070);">
  <div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow-lg bg-dark text-white rounded-4 p-5" style="max-width: 450px; width: 100%;">
      <h3 class="text-center mb-3" id="roleLabel">Employee Login</h3>

      <div class="d-flex justify-content-around mb-3">
        <button type="button" id="btn-employee" class="btn btn-outline-light me-2 w-100 active" onclick="switchRole('employee')">Employee</button>
        <button type="button" id="btn-admin" class="btn btn-outline-light ms-2 w-100" onclick="switchRole('admin')">Admin</button>
      </div>

      <form method="post">
        <input type="hidden" name="role" id="role" value="employee">
        <div class="row">

          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" class="form-control" placeholder="Enter Email" required>
          </div>

          <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input type="password" name="password" id="password" class="form-control" placeholder="Enter Password" required>
          </div>

          <div class="mb-3 text-start ms-3">
            <a href="forgot_password.php" class="text-info">Forgot Password ?</a>
          </div>
        </div>

        <button name="login" class="btn btn-primary w-100">Login</button>
      </form>

      <p class="text-center mt-3">
        New user? <a href="register.php" class="text-info">Register here</a>
      </p>

      <?php
      if (isset($_POST['login'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];
        $role = $_POST['role'];
        $stmt = $conn->prepare("SELECT * FROM Employees WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
          $user = $result->fetch_assoc();

          if ($role === 'employee' && $email === 'admin@gmail.com') {
            echo "<script>showToast('User not found.', 'danger');</script>";
          } else if ($password === $user['password']) {
            if ($role === 'admin' && $email === 'admin@gmail.com') {
              $_SESSION['user_id'] = $user['employee_id'];
              $_SESSION['name'] = $user['name'];
              $_SESSION['role'] = 'admin';
              header("Location: admin_dashboard.php");
              exit;
            } else if ($user['status'] === 'active') {
              $_SESSION['user_id'] = $user['employee_id'];
              $_SESSION['name'] = $user['name'];
              $_SESSION['role'] = 'employee';
              $_SESSION['email'] = $user['email'];
              $_SESSION['isOk'] = 'yes';
              $start = microtime(true);

              $otp = rand(100000, 999999);
              $otpExpires = time() + 60;

              $updateStmt = $conn->prepare("UPDATE Employees SET otp = ?, otp_expires = ? WHERE email = ?");
              $updateStmt->bind_param("iis", $otp, $otpExpires, $email);
              $updateStmt->execute();
              try {
                sendmail($email, 'Your OTP for Login', "<h3>Your new OTP is: <strong>$otp</strong></h3>");
                header("Location: verify_otp.php");
                exit;
              } catch (Exception $e) {
                echo "<script>showToast('OTP mail failed: " . $mail->ErrorInfo . "', 'danger');</script>";
              }
            } else {
              echo "<script>showToast('Account pending approval by manager.', 'warning');</script>";
            }
          } else {
            echo "<script>showToast('Incorrect password.', 'danger');</script>";
          }
        } else {  
          echo "<script>showToast('User not found.', 'danger');</script>";
        }
      }
      ?>
    </div>
  </div>

  <footer class="text-center mt-4 py-3 text-muted small fixed-bottom">
    &copy; <?= date("Y") ?> Employee Leave Portal
  </footer>
</body>

</html>