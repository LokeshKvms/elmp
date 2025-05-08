<?php
session_start();
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Redirect if OTP or email are not set
if (!isset($_SESSION['otp']) || !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify'])) {
        // Verify OTP entered by the user
        $enteredOtp = $_POST['otp'];
        if (time() > $_SESSION['otp_expires']) {
            $error = "OTP expired.";
        } elseif ($enteredOtp == $_SESSION['otp']) {
            // OTP is correct, proceed to the dashboard
            unset($_SESSION['otp'], $_SESSION['otp_expires']);
            if ($_SESSION['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: user_dashboard.php");
            }
            exit;
        } else {
            // Invalid OTP entered
            $error = "Invalid OTP.";
        }
    } elseif (isset($_POST['resend'])) {
        // Only resend OTP when the "Resend OTP" button is clicked
        $otp = rand(100000, 999999);
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_expires'] = time() + 300;  // 5 minutes expiry

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'loki.kvms@gmail.com';
            $mail->Password = 'password';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('loki.kvms@gmail.com', 'Leave Portal');
            $mail->addAddress($_SESSION['email']);

            $mail->isHTML(true);
            $mail->Subject = 'Your OTP for Login';
            $mail->Body = "<h3>Your new OTP is: <strong>$otp</strong></h3>";

            $mail->send();
            $success = "OTP resent successfully.";
        } catch (Exception $e) {
            $error = "OTP resend failed: " . $mail->ErrorInfo;
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

<body class="bg-light d-flex justify-content-center align-items-center vh-100">
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
            <button type="submit" name="verify" class="btn btn-primary w-100">Verify OTP</button>
        </form>

        <form method="post" class="mt-3 text-center">
            <button type="submit" id="resendBtn" name="resend" class="btn btn-link" disabled>Resend OTP</button>
        </form>
    </div>
</body>

</html>