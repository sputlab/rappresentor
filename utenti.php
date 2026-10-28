<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;
use Rappresentor\Core\Mailer;

// Accesso consentito ad amministratori e rappresentanti di classe
Auth::requireCanManageUsers();

$currentUser = Auth::user();
$currentUserId = (int)Auth::id();
$isCurrentAdmin = Auth::isAdmin();
$isCurrentRepresentative = Auth::isRepresentative();

$auth = new Auth($db);
$canAdd = $auth->canAddUser();
$activeCount = $auth->getActiveUsersCount();

// Carica impostazione limite massimo utenti (default 5)
$stmt = $db->query("SELECT key_value FROM rappresentor_settings WHERE key_name = 'max_users'");
$maxUsers = (int)($stmt->fetchColumn() ?: 5);

// Classi gestibili dall'utente loggato per le assegnazioni
if ($isCurrentAdmin) {
    $manageableClasses = Helper::getSystemClasses($db);
} else {
    $manageableClasses = Helper::getAllClasses($db);
}
$manageableClassIds = array_map('intval', array_column($manageableClasses, 'id'));

// Gestione Azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Helper::verifyCsrf()) {
        Helper::setFlash('danger', 'Richiesta non valida.');
    } else {
        // 1. Aggiungi nuovo collaboratore o rappresentante
        if ($_POST['action'] === 'add_user') {
            if (!$canAdd) {
                Helper::setFlash('danger', "Limite massimo di {$maxUsers} utenti attivi raggiunto. Disattiva un account esistente prima di aggiungerne un altro.");
                header('Location: utenti.php');
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            $email = trim(strtolower($_POST['email'] ?? ''));
            $role = $_POST['role'] ?? 'collaboratore';
            $sendInvite = isset($_POST['send_invite']);
            $submittedClasses = isset($_POST['class_ids']) && is_array($_POST['class_ids']) ? array_map('intval', $_POST['class_ids']) : [];

            // I rappresentanti possono creare esclusivamente utenti con ruolo collaboratore
            if (!$isCurrentAdmin) {
                $role = 'collaboratore';
            }

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
                    $newUserId = (int)$db->lastInsertId();

                    // Assegna le classi selezionate se non è admin
                    if ($role !== 'admin') {
                        Helper::assignUserClasses($db, $newUserId, $submittedClasses, $currentUserId);
                    }

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

                    Helper::setFlash('success', "Utente {$name} aggiunto! Password temporanea: {$tempPassword}");
                    header('Location: utenti.php');
                    exit;
                }
            }
        }

        // 2. Attiva / Disattiva utente
        if ($_POST['action'] === 'toggle_active') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 0);

            if ($userId === $currentUserId) {
                Helper::setFlash('danger', 'Non puoi disattivare il tuo stesso account.');
            } else {
                // Se chi esegue è rappresentante, verifica che il target sia un collaboratore
                if (!$isCurrentAdmin) {
                    $targetRole = (string)$db->query("SELECT role FROM rappresentor_users WHERE id = {$userId}")->fetchColumn();
                    if ($targetRole !== 'collaboratore') {
                        Helper::setFlash('danger', 'Non sei autorizzato a modificare questo account.');
                        header('Location: utenti.php');
                        exit;
                    }
                }

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
            if ($userId === $currentUserId) {
                Helper::setFlash('danger', 'Non puoi eliminare il tuo stesso account.');
            } else {
                if (!$isCurrentAdmin) {
                    $targetRole = (string)$db->query("SELECT role FROM rappresentor_users WHERE id = {$userId}")->fetchColumn();
                    if ($targetRole !== 'collaboratore') {
                        Helper::setFlash('danger', 'Non sei autorizzato ad eliminare questo utente.');
                        header('Location: utenti.php');
                        exit;
                    }
                }

                $stmtClasses = $db->prepare("DELETE FROM rappresentor_user_classes WHERE user_id = :id");
                $stmtClasses->execute(['id' => $userId]);

                $stmt = $db->prepare("DELETE FROM rappresentor_users WHERE id = :id");
                $stmt->execute(['id' => $userId]);
                Helper::setFlash('info', 'Collaboratore eliminato.');
                header('Location: utenti.php');
                exit;
            }
        }

        // 4. Modifica dati utente e classi assegnate
        if ($_POST['action'] === 'edit_user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim(strtolower($_POST['email'] ?? ''));
            $role = $_POST['role'] ?? 'collaboratore';
            $password = $_POST['password'] ?? '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $submittedClasses = isset($_POST['class_ids']) && is_array($_POST['class_ids']) ? array_map('intval', $_POST['class_ids']) : [];
            $isMe = ($userId === $currentUserId);

            // Verifica esistenza utente target
            $stmtUser = $db->prepare("SELECT * FROM rappresentor_users WHERE id = :id");
            $stmtUser->execute(['id' => $userId]);
            $existingUser = $stmtUser->fetch();

            if (!$existingUser) {
                Helper::setFlash('danger', 'Utente non trovato.');
                header('Location: utenti.php');
                exit;
            }

            // Regole di sicurezza permessi
            if ($isMe) {
                $role = $existingUser['role'];
                $isActive = 1;
            } elseif (!$isCurrentAdmin) {
                // Il rappresentante può modificare solo collaboratori
                if ($existingUser['role'] !== 'collaboratore') {
                    Helper::setFlash('danger', 'Non sei autorizzato a modificare questo account.');
                    header('Location: utenti.php');
                    exit;
                }
                $role = 'collaboratore';
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

                    // Aggiorna le classi assegnate se non è admin
                    if ($role !== 'admin') {
                        Helper::assignUserClasses($db, $userId, $submittedClasses, $currentUserId);
                    }

                    // Se l'utente modifica se stesso, aggiorna la sessione
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

// Carica utenti visibili
if ($isCurrentAdmin) {
    $users = $db->query("SELECT * FROM rappresentor_users ORDER BY is_active DESC, FIELD(role, 'admin', 'rappresentante', 'collaboratore'), name ASC")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT * FROM rappresentor_users WHERE role = 'collaboratore' OR id = :my_id ORDER BY is_active DESC, FIELD(role, 'rappresentante', 'collaboratore'), name ASC");
    $stmt->execute(['my_id' => $currentUserId]);
    $users = $stmt->fetchAll();
}

// Mappa delle classi assegnate per ciascun utente
$userClassesMap = [];
$allAssignments = $db->query("SELECT uc.user_id, uc.class_id, c.name as class_name, c.school_type, c.school_year 
    FROM rappresentor_user_classes uc 
    JOIN rappresentor_classes c ON uc.class_id = c.id
    ORDER BY c.school_year DESC, c.name ASC")->fetchAll();
foreach ($allAssignments as $row) {
    $userClassesMap[$row['user_id']][] = $row;
}

$pageTitle = 'Gestione Collaboratori';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <span>👥</span>
      <span>Collaboratori & Assegnazione Classi</span>
      <span class="badge <?= $canAdd ? 'badge-info' : 'badge-danger' ?>">
        <?= $activeCount ?> / <?= $maxUsers ?> Attivi
      </span>
    </h1>
    <p class="page-subtitle">
      <?php if ($isCurrentAdmin): ?>
        Gestisci gli account di sistema e assegna le classi che rappresentanti e collaboratori potranno gestire.
      <?php else: ?>
        Gestisci i collaboratori e le classi di tua competenza.
      <?php endif; ?>
    </p>
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
          <th>Classi Gestite</th>
          <th>Stato</th>
          <th>Data Aggiunta</th>
          <th style="text-align: right;">Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): 
          $isMe = (int)$u['id'] === $currentUserId;
          $assignedClasses = $userClassesMap[$u['id']] ?? [];
          $assignedIds = array_map(function($c) { return (int)$c['class_id']; }, $assignedClasses);
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
              <span class="badge <?= $u['role'] === 'admin' ? 'badge-primary' : ($u['role'] === 'rappresentante' ? 'badge-info' : 'badge-secondary') ?>">
                <?= Helper::e(ucfirst($u['role'])) ?>
              </span>
            </td>
            <td>
              <?php if ($u['role'] === 'admin'): ?>
                <span class="badge badge-info" title="L'amministratore supervisiona e gestisce tutte le sezioni">🏫 Tutte le Classi</span>
              <?php elseif (!empty($assignedClasses)): ?>
                <div style="display: flex; flex-wrap: wrap; gap: 4px; max-width: 280px;">
                  <?php foreach ($assignedClasses as $ac): 
                    $typeInfo = Helper::schoolTypeDetails($ac['school_type']);
                  ?>
                    <span class="badge badge-secondary" style="font-size: 0.74rem;">
                      <?= $typeInfo['icon'] ?> <?= Helper::e($ac['class_name']) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <span class="badge badge-secondary" style="opacity: 0.7; font-size: 0.75rem;">Nessuna classe assegnata</span>
              <?php endif; ?>
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
                    'is_me' => $isMe,
                    'assigned_class_ids' => $assignedIds
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>)" title="Modifica dati e classi collaboratore">
                  ✏️ Modifica
                </button>

                <?php if (!$isMe && ($isCurrentAdmin || $u['role'] === 'collaboratore')): ?>
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
      <h3 class="modal-title"><?= $isCurrentAdmin ? "Invita Collaboratore / Rappresentante" : "Invita Nuovo Collaboratore" ?></h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="utenti.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="add_user">

      <div class="modal-body">
        <p style="font-size: 0.88rem; color: var(--text-secondary); margin-top: 0;">
          <?php if ($isCurrentAdmin): ?>
            Crea un account per un rappresentante o collaboratore e assegna le classi che potrà gestire.
          <?php else: ?>
            Aggiungi un co-rappresentante o genitore collaboratore per le tue classi.
          <?php endif; ?>
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

        <?php if ($isCurrentAdmin): ?>
          <div class="form-group">
            <label for="u_role">Ruolo Permessi</label>
            <select id="u_role" name="role" class="form-control" onchange="toggleAddClassSelection(this.value)">
              <option value="collaboratore">Collaboratore di Classe (Operativo sulle classi assegnate)</option>
              <option value="rappresentante" selected>Rappresentante di Classe (Gestione classi e collaboratori)</option>
              <option value="admin">Amministratore (Pieno controllo globale su tutte le classi)</option>
            </select>
          </div>
        <?php else: ?>
          <input type="hidden" name="role" value="collaboratore">
        <?php endif; ?>

        <!-- Selezione Classi Assegnate -->
        <div class="form-group" id="addClassContainer">
          <label style="font-weight: 600; display: block; margin-bottom: 4px;">
            Classi Assegnate da Gestire:
          </label>
          <small class="form-hint" style="margin-top: 0; margin-bottom: 6px;">Seleziona le classi in cui l'utente avrà accesso:</small>
          <?php if (empty($manageableClasses)): ?>
            <div style="font-size: 0.85rem; color: var(--warning-text); background: #fef3c7; padding: 10px; border-radius: var(--radius-md);">
              ⚠️ Nessuna classe disponibile da assegnare. Crea prima una classe dalla sezione "Gestione Classi".
            </div>
          <?php else: ?>
            <div class="class-checkbox-grid">
              <?php foreach ($manageableClasses as $mc): 
                $typeInfo = Helper::schoolTypeDetails($mc['school_type']);
              ?>
                <label class="class-checkbox-label">
                  <input type="checkbox" name="class_ids[]" value="<?= (int)$mc['id'] ?>">
                  <span><?= $typeInfo['icon'] ?> <?= Helper::e($mc['name']) ?> (<?= Helper::e($mc['school_year']) ?>)</span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
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
      <h3 class="modal-title">Modifica Dati & Classi Assegnate</h3>
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

        <?php if ($isCurrentAdmin): ?>
          <div class="form-group" id="edit_role_container">
            <label for="edit_role">Ruolo Permessi</label>
            <select id="edit_role" name="role" class="form-control" onchange="toggleEditClassSelection(this.value)">
              <option value="collaboratore">Collaboratore di Classe (Operativo sulle classi assegnate)</option>
              <option value="rappresentante">Rappresentante di Classe (Gestione classi e collaboratori)</option>
              <option value="admin">Amministratore (Pieno controllo globale su tutte le classi)</option>
            </select>
            <small class="form-hint" id="edit_role_hint" style="display: none; color: var(--warning-text);">Non puoi rimuovere il tuo stesso ruolo di amministratore.</small>
          </div>
        <?php else: ?>
          <input type="hidden" id="edit_role" name="role" value="collaboratore">
        <?php endif; ?>

        <!-- Selezione Classi Assegnate in Modifica -->
        <div class="form-group" id="editClassContainer">
          <label style="font-weight: 600; display: block; margin-bottom: 4px;">
            Classi Assegnate da Gestire:
          </label>
          <small class="form-hint" style="margin-top: 0; margin-bottom: 6px;">Seleziona le sezioni a cui l'utente ha accesso:</small>
          <?php if (empty($manageableClasses)): ?>
            <div style="font-size: 0.85rem; color: var(--warning-text); background: #fef3c7; padding: 10px; border-radius: var(--radius-md);">
              ⚠️ Nessuna classe disponibile da assegnare.
            </div>
          <?php else: ?>
            <div class="class-checkbox-grid">
              <?php foreach ($manageableClasses as $mc): 
                $typeInfo = Helper::schoolTypeDetails($mc['school_type']);
              ?>
                <label class="class-checkbox-label">
                  <input type="checkbox" name="class_ids[]" value="<?= (int)$mc['id'] ?>" class="edit-class-checkbox" id="edit_cls_<?= (int)$mc['id'] ?>">
                  <span><?= $typeInfo['icon'] ?> <?= Helper::e($mc['name']) ?> (<?= Helper::e($mc['school_year']) ?>)</span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div id="editAdminClassesNote" style="display: none; font-size: 0.82rem; color: var(--text-muted); background: var(--bg-surface-alt); padding: 8px 12px; border-radius: var(--radius-sm); margin-top: 6px;">
            ℹ️ Gli utenti con ruolo Amministratore hanno accesso globale automatico a tutte le classi della scuola.
          </div>
        </div>

        <div class="form-group">
          <label for="edit_password">Nuova Password (opzionale)</label>
          <input type="password" id="edit_password" name="password" class="form-control" placeholder="•••••••• (Lascia vuoto per non modificare)" minlength="8">
          <small class="form-hint">Compila questo campo solo se desideri reimpostare la password dell'utente (minimo 8 caratteri).</small>
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
function toggleAddClassSelection(role) {
  const container = document.getElementById('addClassContainer');
  if (!container) return;
  if (role === 'admin') {
    container.style.opacity = '0.5';
    container.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.disabled = true);
  } else {
    container.style.opacity = '1';
    container.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.disabled = false);
  }
}

function toggleEditClassSelection(role) {
  const note = document.getElementById('editAdminClassesNote');
  const grid = document.querySelector('#editClassContainer .class-checkbox-grid');
  if (role === 'admin') {
    if (grid) grid.style.display = 'none';
    if (note) note.style.display = 'block';
  } else {
    if (grid) grid.style.display = 'grid';
    if (note) note.style.display = 'none';
  }
}

function openEditUserModal(userData) {
  document.getElementById('edit_user_id').value = userData.id;
  document.getElementById('edit_name').value = userData.name;
  document.getElementById('edit_email').value = userData.email;
  document.getElementById('edit_password').value = '';
  document.getElementById('edit_is_active').checked = (userData.is_active === 1);

  const roleEl = document.getElementById('edit_role');
  if (roleEl) {
    roleEl.value = userData.role;
  }

  // Spunta le checkbox delle classi assegnate
  const assignedIds = Array.isArray(userData.assigned_class_ids) ? userData.assigned_class_ids : [];
  document.querySelectorAll('.edit-class-checkbox').forEach((cb) => {
    cb.checked = assignedIds.includes(parseInt(cb.value, 10));
  });

  toggleEditClassSelection(userData.role);

  const roleHint = document.getElementById('edit_role_hint');
  const activeContainer = document.getElementById('edit_active_container');

  if (userData.is_me) {
    if (roleEl) roleEl.disabled = true;
    if (roleHint) roleHint.style.display = 'block';
    if (activeContainer) activeContainer.style.display = 'none';
  } else {
    if (roleEl) roleEl.disabled = false;
    if (roleHint) roleHint.style.display = 'none';
    if (activeContainer) activeContainer.style.display = 'block';
  }

  openModal('modalEditUser');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
