<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

class MailService
{
    private $mailer;

    public function __construct()
    {
        // Load mail configuration settings
        $config = require __DIR__ . '/../connection/mail_config.php';

        // Initialize PHPMailer
        $this->mailer = new PHPMailer(true);
        $this->mailer->SMTPDebug = 0;

        try {
            // Configure SMTP settings
            $this->mailer->isSMTP();
            $this->mailer->Host       = $config['host'];
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = $config['username'];
            $this->mailer->Password   = $config['password'];
            $this->mailer->SMTPSecure = 'tls'; // or 'ssl' if using port 465
            $this->mailer->Port       = $config['port'];

            // Set sender information
            $this->mailer->setFrom($config['from_email'], $config['from_name']);
        } catch (Exception $e) {
            // Handle initialization error if needed
        }
    }

    /**
     * Sends an HTML email to the specified recipient.
     *
     * @param string $to Recipient email address.
     * @param string $subject Email subject.
     * @param string $body HTML email body.
     * @return bool True if the email was sent, false otherwise.
     */
    public function sendMail($to, $subject, $body)
    {
        try {
            // Clear previous recipients and add new one
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);

            // Set email subject and HTML body
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body    = $body;

            // Send the email
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            // Log or handle the error if needed
            return false;
        }
    }
}