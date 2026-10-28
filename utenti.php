<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;
use Rappresentor\Core\Mailer;

// Riservato all'amministratore
Auth::requireAdmin();

$currentUser = Auth::user();
$auth = new Auth($db);
$canAdd = $auth->canAddUser();
$activeCount = $auth->getActiveUsersCount();

// Carica impostazione limite massimo utenti (default 5)
$stmt = $db->query("SELECT key_value FROM rappresentor_settings WHERE key_name = 'max_users'");
$maxUsers = (int)($stmt->fetchColumn() ?: 5);

// Gestione Azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Helper::verifyCsrf()) {
        Helper::setFlash('danger', 'Richiesta non valida.');
    } else {
        // 1. Aggiungi nuovo collaboratore
        if ($_POST['action'] === 'add_user') {
            if (!$canAdd) {
                Helper::setFlash('danger', "Limite massimo di {$maxUsers} utenti attivi raggiunto. Disattiva un account esistente prima di aggiungerne un altro.");
                header('Location: utenti.php');
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            $email = trim(strtolower($_POST['email'] ?? ''));
            $role = $_POST['role'] ?? 'rappresentante';
            $sendInvite = isset($_POST['send_invite']);

            if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Helper::setFlash('danger', 'Nome ed email valida sono obbligatori.');
            } else {
                // Verifica email duplicata
                $check = $db->prepare("SELECT id FROM rappresentor_users WHERE email = :email");
                $check->execute(['email' => $email]);
                if ($check->fetch()) {
                    Helper::setFlash('danger', 'Un utente con questo indirizzo email è già registrato.');
                } else {
                    // Genera password temporanea
                    $tempPassword = bin2hex(random_bytes(4)) . '!' . rand(10, 99);
                    $passHash = password_hash($tempPassword, PASSWORD_DEFAULT);

                    $stmt = $db->prepare("INSERT INTO rappresentor_users (name, email, password_hash, role, is_active) VALUES (:nm, :em, :ph, :ro, 1)");
                    $stmt->execute([
                        'nm' => $name,
                        'em' => $email,
                        'ph' => $passHash,
                        'ro' => $role
                    ]);

                    // Invia email di invito con credenziali se richiesto
                    if ($sendInvite) {
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $baseDir = dirname($_SERVER['PHP_SELF']);
                        if ($baseDir === '/' || $baseDir === '\\') {
                            $baseDir = '';
                        }
                        $loginUrl = "{$protocol}://{$host}{$baseDir}/login.php";

                        $mailer = new Mailer($db);
                        $mailer->sendInvitationEmail($email, $name, $tempPassword, $loginUrl);
                    }

                    Helper::setFlash('success', "Collaboratore {$name} aggiunto! Password temporanea: {$tempPassword}");
                    header('Location: utenti.php');
                    exit;
                }
            }
        }

        // 2. Attiva / Disattiva utente
        if ($_POST['action'] === 'toggle_active') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 0);

            if ($userId === Auth::id()) {
                Helper::setFlash('danger', 'Non puoi disattivare il tuo stesso account amministratore.');
            } else {
                $stmt = $db->prepare("UPDATE rappresentor_users SET is_active = :ia WHERE id = :id");
                $stmt->execute(['ia' => $isActive, 'id' => $userId]);
                Helper::setFlash('info', 'Stato utente aggiornato.');
                header('Location: utenti.php');
                exit;
            }
        }

        // 3. Elimina utente
        if ($_POST['action'] === 'delete_user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId === Auth::id()) {
                Helper::setFlash('danger', 'Non puoi eliminare il tuo stesso account.');
            } else {
                $stmt = $db->prepare("DELETE FROM rappresentor_users WHERE id = :id");
                $stmt->execute(['id' => $userId]);
                Helper::setFlash('info', 'Collaboratore eliminato.');
                header('Location: utenti.php');
                exit;
            }
        }
    }
}

// Carica tutti gli utenti
$users = $db->query("SELECT * FROM rappresentor_users ORDER BY is_active DESC, role ASC, name ASC")->fetchAll();

$pageTitle = 'Gestione Collaboratori';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <span>👥</span>
      <span>Collaboratori & Accessi</span>
      <span class="badge <?= $canAdd ? 'badge-info' : 'badge-danger' ?>">
        <?= $activeCount ?> / <?= $maxUsers ?> Attivi
      </span>
    </h1>
    <p class="page-subtitle">Gestisci fino a <?= $maxUsers ?> persone autorizzate a lavorare sulle attività della scuola.</p>
  </div>
  <div>
    <button class="btn btn-primary" data-modal-target="modalAddUser" <?= !$canAdd ? 'disabled' : '' ?>>
      + Nuovo Collaboratore
    </button>
  </div>
</div>

<?php if (!$canAdd): ?>
  <div class="alert alert-warning">
    ⚠️ <strong>Limite raggiunto:</strong> Hai raggiunto il numero massimo di <?= $maxUsers ?> collaboratori attivi consentiti. Per aggiungere una nuova persona, disattiva un account non più utilizzato.
  </div>
<?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Utente</th>
          <th>Email</th>
          <th>Ruolo</th>
          <th>Stato</th>
          <th>Data Aggiunta</th>
          <th style="text-align: right;">Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): 
          $isMe = (int)$u['id'] === Auth::id();
        ?>
          <tr>
            <td>
              <div style="display: flex; align-items: center; gap: 10px;">
                <div class="user-avatar" style="width: 28px; height: 28px; font-size: 0.75rem;">
                  <?= strtoupper(substr($u['name'], 0, 1)) ?>
                </div>
                <strong><?= Helper::e($u['name']) ?></strong>
                <?php if ($isMe): ?>
                  <span class="badge badge-primary">Tu</span>
                <?php endif; ?>
              </div>
            </td>
            <td><?= Helper::e($u['email']) ?></td>
            <td>
              <span class="badge <?= $u['role'] === 'admin' ? 'badge-primary' : 'badge-secondary' ?>">
                <?= Helper::e(ucfirst($u['role'])) ?>
              </span>
            </td>
            <td>
              <span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                <?= $u['is_active'] ? 'Attivo' : 'Disattivato' ?>
              </span>
            </td>
            <td><?= Helper::formatDate($u['created_at']) ?></td>
            <td style="text-align: right;">
              <?php if (!$isMe): ?>
                <div style="display: inline-flex; gap: 6px;">
                  <form method="POST" action="utenti.php" style="display: inline;">
                    <?= Helper::csrfField() ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <input type="hidden" name="is_active" value="<?= $u['is_active'] ? '0' : '1' ?>">
                    <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-secondary' : 'btn-success' ?>">
                      <?= $u['is_active'] ? 'Disattiva' : 'Attiva' ?>
                    </button>
                  </form>

                  <form method="POST" action="utenti.php" onsubmit="return confirm('Confermi l\'eliminazione definitiva di questo collaboratore?');" style="display: inline;">
                    <?= Helper::csrfField() ?>
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger" title="Elimina">🗑️</button>
                  </form>
                </div>
              <?php else: ?>
                <span style="color: var(--text-muted); font-size: 0.8rem;">Sessione corrente</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Aggiungi Utente -->
<div class="modal-backdrop" id="modalAddUser">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Invita Nuovo Collaboratore (max <?= $maxUsers ?>)</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="utenti.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="add_user">

      <div class="modal-body">
        <p style="font-size: 0.88rem; color: var(--text-secondary); margin-top: 0;">
          Aggiungi un co-rappresentante di classe, un tesoriere o un genitore collaboratore.
        </p>

        <div class="form-group">
          <label for="u_name">Nome e Cognome *</label>
          <input type="text" id="u_name" name="name" class="form-control" placeholder="es. Marco Verdi" required>
        </div>

        <div class="form-group">
          <label for="u_email">Indirizzo Email *</label>
          <input type="email" id="u_email" name="email" class="form-control" placeholder="marco.verdi@email.it" required>
          <small class="form-hint">Verrà usata per l'accesso e per il reset password via mail.</small>
        </div>

        <div class="form-group">
          <label for="u_role">Ruolo Permessi</label>
          <select id="u_role" name="role" class="form-control">
            <option value="rappresentante">Rappresentante / Tesoriere (Gestione cassa, alunni, eventi)</option>
            <option value="collaboratore">Collaboratore di Classe (Inserimento e visualizzazione)</option>
            <option value="admin">Amministratore (Pieno controllo e gestione utenti)</option>
          </select>
        </div>

        <div class="form-group">
          <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
            <input type="checkbox" name="send_invite" value="1" checked>
            <span>Invia email automatica di notifica con le credenziali di accesso</span>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Crea e Invita</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
