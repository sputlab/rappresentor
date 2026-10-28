<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;

Auth::requireAuth('login.php');

$currentUser = Auth::user();

// Gestione Azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Helper::verifyCsrf()) {
        Helper::setFlash('danger', 'Richiesta non valida.');
    } else {
        if ($_POST['action'] === 'add_class') {
            if (!Auth::canManageUsers()) {
                Helper::setFlash('danger', 'Solo amministratori e rappresentanti possono creare nuove classi.');
                header('Location: classi.php');
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            $type = $_POST['school_type'] ?? 'primaria';
            $year = trim($_POST['school_year'] ?? '2026/2027');
            $school = trim($_POST['school_name'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if (empty($name)) {
                Helper::setFlash('danger', 'Il nome della classe è obbligatorio.');
            } else {
                $stmt = $db->prepare("INSERT INTO rappresentor_classes (name, school_type, school_year, school_name, notes) VALUES (:nm, :ty, :yr, :sc, :nt)");
                $stmt->execute([
                    'nm' => $name,
                    'ty' => $type,
                    'yr' => $year,
                    'sc' => $school,
                    'nt' => $notes
                ]);
                $newId = (int)$db->lastInsertId();
                $_SESSION['active_class_id'] = $newId;

                // Se chi crea la classe è un rappresentante, assegna automaticamente la classe a lui
                if (Auth::isRepresentative()) {
                    Helper::assignUserClasses($db, (int)Auth::id(), array_merge(Helper::getUserClassIds($db, (int)Auth::id()), [$newId]), (int)Auth::id());
                }

                Helper::setFlash('success', "Classe {$name} creata con successo e impostata come attiva!");
                header('Location: classi.php');
                exit;
            }
        }

        if ($_POST['action'] === 'toggle_archive') {
            $classId = (int)($_POST['class_id'] ?? 0);
            $archive = (int)($_POST['archive'] ?? 0);

            // Verifica autorizzazione sulla classe
            if (!Helper::canManageClass($db, $classId)) {
                Helper::setFlash('danger', 'Non sei autorizzato a modificare questa classe.');
                header('Location: classi.php');
                exit;
            }

            $stmt = $db->prepare("UPDATE rappresentor_classes SET is_archived = :ar WHERE id = :id");
            $stmt->execute(['ar' => $archive, 'id' => $classId]);

            Helper::setFlash('info', 'Stato classe aggiornato.');
            header('Location: classi.php');
            exit;
        }
    }
}

// Carica le classi: gli admin vedono tutto il sistema, i rappresentanti/collaboratori solo le classi assegnate
if (Auth::isAdmin()) {
    $stmt = $db->query("SELECT c.*, 
        (SELECT COUNT(*) FROM rappresentor_students s WHERE s.class_id = c.id AND s.is_active = 1) as total_students,
        (SELECT COALESCE(SUM(CASE WHEN type = 'entrata' THEN amount ELSE -amount END), 0) FROM rappresentor_cashbox_transactions ct WHERE ct.class_id = c.id) as saldo
        FROM rappresentor_classes c ORDER BY c.is_archived ASC, c.school_year DESC, c.name ASC");
    $classesList = $stmt->fetchAll();
} else {
    $stmt = $db->prepare("SELECT c.*, 
        (SELECT COUNT(*) FROM rappresentor_students s WHERE s.class_id = c.id AND s.is_active = 1) as total_students,
        (SELECT COALESCE(SUM(CASE WHEN type = 'entrata' THEN amount ELSE -amount END), 0) FROM rappresentor_cashbox_transactions ct WHERE ct.class_id = c.id) as saldo
        FROM rappresentor_classes c 
        JOIN rappresentor_user_classes uc ON c.id = uc.class_id
        WHERE uc.user_id = :uid
        ORDER BY c.is_archived ASC, c.school_year DESC, c.name ASC");
    $stmt->execute(['uid' => Auth::id()]);
    $classesList = $stmt->fetchAll();
}

$pageTitle = 'Gestione Classi';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <span>🏫</span>
      <span>Gestione Classi & Sezioni</span>
    </h1>
    <p class="page-subtitle">Configura e gestisci le classi dall'asilo nido alle medie.</p>
  </div>
  <?php if (Auth::canManageUsers()): ?>
  <div>
    <button class="btn btn-primary" data-modal-target="modalAddClass">+ Aggiungi Nuova Classe</button>
  </div>
  <?php endif; ?>
</div>

<div class="grid-2">
  <?php foreach ($classesList as $c): 
    $tInfo = Helper::schoolTypeDetails($c['school_type']);
    $isActive = (int)$c['id'] === (int)$activeClassId;
  ?>
    <div class="card" style="border-left: 4px solid <?= $isActive ? 'var(--primary)' : 'var(--border-color)' ?>;">
      <div class="card-header">
        <div style="display: flex; align-items: center; gap: 8px;">
          <span style="font-size: 1.3rem;"><?= $tInfo['icon'] ?></span>
          <div>
            <h3 class="card-title" style="margin: 0;">
              <?= Helper::e($c['name']) ?>
              <?php if ($isActive): ?>
                <span class="badge badge-primary">Attiva</span>
              <?php endif; ?>
              <?php if ($c['is_archived']): ?>
                <span class="badge badge-secondary">Archiviata</span>
              <?php endif; ?>
            </h3>
            <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px;">
              <?= Helper::e($c['school_name'] ?? 'Plesso') ?> • A.S. <?= Helper::e($c['school_year']) ?>
            </div>
          </div>
        </div>

        <span class="badge <?= $tInfo['badge'] ?>"><?= $tInfo['label'] ?></span>
      </div>

      <div class="card-body">
        <div style="display: flex; justify-content: space-between; margin-bottom: 16px;">
          <div>
            <span style="font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase;">Alunni:</span>
            <div style="font-size: 1.25rem; font-weight: 700;"><?= (int)$c['total_students'] ?></div>
          </div>
          <div style="text-align: right;">
            <span style="font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase;">Saldo Cassa:</span>
            <div style="font-size: 1.25rem; font-weight: 700; color: <?= (float)$c['saldo'] >= 0 ? 'var(--success-text)' : 'var(--danger-text)' ?>;">
              <?= Helper::formatMoney($c['saldo']) ?>
            </div>
          </div>
        </div>

        <?php 
          $assignedUsers = Helper::getClassAssignedUsers($db, (int)$c['id']); 
          if (!empty($assignedUsers)):
        ?>
          <div style="margin-bottom: 14px; font-size: 0.82rem; border-top: 1px dashed var(--border-color); padding-top: 10px;">
            <span style="color: var(--text-secondary); font-size: 0.72rem; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 6px;">Team Assegnato:</span>
            <div style="display: flex; flex-wrap: wrap; gap: 4px;">
              <?php foreach ($assignedUsers as $au): ?>
                <span class="badge <?= $au['role'] === 'rappresentante' ? 'badge-primary' : ($au['role'] === 'admin' ? 'badge-warning' : 'badge-secondary') ?>" style="font-size: 0.72rem; font-weight: 500;" title="<?= Helper::e($au['email']) ?>">
                  <?= $au['role'] === 'rappresentante' ? '⭐ ' : ($au['role'] === 'admin' ? '🛡️ ' : '👤 ') ?>
                  <?= Helper::e($au['name']) ?>
                </span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 14px;">
          <?php if (!$isActive): ?>
            <a href="?switch_class=<?= (int)$c['id'] ?>" class="btn btn-sm btn-primary">
              Seleziona come Attiva &rarr;
            </a>
          <?php else: ?>
            <span style="font-size: 0.85rem; color: var(--primary); font-weight: 600;">✓ In uso</span>
          <?php endif; ?>

          <?php if (Auth::canManageUsers()): ?>
          <form method="POST" action="classi.php" style="display: inline;">
            <?= Helper::csrfField() ?>
            <input type="hidden" name="action" value="toggle_archive">
            <input type="hidden" name="class_id" value="<?= (int)$c['id'] ?>">
            <input type="hidden" name="archive" value="<?= $c['is_archived'] ? '0' : '1' ?>">
            <button type="submit" class="btn btn-sm btn-secondary" style="font-size: 0.78rem;">
              <?= $c['is_archived'] ? 'Ripristina' : 'Archivia' ?>
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Modal Aggiungi Classe -->
<div class="modal-backdrop" id="modalAddClass">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Aggiungi Nuova Classe / Sezione</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="classi.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="add_class">

      <div class="modal-body">
        <div class="form-group">
          <label for="c_school_name">Nome Istituto Scolastico / Plesso</label>
          <input type="text" id="c_school_name" name="school_name" class="form-control" placeholder="es. I.C. Alessandro Manzoni" value="<?= Helper::e($activeClass['school_name'] ?? '') ?>">
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="c_name">Nome Sezione / Classe *</label>
            <input type="text" id="c_name" name="name" class="form-control" placeholder="es. 2^ B oppure Sezione Farfalle" required>
          </div>

          <div class="form-group">
            <label for="c_type">Grado Scolastico *</label>
            <select id="c_type" name="school_type" class="form-control" required>
              <option value="nido">👶 Asilo Nido (0-3 anni)</option>
              <option value="infanzia">🎨 Scuola dell'Infanzia / Materna (3-6 anni)</option>
              <option value="primaria" selected>🎒 Scuola Primaria / Elementari (6-11 anni)</option>
              <option value="secondaria_1">📐 Secondaria 1° Grado / Medie (11-14 anni)</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="c_year">Anno Scolastico *</label>
          <input type="text" id="c_year" name="school_year" class="form-control" value="2026/2027" required>
        </div>

        <div class="form-group">
          <label for="c_notes">Note</label>
          <textarea id="c_notes" name="notes" class="form-control" rows="2" placeholder="Note per coordinatori, orari..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Crea Classe</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
