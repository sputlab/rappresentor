<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;

Auth::requireAuth('login.php');

$currentUser = Auth::user();
$activeClass = Helper::getActiveClass($db);
$activeClassId = $activeClass['id'] ?? null;

// Gestione Export CSV
if ($activeClassId && isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $db->prepare("SELECT s.last_name, s.first_name, s.birth_date, s.allergies, s.notes,
        g.first_name as g_first_name, g.last_name as g_last_name, g.relation, g.phone, g.email, g.consent_whatsapp
        FROM rappresentor_students s 
        LEFT JOIN rappresentor_guardians g ON s.id = g.student_id 
        WHERE s.class_id = :cid AND s.is_active = 1
        ORDER BY s.last_name ASC, s.first_name ASC");
    $stmt->execute(['cid' => $activeClassId]);
    $exportRows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="alunni_classe_' . $activeClassId . '_' . date('Ymd') . '.csv"');
    
    $out = fopen('php://output', 'w');
    // BOM UTF-8 per Excel
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Cognome Alunno', 'Nome Alunno', 'Data Nascita', 'Allergie/Note Sanitarie', 'Note', 'Genitore Cognome', 'Genitore Nome', 'Relazione', 'Telefono', 'Email', 'Consenso WA'], ';');
    
    foreach ($exportRows as $r) {
        fputcsv($out, [
            $r['last_name'],
            $r['first_name'],
            $r['birth_date'] ? date('d/m/Y', strtotime($r['birth_date'])) : '',
            $r['allergies'],
            $r['notes'],
            $r['g_last_name'],
            $r['g_first_name'],
            $r['relation'],
            $r['phone'],
            $r['email'],
            $r['consent_whatsapp'] ? 'Sì' : 'No'
        ], ';');
    }
    fclose($out);
    exit;
}

// Elaborazione inserimento nuovo alunno
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Helper::verifyCsrf()) {
        Helper::setFlash('danger', 'Richiesta non valida. Riprova.');
        header('Location: alunni.php');
        exit;
    }

    if ($activeClassId && $_POST['action'] === 'add_student') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $birthDate = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        $allergies = trim($_POST['allergies'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // Genitore primario (opzionale contestuale)
        $gFirstName = trim($_POST['guardian_first_name'] ?? '');
        $gLastName = trim($_POST['guardian_last_name'] ?? '');
        $gRelation = $_POST['guardian_relation'] ?? 'madre';
        $gPhone = trim($_POST['guardian_phone'] ?? '');
        $gEmail = trim($_POST['guardian_email'] ?? '');
        $gConsent = isset($_POST['guardian_consent']) ? 1 : 0;

        if (empty($firstName) || empty($lastName)) {
            Helper::setFlash('danger', 'Nome e cognome dell\'alunno sono obbligatori.');
        } else {
            $stmt = $db->prepare("INSERT INTO rappresentor_students (class_id, first_name, last_name, birth_date, allergies, notes) VALUES (:cid, :fn, :ln, :bd, :al, :nt)");
            $stmt->execute([
                'cid' => $activeClassId,
                'fn' => $firstName,
                'ln' => $lastName,
                'bd' => $birthDate,
                'al' => $allergies,
                'nt' => $notes
            ]);
            $studentId = (int)$db->lastInsertId();

            if (!empty($gFirstName) || !empty($gPhone)) {
                $stmt = $db->prepare("INSERT INTO rappresentor_guardians (student_id, first_name, last_name, relation, phone, email, is_primary, consent_whatsapp) 
                    VALUES (:sid, :gfn, :gln, :grel, :gph, :gem, 1, :gcon)");
                $stmt->execute([
                    'sid' => $studentId,
                    'gfn' => !empty($gFirstName) ? $gFirstName : $lastName,
                    'gln' => !empty($gLastName) ? $gLastName : $lastName,
                    'grel' => $gRelation,
                    'gph' => $gPhone,
                    'gem' => $gEmail,
                    'gcon' => $gConsent
                ]);
            }

            // Inizializza le righe di pagamento per le raccolte quote aperte della classe
            $openCamps = $db->prepare("SELECT id FROM rappresentor_campaigns WHERE class_id = :cid AND is_closed = 0");
            $openCamps->execute(['cid' => $activeClassId]);
            $camps = $openCamps->fetchAll();
            if (!empty($camps)) {
                $payIns = $db->prepare("INSERT IGNORE INTO rappresentor_payments (campaign_id, student_id, status, amount_paid) VALUES (:cid, :sid, 'non_pagato', 0.00)");
                foreach ($camps as $cp) {
                    $payIns->execute(['cid' => $cp['id'], 'sid' => $studentId]);
                }
            }

            Helper::setFlash('success', "Alunno {$firstName} {$lastName} aggiunto con successo!");
            header('Location: alunni.php');
            exit;
        }
    }

    if ($_POST['action'] === 'add_guardian') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $relation = $_POST['relation'] ?? 'madre';
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $consent = isset($_POST['consent_whatsapp']) ? 1 : 0;

        if (empty($firstName) || empty($lastName) || $studentId <= 0) {
            Helper::setFlash('danger', 'Dati contatto incompleti.');
        } else {
            // Verifica che l'alunno appartenga alla classe attiva
            $chk = $db->prepare("SELECT id FROM rappresentor_students WHERE id = :sid AND class_id = :cid");
            $chk->execute(['sid' => $studentId, 'cid' => $activeClassId]);
            if (!$chk->fetch()) {
                Helper::setFlash('danger', 'Alunno non trovato nella classe attiva.');
            } else {
                $stmt = $db->prepare("INSERT INTO rappresentor_guardians (student_id, first_name, last_name, relation, phone, email, consent_whatsapp) 
                    VALUES (:sid, :fn, :ln, :rel, :ph, :em, :con)");
                $stmt->execute([
                    'sid' => $studentId,
                    'fn' => $firstName,
                    'ln' => $lastName,
                    'rel' => $relation,
                    'ph' => $phone,
                    'em' => $email,
                    'con' => $consent
                ]);
                Helper::setFlash('success', 'Contatto famiglia aggiunto con successo!');
            }
            header('Location: alunni.php');
            exit;
        }
    }

    if ($activeClassId && $_POST['action'] === 'edit_guardian') {
        $guardianId = (int)($_POST['guardian_id'] ?? 0);
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $relation = trim($_POST['relation'] ?? 'madre');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $consent = isset($_POST['consent_whatsapp']) ? 1 : 0;

        if (empty($firstName) || empty($lastName) || $guardianId <= 0) {
            Helper::setFlash('danger', 'Dati contatto incompleti. Nome e cognome sono obbligatori.');
        } else {
            $stmt = $db->prepare("UPDATE rappresentor_guardians g 
                JOIN rappresentor_students s ON g.student_id = s.id 
                SET g.first_name = :fn, g.last_name = :ln, g.relation = :rel, g.phone = :ph, g.email = :em, g.consent_whatsapp = :con 
                WHERE g.id = :gid AND s.class_id = :cid");
            $stmt->execute([
                'fn' => $firstName,
                'ln' => $lastName,
                'rel' => $relation,
                'ph' => $phone,
                'em' => $email,
                'con' => $consent,
                'gid' => $guardianId,
                'cid' => $activeClassId
            ]);
            Helper::setFlash('success', 'Contatto famiglia modificato con successo!');
            header('Location: alunni.php');
            exit;
        }
    }

    if ($activeClassId && $_POST['action'] === 'delete_guardian') {
        $guardianId = (int)($_POST['guardian_id'] ?? 0);
        if ($guardianId > 0) {
            $stmt = $db->prepare("DELETE g FROM rappresentor_guardians g 
                JOIN rappresentor_students s ON g.student_id = s.id 
                WHERE g.id = :gid AND s.class_id = :cid");
            $stmt->execute(['gid' => $guardianId, 'cid' => $activeClassId]);
            Helper::setFlash('info', 'Contatto famiglia rimosso con successo.');
        }
        header('Location: alunni.php');
        exit;
    }

    if ($activeClassId && $_POST['action'] === 'edit_student') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $birthDate = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        $allergies = trim($_POST['allergies'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($firstName) || empty($lastName) || $studentId <= 0) {
            Helper::setFlash('danger', 'Nome e cognome dell\'alunno sono obbligatori.');
        } else {
            $stmt = $db->prepare("UPDATE rappresentor_students SET first_name = :fn, last_name = :ln, birth_date = :bd, allergies = :al, notes = :nt WHERE id = :id AND class_id = :cid");
            $stmt->execute([
                'fn' => $firstName,
                'ln' => $lastName,
                'bd' => $birthDate,
                'al' => $allergies,
                'nt' => $notes,
                'id' => $studentId,
                'cid' => $activeClassId
            ]);
            Helper::setFlash('success', "Dati dell'alunno {$firstName} {$lastName} aggiornati con successo!");
            header('Location: alunni.php');
            exit;
        }
    }

    if ($activeClassId && $_POST['action'] === 'delete_student') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM rappresentor_students WHERE id = :id AND class_id = :cid");
        $stmt->execute(['id' => $studentId, 'cid' => $activeClassId]);
        Helper::setFlash('info', 'Alunno rimosso dalla classe.');
        header('Location: alunni.php');
        exit;
    }
}

// Carica tutti gli alunni con relativi genitori se la classe è presente
$students = [];
$guardiansByStudent = [];
$allPhoneNumbers = [];
$whatsappGroupString = '';

if ($activeClassId) {
    $stmt = $db->prepare("SELECT s.* FROM rappresentor_students s WHERE s.class_id = :cid AND s.is_active = 1 ORDER BY s.last_name ASC, s.first_name ASC");
    $stmt->execute(['cid' => $activeClassId]);
    $students = $stmt->fetchAll();

    $studentIds = array_column($students, 'id');
    if (!empty($studentIds)) {
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $db->prepare("SELECT * FROM rappresentor_guardians WHERE student_id IN ({$placeholders}) ORDER BY is_primary DESC, id ASC");
        $stmt->execute($studentIds);
        $guardians = $stmt->fetchAll();

        foreach ($guardians as $g) {
            $guardiansByStudent[$g['student_id']][] = $g;
            if (!empty($g['phone']) && (int)$g['consent_whatsapp'] === 1) {
                $allPhoneNumbers[] = Helper::cleanPhone($g['phone']);
            }
        }
    }
    $allPhoneNumbers = array_unique($allPhoneNumbers);
    $whatsappGroupString = implode(', ', $allPhoneNumbers);
}

$pageTitle = 'Alunni & Famiglie';
require_once __DIR__ . '/includes/header.php';

if (!$activeClassId) {
    echo '<div class="alert alert-warning">Seleziona o crea prima una classe.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <span>🎒</span>
      <span>Anagrafica Alunni & Famiglie</span>
      <span class="badge badge-primary"><?= count($students) ?> Alunni</span>
    </h1>
    <p class="page-subtitle">Gestione rubrica contatti genitori, numeri WhatsApp e note sanitarie/allergie.</p>
  </div>
  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <button class="btn btn-primary" data-modal-target="modalAddStudent">+ Nuovo Alunno</button>
    <?php if (!empty($allPhoneNumbers)): ?>
      <button class="btn btn-whatsapp" data-modal-target="modalCopyPhones" title="Copia lista numeri per WhatsApp">
        📱 Rubrica WhatsApp (<?= count($allPhoneNumbers) ?>)
      </button>
    <?php endif; ?>
    <a href="alunni.php?export=csv" class="btn btn-secondary">📥 Esporta CSV</a>
  </div>
</div>

<!-- Filtro di ricerca rapido -->
<div class="card" style="margin-bottom: 20px;">
  <div style="padding: 12px 16px;">
    <input type="text" class="form-control" placeholder="🔍 Cerca alunno per nome o cognome..." data-filter-table="studentsTable">
  </div>
</div>

<!-- Tabella Alunni -->
<div class="card">
  <!-- Vista Tabella Desktop -->
  <div class="desktop-table-view">
    <div class="table-responsive">
      <table class="table" id="studentsTable">
        <thead>
          <tr>
            <th>Alunno</th>
            <th>Età / Data Nascita</th>
            <th>Allergie & Note</th>
            <th>Genitori & Contatti</th>
            <th style="text-align: right;">Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($students)): ?>
            <tr>
              <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                Nessun alunno presente in questa classe. Clicca su "+ Nuovo Alunno" per inserire il primo.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($students as $stu): 
              $stuGuardians = $guardiansByStudent[$stu['id']] ?? [];
              $age = Helper::calculateAge($stu['birth_date']);
            ?>
              <tr>
                <td>
                  <div style="font-weight: 700; font-size: 0.98rem;">
                    <?= Helper::e($stu['last_name']) ?> <?= Helper::e($stu['first_name']) ?>
                  </div>
                </td>
                <td>
                  <?php if ($stu['birth_date']): ?>
                    <div><?= Helper::formatDate($stu['birth_date']) ?></div>
                    <small style="color: var(--text-muted);"><?= $age ?></small>
                  <?php else: ?>
                    <span style="color: var(--text-muted);">-</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($stu['allergies'])): ?>
                    <span class="badge badge-danger" title="Allergie o intolleranze">
                      ⚠️ <?= Helper::e($stu['allergies']) ?>
                    </span>
                  <?php endif; ?>
                  <?php if (!empty($stu['notes'])): ?>
                    <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px;">
                      📝 <?= Helper::e($stu['notes']) ?>
                    </div>
                  <?php endif; ?>
                  <?php if (empty($stu['allergies']) && empty($stu['notes'])): ?>
                    <span style="color: var(--text-muted); font-size: 0.85rem;">Nessuna nota</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (empty($stuGuardians)): ?>
                    <span style="color: var(--text-muted); font-size: 0.82rem;">Nessun contatto registrato</span>
                  <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                      <?php foreach ($stuGuardians as $g): ?>
                        <div style="font-size: 0.85rem; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding-bottom: 4px; border-bottom: 1px dashed var(--border-color);">
                          <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <strong><?= Helper::e(trim($g['first_name'] . ' ' . ($g['last_name'] ?? ''))) ?></strong>
                            <span style="color: var(--text-muted); font-size: 0.78rem;">(<?= Helper::e(ucfirst($g['relation'])) ?>)</span>:
                            <?php if ($g['phone']): ?>
                              <a href="tel:<?= Helper::e($g['phone']) ?>" style="color: var(--text-secondary);" title="Chiama">
                                📞 <?= Helper::e($g['phone']) ?>
                              </a>
                              <?php if ($g['consent_whatsapp']): ?>
                                <a href="<?= Helper::waLink($g['phone']) ?>" target="_blank" class="badge" style="background:#25D366; color:white; font-size: 0.68rem; padding: 2px 5px;" title="Apri WhatsApp">
                                  WA
                                </a>
                              <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($g['email']): ?>
                              <a href="mailto:<?= Helper::e($g['email']) ?>" style="color: var(--primary);" title="Invia Email">
                                ✉️
                              </a>
                            <?php endif; ?>
                          </div>
                          <div style="display: inline-flex; align-items: center; gap: 4px; flex-shrink: 0;">
                            <button type="button" class="btn btn-xs btn-secondary" onclick="openEditGuardianModal(<?= htmlspecialchars(json_encode([
                                'id' => (int)$g['id'],
                                'student_id' => (int)$g['student_id'],
                                'first_name' => $g['first_name'],
                                'last_name' => $g['last_name'],
                                'relation' => $g['relation'],
                                'phone' => $g['phone'] ?? '',
                                'email' => $g['email'] ?? '',
                                'consent_whatsapp' => (int)$g['consent_whatsapp']
                            ]), ENT_QUOTES, 'UTF-8') ?>)" title="Modifica contatto famiglia">
                              ✏️
                            </button>
                            <form method="POST" action="alunni.php" onsubmit="return confirm('Confermi di voler eliminare questo contatto famiglia?');" style="display: inline; margin: 0;">
                              <?= Helper::csrfField() ?>
                              <input type="hidden" name="action" value="delete_guardian">
                              <input type="hidden" name="guardian_id" value="<?= (int)$g['id'] ?>">
                              <button type="submit" class="btn btn-xs btn-danger" title="Elimina contatto famiglia">🗑️</button>
                            </form>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td style="text-align: right;">
                  <div style="display: inline-flex; gap: 6px;">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="openAddGuardianModal(<?= (int)$stu['id'] ?>, '<?= Helper::e(addslashes($stu['first_name'] . ' ' . $stu['last_name'])) ?>')" title="Aggiungi contatto famiglia">
                      + Contatto
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="openEditStudentModal(<?= htmlspecialchars(json_encode([
                        'id' => (int)$stu['id'],
                        'first_name' => $stu['first_name'],
                        'last_name' => $stu['last_name'],
                        'birth_date' => $stu['birth_date'] ?? '',
                        'allergies' => $stu['allergies'] ?? '',
                        'notes' => $stu['notes'] ?? ''
                    ]), ENT_QUOTES, 'UTF-8') ?>)" title="Modifica dati alunno">
                      ✏️ Modifica
                    </button>
                    <form method="POST" action="alunni.php" onsubmit="return confirm('Confermi di voler rimuovere questo alunno dalla classe?');" style="display: inline;">
                      <?= Helper::csrfField() ?>
                      <input type="hidden" name="action" value="delete_student">
                      <input type="hidden" name="student_id" value="<?= (int)$stu['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger" title="Elimina">🗑️</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Vista Schede Alunni Mobile (Zero Scorrimento Orizzontale) -->
  <div class="mobile-cards-view" data-filter-target="studentsTable">
    <?php if (empty($students)): ?>
      <div style="text-align: center; padding: 32px 16px; color: var(--text-muted); font-size: 0.9rem;">
        Nessun alunno presente in questa classe. Clicca su "+ Nuovo Alunno" per inserire il primo.
      </div>
    <?php else: ?>
      <?php foreach ($students as $stu): 
        $stuGuardians = $guardiansByStudent[$stu['id']] ?? [];
        $age = Helper::calculateAge($stu['birth_date']);
      ?>
        <div class="student-mobile-card mobile-filter-card">
          <div class="student-card-header">
            <div>
              <div class="student-card-title">
                <?= Helper::e($stu['last_name']) ?> <?= Helper::e($stu['first_name']) ?>
              </div>
              <?php if ($stu['birth_date']): ?>
                <div style="font-size: 0.76rem; color: var(--text-muted);">
                  🎂 <?= Helper::formatDate($stu['birth_date']) ?> (<?= $age ?>)
                </div>
              <?php endif; ?>
            </div>
            <?php if (!empty($stu['allergies'])): ?>
              <span class="badge badge-danger" title="Allergie o intolleranze" style="font-size: 0.72rem;">
                ⚠️ <?= Helper::e($stu['allergies']) ?>
              </span>
            <?php endif; ?>
          </div>

          <?php if (!empty($stu['notes'])): ?>
            <div style="font-size: 0.8rem; color: var(--text-secondary); background: #fffbeb; padding: 6px 8px; border-radius: var(--radius-sm); border: 1px solid #fef3c7;">
              📝 <?= Helper::e($stu['notes']) ?>
            </div>
          <?php endif; ?>

          <!-- Elenco Genitori & Contatti Diretti -->
          <div class="student-guardians-list">
            <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">
              Contatti Famiglia
            </div>
            <?php if (empty($stuGuardians)): ?>
              <span style="font-size: 0.78rem; color: var(--text-muted); font-style: italic;">Nessun contatto registrato</span>
            <?php else: ?>
              <?php foreach ($stuGuardians as $g): ?>
                <div style="font-size: 0.82rem; display: flex; align-items: center; justify-content: space-between; gap: 6px; flex-wrap: wrap; padding: 4px 0; border-bottom: 1px dashed var(--border-color);">
                  <div>
                    <strong><?= Helper::e(trim($g['first_name'] . ' ' . ($g['last_name'] ?? ''))) ?></strong>
                    <span style="font-size: 0.74rem; color: var(--text-secondary);">(<?= Helper::e(ucfirst($g['relation'])) ?>)</span>
                  </div>
                  <div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                    <?php if ($g['phone']): ?>
                      <a href="tel:<?= Helper::e($g['phone']) ?>" class="badge badge-secondary" style="font-size: 0.70rem; padding: 2px 6px;" title="Chiama">
                        📞 <?= Helper::e($g['phone']) ?>
                      </a>
                      <?php if ($g['consent_whatsapp']): ?>
                        <a href="<?= Helper::waLink($g['phone']) ?>" target="_blank" class="badge" style="background:#25D366; color:white; font-size: 0.70rem; padding: 2px 6px;" title="Apri WhatsApp">
                          WA
                        </a>
                      <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($g['email']): ?>
                      <a href="mailto:<?= Helper::e($g['email']) ?>" class="badge badge-secondary" style="font-size: 0.70rem; padding: 2px 6px;" title="Invia Email">
                        ✉️
                      </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-xs btn-secondary" onclick="openEditGuardianModal(<?= htmlspecialchars(json_encode([
                        'id' => (int)$g['id'],
                        'student_id' => (int)$g['student_id'],
                        'first_name' => $g['first_name'],
                        'last_name' => $g['last_name'],
                        'relation' => $g['relation'],
                        'phone' => $g['phone'] ?? '',
                        'email' => $g['email'] ?? '',
                        'consent_whatsapp' => (int)$g['consent_whatsapp']
                    ]), ENT_QUOTES, 'UTF-8') ?>)" title="Modifica contatto" style="margin-left: 2px;">
                      ✏️
                    </button>
                    <form method="POST" action="alunni.php" onsubmit="return confirm('Confermi di voler eliminare questo contatto famiglia?');" style="display: inline; margin: 0;">
                      <?= Helper::csrfField() ?>
                      <input type="hidden" name="action" value="delete_guardian">
                      <input type="hidden" name="guardian_id" value="<?= (int)$g['id'] ?>">
                      <button type="submit" class="btn btn-xs btn-danger" title="Elimina contatto">🗑️</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- Azioni -->
          <div class="student-card-actions">
            <button type="button" class="btn btn-sm btn-secondary" onclick="openAddGuardianModal(<?= (int)$stu['id'] ?>, '<?= Helper::e(addslashes($stu['first_name'] . ' ' . $stu['last_name'])) ?>')" title="Aggiungi contatto famiglia">
              + Contatto
            </button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="openEditStudentModal(<?= htmlspecialchars(json_encode([
                'id' => (int)$stu['id'],
                'first_name' => $stu['first_name'],
                'last_name' => $stu['last_name'],
                'birth_date' => $stu['birth_date'] ?? '',
                'allergies' => $stu['allergies'] ?? '',
                'notes' => $stu['notes'] ?? ''
            ]), ENT_QUOTES, 'UTF-8') ?>)" title="Modifica dati alunno">
              ✏️ Modifica
            </button>
            <form method="POST" action="alunni.php" onsubmit="return confirm('Confermi di voler rimuovere questo alunno dalla classe?');" style="display: inline; margin: 0;">
              <?= Helper::csrfField() ?>
              <input type="hidden" name="action" value="delete_student">
              <input type="hidden" name="student_id" value="<?= (int)$stu['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger" title="Elimina">🗑️</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Modal Aggiungi Alunno -->
<div class="modal-backdrop" id="modalAddStudent">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Nuovo Alunno</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="alunni.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="add_student">
      
      <div class="modal-body">
        <h4 style="margin: 0 0 12px; font-size: 0.95rem; color: var(--primary);">Dati Alunno</h4>
        <div class="form-grid">
          <div class="form-group">
            <label for="last_name">Cognome Alunno *</label>
            <input type="text" id="last_name" name="last_name" class="form-control" placeholder="es. Rossi" required>
          </div>
          <div class="form-group">
            <label for="first_name">Nome Alunno *</label>
            <input type="text" id="first_name" name="first_name" class="form-control" placeholder="es. Sofia" required>
          </div>
        </div>

        <div class="form-group">
          <label for="birth_date">Data di Nascita</label>
          <input type="date" id="birth_date" name="birth_date" class="form-control">
        </div>

        <div class="form-group">
          <label for="allergies">Allergie Alimentari o Intolleranze (per merende/feste)</label>
          <input type="text" id="allergies" name="allergies" class="form-control" placeholder="es. Celiachia, allergia frutta a guscio, favismo...">
        </div>

        <div class="form-group">
          <label for="notes">Note Aggiuntive / Deleghe al ritiro</label>
          <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="Note per uscite, nonni delegati..."></textarea>
        </div>

        <h4 style="margin: 18px 0 12px; font-size: 0.95rem; color: var(--primary); border-top: 1px solid var(--border-color); padding-top: 14px;">
          Contatto Principale Genitore (Opzionale)
        </h4>

        <div class="form-grid">
          <div class="form-group">
            <label for="guardian_first_name">Nome e Cognome Genitore</label>
            <input type="text" id="guardian_first_name" name="guardian_first_name" class="form-control" placeholder="es. Laura Bianchi">
          </div>
          <div class="form-group">
            <label for="guardian_relation">Ruolo / Parentela</label>
            <select id="guardian_relation" name="guardian_relation" class="form-control">
              <option value="madre">Madre</option>
              <option value="padre">Padre</option>
              <option value="nonna">Nonna</option>
              <option value="nonno">Nonno</option>
              <option value="tutore">Tutore legale</option>
              <option value="altro">Altro</option>
            </select>
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="guardian_phone">Telefono Cellulare</label>
            <input type="tel" id="guardian_phone" name="guardian_phone" class="form-control" placeholder="es. 3331234567">
          </div>
          <div class="form-group">
            <label for="guardian_email">Email</label>
            <input type="email" id="guardian_email" name="guardian_email" class="form-control" placeholder="es. laura@email.it">
          </div>
        </div>

        <div class="form-group">
          <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
            <input type="checkbox" name="guardian_consent" value="1" checked>
            <span>Consenso all'inserimento nel gruppo WhatsApp di classe</span>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Salva Alunno</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Modifica Alunno -->
<div class="modal-backdrop" id="modalEditStudent">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Modifica Alunno</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="alunni.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="edit_student">
      <input type="hidden" name="student_id" id="edit_student_id" value="">
      
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label for="edit_last_name">Cognome Alunno *</label>
            <input type="text" id="edit_last_name" name="last_name" class="form-control" placeholder="es. Rossi" required>
          </div>
          <div class="form-group">
            <label for="edit_first_name">Nome Alunno *</label>
            <input type="text" id="edit_first_name" name="first_name" class="form-control" placeholder="es. Sofia" required>
          </div>
        </div>

        <div class="form-group">
          <label for="edit_birth_date">Data di Nascita</label>
          <input type="date" id="edit_birth_date" name="birth_date" class="form-control">
        </div>

        <div class="form-group">
          <label for="edit_allergies">Allergie Alimentari o Intolleranze (per merende/feste)</label>
          <input type="text" id="edit_allergies" name="allergies" class="form-control" placeholder="es. Celiachia, allergia frutta a guscio, favismo...">
        </div>

        <div class="form-group">
          <label for="edit_notes">Note Aggiuntive / Deleghe al ritiro</label>
          <textarea id="edit_notes" name="notes" class="form-control" rows="3" placeholder="Note per uscite, nonni delegati..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Salva Modifiche</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Aggiungi Genitore -->
<div class="modal-backdrop" id="modalAddGuardian">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Aggiungi Contatto Famiglia</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="alunni.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="add_guardian">
      <input type="hidden" name="student_id" id="guardianStudentId" value="">

      <div class="modal-body">
        <p style="font-size: 0.9rem; margin-top: 0;">Per l'alunno: <strong id="guardianStudentName"></strong></p>

        <div class="form-grid">
          <div class="form-group">
            <label for="g_first_name">Nome *</label>
            <input type="text" id="g_first_name" name="first_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="g_last_name">Cognome *</label>
            <input type="text" id="g_last_name" name="last_name" class="form-control" required>
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="g_relation">Relazione / Ruolo</label>
            <select id="g_relation" name="relation" class="form-control">
              <option value="madre">Madre</option>
              <option value="padre">Padre</option>
              <option value="nonna">Nonna</option>
              <option value="nonno">Nonno</option>
              <option value="tutore">Tutore legale</option>
              <option value="altro">Altro</option>
            </select>
          </div>
          <div class="form-group">
            <label for="g_phone">Cellulare</label>
            <input type="tel" id="g_phone" name="phone" class="form-control" placeholder="3331234567">
          </div>
        </div>

        <div class="form-group">
          <label for="g_email">Indirizzo Email</label>
          <input type="email" id="g_email" name="email" class="form-control" placeholder="email@dominio.it">
        </div>

        <div class="form-group">
          <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
            <input type="checkbox" name="consent_whatsapp" value="1" checked>
            <span>Consenso inserimento chat WhatsApp di classe</span>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Salva Contatto</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Modifica Contatto Famiglia -->
<div class="modal-backdrop" id="modalEditGuardian">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Modifica Contatto Famiglia</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="alunni.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="edit_guardian">
      <input type="hidden" name="guardian_id" id="edit_guardian_id" value="">

      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label for="edit_g_first_name">Nome *</label>
            <input type="text" id="edit_g_first_name" name="first_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="edit_g_last_name">Cognome *</label>
            <input type="text" id="edit_g_last_name" name="last_name" class="form-control" required>
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="edit_g_relation">Relazione / Ruolo</label>
            <select id="edit_g_relation" name="relation" class="form-control">
              <option value="madre">Madre</option>
              <option value="padre">Padre</option>
              <option value="nonna">Nonna</option>
              <option value="nonno">Nonno</option>
              <option value="tutore">Tutore legale</option>
              <option value="altro">Altro</option>
            </select>
          </div>
          <div class="form-group">
            <label for="edit_g_phone">Cellulare</label>
            <input type="tel" id="edit_g_phone" name="phone" class="form-control" placeholder="3331234567">
          </div>
        </div>

        <div class="form-group">
          <label for="edit_g_email">Indirizzo Email</label>
          <input type="email" id="edit_g_email" name="email" class="form-control" placeholder="email@dominio.it">
        </div>

        <div class="form-group">
          <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
            <input type="checkbox" id="edit_g_consent_whatsapp" name="consent_whatsapp" value="1">
            <span>Consenso inserimento chat WhatsApp di classe</span>
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

<!-- Modal Copia Rubrica WhatsApp -->
<div class="modal-backdrop" id="modalCopyPhones">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Rubrica Numeri WhatsApp</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <p style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 0;">
        Elenco di tutti i numeri cellulari dei genitori con consenso WhatsApp (formato internazionale con prefisso 39).
      </p>
      <textarea id="phonesList" class="form-control" rows="6" readonly><?= Helper::e($whatsappGroupString) ?></textarea>
      <small class="form-hint">Puoi copiare questi numeri per creare il gruppo WhatsApp della classe o per una lista broadcast.</small>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-modal-close>Chiudi</button>
      <button type="button" class="btn btn-whatsapp" data-copy-target="#phonesList" data-copy-success="Numeri copiati negli appunti!">
        📋 Copia Tutti i Numeri
      </button>
    </div>
  </div>
</div>

<script>
function openAddGuardianModal(studentId, studentName) {
  document.getElementById('guardianStudentId').value = studentId;
  document.getElementById('guardianStudentName').innerText = studentName;
  openModal('modalAddGuardian');
}

function openEditGuardianModal(guardian) {
  document.getElementById('edit_guardian_id').value = guardian.id;
  document.getElementById('edit_g_first_name').value = guardian.first_name || '';
  document.getElementById('edit_g_last_name').value = guardian.last_name || '';
  document.getElementById('edit_g_relation').value = guardian.relation || 'madre';
  document.getElementById('edit_g_phone').value = guardian.phone || '';
  document.getElementById('edit_g_email').value = guardian.email || '';
  document.getElementById('edit_g_consent_whatsapp').checked = (parseInt(guardian.consent_whatsapp, 10) === 1);
  openModal('modalEditGuardian');
}

function openEditStudentModal(student) {
  document.getElementById('edit_student_id').value = student.id;
  document.getElementById('edit_first_name').value = student.first_name || '';
  document.getElementById('edit_last_name').value = student.last_name || '';
  document.getElementById('edit_birth_date').value = student.birth_date || '';
  document.getElementById('edit_allergies').value = student.allergies || '';
  document.getElementById('edit_notes').value = student.notes || '';
  openModal('modalEditStudent');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
