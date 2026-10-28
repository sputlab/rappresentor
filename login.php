<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;

// Se già loggato, vai alla dashboard
if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = '';
$flash = Helper::getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helper::verifyCsrf()) {
        $error = 'Sessione scaduta o richiesta non valida. Riprova.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $auth = new Auth($db);
        $result = $auth->login($email, $password);

        if ($result['success']) {
            header('Location: index.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Accedi - Rappresentor</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">

<div class="auth-card">
  <div class="auth-header">
    <div class="auth-logo">🎓</div>
    <h1 class="auth-title">Rappresentor</h1>
    <p class="auth-subtitle">Piattaforma di supporto per Rappresentanti di Classe</p>
  </div>

  <div class="auth-body-content">
    <?php if ($flash): ?>
      <div class="alert alert-<?= Helper::e($flash['type']) ?>">
        <?= Helper::e($flash['message']) ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <?= Helper::e($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <?= Helper::csrfField() ?>

      <div class="form-group">
        <label for="email">Indirizzo Email</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="nome@dominio.it" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </div>

      <div class="form-group">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
          <label for="password" style="margin-bottom: 0;">Password</label>
          <a href="password_dimenticata.php" style="font-size: 0.8rem;">Password dimenticata?</a>
        </div>
        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>

      <div style="margin-top: 24px;">
        <button type="submit" class="btn btn-primary" style="width: 100%;">
          Accedi all'Area Riservata
        </button>
      </div>
    </form>
  </div>
</div>

</body>
</html>
