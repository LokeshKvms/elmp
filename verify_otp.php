<?php
session_start();
require 'includes/db.php';
require 'includes/mail.php';

if (isset($_SESSION['role']) || isset($_SESSION['log'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
        exit;
    } else if ($_SESSION['role'] === 'employee') {
        header("Location: user_dashboard.php");
        exit;
    }
}

if (!isset($_SESSION['email']) || !isset($_SESSION['isOk'])) {
    header("Location: index.php");
    exit;
}

$email = $_SESSION['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify'])) {
        $enteredOtp = $_POST['otp'];

        // Fetch stored OTP and expiry from database
        $stmt = $conn->prepare("SELECT otp, otp_expires, employee_id FROM Employees WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $storedOtp = $row['otp'];
            $otpExpires = $row['otp_expires'];
            $role = $row['employee_id'];

            if (time() > $otpExpires) {
                $error = "OTP expired.";
            } elseif ($enteredOtp == $storedOtp) {
                // Clear OTP fields
                $stmt = $conn->prepare("UPDATE Employees SET otp = NULL, otp_expires = NULL WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $_SESSION['login_time'] = time();

                // Redirect to dashboard
                if ($role === 1) {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: user_dashboard.php");
                }
                exit;
            } else {
                $error = "Invalid OTP.";
            }
        } else {
            $error = "User not found.";
        }
    } elseif (isset($_POST['resend'])) {
        $otp = rand(100000, 999999);
        $expires = time() + 300;

        $stmt = $conn->prepare("UPDATE Employees SET otp = ?, otp_expires = ? WHERE email = ?");
        $stmt->bind_param("iis", $otp, $expires, $email);
        if ($stmt->execute()) {
            try {
                sendmail($email, 'Your OTP for Login', "<h3>Your new OTP is: <strong>$otp</strong></h3>");
                $success = "OTP resent successfully.";
            } catch (Exception $e) {
                $error = "OTP resend failed: " . $mail->ErrorInfo;
            }
        } else {
            $error = "Failed to update OTP.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Verify OTP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script>
        let resendBtn;
        let timer;

        function startTimer() {
            resendBtn = document.getElementById("resendBtn");
            resendBtn.disabled = true;
            let timeLeft = 60;
            timer = setInterval(() => {
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    resendBtn.disabled = false;
                    resendBtn.innerText = "Resend OTP";
                } else {
                    resendBtn.innerText = "Resend in " + timeLeft + "s";
                    timeLeft--;
                }
            }, 1000);
        }

        window.onload = startTimer;
    </script>
</head>

<body class="d-flex justify-content-center align-items-center vh-100" style="background-color:#F5F7FA;">
    <div class="card p-4 shadow-lg rounded-3" style="max-width: 400px; width: 100%;">
        <h4 class="text-center mb-3">Verify OTP</h4>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label for="otp" class="form-label">Enter OTP</label>
                <input type="text" name="otp" id="otp" class="form-control" required>
            </div>
            <button type="submit" name="verify" class="btn btn-dark w-100">Verify OTP</button>
        </form>

        <form method="post" class="mt-3 text-center">
            <button type="submit" id="resendBtn" name="resend" class="btn btn-link" disabled>Resend OTP</button>
        </form>
    </div>
</body>

</html>