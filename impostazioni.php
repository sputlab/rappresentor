<?php
declare(strict_types=1);

$pageTitle = 'Impostazioni & Notifiche';
require_once __DIR__ . '/includes/header.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;
use Rappresentor\Core\Mailer;

// Riservato all'amministratore
Auth::requireAdmin();

// Carica impostazioni attuali
$settingsRows = $db->query("SELECT key_name, key_value FROM settings")->fetchAll();
$settings = [];
foreach ($settingsRows as $row) {
    $settings[$row['key_name']] = $row['key_value'];
}

// Gestione Salvataggio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Helper::verifyCsrf()) {
        Helper::setFlash('danger', 'Richiesta non valida.');
    } else {
        if ($_POST['action'] === 'save_settings') {
            $keysToUpdate = [
                'school_name'     => trim($_POST['school_name'] ?? ''),
                'school_year'     => trim($_POST['school_year'] ?? '2026/2027'),
                'max_users'       => (string)max(1, min(10, (int)($_POST['max_users'] ?? 5))),
                'smtp_enabled'    => isset($_POST['smtp_enabled']) ? '1' : '0',
                'smtp_host'       => trim($_POST['smtp_host'] ?? 'localhost'),
                'smtp_port'       => trim($_POST['smtp_port'] ?? '587'),
                'smtp_secure'     => $_POST['smtp_secure'] ?? 'tls',
                'smtp_user'       => trim($_POST['smtp_user'] ?? ''),
                'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
                'smtp_from_name'  => trim($_POST['smtp_from_name'] ?? 'Rappresentor')
            ];

            // Se è stata digitata una nuova password SMTP, salvala
            if (!empty($_POST['smtp_pass'])) {
                $keysToUpdate['smtp_pass'] = $_POST['smtp_pass'];
            }

            $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE key_value = :v");
            foreach ($keysToUpdate as $k => $v) {
                $stmt->execute(['k' => $k, 'v' => $v]);
            }

            Helper::setFlash('success', 'Impostazioni salvate con successo!');
            header('Location: impostazioni.php');
            exit;
        }

        if ($_POST['action'] === 'test_email') {
            $testTo = trim($_POST['test_email_recipient'] ?? $currentUser['email']);
            $mailer = new Mailer($db);
            $subject = "Test Notifiche - Rappresentor v" . Helper::getAppVersion();
            $body = "<p>Ciao <strong>" . Helper::e($currentUser['name']) . "</strong>,</p>" .
                    "<p>Questo &egrave; un messaggio di test inviato da <strong>Rappresentor</strong>.</p>" .
                    "<p>Se stai leggendo questa email, il motore di notifica e la configurazione mail sul tuo dominio funzionano correttamente! 🎉</p>" .
                    "<p style='color:#64748b; font-size:12px;'>Data invio: " . date('d/m/Y H:i:s') . "</p>";

            $sent = $mailer->send($testTo, $currentUser['name'], $subject, $body);

            if ($sent) {
                Helper::setFlash('success', "Email di test inviata con successo a {$testTo}!");
            } else {
                Helper::setFlash('danger', "Impossibile inviare l'email di test. Verifica parametri host/porta/credenziali SMTP o i log di sistema del tuo server.");
            }
            header('Location: impostazioni.php');
            exit;
        }
    }
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <span>⚙️</span>
      <span>Impostazioni & Notifiche Mail</span>
    </h1>
    <p class="page-subtitle">Configura i parametri del plesso scolastico, server email e versioning.</p>
  </div>
</div>

<form method="POST" action="impostazioni.php">
  <?= Helper::csrfField() ?>
  <input type="hidden" name="action" value="save_settings">

  <div class="grid-2">
    <!-- Configurazione Scuola e Sistema -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">🏫 Dati Scuola & Sistema</h2>
        <span class="badge badge-primary">SemVer v<?= Helper::e(Helper::getAppVersion()) ?></span>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label for="school_name">Nome Istituto Scolastico / Comprensivo</label>
          <input type="text" id="school_name" name="school_name" class="form-control" value="<?= Helper::e($settings['school_name'] ?? '') ?>" placeholder="es. I.C. Don Lorenzo Milani">
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="school_year">Anno Scolastico di Riferimento</label>
            <input type="text" id="school_year" name="school_year" class="form-control" value="<?= Helper::e($settings['school_year'] ?? '2026/2027') ?>" required>
          </div>

          <div class="form-group">
            <label for="max_users">Limite Max Collaboratori (SaaS)</label>
            <input type="number" id="max_users" name="max_users" min="1" max="10" class="form-control" value="<?= Helper::e($settings['max_users'] ?? '5') ?>" required>
            <small class="form-hint">Impostazione predefinita a 5 persone.</small>
          </div>
        </div>

        <div style="background: var(--bg-surface-alt); padding: 14px; border-radius: var(--radius-md); font-size: 0.85rem; color: var(--text-secondary); margin-top: 14px;">
          <strong>Informazioni Versione SemVer:</strong><br>
          Versione corrente dell'applicazione: <code>v<?= Helper::e(Helper::getAppVersion()) ?></code><br>
          File di tracciamento modifiche: <code>CHANGELOG.md</code>
        </div>
      </div>
    </div>

    <!-- Configurazione Notifiche & SMTP -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">📧 Configurazione Email & Reset Password</h2>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
            <input type="checkbox" name="smtp_enabled" value="1" <?= (!empty($settings['smtp_enabled']) && $settings['smtp_enabled'] === '1') ? 'checked' : '' ?>>
            <span>Abilita invio tramite SMTP autenticato (Consigliato per evitare cartella SPAM)</span>
          </label>
          <small class="form-hint">Se non selezionato, verrà utilizzato il meccanismo mail() nativo di PHP fornito dal server di hosting.</small>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="smtp_host">Host SMTP</label>
            <input type="text" id="smtp_host" name="smtp_host" class="form-control" value="<?= Helper::e($settings['smtp_host'] ?? 'localhost') ?>" placeholder="smtp.tuodominio.it">
          </div>

          <div class="form-group">
            <label for="smtp_port">Porta SMTP</label>
            <input type="number" id="smtp_port" name="smtp_port" class="form-control" value="<?= Helper::e($settings['smtp_port'] ?? '587') ?>" placeholder="587 o 465">
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="smtp_secure">Crittografia</label>
            <select id="smtp_secure" name="smtp_secure" class="form-control">
              <option value="tls" <?= ($settings['smtp_secure'] ?? '') === 'tls' ? 'selected' : '' ?>>STARTTLS (Porta 587)</option>
              <option value="ssl" <?= ($settings['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (Porta 465)</option>
              <option value="none" <?= ($settings['smtp_secure'] ?? '') === 'none' ? 'selected' : '' ?>>Nessuna (Porta 25)</option>
            </select>
          </div>

          <div class="form-group">
            <label for="smtp_user">Username / Email SMTP</label>
            <input type="text" id="smtp_user" name="smtp_user" class="form-control" value="<?= Helper::e($settings['smtp_user'] ?? '') ?>" placeholder="noreply@tuodominio.it">
          </div>
        </div>

        <div class="form-group">
          <label for="smtp_pass">Password SMTP</label>
          <input type="password" id="smtp_pass" name="smtp_pass" class="form-control" placeholder="<?= !empty($settings['smtp_pass']) ? '•••••••• (Non modificata)' : 'Inserisci password SMTP' ?>">
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="smtp_from_email">Email Mittente (From)</label>
            <input type="email" id="smtp_from_email" name="smtp_from_email" class="form-control" value="<?= Helper::e($settings['smtp_from_email'] ?? '') ?>" placeholder="rappresentante@tuodominio.it">
          </div>

          <div class="form-group">
            <label for="smtp_from_name">Nome Mittente</label>
            <input type="text" id="smtp_from_name" name="smtp_from_name" class="form-control" value="<?= Helper::e($settings['smtp_from_name'] ?? 'Rappresentor') ?>" placeholder="Rappresentanti di Classe">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div style="text-align: right; margin-bottom: 24px;">
    <button type="submit" class="btn btn-primary btn-lg">Salva Impostazioni</button>
  </div>
</form>

<!-- Test Invio Email -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">🧪 Verifica Funzionamento Invio Email</h2>
  </div>
  <div class="card-body">
    <p style="font-size: 0.88rem; color: var(--text-secondary); margin-top: 0;">
      Invia un messaggio di prova per verificare che il server o i dati SMTP inseriti siano in grado di recapitare i messaggi di reset password e invito collaboratori.
    </p>

    <form method="POST" action="impostazioni.php" style="display: flex; gap: 12px; max-width: 500px; flex-wrap: wrap;">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="test_email">

      <input type="email" name="test_email_recipient" class="form-control" style="flex: 1; min-width: 240px;" value="<?= Helper::e($currentUser['email']) ?>" required placeholder="La tua email">
      <button type="submit" class="btn btn-secondary">Invia Email di Test</button>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
