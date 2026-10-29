<?php
declare(strict_types=1);

namespace Rappresentor\Core;

use PDO;
use Exception;

/**
 * Gestore invio Email per Rappresentor
 * Supporta SMTP nativo via socket (senza dipendenze esterne) e mail() di PHP.
 * Versione SemVer: 1.2.0
 */
class Mailer
{
    private PDO $db;
    private array $settings = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->loadSettings();
    }

    /**
     * Carica impostazioni mail dal database
     */
    private function loadSettings(): void
    {
        try {
            $stmt = $this->db->query("SELECT key_name, key_value FROM rappresentor_settings WHERE key_name LIKE 'smtp_%' OR key_name = 'app_name'");
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $this->settings[$row['key_name']] = $row['key_value'];
            }
        } catch (Exception $e) {
            // fallback di default
            $this->settings = [
                'app_name' => 'Rappresentor',
                'smtp_enabled' => '0',
                'smtp_from_email' => 'noreply@miodominio.it',
                'smtp_from_name' => 'Rappresentor'
            ];
        }
    }

    /**
     * Invia una email formattata in HTML
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        $fromEmail = !empty($this->settings['smtp_from_email']) ? $this->settings['smtp_from_email'] : 'noreply@miodominio.it';
        $fromName = !empty($this->settings['smtp_from_name']) ? $this->settings['smtp_from_name'] : 'Rappresentor';

        if (empty($textBody)) {
            $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
        }

        // Se SMTP è abilitato nelle impostazioni, usa il socket SMTP
        if (!empty($this->settings['smtp_enabled']) && $this->settings['smtp_enabled'] === '1') {
            return $this->sendViaSmtp($toEmail, $toName, $subject, $htmlBody, $textBody, $fromEmail, $fromName);
        }

        // Altrimenti fallback su mail() nativo di PHP
        return $this->sendViaPhpMail($toEmail, $toName, $subject, $htmlBody, $fromEmail, $fromName);
    }

    /**
     * Invia tramite mail() nativo di PHP
     */
    private function sendViaPhpMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $fromEmail, string $fromName): bool
    {
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            "From: {$encodedFromName} <{$fromEmail}>",
            "Reply-To: {$fromEmail}",
            'X-Mailer: PHP/' . phpversion() . ' (Rappresentor)'
        ];

        return @mail($toEmail, $encodedSubject, $htmlBody, implode("\r\n", $headers));
    }

    /**
     * Invia tramite socket SMTP nativo con supporto TLS/SSL e AUTH LOGIN
     */
    private function sendViaSmtp(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody, string $fromEmail, string $fromName): bool
    {
        $host = $this->settings['smtp_host'] ?? 'localhost';
        $port = (int)($this->settings['smtp_port'] ?? 587);
        $user = $this->settings['smtp_user'] ?? '';
        $pass = $this->settings['smtp_pass'] ?? '';
        $secure = strtolower($this->settings['smtp_secure'] ?? 'tls');

        $boundary = '=_boundary_' . md5((string)time());

        $remoteHost = $host;
        if ($secure === 'ssl') {
            $remoteHost = 'ssl://' . $host;
        }

        $socket = @fsockopen($remoteHost, $port, $errno, $errstr, 15);
        if (!$socket) {
            error_log("Rappresentor SMTP Error: Impossibile connettersi a {$remoteHost}:{$port} ($errno - $errstr)");
            return false;
        }

        $readResponse = function ($sock, $expectedCode) {
            $res = '';
            while ($line = fgets($sock, 515)) {
                $res .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            if (empty($res) || substr($res, 0, 3) !== (string)$expectedCode) {
                return false;
            }
            return true;
        };

        if (!$readResponse($socket, 220)) {
            fclose($socket);
            return false;
        }

        $clientHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
        fputs($socket, "EHLO {$clientHost}\r\n");
        if (!$readResponse($socket, 250)) {
            fputs($socket, "HELO {$clientHost}\r\n");
            if (!$readResponse($socket, 250)) {
                fclose($socket);
                return false;
            }
        }

        // Se TLS e porta non già SSL
        if ($secure === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            if ($readResponse($socket, 220)) {
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                fputs($socket, "EHLO {$clientHost}\r\n");
                $readResponse($socket, 250);
            }
        }

        // Autenticazione se configurata
        if (!empty($user) && !empty($pass)) {
            fputs($socket, "AUTH LOGIN\r\n");
            if (!$readResponse($socket, 334)) {
                fclose($socket);
                return false;
            }

            fputs($socket, base64_encode($user) . "\r\n");
            if (!$readResponse($socket, 334)) {
                fclose($socket);
                return false;
            }

            fputs($socket, base64_encode($pass) . "\r\n");
            if (!$readResponse($socket, 235)) {
                fclose($socket);
                return false;
            }
        }

        // Mittente e destinatario
        fputs($socket, "MAIL FROM: <{$fromEmail}>\r\n");
        if (!$readResponse($socket, 250)) {
            fclose($socket);
            return false;
        }

        fputs($socket, "RCPT TO: <{$toEmail}>\r\n");
        if (!$readResponse($socket, 250)) {
            fclose($socket);
            return false;
        }

        fputs($socket, "DATA\r\n");
        if (!$readResponse($socket, 354)) {
            fclose($socket);
            return false;
        }

        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $encodedToName = '=?UTF-8?B?' . base64_encode($toName) . '?=';
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $headers = [];
        $headers[] = "From: {$encodedFromName} <{$fromEmail}>";
        $headers[] = "To: {$encodedToName} <{$toEmail}>";
        $headers[] = "Subject: {$encodedSubject}";
        $headers[] = "Date: " . date('r');
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        $headers[] = "X-Mailer: Rappresentor/1.2.0";

        $message = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($textBody)) . "\r\n";

        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $message .= "--{$boundary}--\r\n";
        $message .= ".\r\n";

        fputs($socket, $message);
        $result = $readResponse($socket, 250);

        fputs($socket, "QUIT\r\n");
        fclose($socket);

        return $result;
    }

    /**
     * Risolve l'URL pubblico assoluto dell'icona ufficiale dell'applicazione per i template email
     */
    private function getLogoUrl(string $actionLink = ''): string
    {
        if (!empty($actionLink)) {
            $parsed = parse_url($actionLink);
            if (!empty($parsed['scheme']) && !empty($parsed['host'])) {
                $basePath = !empty($parsed['path']) ? dirname($parsed['path']) : '';
                if ($basePath === '/' || $basePath === '\\' || $basePath === '.') {
                    $basePath = '';
                }
                $port = !empty($parsed['port']) ? ':' . $parsed['port'] : '';
                return "{$parsed['scheme']}://{$parsed['host']}{$port}{$basePath}/assets/icons/master-icon.png";
            }
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '';
        if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
            $scriptDir = '';
        }
        return "{$protocol}://{$host}{$scriptDir}/assets/icons/master-icon.png";
    }

    /**
     * Genera template email HTML moderno e responsive per reset password
     */
    public function sendPasswordResetEmail(string $toEmail, string $userName, string $resetLink): bool
    {
        $appName = $this->settings['app_name'] ?? 'Rappresentor';
        $subject = "Recupero password - {$appName}";
        $logoUrl = $this->getLogoUrl($resetLink);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 24px; color: #1e293b; }
    .card { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .header { text-align: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 24px; }
    .header-logo { width: 64px; height: 64px; border-radius: 16px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 12px; display: inline-block; vertical-align: middle; }
    .header h1 { font-size: 22px; color: #1e293b; margin: 0; font-weight: 700; letter-spacing: -0.01em; }
    .content p { line-height: 1.6; font-size: 15px; color: #334155; }
    .btn-container { text-align: center; margin: 30px 0; }
    .btn { background-color: #2563eb; color: #ffffff !important; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; display: inline-block; font-size: 15px; }
    .footer { font-size: 12px; color: #64748b; text-align: center; margin-top: 28px; border-top: 1px solid #e2e8f0; padding-top: 16px; }
    .url-fallback { word-break: break-all; font-size: 13px; color: #64748b; background: #f8fafc; padding: 10px; border-radius: 6px; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <img src="{$logoUrl}" alt="{$appName}" class="header-logo" width="64" height="64">
      <h1>{$appName}</h1>
    </div>
    <div class="content">
      <p>Ciao <strong>{$userName}</strong>,</p>
      <p>È stata richiesta una procedura di reimpostazione della password per il tuo account su <strong>{$appName}</strong>.</p>
      <p>Per scegliere una nuova password, clicca sul pulsante qui sotto:</p>
      <div class="btn-container">
        <a href="{$resetLink}" class="btn" target="_blank">Reimposta Password</a>
      </div>
      <p>Il link rimarrà valido per <strong>60 minuti</strong>. Se non hai richiesto tu il reset, ignora pure questa email: il tuo account rimarrà sicuro.</p>
      <p class="url-fallback">Se il pulsante non funziona, copia e incolla questo indirizzo nel tuo browser:<br><a href="{$resetLink}">{$resetLink}</a></p>
    </div>
    <div class="footer">
      <p>Questa è una notifica automatica inviata da {$appName}.</p>
    </div>
  </div>
</body>
</html>
HTML;

        return $this->send($toEmail, $userName, $subject, $html);
    }

    /**
     * Invia notifica invito nuovo collaboratore
     */
    public function sendInvitationEmail(string $toEmail, string $userName, string $tempPassword, string $loginLink): bool
    {
        $appName = $this->settings['app_name'] ?? 'Rappresentor';
        $subject = "Sei stato invitato su {$appName}";
        $logoUrl = $this->getLogoUrl($loginLink);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 24px; color: #1e293b; }
    .card { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .header { text-align: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 24px; }
    .header-logo { width: 64px; height: 64px; border-radius: 16px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 12px; display: inline-block; vertical-align: middle; }
    .header h1 { font-size: 22px; color: #1e293b; margin: 0; font-weight: 700; letter-spacing: -0.01em; }
    .content p { line-height: 1.6; font-size: 15px; color: #334155; }
    .credentials { background: #f1f5f9; border-left: 4px solid #2563eb; padding: 14px 18px; margin: 20px 0; border-radius: 4px; }
    .btn-container { text-align: center; margin: 30px 0; }
    .btn { background-color: #2563eb; color: #ffffff !important; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; display: inline-block; font-size: 15px; }
    .footer { font-size: 12px; color: #64748b; text-align: center; margin-top: 28px; border-top: 1px solid #e2e8f0; padding-top: 16px; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <img src="{$logoUrl}" alt="{$appName}" class="header-logo" width="64" height="64">
      <h1>{$appName}</h1>
    </div>
    <div class="content">
      <p>Ciao <strong>{$userName}</strong>,</p>
      <p>Sei stato aggiunto come collaboratore/rappresentante su <strong>{$appName}</strong> per collaborare alla gestione delle attività di classe.</p>
      <div class="credentials">
        <p style="margin: 4px 0;"><strong>Email:</strong> {$toEmail}</p>
        <p style="margin: 4px 0;"><strong>Password temporanea:</strong> <code>{$tempPassword}</code></p>
      </div>
      <p>Puoi accedere all'applicazione cliccando qui sotto (ti consigliamo di modificare la password al primo accesso):</p>
      <div class="btn-container">
        <a href="{$loginLink}" class="btn" target="_blank">Accedi a Rappresentor</a>
      </div>
    </div>
    <div class="footer">
      <p>Questa è una notifica automatica inviata da {$appName}.</p>
    </div>
  </div>
</body>
</html>
HTML;

        return $this->send($toEmail, $userName, $subject, $html);
    }
}
