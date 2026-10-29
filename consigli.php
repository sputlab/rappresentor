<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;

Auth::requireAuth('login.php');

$currentUser = Auth::user();
$activeClass = Helper::getActiveClass($db);
$activeClassId = $activeClass['id'] ?? null;

// Gestione Azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Helper::verifyCsrf()) {
        Helper::setFlash('danger', 'Richiesta non valida.');
    } else {
        if ($activeClassId && $_POST['action'] === 'add_topic') {
            $title = trim($_POST['title'] ?? '');
            $category = $_POST['category'] ?? 'varie';
            $description = trim($_POST['description'] ?? '');

            if (empty($title)) {
                Helper::setFlash('danger', 'Il titolo dell\'argomento è obbligatorio.');
            } else {
                $stmt = $db->prepare("INSERT INTO rappresentor_interclasse_topics (class_id, title, category, description, status) 
                    VALUES (:cid, :ti, :ca, :de, 'proposto')");
                $stmt->execute([
                    'cid' => $activeClassId,
                    'ti' => $title,
                    'ca' => $category,
                    'de' => $description
                ]);
                Helper::setFlash('success', 'Argomento aggiunto al quaderno del Consiglio!');
                header('Location: consigli.php');
                exit;
            }
        }

        if ($activeClassId && $_POST['action'] === 'update_topic') {
            $topicId = (int)($_POST['topic_id'] ?? 0);
            $status = $_POST['status'] ?? 'proposto';
            $outcome = trim($_POST['outcome_notes'] ?? '');

            $stmt = $db->prepare("UPDATE rappresentor_interclasse_topics SET status = :st, outcome_notes = :ou WHERE id = :id AND class_id = :cid");
            $stmt->execute([
                'st' => $status,
                'ou' => $outcome,
                'id' => $topicId,
                'cid' => $activeClassId
            ]);
            Helper::setFlash('success', 'Esito dell\'argomento aggiornato con successo.');
            header('Location: consigli.php');
            exit;
        }

        if ($activeClassId && $_POST['action'] === 'delete_topic') {
            $topicId = (int)($_POST['topic_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM rappresentor_interclasse_topics WHERE id = :id AND class_id = :cid");
            $stmt->execute(['id' => $topicId, 'cid' => $activeClassId]);
            Helper::setFlash('info', 'Argomento rimosso.');
            header('Location: consigli.php');
            exit;
        }
    }
}

// Carica tutti gli argomenti della classe
$topics = [];
$activeTopics = [];
$archivedTopics = [];
$reportTopics = '';

if ($activeClassId) {
    $stmt = $db->prepare("SELECT * FROM rappresentor_interclasse_topics WHERE class_id = :cid ORDER BY FIELD(status, 'proposto', 'in_discussione', 'approvato', 'risolto', 'archiviato'), id DESC");
    $stmt->execute(['cid' => $activeClassId]);
    $topics = $stmt->fetchAll();

    foreach ($topics as $tp) {
        if ($tp['status'] === 'archiviato') {
            $archivedTopics[] = $tp;
        } else {
            $activeTopics[] = $tp;
        }
    }

    // Prepara testo riassuntivo per WhatsApp
    $reportTopics = "📋 *Punti & Richieste per il Consiglio d'Interclasse/Docenti*\n";
    $reportTopics .= "🏫 " . ($activeClass['name'] ?? 'Classe') . "\n\n";
    foreach ($activeTopics as $t) {
        $statusIcon = match($t['status']) {
            'proposto' => '🟡 Proposto',
            'in_discussione' => '🟠 In trattazione',
            'approvato' => '🟢 Accolto/Approvato',
            'risolto' => '✅ Risolto',
            default => '⚪'
        };
        $reportTopics .= "• *[" . strtoupper($t['category']) . "]* " . $t['title'] . " (" . $statusIcon . ")\n";
        if (!empty($t['outcome_notes'])) {
            $reportTopics .= "  ↳ _Risposta docenti_: " . $t['outcome_notes'] . "\n";
        }
    }
}

$pageTitle = 'Consigli & Note di Classe';
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
      <span>📋</span>
      <span>Consigli d'Interclasse & Note con i Docenti</span>
    </h1>
    <p class="page-subtitle">Raccogli le richieste dei genitori da sottoporre agli insegnanti e traccia le risposte.</p>
  </div>
  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <button class="btn btn-primary" data-modal-target="modalAddTopic">+ Nuovo Argomento</button>
    <button class="btn btn-whatsapp" data-modal-target="modalReportTopics">📱 Copia Resoconto WA</button>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">📌 Quaderno delle Istanze e Problematiche</h2>
    <span class="badge badge-primary"><?= count($activeTopics) ?> attive</span>
  </div>
  <div class="card-body">
    <?php if (empty($activeTopics)): ?>
      <p style="color: var(--text-muted); text-align: center; padding: 32px 0; margin: 0;">
        Nessun argomento attivo. Clicca su "+ Nuovo Argomento" per annotare una richiesta ricevuta dai genitori.
      </p>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 14px;">
        <?php foreach ($activeTopics as $tp): 
          $badgeClass = match($tp['status']) {
            'proposto' => 'badge-warning',
            'in_discussione' => 'badge-info',
            'approvato', 'risolto' => 'badge-success',
            default => 'badge-secondary'
          };
        ?>
          <div style="border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; background: var(--bg-surface);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap;">
              <div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px;">
                  <span class="badge badge-secondary" style="text-transform: uppercase; font-size: 0.72rem;">
                    <?= Helper::e($tp['category']) ?>
                  </span>
                  <span class="badge <?= $badgeClass ?>">
                    <?= Helper::e(str_replace('_', ' ', ucfirst($tp['status']))) ?>
                  </span>
                  <small style="color: var(--text-muted);">Inserito il <?= Helper::formatDate($tp['created_at']) ?></small>
                </div>
                <h3 style="margin: 0; font-size: 1.05rem;"><?= Helper::e($tp['title']) ?></h3>
                <?php if (!empty($tp['description'])): ?>
                  <p style="margin: 6px 0 0; font-size: 0.88rem; color: var(--text-secondary);">
                    <?= nl2br(Helper::e($tp['description'])) ?>
                  </p>
                <?php endif; ?>
              </div>

              <div style="display: flex; gap: 6px;">
                <button class="btn btn-sm btn-secondary" onclick="openUpdateTopicModal(<?= (int)$tp['id'] ?>, '<?= Helper::e(addslashes($tp['title'])) ?>', '<?= $tp['status'] ?>', '<?= Helper::e(addslashes($tp['outcome_notes'] ?? '')) ?>')">
                  ✏️ Aggiorna Esito
                </button>
                <form method="POST" action="consigli.php" data-confirm="Vuoi eliminare questa voce dal consiglio?" data-confirm-title="Elimina Argomento" data-confirm-type="danger" data-confirm-btn="Elimina" style="display: inline;">
                  <?= Helper::csrfField() ?>
                  <input type="hidden" name="action" value="delete_topic">
                  <input type="hidden" name="topic_id" value="<?= (int)$tp['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-secondary" style="color: var(--danger);" title="Elimina definitivamente questo argomento archiviato">🗑️ Elimina</button>
                </form>
              </div>
            </div>

            <!-- Box Risposta Insegnanti se presente -->
            <?php if (!empty($tp['outcome_notes'])): ?>
              <div style="margin-top: 12px; background: #f8fafc; border-left: 3px solid var(--primary); padding: 10px 14px; border-radius: 4px; font-size: 0.88rem;">
                <strong style="color: var(--primary);">Risposta / Esito emerso con i docenti:</strong>
                <p style="margin: 4px 0 0; color: var(--text-primary);"><?= nl2br(Helper::e($tp['outcome_notes'])) ?></p>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Sezione Argomenti Archiviati in fondo (Accordion Chiuso di Default) -->
<?php if (!empty($archivedTopics)): ?>
  <details class="card accordion-card" style="margin-top: 24px;">
    <summary class="card-header">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span class="accordion-icon">▶</span>
        <h2 class="card-title" style="margin: 0;">📁 Argomenti & Istanze Archiviate</h2>
      </div>
      <div style="display: flex; align-items: center; gap: 8px;">
        <span class="badge badge-secondary"><?= count($archivedTopics) ?> archiviati</span>
        <span style="font-size: 0.8rem; color: var(--text-muted);">(clicca per visualizzare)</span>
      </div>
    </summary>
    <div class="card-body">
      <div style="display: flex; flex-direction: column; gap: 10px;">
        <?php foreach ($archivedTopics as $tp): ?>
          <div style="border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; background: var(--bg-surface-alt); opacity: 0.88;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; flex-wrap: wrap;">
              <div>
                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                  <span class="badge badge-secondary" style="font-size: 0.7rem;"><?= Helper::e($tp['category']) ?></span>
                  <span class="badge badge-secondary" style="font-size: 0.7rem;">Archiviato</span>
                  <small style="color: var(--text-muted); font-size: 0.75rem;">del <?= Helper::formatDate($tp['created_at']) ?></small>
                </div>
                <h4 style="margin: 0; font-size: 0.98rem;"><?= Helper::e($tp['title']) ?></h4>
                <?php if (!empty($tp['outcome_notes'])): ?>
                  <div style="margin-top: 6px; font-size: 0.82rem; color: var(--text-secondary);">
                    <strong>Esito:</strong> <?= Helper::e($tp['outcome_notes']) ?>
                  </div>
                <?php endif; ?>
              </div>

              <div style="display: flex; gap: 6px;">
                <button class="btn btn-sm btn-secondary" onclick="openUpdateTopicModal(<?= (int)$tp['id'] ?>, '<?= Helper::e(addslashes($tp['title'])) ?>', '<?= $tp['status'] ?>', '<?= Helper::e(addslashes($tp['outcome_notes'] ?? '')) ?>')">
                  ✏️ Modifica
                </button>
                <form method="POST" action="consigli.php" data-confirm="Vuoi eliminare definitivamente questa voce archiviata?" data-confirm-title="Elimina Argomento Archiviato" data-confirm-type="danger" data-confirm-btn="Elimina" style="display: inline;">
                  <?= Helper::csrfField() ?>
                  <input type="hidden" name="action" value="delete_topic">
                  <input type="hidden" name="topic_id" value="<?= (int)$tp['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-secondary" style="color: var(--danger);" title="Elimina definitivamente questo argomento archiviato">🗑️ Elimina</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </details>
<?php endif; ?>

<!-- Modal Aggiungi Argomento -->
<div class="modal-backdrop" id="modalAddTopic">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Segnala Argomento per il Consiglio</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="consigli.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="add_topic">

      <div class="modal-body">
        <div class="form-group">
          <label for="top_title">Titolo Argomento o Segnalazione *</label>
          <input type="text" id="top_title" name="title" class="form-control" placeholder="es. Riscaldamento aule, Porzioni mensa scolastica, Compiti fine settimana..." required>
        </div>

        <div class="form-group">
          <label for="top_cat">Ambito / Categoria</label>
          <select id="top_cat" name="category" class="form-control">
            <option value="didattica">Didattica & Compiti</option>
            <option value="mensa">Mensa Scolastica & Ristorazione</option>
            <option value="struttura_sicurezza">Struttura, Aule & Sicurezza</option>
            <option value="gite_uscite">Gite, Uscite Didattiche & Trasporti</option>
            <option value="comportamento">Comportamento & Clima di Classe</option>
            <option value="varie" selected>Varie</option>
          </select>
        </div>

        <div class="form-group">
          <label for="top_desc">Descrizione e dettagli segnalati dai genitori</label>
          <textarea id="top_desc" name="description" class="form-control" rows="3" placeholder="Dettagli emersi nelle chat genitori da presentare ai docenti..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Inserisci Argomento</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Aggiorna Esito -->
<div class="modal-backdrop" id="modalUpdateTopic">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Aggiorna Esito del Consiglio</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="consigli.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="update_topic">
      <input type="hidden" name="topic_id" id="editTopicId" value="">

      <div class="modal-body">
        <p style="font-size: 0.95rem; margin-top: 0;">Argomento: <strong id="editTopicTitle"></strong></p>

        <div class="form-group">
          <label for="edit_topic_status">Stato Attuale</label>
          <select id="edit_topic_status" name="status" class="form-control">
            <option value="proposto">🟡 Proposto</option>
            <option value="in_discussione">🟠 In Trattazione con Docenti / Dirigente</option>
            <option value="approvato">🟢 Richiesta Accolta / Approvata</option>
            <option value="risolto">✅ Risolto Definitivamente</option>
            <option value="archiviato">⚪ Archiviato</option>
          </select>
        </div>

        <div class="form-group">
          <label for="edit_outcome">Risposta dei docenti / Risultato della riunione</label>
          <textarea id="edit_outcome" name="outcome_notes" class="form-control" rows="4" placeholder="Cosa è stato deciso durante il consiglio d'interclasse..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Salva Aggiornamento</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Resoconto WhatsApp -->
<div class="modal-backdrop" id="modalReportTopics">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Resoconto Consiglio per WhatsApp</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <textarea id="textReportTopics" class="form-control" rows="10" readonly><?= Helper::e($reportTopics) ?></textarea>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-modal-close>Chiudi</button>
      <button type="button" class="btn btn-whatsapp" data-copy-target="#textReportTopics" data-copy-success="Resoconto copiato negli appunti!">
        📋 Copia per i Genitori
      </button>
    </div>
  </div>
</div>

<script>
function openUpdateTopicModal(id, title, status, outcome) {
  document.getElementById('editTopicId').value = id;
  document.getElementById('editTopicTitle').innerText = title;
  document.getElementById('edit_topic_status').value = status;
  document.getElementById('edit_outcome').value = outcome || '';
  openModal('modalUpdateTopic');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
