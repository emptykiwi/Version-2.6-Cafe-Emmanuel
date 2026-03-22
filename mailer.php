<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// =======================================================================
// 1. CONFIGURATION
// =======================================================================
const MAIL_FROM_NAME = 'Cafe Emmanuel';
const MAIL_SMTP_HOST = 'smtp.gmail.com';
const MAIL_SMTP_PORT = 465; 
const MAIL_SMTP_SECURE = 'ssl';

// CORRECT CREDENTIALS HERE
const MAIL_SMTP_USER = 'isaacjedm@gmail.com';   
const MAIL_SMTP_PASS = 'fyoi gink blvf flaz'; // REPLACE THIS (e.g. xxxx xxxx xxxx xxxx)

function send_email($to, $subject, $body) {
    // 2. LOAD PHPMAILER
    $phpmailerDir = __DIR__ . '/PHPMailer';

    if (file_exists($phpmailerDir . '/PHPMailer.php')) {
        require_once $phpmailerDir . '/Exception.php';
        require_once $phpmailerDir . '/PHPMailer.php';
        require_once $phpmailerDir . '/SMTP.php';
    } else {
        error_log("CRITICAL: PHPMailer files not found in $phpmailerDir");
        return false;
    }

    // 3. SEND EMAIL
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_SMTP_USER;
        $mail->Password   = MAIL_SMTP_PASS;
        $mail->SMTPSecure = MAIL_SMTP_SECURE;
        $mail->Port       = MAIL_SMTP_PORT;

        // Recipients
        $mail->setFrom(MAIL_SMTP_USER, MAIL_FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $body));

        $mail->send();
        return true;

    } catch (Exception $e) {
        // Log error to file (check php_error.log)
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>