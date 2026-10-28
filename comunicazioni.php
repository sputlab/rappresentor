<?php
declare(strict_types=1);

$pageTitle = 'Bacheca & Messaggi WhatsApp';
require_once __DIR__ . '/includes/header.php';

use Rappresentor\Core\Helper;

if (!$activeClassId) {
    echo '<div class="alert alert-warning">Seleziona o crea prima una classe.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Gestione aggiunta nuovo template personalizzato
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_template') {
    if (!Helper::verifyCsrf()) {
        Helper::setFlash('danger', 'Richiesta non valida.');
    } else {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Varie');
        $content = trim($_POST['content'] ?? '');

        if (!empty($title) && !empty($content)) {
            $stmt = $db->prepare("INSERT INTO templates (title, category, content, is_default) VALUES (:ti, :ca, :co, 0)");
            $stmt->execute(['ti' => $title, 'ca' => $category, 'co' => $content]);
            Helper::setFlash('success', 'Template salvato con successo!');
            header('Location: comunicazioni.php');
            exit;
        }
    }
}

// Carica tutti i template dal database
$templates = $db->query("SELECT * FROM templates ORDER BY is_default DESC, title ASC")->fetchAll();

// Dati dinamici classe attiva per sostituzione automatica variabili
$className = $activeClass['name'] ?? 'Classe';

// Saldo e ultime uscite
$stmt = $db->prepare("SELECT 
    COALESCE(SUM(CASE WHEN type = 'entrata' THEN amount ELSE 0 END), 0) as total_entrate,
    COALESCE(SUM(CASE WHEN type = 'uscita' THEN amount ELSE 0 END), 0) as total_uscite
    FROM cashbox_transactions WHERE class_id = :cid");
$stmt->execute(['cid' => $activeClassId]);
$cash = $stmt->fetch();
$saldo = (float)($cash['total_entrate'] ?? 0) - (float)($cash['total_uscite'] ?? 0);

$stmt = $db->prepare("SELECT transaction_date, description, amount FROM cashbox_transactions WHERE class_id = :cid AND type = 'uscita' ORDER BY transaction_date DESC, id DESC LIMIT 4");
$stmt->execute(['cid' => $activeClassId]);
$recentExpenses = $stmt->fetchAll();
$usciteText = "";
foreach ($recentExpenses as $exp) {
    $usciteText .= "- " . Helper::formatDate($exp['transaction_date']) . ": " . $exp['description'] . " (" . Helper::formatMoney($exp['amount']) . ")\n";
}
if (empty($usciteText)) {
    $usciteText = "Nessuna spesa recente registrata.";
}

// Ultima campagna quote attiva
$stmt = $db->prepare("SELECT * FROM campaigns WHERE class_id = :cid AND is_closed = 0 ORDER BY id DESC LIMIT 1");
$stmt->execute(['cid' => $activeClassId]);
$latestCampaign = $stmt->fetch();
$quotaTitle = $latestCampaign['title'] ?? 'Cassa Comune';
$quotaAmount = $latestCampaign['quota_per_student'] ? number_format((float)$latestCampaign['quota_per_student'], 2, ',', '.') : '10,00';
$quotaDeadline = $latestCampaign['deadline'] ? Helper::formatDate($latestCampaign['deadline']) : 'prossima settimana';

// Template selezionato da visualizzare
$selectedTplId = isset($_GET['tpl']) ? (int)$_GET['tpl'] : ($templates[0]['id'] ?? 0);
$selectedTemplate = null;
foreach ($templates as $t) {
    if ((int)$t['id'] === $selectedTplId) {
        $selectedTemplate = $t;
        break;
    }
}
if (!$selectedTemplate && !empty($templates)) {
    $selectedTemplate = $templates[0];
}

// Funzione di sostituzione variabili
$interpolatedContent = "";
if ($selectedTemplate) {
    $interpolatedContent = strtr($selectedTemplate['content'], [
        '{CLASSE}' => $className,
        '{DATA_OGGI}' => date('d/m/Y'),
        '{SALDO_CASSA}' => Helper::formatMoney($saldo),
        '{LISTA_USCITE}' => trim($usciteText),
        '{TITOLO_RACCOLTA}' => $quotaTitle,
        '{QUOTA}' => $quotaAmount,
        '{SCADENZA}' => $quotaDeadline,
        '{NOME_EVENTO}' => 'Assemblea di Classe con Docenti',
        '{DATA_EVENTO}' => 'Giovedì ' . date('d/m/Y', strtotime('+7 days')),
        '{ORA_EVENTO}' => '17:00',
        '{LUOGO}' => 'Presso i locali della scuola / Aula Magna',
        '{DESCRIZIONE_EVENTO}' => '- Andamento didattico della classe\n- Programmazione uscite e visite guidate\n- Varie ed eventuali'
    ]);
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <span>📢</span>
      <span>Bacheca & Messaggi WhatsApp</span>
    </h1>
    <p class="page-subtitle">Componi e copia con un click messaggi formattati per i gruppi genitori.</p>
  </div>
  <div>
    <button class="btn btn-primary" data-modal-target="modalNewTemplate">+ Nuovo Modello</button>
  </div>
</div>

<div class="grid-2">
  <!-- Elenco Modelli Disponibili -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">📑 Modelli Predefiniti</h2>
      <span style="font-size: 0.8rem; color: var(--text-muted);"><?= count($templates) ?> modelli</span>
    </div>
    <div class="card-body" style="padding: 12px;">
      <div style="display: flex; flex-direction: column; gap: 8px;">
        <?php foreach ($templates as $tpl): 
          $isActive = $selectedTemplate && ((int)$tpl['id'] === (int)$selectedTemplate['id']);
        ?>
          <a href="comunicazioni.php?tpl=<?= (int)$tpl['id'] ?>" 
             style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid <?= $isActive ? 'var(--primary)' : 'var(--border-color)' ?>; background: <?= $isActive ? 'var(--primary-light)' : 'var(--bg-surface)' ?>; color: var(--text-primary); text-decoration: none;">
            <div>
              <div style="font-weight: 600; font-size: 0.95rem; color: <?= $isActive ? 'var(--primary)' : 'inherit' ?>;">
                <?= Helper::e($tpl['title']) ?>
              </div>
              <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 2px;">
                Categoria: <strong><?= Helper::e($tpl['category']) ?></strong>
              </div>
            </div>
            <span class="badge <?= $isActive ? 'badge-primary' : 'badge-secondary' ?>">
              <?= $isActive ? 'Selezionato' : 'Usa &rarr;' ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Anteprima WhatsApp & Compositore -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title" style="color: var(--whatsapp-dark);">
        <span>💬</span>
        <span>Anteprima Chat WhatsApp</span>
      </h2>
      <button class="btn btn-sm btn-whatsapp" data-copy-target="#composerText" data-copy-success="Messaggio WhatsApp copiato negli appunti!">
        📋 Copia per WhatsApp
      </button>
    </div>
    <div class="card-body">
      <div class="form-group">
        <label for="composerText">Testo pronto per l'invio (modificabile prima di copiare):</label>
        <textarea id="composerText" class="form-control" rows="12" style="font-family: inherit; font-size: 0.92rem; line-height: 1.5;"><?= Helper::e($interpolatedContent) ?></textarea>
      </div>

      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px;">
        <small class="form-hint">
          💡 La formattazione con <code>*testo*</code> apparirà in <strong>grassetto</strong> su WhatsApp.
        </small>
        <button type="button" class="btn btn-whatsapp" data-copy-target="#composerText" data-copy-success="Messaggio WhatsApp copiato negli appunti!">
          📋 Copia Negli Appunti
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Aggiungi Modello -->
<div class="modal-backdrop" id="modalNewTemplate">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Crea Nuovo Modello Messaggio</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="comunicazioni.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="save_template">

      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label for="tpl_title">Titolo Modello *</label>
            <input type="text" id="tpl_title" name="title" class="form-control" placeholder="es. Ringraziamento Fine Anno" required>
          </div>
          <div class="form-group">
            <label for="tpl_cat">Categoria</label>
            <input type="text" id="tpl_cat" name="category" class="form-control" placeholder="es. Feste, Scuola, Cassa" value="Varie">
          </div>
        </div>

        <div class="form-group">
          <label for="tpl_content">Contenuto con Segnaposto Variabili *</label>
          <textarea id="tpl_content" name="content" class="form-control" rows="8" placeholder="Ciao a tutti i genitori di {CLASSE}..." required></textarea>
          <small class="form-hint">
            Variabili supportate: <code>{CLASSE}</code>, <code>{DATA_OGGI}</code>, <code>{SALDO_CASSA}</code>, <code>{LISTA_USCITE}</code>, <code>{TITOLO_RACCOLTA}</code>, <code>{QUOTA}</code>, <code>{SCADENZA}</code>
          </small>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Salva Modello</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
