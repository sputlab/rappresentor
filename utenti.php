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

        // 4. Modifica dati collaboratore
        if ($_POST['action'] === 'edit_user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim(strtolower($_POST['email'] ?? ''));
            $role = $_POST['role'] ?? 'rappresentante';
            $password = $_POST['password'] ?? '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $isMe = ($userId === Auth::id());

            // Protezione account amministratore della sessione attiva
            if ($isMe) {
                $role = 'admin';
                $isActive = 1;
            }

            // Verifica esistenza utente
            $stmtUser = $db->prepare("SELECT * FROM rappresentor_users WHERE id = :id");
            $stmtUser->execute(['id' => $userId]);
            $existingUser = $stmtUser->fetch();

            if (!$existingUser) {
                Helper::setFlash('danger', 'Collaboratore non trovato.');
                header('Location: utenti.php');
                exit;
            }

            if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Helper::setFlash('danger', 'Nome ed email valida sono obbligatori.');
            } else {
                // Verifica email duplicata
                $check = $db->prepare("SELECT id FROM rappresentor_users WHERE email = :email AND id != :id");
                $check->execute(['email' => $email, 'id' => $userId]);
                if ($check->fetch()) {
                    Helper::setFlash('danger', 'Un altro utente è già registrato con questo indirizzo email.');
                } else {
                    // Se l'utente era disattivo e viene attivato, verifica limite max_users
                    if ((int)$existingUser['is_active'] === 0 && $isActive === 1 && !$canAdd) {
                        Helper::setFlash('danger', "Impossibile attivare l'utente: raggiunto il limite massimo di {$maxUsers} collaboratori attivi.");
                        header('Location: utenti.php');
                        exit;
                    }

                    if (!empty($password)) {
                        if (strlen($password) < 8) {
                            Helper::setFlash('danger', 'La nuova password deve contenere almeno 8 caratteri.');
                            header('Location: utenti.php');
                            exit;
                        }
                        $passHash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE rappresentor_users SET name = :nm, email = :em, role = :ro, is_active = :ia, password_hash = :ph WHERE id = :id");
                        $stmt->execute([
                            'nm' => $name,
                            'em' => $email,
                            'ro' => $role,
                            'ia' => $isActive,
                            'ph' => $passHash,
                            'id' => $userId
                        ]);
                    } else {
                        $stmt = $db->prepare("UPDATE rappresentor_users SET name = :nm, email = :em, role = :ro, is_active = :ia WHERE id = :id");
                        $stmt->execute([
                            'nm' => $name,
                            'em' => $email,
                            'ro' => $role,
                            'ia' => $isActive,
                            'id' => $userId
                        ]);
                    }

                    // Se l'admin ha modificato il suo stesso account, aggiorna le variabili di sessione
                    if ($isMe) {
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                    }

                    Helper::setFlash('success', "Dati di {$name} aggiornati con successo!");
                    header('Location: utenti.php');
                    exit;
                }
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
              <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: flex-end;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="openEditUserModal(<?= htmlspecialchars(json_encode([
                    'id' => (int)$u['id'],
                    'name' => $u['name'],
                    'email' => $u['email'],
                    'role' => $u['role'],
                    'is_active' => (int)$u['is_active'],
                    'is_me' => $isMe
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>)" title="Modifica dati collaboratore">
                  ✏️ Modifica
                </button>

                <?php if (!$isMe): ?>
                  <form method="POST" action="utenti.php" style="display: inline;">
                    <?= Helper::csrfField() ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <input type="hidden" name="is_active" value="<?= $u['is_active'] ? '0' : '1' ?>">
                    <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-secondary' : 'btn-success' ?>" title="<?= $u['is_active'] ? 'Disattiva account' : 'Attiva account' ?>">
                      <?= $u['is_active'] ? 'Disattiva' : 'Attiva' ?>
                    </button>
                  </form>

                  <form method="POST" action="utenti.php" onsubmit="return confirm('Confermi l\'eliminazione definitiva di questo collaboratore?');" style="display: inline;">
                    <?= Helper::csrfField() ?>
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger" title="Elimina">🗑️</button>
                  </form>
                <?php endif; ?>
              </div>
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

<!-- Modal Modifica Collaboratore -->
<div class="modal-backdrop" id="modalEditUser">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Modifica Collaboratore</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="utenti.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="edit_user_id" value="">

      <div class="modal-body">
        <div class="form-group">
          <label for="edit_name">Nome e Cognome *</label>
          <input type="text" id="edit_name" name="name" class="form-control" required placeholder="es. Marco Verdi">
        </div>

        <div class="form-group">
          <label for="edit_email">Indirizzo Email *</label>
          <input type="email" id="edit_email" name="email" class="form-control" required placeholder="marco.verdi@email.it">
          <small class="form-hint">Usata per l'accesso e per il reset password via mail.</small>
        </div>

        <div class="form-group" id="edit_role_container">
          <label for="edit_role">Ruolo Permessi</label>
          <select id="edit_role" name="role" class="form-control">
            <option value="rappresentante">Rappresentante / Tesoriere (Gestione cassa, alunni, eventi)</option>
            <option value="collaboratore">Collaboratore di Classe (Inserimento e visualizzazione)</option>
            <option value="admin">Amministratore (Pieno controllo e gestione utenti)</option>
          </select>
          <small class="form-hint" id="edit_role_hint" style="display: none; color: var(--warning-text);">Non puoi rimuovere il tuo stesso ruolo di amministratore.</small>
        </div>

        <div class="form-group">
          <label for="edit_password">Nuova Password (opzionale)</label>
          <input type="password" id="edit_password" name="password" class="form-control" placeholder="•••••••• (Lascia vuoto per non modificare)" minlength="8">
          <small class="form-hint">Compila questo campo solo se desideri reimpostare la password (minimo 8 caratteri).</small>
        </div>

        <div class="form-group" id="edit_active_container">
          <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
            <input type="checkbox" id="edit_is_active" name="is_active" value="1">
            <span>Account attivo (può accedere all'applicazione)</span>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Salva Modifiche</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditUserModal(userData) {
  document.getElementById('edit_user_id').value = userData.id;
  document.getElementById('edit_name').value = userData.name;
  document.getElementById('edit_email').value = userData.email;
  document.getElementById('edit_role').value = userData.role;
  document.getElementById('edit_password').value = '';
  document.getElementById('edit_is_active').checked = (userData.is_active === 1);

  const roleSelect = document.getElementById('edit_role');
  const roleHint = document.getElementById('edit_role_hint');
  const activeContainer = document.getElementById('edit_active_container');

  if (userData.is_me) {
    roleSelect.disabled = true;
    roleHint.style.display = 'block';
    activeContainer.style.display = 'none';
  } else {
    roleSelect.disabled = false;
    roleHint.style.display = 'none';
    activeContainer.style.display = 'block';
  }

  openModal('modalEditUser');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
