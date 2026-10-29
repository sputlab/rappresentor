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
            Helper::setFlash('danger', 'Dati genitore incompleti.');
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
            Helper::setFlash('success', 'Contatto genitore aggiunto con successo!');
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
                  <span style="color: var(--text-muted); font-size: 0.82rem;">Nessun genitore registrato</span>
                <?php else: ?>
                  <div style="display: flex; flex-direction: column; gap: 6px;">
                    <?php foreach ($stuGuardians as $g): ?>
                      <div style="font-size: 0.85rem; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                        <strong><?= Helper::e($g['first_name']) ?> (<?= Helper::e($g['relation']) ?>):</strong>
                        <?php if ($g['phone']): ?>
                          <a href="tel:<?= Helper::e($g['phone']) ?>" style="color: var(--text-secondary);" title="Chiama">
                            📞 <?= Helper::e($g['phone']) ?>
                          </a>
                          <?php if ($g['consent_whatsapp']): ?>
                            <a href="<?= Helper::waLink($g['phone']) ?>" target="_blank" class="badge" style="background:#25D366; color:white;" title="Apri WhatsApp">
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
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 6px;">
                  <button class="btn btn-sm btn-secondary" onclick="openAddGuardianModal(<?= (int)$stu['id'] ?>, '<?= Helper::e(addslashes($stu['first_name'] . ' ' . $stu['last_name'])) ?>')" title="Aggiungi altro genitore">
                    + Genitore
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
            <label for="guardian_relation">Ruolo</label>
            <select id="guardian_relation" name="guardian_relation" class="form-control">
              <option value="madre">Madre</option>
              <option value="padre">Padre</option>
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
            <label for="g_relation">Relazione</label>
            <select id="g_relation" name="relation" class="form-control">
              <option value="madre">Madre</option>
              <option value="padre">Padre</option>
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
