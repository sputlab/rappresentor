<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Mailer;
use Rappresentor\Core\Helper;

$message = '';
$isSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helper::verifyCsrf()) {
        $message = 'Sessione scaduta. Riprova.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Inserisci un indirizzo email valido.';
        } else {
            $auth = new Auth($db);
            $tokenData = $auth->generateResetToken($email);

            if ($tokenData) {
                // Costruisci link di reset
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $baseDir = dirname($_SERVER['PHP_SELF']);
                if ($baseDir === '/' || $baseDir === '\\') {
                    $baseDir = '';
                }
                $resetUrl = "{$protocol}://{$host}{$baseDir}/reset_password.php?token=" . urlencode($tokenData['token']);

                $mailer = new Mailer($db);
                $mailer->sendPasswordResetEmail($tokenData['user']['email'], $tokenData['user']['name'], $resetUrl);
            }

            // Mostra sempre messaggio rassicurante per prevenire user enumeration
            $isSuccess = true;
            $message = 'Se l\'indirizzo email è registrato nel sistema, riceverai a breve un messaggio con le istruzioni e il link per impostare una nuova password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recupero Password - Rappresentor</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">

<div class="auth-card">
  <div class="auth-header">
    <div class="auth-logo">🔐</div>
    <h1 class="auth-title">Recupera Password</h1>
    <p class="auth-subtitle">Riceverai un link sicuro via email</p>
  </div>

  <div class="auth-body-content">
    <?php if ($message): ?>
      <div class="alert alert-<?= $isSuccess ? 'success' : 'danger' ?>">
        <?= Helper::e($message) ?>
      </div>
    <?php endif; ?>

    <?php if (!$isSuccess): ?>
      <form method="POST" action="password_dimenticata.php">
        <?= Helper::csrfField() ?>

        <div class="form-group">
          <label for="email">La tua email registrata</label>
          <input type="email" id="email" name="email" class="form-control" placeholder="nome@dominio.it" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
          <small class="form-hint">Ti invieremo un link valido per 60 minuti.</small>
        </div>

        <div style="margin-top: 24px;">
          <button type="submit" class="btn btn-primary" style="width: 100%;">
            Invia Link di Ripristino
          </button>
        </div>
      </form>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 20px;">
      <a href="login.php" style="font-size: 0.88rem;">&larr; Torna alla schermata di accesso</a>
    </div>
  </div>
</div>

</body>
</html>
