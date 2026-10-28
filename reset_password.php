<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$auth = new Auth($db);
$user = $auth->verifyResetToken($token);

$error = '';
$success = false;

if (!$user) {
    $error = 'Il link di recupero password non è valido oppure è già scaduto (validità 60 minuti). Richiedi un nuovo link.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helper::verifyCsrf()) {
        $error = 'Sessione scaduta. Riprova.';
    } else {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 8) {
            $error = 'La nuova password deve contenere almeno 8 caratteri.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Le due password inserite non coincidono.';
        } else {
            if ($auth->resetPassword($token, $newPassword)) {
                $success = true;
                Helper::setFlash('success', 'La password è stata aggiornata con successo! Ora puoi accedere con le nuove credenziali.');
                header('Location: login.php');
                exit;
            } else {
                $error = 'Si è verificato un errore durante l\'aggiornamento della password. Riprova.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reimposta Password - Rappresentor</title>

  <!-- PWA Manifest & Icons -->
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="icon" type="image/svg+xml" href="assets/icons/favicon.svg">
  <link rel="alternate icon" type="image/png" href="assets/icons/favicon-32x32.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
  <meta name="theme-color" content="#2563eb">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="Rappresentor">

  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">

<div class="auth-card">
  <div class="auth-header">
    <div class="auth-logo">
      <img src="assets/icons/master-icon.svg" alt="Rappresentor Logo" width="64" height="64" style="border-radius: 16px; box-shadow: var(--shadow-md);">
    </div>
    <h1 class="auth-title">Nuova Password</h1>
    <p class="auth-subtitle">Imposta una password sicura per il tuo account</p>
  </div>

  <div class="auth-body-content">
    <?php if ($error): ?>
      <div class="alert alert-danger">
        <?= Helper::e($error) ?>
      </div>
      <?php if (!$user): ?>
        <div style="text-align: center; margin-top: 16px;">
          <a href="password_dimenticata.php" class="btn btn-primary">Richiedi Nuovo Link</a>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($user && !$success): ?>
      <form method="POST" action="reset_password.php">
        <?= Helper::csrfField() ?>
        <input type="hidden" name="token" value="<?= Helper::e($token) ?>">

        <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 16px;">
          Stai reimpostando la password per l'account: <strong><?= Helper::e($user['email']) ?></strong>
        </p>

        <div class="form-group">
          <label for="new_password">Nuova Password (min. 8 caratteri)</label>
          <input type="password" id="new_password" name="new_password" class="form-control" minlength="8" required autofocus>
        </div>

        <div class="form-group">
          <label for="confirm_password">Conferma Nuova Password</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="8" required>
        </div>

        <div style="margin-top: 24px;">
          <button type="submit" class="btn btn-primary" style="width: 100%;">
            Salva Nuova Password
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
