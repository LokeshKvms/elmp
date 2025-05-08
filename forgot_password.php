<?php
session_start();
include 'includes/db.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$showOtpField = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $otp_input = $_POST['otp'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM Employees WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $_SESSION['reset_email'] = $email;
        $user = $result->fetch_assoc();

        if (empty($otp_input)) {
            $otp = rand(100000, 999999);
            $expires = time() + 300;

            $update = $conn->prepare("UPDATE Employees SET otp = ?, otp_expires = ? WHERE email = ?");
            $update->bind_param("iis", $otp, $expires, $email);
            $update->execute();

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'loki.kvms@gmail.com';
                $mail->Password = '';  
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('loki.kvms@gmail.com', 'Leave Portal');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset OTP';
                $mail->Body = "<h3>Your OTP is: <strong>$otp</strong></h3>";
                $mail->send();

                $showOtpField = true;
                echo "<script>setTimeout(() => showToast('OTP sent to your email.', 'success'), 100);</script>";
            } catch (Exception $e) {
                echo "<script>setTimeout(() => showToast('Failed to send OTP.', 'danger'), 100);</script>";
            }
        } else {
            $storedOtp = $user['otp'];
            $expiresAt = $user['otp_expires'];

            if ($otp_input === $storedOtp && time() < $expiresAt) {
                // Clear OTP after success
                $clear = $conn->prepare("UPDATE Employees SET otp = NULL, otp_expires = NULL WHERE email = ?");
                $clear->bind_param("s", $email);
                $clear->execute();

                header("Location: reset_password.php");
                exit;
            } else {
                $showOtpField = true;
                echo "<script>setTimeout(() => showToast('Invalid or expired OTP.', 'danger'), 100);</script>";
            }
        }
    } else {
        echo "<script>setTimeout(() => showToast('Email not found.', 'danger'), 100);</script>";
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script>
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast position-fixed top-0 end-0 m-3 text-white bg-${type} show`;
            toast.innerHTML = `<div class="toast-body">${message}</div>`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
    </script>
</head>

<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
    <div class="card p-4 shadow" style="width: 100%; max-width: 400px;">
        <h4 class="mb-3 text-center">Forgot Password</h4>
        <form method="POST">
            <div class="mb-3">
                <label>Email:</label>
                <input type="email" name="email" class="form-control" required value="<?= $_POST['email'] ?? '' ?>">
            </div>

            <?php if ($showOtpField): ?>
                <div class="mb-3">
                    <label>Enter OTP:</label>
                    <input type="text" name="otp" class="form-control" required>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary w-100"><?= $showOtpField ? "Verify OTP" : "Send OTP" ?></button>
        </form>
    </div>
</body>

</html>