<?php
// ParlReg — Mailer (PHPMailer wrapper)
// Requires: composer require phpmailer/phpmailer
// OR manual include from vendor/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

class Mailer {
    /**
     * Send an email using the given SMTP profile (or system default).
     *
     * @param array $to          ['email' => '...', 'name' => '...']
     * @param string $subject
     * @param string $body       HTML body
     * @param int|null $smtpProfileId
     */
    public static function send(array $to, string $subject, string $body, ?int $smtpProfileId = null): bool {
        // Load SMTP profile
        $profile = $smtpProfileId
            ? DB::row('SELECT * FROM smtp_profiles WHERE id = ?', [$smtpProfileId])
            : null;

        if (!$profile) {
            Logger::email("No SMTP profile configured. Cannot send to {$to['email']}.");
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $profile['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $profile['username'];
            $mail->Password   = self::decryptPassword($profile['password_encrypted']);
            $mail->SMTPSecure = $profile['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) $profile['port'];
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($profile['username'], 'Parliamentary Services');
            $mail->addAddress($to['email'], $to['name'] ?? '');
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            return true;
        } catch (MailException $e) {
            Logger::email("Mail failed to {$to['email']}: " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Resolve and send a template-based email.
     */
    public static function sendTemplate(string $type, array $to, array $vars, int $eventId, ?int $smtpProfileId = null, string $lang = 'en'): bool {
        // Try event-specific template first, then global
        $template = DB::row("SELECT * FROM email_templates WHERE type = ? AND event_id = ?", [$type, $eventId])
                 ?? DB::row("SELECT * FROM email_templates WHERE type = ? AND event_id IS NULL", [$type]);

        if (!$template) {
            Logger::email("No template found for type=$type event=$eventId");
            return false;
        }

        $subjectKey = "subject_$lang";
        $bodyKey    = "body_$lang";
        $subject    = self::merge($template[$subjectKey] ?? $template['subject_en'], $vars);
        $body       = nl2br(self::merge($template[$bodyKey] ?? $template['body_en'], $vars));

        return self::send($to, $subject, $body, $smtpProfileId);
    }

    private static function merge(string $template, array $vars): string {
        foreach ($vars as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        return $template;
    }

    private static function decryptPassword(string $encrypted): string {
        // Simple XOR-based decryption placeholder
        // In production: use OpenSSL AES-256-CBC
        // openssl_decrypt($encrypted, 'AES-256-CBC', ENCRYPT_KEY, 0, substr(ENCRYPT_KEY, 0, 16))
        return $encrypted; // Replace with real decryption
    }
}
