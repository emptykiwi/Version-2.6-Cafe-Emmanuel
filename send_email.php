<?php
// 1. Load the installed PHPMailer files
// We use 'require' to include the files you uploaded. 
// Make sure the path matches where you put the files (e.g., inside a 'PHPMailer' folder).
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// 2. Import the PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// 3. Create a new PHPMailer instance
// Passing `true` enables exceptions so we can catch errors
$mail = new PHPMailer(true);

try {
    // ------------------------------------------------
    // SERVER SETTINGS (Configure these for your host)
    // ------------------------------------------------
    
    // Enable verbose debug output (Use SMTP::DEBUG_OFF for production, SMTP::DEBUG_SERVER for testing)
    $mail->SMTPDebug = SMTP::DEBUG_SERVER; 
    
    // Send using SMTP
    $mail->isSMTP();                                            
    
    // Set the SMTP server to send through (e.g., smtp.gmail.com, smtp.office365.com)
    $mail->Host       = 'smtp.gmail.com';                     
    
    // Enable SMTP authentication
    $mail->SMTPAuth   = true;                                   
    
    // SMTP username (usually your email address)
    $mail->Username   = 'isaacjedm@example.com';                     
    
    // SMTP password (WARNING: Use an "App Password" if using Gmail/Outlook, not your main password)
    $mail->Password   = 'Kaicute-1206';                               
    
    // Enable implicit TLS encryption (SMTPS) - typically port 465
    // Or use PHPMailer::ENCRYPTION_STARTTLS for port 587
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            
    
    // TCP port to connect to (465 for SMTPS, 587 for STARTTLS)
    $mail->Port       = 465;                                    

    // ------------------------------------------------
    // RECIPIENTS
    // ------------------------------------------------
    
    // Who is sending the email?
    $mail->setFrom('isaacjedm@gmail.com', 'Mailer System');
    
    // Who is receiving the email?
    $mail->addAddress('isaacjedm@gmail.com', 'Joe User');     // Add a recipient
    // $mail->addReplyTo('info@example.com', 'Information');    // Optional: Reply-to address
    // $mail->addCC('cc@example.com');                          // Optional: CC
    // $mail->addBCC('bcc@example.com');                        // Optional: BCC

    // ------------------------------------------------
    // CONTENT
    // ------------------------------------------------
    
    // Set email format to HTML
    $mail->isHTML(true);                                  
    $mail->Subject = 'Test Email from PHPMailer';
    $mail->Body    = 'This is the HTML message body <b>in bold!</b>';
    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

    // 4. Send the email
    $mail->send();
    echo 'Message has been sent successfully';

} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}