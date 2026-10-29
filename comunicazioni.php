<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;

Auth::requireAuth('login.php');

$currentUser = Auth::user();
$activeClass = Helper::getActiveClass($db);
$activeClassId = $activeClass['id'] ?? null;

// Gestione aggiunta nuovo template personalizzato
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_template') {
    if (!Helper::verifyCsrf()) {
        Helper::setFlash('danger', 'Richiesta non valida.');
    } else {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Varie');
        $content = trim($_POST['content'] ?? '');

        if (!empty($title) && !empty($content)) {
            $stmt = $db->prepare("INSERT INTO rappresentor_templates (title, category, content, is_default) VALUES (:ti, :ca, :co, 0)");
            $stmt->execute(['ti' => $title, 'ca' => $category, 'co' => $content]);
            Helper::setFlash('success', 'Template salvato con successo!');
            header('Location: comunicazioni.php');
            exit;
        }
    }
}

// Carica tutti i template dal database
$templates = $db->query("SELECT * FROM rappresentor_templates ORDER BY is_default DESC, title ASC")->fetchAll();

// Dati dinamici classe attiva per sostituzione automatica variabili
$className = $activeClass['name'] ?? 'Classe';
$saldo = 0.0;
$usciteText = "Nessuna spesa recente registrata.";
$quotaTitle = 'Cassa Comune';
$quotaAmount = '10,00';
$quotaDeadline = 'prossima settimana';
$dettaglioQuote = 'Al momento non risultano raccolte quote attive per la nostra classe.';

$currentMonth = (int)date('n');
$monthlyBirthdays = [];
$allStudents = [];
$birthdayEvents = [];

if ($activeClassId) {
    // Saldo cassa aggiornato (entrate da quote versate + movimenti manuali - uscite)
    $cashSummary = Helper::getClassCashSummary($db, $activeClassId);
    $saldo = $cashSummary['saldo'];

    $stmt = $db->prepare("SELECT transaction_date, description, amount FROM rappresentor_cashbox_transactions WHERE class_id = :cid AND type = 'uscita' ORDER BY transaction_date DESC, id DESC LIMIT 4");
    $stmt->execute(['cid' => $activeClassId]);
    $recentExpenses = $stmt->fetchAll();
    if (!empty($recentExpenses)) {
        $usciteText = "";
        foreach ($recentExpenses as $exp) {
            $usciteText .= "- " . Helper::formatDate($exp['transaction_date']) . ": " . $exp['description'] . " (" . Helper::formatMoney($exp['amount']) . ")\n";
        }
    }

    // Campagne quote attive per la classe (tutte le campagne aperte)
    $campSummary = Helper::getActiveCampaignsSummary($db, $activeClassId);
    $quotaTitle = $campSummary['quota_title'];
    $quotaAmount = $campSummary['quota_amount'];
    $quotaDeadline = $campSummary['quota_deadline'];
    $dettaglioQuote = $campSummary['formatted_block'];

    // Compleanni degli alunni del mese corrente
    $stmt = $db->prepare("SELECT id, first_name, last_name, birth_date FROM rappresentor_students WHERE class_id = :cid AND is_active = 1 AND birth_date IS NOT NULL AND MONTH(birth_date) = :m ORDER BY DAY(birth_date) ASC");
    $stmt->execute(['cid' => $activeClassId, 'm' => $currentMonth]);
    $monthlyBirthdays = $stmt->fetchAll();

    // Tutti gli alunni attivi della classe
    $stmt = $db->prepare("SELECT id, first_name, last_name, birth_date FROM rappresentor_students WHERE class_id = :cid AND is_active = 1 ORDER BY last_name ASC, first_name ASC");
    $stmt->execute(['cid' => $activeClassId]);
    $allStudents = $stmt->fetchAll();

    // Eventi catalogati come Compleanno o Festa in calendario
    $stmt = $db->prepare("SELECT id, title, event_type, event_date, event_time, location, description FROM rappresentor_events WHERE class_id = :cid AND (event_type = 'compleanno' OR event_type = 'festa' OR LOWER(title) LIKE '%compleanno%') ORDER BY (event_date >= CURDATE()) DESC, event_date ASC");
    $stmt->execute(['cid' => $activeClassId]);
    $birthdayEvents = $stmt->fetchAll();

    // Eventi catalogati come Gita o Uscita didattica in calendario
    $stmt = $db->prepare("SELECT id, title, event_type, event_date, event_time, location, description FROM rappresentor_events WHERE class_id = :cid AND (event_type = 'gita' OR LOWER(title) LIKE '%gita%' OR LOWER(title) LIKE '%uscita%') ORDER BY (event_date >= CURDATE()) DESC, event_date ASC");
    $stmt->execute(['cid' => $activeClassId]);
    $gitaEvents = $stmt->fetchAll();

    // Eventi catalogati come Riunione, Assemblea o Consiglio in calendario
    $stmt = $db->prepare("SELECT id, title, event_type, event_date, event_time, location, description FROM rappresentor_events WHERE class_id = :cid AND (event_type = 'riunione' OR LOWER(title) LIKE '%consiglio%' OR LOWER(title) LIKE '%assemblea%' OR LOWER(title) LIKE '%riunione%' OR LOWER(title) LIKE '%interclasse%') ORDER BY (event_date >= CURDATE()) DESC, event_date ASC");
    $stmt->execute(['cid' => $activeClassId]);
    $riunioneEvents = $stmt->fetchAll();
} else {
    $gitaEvents = [];
    $riunioneEvents = [];
}

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

// Rimuove qualsiasi testo residuo "Vi preghiamo di..." dal modello compleanno
if ($selectedTemplate && stripos($selectedTemplate['title'], 'Compleanno') !== false) {
    $selectedTemplate['content'] = preg_replace('/\n*Vi preghiamo di[^\n]*/i', '', $selectedTemplate['content']);
}

// Identificazione tipo di modello per controlli ausiliari interattivi
$isCassaTemplate = $selectedTemplate && ($selectedTemplate['category'] === 'Cassa' || stripos($selectedTemplate['title'], 'Quote') !== false || stripos($selectedTemplate['title'], 'Cassa') !== false || stripos($selectedTemplate['title'], 'Pagamento') !== false);
$isBirthdayTemplate = $selectedTemplate && ($selectedTemplate['category'] === 'Feste' || stripos($selectedTemplate['title'], 'Compleanno') !== false || stripos($selectedTemplate['title'], 'Festa') !== false);
$isGitaTemplate = $selectedTemplate && ($selectedTemplate['category'] === 'Gite' || stripos($selectedTemplate['title'], 'Gita') !== false || stripos($selectedTemplate['title'], 'Uscita') !== false);
$isRiunioneTemplate = $selectedTemplate && ($selectedTemplate['category'] === 'Riunioni' || stripos($selectedTemplate['title'], 'Riunione') !== false || stripos($selectedTemplate['title'], 'Assemblea') !== false || stripos($selectedTemplate['title'], 'Consiglio') !== false);

// Valori predefiniti per interpolazione
$initialNomeRiunione = 'Assemblea di Classe con Docenti';
$initialFesteggiato = 'Nome Alunno';
$initialDate = date('d/m/Y', strtotime('+7 days'));
$initialTime = '16:30';
$initialReturnTime = '16:30';
$initialLocation = 'Presso i locali della scuola / Aula Magna';
$initialDescription = "- Andamento didattico della classe\n- Programmazione attività e progetti\n- Varie ed eventuali";

if ($isBirthdayTemplate) {
    $initialLocation = 'Presso la sala feste';
    if (!empty($monthlyBirthdays)) {
        $initialFesteggiato = $monthlyBirthdays[0]['first_name'] . ' ' . $monthlyBirthdays[0]['last_name'];
        $bDay = date('d', strtotime($monthlyBirthdays[0]['birth_date']));
        $initialDate = date('d/m/Y', strtotime(date('Y') . '-' . date('m-d', strtotime($monthlyBirthdays[0]['birth_date']))));
    } elseif (!empty($allStudents)) {
        $initialFesteggiato = $allStudents[0]['first_name'] . ' ' . $allStudents[0]['last_name'];
    }
} elseif ($isGitaTemplate) {
    $initialLocation = 'Museo Civico / Fattoria Didattica';
    $initialTime = '08:30';
    $initialReturnTime = '16:30';
    $initialDescription = "- Partenza dal piazzale della scuola con pullman GT\n- Visita guidata didattica e laboratori\n- Pausa pranzo al sacco\n- Rientro a scuola previsto nel pomeriggio";

    if (!empty($gitaEvents)) {
        $firstGita = $gitaEvents[0];
        $initialLocation = !empty($firstGita['location']) ? $firstGita['location'] : $firstGita['title'];
        $initialDate = Helper::formatDate($firstGita['event_date']);
        if (!empty($firstGita['event_time'])) {
            $initialTime = substr($firstGita['event_time'], 0, 5);
        }
        if (!empty($firstGita['description'])) {
            $initialDescription = $firstGita['description'];
        }
    }
} elseif ($isRiunioneTemplate) {
    $initialTime = '17:00';
    $initialLocation = 'Presso i locali della scuola / Aula Magna';
    if (!empty($riunioneEvents)) {
        $firstR = $riunioneEvents[0];
        $initialNomeRiunione = $firstR['title'];
        $initialDate = Helper::formatDate($firstR['event_date']);
        if (!empty($firstR['event_time'])) {
            $initialTime = substr($firstR['event_time'], 0, 5);
        }
        if (!empty($firstR['location'])) {
            $initialLocation = $firstR['location'];
        }
        if (!empty($firstR['description'])) {
            $initialDescription = $firstR['description'];
        }
    }
}

$defaultPaymentMethods = "- In contanti (in bustina con nome dell'alunno)\n- Bonifico bancario (dati IBAN forniti in privato)\n- Satispay (contattatemi per link/numero)";

// Funzione di sostituzione variabili
$interpolatedContent = "";
if ($selectedTemplate) {
    $interpolatedContent = strtr($selectedTemplate['content'], [
        '{CLASSE}' => $className,
        '{DATA_OGGI}' => date('d/m/Y'),
        '{SALDO_CASSA}' => Helper::formatMoney($saldo),
        '{LISTA_USCITE}' => trim($usciteText),
        '{DETTAGLIO_QUOTE}' => $dettaglioQuote,
        '{CAMPAGNE_ATTIVE}' => $dettaglioQuote,
        '{LISTA_QUOTE}' => $dettaglioQuote,
        '{TITOLO_RACCOLTA}' => $quotaTitle,
        '{QUOTA}' => $quotaAmount,
        '{SCADENZA}' => $quotaDeadline,
        '{NOME_EVENTO}' => $initialNomeRiunione,
        '{DATA_EVENTO}' => $initialDate,
        '{ORA_EVENTO}' => $initialTime,
        '{ORA_RIENTRO}' => $initialReturnTime,
        '{LUOGO}' => $initialLocation,
        '{DESCRIZIONE_EVENTO}' => $initialDescription,
        '{FESTEGGIATO}' => $initialFesteggiato,
        '{METODI_PAGAMENTO}' => $defaultPaymentMethods,
    ]);
}

$pageTitle = 'Bacheca & Messaggi WhatsApp';
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

      <?php if ($isCassaTemplate): ?>
        <!-- Pannello Selezione Metodi di Pagamento con Flag -->
        <div style="background: var(--bg-surface-alt); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 14px;">
          <div style="font-weight: 700; font-size: 0.85rem; margin-bottom: 8px; color: var(--text-primary); display: flex; align-items: center; gap: 6px;">
            <span>💳</span> Modalità di versamento da includere nel messaggio:
          </div>
          <div style="display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.85rem;">
            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none;">
              <input type="checkbox" class="composer-payment-method" value="contanti" checked onchange="updateComposerPaymentMethods()"> 💶 Contanti
            </label>
            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none;">
              <input type="checkbox" class="composer-payment-method" value="bonifico" checked onchange="updateComposerPaymentMethods()"> 🏦 Bonifico
            </label>
            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none;">
              <input type="checkbox" class="composer-payment-method" value="satispay" checked onchange="updateComposerPaymentMethods()"> 📱 Satispay
            </label>
            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none;">
              <input type="checkbox" class="composer-payment-method" value="paypal" onchange="updateComposerPaymentMethods()"> 💳 PayPal
            </label>
            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none;">
              <input type="checkbox" class="composer-payment-method" value="pagopa" onchange="updateComposerPaymentMethods()"> 🏛️ PagoPA
            </label>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($isBirthdayTemplate): ?>
        <!-- Pannello Autocompilazione Compleanno / Festa da Menu a Tendina -->
        <div style="background: var(--bg-surface-alt); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 14px;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
            <strong style="font-size: 0.85rem; color: var(--text-primary); display: flex; align-items: center; gap: 6px;">
              <span>🎂</span> Autocompila da Compleanno o Evento a Calendario:
            </strong>
            <span style="font-size: 0.78rem; color: var(--text-secondary);">Seleziona per autocompilare dati e testo</span>
          </div>

          <div style="margin-bottom: 10px;">
            <select id="birthdaySourceSelect" class="form-control" onchange="applyBirthdaySource(this)" style="font-size: 0.88rem;">
              <option value="">-- Seleziona un festeggiato o evento compleanno --</option>
              
              <?php if (!empty($monthlyBirthdays)): ?>
                <optgroup label="🎂 Compleanni Alunni del Mese (<?= Helper::formatMonthName($currentMonth) ?>)">
                  <?php foreach ($monthlyBirthdays as $mb): 
                    $bDay = date('d', strtotime($mb['birth_date']));
                    $bFormatted = $bDay . ' ' . Helper::formatMonthName((int)date('m', strtotime($mb['birth_date'])));
                    $partyDate = date('Y') . '-' . date('m-d', strtotime($mb['birth_date']));
                    $partyDateFmt = Helper::formatDate($partyDate);
                  ?>
                    <option value="student_<?= $mb['id'] ?>"
                            data-name="<?= Helper::e($mb['first_name'] . ' ' . $mb['last_name']) ?>"
                            data-date="<?= Helper::e($partyDateFmt) ?>"
                            data-time="16:30"
                            data-location="Presso i locali della scuola / Sala feste">
                      <?= Helper::e($mb['first_name'] . ' ' . $mb['last_name']) ?> (<?= $bFormatted ?>)
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endif; ?>

              <?php if (!empty($birthdayEvents)): ?>
                <optgroup label="📅 Eventi Compleanno in Calendario">
                  <?php foreach ($birthdayEvents as $be): 
                    $evDateFmt = Helper::formatDate($be['event_date']);
                    $evTime = !empty($be['event_time']) ? substr($be['event_time'], 0, 5) : '16:30';
                    $evLoc = !empty($be['location']) ? $be['location'] : 'Presso la sala feste';
                    $festName = $be['title'];
                    if (preg_match('/compleanno\s+(?:di\s+)?(.+)/i', $be['title'], $matches)) {
                        $festName = trim($matches[1]);
                    } elseif (preg_match('/festa\s+(?:di\s+)?(.+)/i', $be['title'], $matches)) {
                        $festName = trim($matches[1]);
                    }
                  ?>
                    <option value="event_<?= $be['id'] ?>"
                            data-name="<?= Helper::e($festName) ?>"
                            data-date="<?= Helper::e($evDateFmt) ?>"
                            data-time="<?= Helper::e($evTime) ?>"
                            data-location="<?= Helper::e($evLoc) ?>">
                      <?= Helper::e($be['title']) ?> (<?= $evDateFmt ?><?= !empty($be['location']) ? ' - ' . Helper::e($be['location']) : '' ?>)
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endif; ?>

              <?php 
                $otherStudents = array_filter($allStudents, function($s) use ($monthlyBirthdays) {
                  foreach ($monthlyBirthdays as $mb) {
                    if ($mb['id'] === $s['id']) return false;
                  }
                  return true;
                });
                if (!empty($otherStudents)): 
              ?>
                <optgroup label="👶 Altri Alunni della Classe">
                  <?php foreach ($otherStudents as $os): 
                    $bInfo = '';
                    $partyDateFmt = date('d/m/Y');
                    if (!empty($os['birth_date'])) {
                      $bDay = date('d', strtotime($os['birth_date']));
                      $bInfo = ' (' . $bDay . ' ' . Helper::formatMonthName((int)date('m', strtotime($os['birth_date']))) . ')';
                      $partyDate = date('Y') . '-' . date('m-d', strtotime($os['birth_date']));
                      $partyDateFmt = Helper::formatDate($partyDate);
                    }
                  ?>
                    <option value="student_<?= $os['id'] ?>"
                            data-name="<?= Helper::e($os['first_name'] . ' ' . $os['last_name']) ?>"
                            data-date="<?= Helper::e($partyDateFmt) ?>"
                            data-time="16:30"
                            data-location="Presso i locali della scuola / Sala feste">
                      <?= Helper::e($os['first_name'] . ' ' . $os['last_name']) ?><?= $bInfo ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endif; ?>
            </select>
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px;">
            <div>
              <label for="b_festeggiato" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Festeggiato/a</label>
              <input type="text" id="b_festeggiato" class="form-control" style="font-size: 0.82rem; padding: 4px 8px;" value="<?= Helper::e($initialFesteggiato) ?>" oninput="syncBirthdayText()">
            </div>
            <div>
              <label for="b_data" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Data Festa</label>
              <input type="text" id="b_data" class="form-control" style="font-size: 0.82rem; padding: 4px 8px;" value="<?= Helper::e($initialDate) ?>" oninput="syncBirthdayText()">
            </div>
            <div>
              <label for="b_ora" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Orario</label>
              <input type="text" id="b_ora" class="form-control" style="font-size: 0.82rem; padding: 4px 8px;" value="<?= Helper::e($initialTime) ?>" oninput="syncBirthdayText()">
            </div>
            <div>
              <label for="b_luogo" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Luogo</label>
              <input type="text" id="b_luogo" class="form-control" style="font-size: 0.82rem; padding: 4px 8px;" value="<?= Helper::e($initialLocation) ?>" oninput="syncBirthdayText()">
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($isGitaTemplate): ?>
        <!-- Pannello Ausiliario per Parametri Gita Scolastica con Menu a Tendina -->
        <div style="background: var(--bg-surface-alt); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 14px;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
            <strong style="font-size: 0.85rem; color: var(--text-primary); display: flex; align-items: center; gap: 6px;">
              <span>🚌</span> Autocompila da Gita Scolastica a Calendario:
            </strong>
            <span style="font-size: 0.78rem; color: var(--text-secondary);">Seleziona per autocompilare dati e testo</span>
          </div>

          <div style="margin-bottom: 10px;">
            <select id="gitaSourceSelect" class="form-control" onchange="applyGitaSource(this)" style="font-size: 0.88rem;">
              <option value="">-- Seleziona una gita o uscita a calendario per compilare --</option>
              <?php if (!empty($gitaEvents)): ?>
                <?php foreach ($gitaEvents as $ge): 
                  $gDateFmt = Helper::formatDate($ge['event_date']);
                  $gTime = !empty($ge['event_time']) ? substr($ge['event_time'], 0, 5) : '08:30';
                  $gLoc = !empty($ge['location']) ? $ge['location'] : $ge['title'];
                ?>
                  <option value="gita_<?= $ge['id'] ?>"
                          data-dest="<?= Helper::e($gLoc) ?>"
                          data-date="<?= Helper::e($gDateFmt) ?>"
                          data-ritrovo="<?= Helper::e($gTime) ?>"
                          data-rientro="16:30"
                          data-quota="<?= Helper::e($quotaAmount) ?>"
                          data-scadenza="<?= Helper::e($quotaDeadline) ?>"
                          data-desc="<?= Helper::e($ge['description'] ?? '') ?>">
                    <?= Helper::e($ge['title']) ?> (<?= $gDateFmt ?><?= !empty($ge['location']) ? ' - ' . Helper::e($ge['location']) : '' ?>)
                  </option>
                <?php endforeach; ?>
              <?php else: ?>
                <option value="" disabled>Nessuna gita registrata nel calendario della classe</option>
              <?php endif; ?>
            </select>
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px;">
            <div>
              <label for="g_luogo" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Destinazione</label>
              <input type="text" id="g_luogo" class="form-control" style="font-size: 0.82rem; padding: 4px 8px;" value="<?= Helper::e($initialLocation) ?>" oninput="syncGitaText()">
            </div>
            <div>
              <label for="g_data" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Data</label>
              <input type="text" id="g_data" class="form-control" style="font-size: 0.82rem; padding: 4px 8px;" value="<?= Helper::e($initialDate) ?>" oninput="syncGitaText()">
            </div>
            <div>
              <label for="g_ritrovo" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Ritrovo</label>
              <input type="text" id="g_ritrovo" class="form-control" style="font-size: 0.82rem; padding: 4px 8px;" value="<?= Helper::e($initialTime) ?>" oninput="syncGitaText()">
            </div>
            <div>
              <label for="g_rientro" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Rientro</label>
              <input type="text" id="g_rientro" class="form-control" style="font-size: 0.82rem; padding: 4px 8px;" value="<?= Helper::e($initialReturnTime) ?>" oninput="syncGitaText()">
            </div>
            <div>
              <label for="g_quota" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Quota (€)</label>
              <input type="text" id="g_quota" class="form-control" style="font-size: 0.82rem; padding: 4px 8px;" value="<?= Helper::e($quotaAmount) ?>" oninput="syncGitaText()">
            </div>
            <div>
              <label for="g_scadenza" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Scadenza</label>
              <input type="text" id="g_scadenza" class="form-control" style="font-size: 0.82rem; padding: 4px 8px;" value="<?= Helper::e($quotaDeadline) ?>" oninput="syncGitaText()">
            </div>
          </div>

          <div style="margin-top: 8px;">
            <label for="g_desc" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Programma / Dettagli</label>
            <textarea id="g_desc" class="form-control" rows="2" style="font-size: 0.82rem; padding: 4px 8px;" oninput="syncGitaText()"><?= Helper::e($initialDescription) ?></textarea>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($isRiunioneTemplate): ?>
        <!-- Pannello Autocompilazione Riunione / Assemblea / Consiglio da Menu a Tendina -->
        <div style="background: var(--bg-surface-alt); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 14px;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
            <strong style="font-size: 0.85rem; color: var(--text-primary); display: flex; align-items: center; gap: 6px;">
              <span>👥</span> Autocompila da Riunione o Consiglio a Calendario:
            </strong>
            <span style="font-size: 0.78rem; color: var(--text-secondary);">Seleziona per autocompilare dati e testo</span>
          </div>

          <div style="margin-bottom: 10px;">
            <select id="riunioneSourceSelect" class="form-control" onchange="applyRiunioneSource(this)" style="font-size: 0.88rem;">
              <option value="">-- Seleziona una riunione o consiglio a calendario --</option>
              <?php if (!empty($riunioneEvents)): ?>
                <?php foreach ($riunioneEvents as $re): 
                  $rDateFmt = Helper::formatDate($re['event_date']);
                  $rTime = !empty($re['event_time']) ? substr($re['event_time'], 0, 5) : '17:00';
                  $rLoc = !empty($re['location']) ? $re['location'] : 'Presso i locali della scuola / Aula Magna';
                ?>
                  <option value="riunione_<?= $re['id'] ?>"
                          data-nome="<?= Helper::e($re['title']) ?>"
                          data-date="<?= Helper::e($rDateFmt) ?>"
                          data-time="<?= Helper::e($rTime) ?>"
                          data-location="<?= Helper::e($rLoc) ?>"
                          data-desc="<?= Helper::e($re['description'] ?? '') ?>">
                    <?= Helper::e($re['title']) ?> (<?= $rDateFmt ?> ore <?= $rTime ?>)
                  </option>
                <?php endforeach; ?>
              <?php else: ?>
                <option value="" disabled>Nessuna riunione registrata nel calendario della classe</option>
              <?php endif; ?>
            </select>
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px;">
            <div>
              <label for="r_nome" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Nome Riunione</label>
              <input type="text" id="r_nome" class="form-control" style="font-size: 0.82rem; padding: 4px 8px;" value="<?= Helper::e($initialNomeRiunione) ?>" oninput="syncRiunioneText()">
            </div>
            <div>
              <label for="r_data" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Data</label>
              <input type="text" id="r_data" class="form-control" style="font-size: 0.82rem; padding: 4px 8px;" value="<?= Helper::e($initialDate) ?>" oninput="syncRiunioneText()">
            </div>
            <div>
              <label for="r_ora" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Orario</label>
              <input type="text" id="r_ora" class="form-control" style="font-size: 0.82rem; padding: 4px 8px;" value="<?= Helper::e($initialTime) ?>" oninput="syncRiunioneText()">
            </div>
            <div>
              <label for="r_luogo" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Luogo</label>
              <input type="text" id="r_luogo" class="form-control" style="font-size: 0.82rem; padding: 4px 8px;" value="<?= Helper::e($initialLocation) ?>" oninput="syncRiunioneText()">
            </div>
          </div>

          <div style="margin-top: 8px;">
            <label for="r_desc" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; display: block;">Ordine del Giorno / Punti da trattare</label>
            <textarea id="r_desc" class="form-control" rows="2" style="font-size: 0.82rem; padding: 4px 8px;" oninput="syncRiunioneText()"><?= Helper::e($initialDescription) ?></textarea>
          </div>
        </div>
      <?php endif; ?>

      <!-- Pannello Traduzioni Multilingua con Flag -->
      <div style="background: var(--bg-surface-alt); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 14px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
          <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-primary); display: flex; align-items: center; gap: 6px;">
            <span>🌐</span> Traduzioni multilingua da includere nel messaggio:
          </div>
          <div id="translationStatus" style="font-size: 0.78rem; color: var(--text-secondary); display: flex; align-items: center; gap: 6px;">
            <span>Solo Italiano</span>
          </div>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 8px; font-size: 0.85rem;">
          <label id="label_lang_en" style="display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none; background: var(--bg-surface); border: 1px solid var(--border-color); padding: 6px 10px; border-radius: var(--radius-sm); transition: all 0.2s;">
            <input type="checkbox" class="composer-lang-flag" value="en" onchange="toggleLanguageTranslation('en', this.checked)">
            <span>🇬🇧 Inglese</span>
          </label>
          <label id="label_lang_ro" style="display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none; background: var(--bg-surface); border: 1px solid var(--border-color); padding: 6px 10px; border-radius: var(--radius-sm); transition: all 0.2s;">
            <input type="checkbox" class="composer-lang-flag" value="ro" onchange="toggleLanguageTranslation('ro', this.checked)">
            <span>🇷🇴 Rumeno</span>
          </label>
          <label id="label_lang_hr" style="display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none; background: var(--bg-surface); border: 1px solid var(--border-color); padding: 6px 10px; border-radius: var(--radius-sm); transition: all 0.2s;">
            <input type="checkbox" class="composer-lang-flag" value="hr" onchange="toggleLanguageTranslation('hr', this.checked)">
            <span>🇭🇷 Croato</span>
          </label>
          <label id="label_lang_sq" style="display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none; background: var(--bg-surface); border: 1px solid var(--border-color); padding: 6px 10px; border-radius: var(--radius-sm); transition: all 0.2s;">
            <input type="checkbox" class="composer-lang-flag" value="sq" onchange="toggleLanguageTranslation('sq', this.checked)">
            <span>🇦🇱 Albanese</span>
          </label>
          <label id="label_lang_bn" style="display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none; background: var(--bg-surface); border: 1px solid var(--border-color); padding: 6px 10px; border-radius: var(--radius-sm); transition: all 0.2s;">
            <input type="checkbox" class="composer-lang-flag" value="bn" onchange="toggleLanguageTranslation('bn', this.checked)">
            <span>🇧🇩 Bengalese</span>
          </label>
          <label id="label_lang_tr" style="display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none; background: var(--bg-surface); border: 1px solid var(--border-color); padding: 6px 10px; border-radius: var(--radius-sm); transition: all 0.2s;">
            <input type="checkbox" class="composer-lang-flag" value="tr" onchange="toggleLanguageTranslation('tr', this.checked)">
            <span>🇹🇷 Turco</span>
          </label>
        </div>

        <!-- Indicatore sequenza ordine attivo -->
        <div id="activeLanguagesBadgeContainer" style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed var(--border-color); font-size: 0.78rem; color: var(--text-secondary); display: none; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
          <div>
            <span>Sequenza lingue nel messaggio: </span>
            <span id="activeLanguagesBadgeList" style="font-weight: 700; color: var(--text-primary);">🇮🇹 Italiano</span>
          </div>
          <button type="button" class="btn btn-xs btn-secondary" onclick="refreshTranslations(true)" title="Aggiorna traduzioni per il testo corrente">
            🔄 Aggiorna Traduzioni
          </button>
        </div>
      </div>

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
            <input type="text" id="tpl_cat" name="category" class="form-control" placeholder="es. Feste, Scuola, Cassa, Gite" value="Varie">
          </div>
        </div>

        <div class="form-group">
          <label for="tpl_content">Contenuto con Segnaposto Variabili *</label>
          <textarea id="tpl_content" name="content" class="form-control" rows="8" placeholder="Ciao a tutti i genitori di {CLASSE}..." required></textarea>
          <small class="form-hint">
            Variabili supportate: <code>{CLASSE}</code>, <code>{DATA_OGGI}</code>, <code>{SALDO_CASSA}</code>, <code>{LISTA_USCITE}</code>, <code>{DETTAGLIO_QUOTE}</code>, <code>{TITOLO_RACCOLTA}</code>, <code>{QUOTA}</code>, <code>{SCADENZA}</code>, <code>{FESTEGGIATO}</code>, <code>{DATA_EVENTO}</code>, <code>{ORA_EVENTO}</code>, <code>{ORA_RIENTRO}</code>, <code>{LUOGO}</code>
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

<script>
const activeClassName = <?= json_encode($className) ?>;
const baseRawTemplateContent = <?= json_encode($selectedTemplate ? $selectedTemplate['content'] : '') ?>;
const csrfToken = <?= json_encode(Helper::csrfToken()) ?>;

const SUPPORTED_LANGUAGES = {
  en: { flag: '🇬🇧', label: 'English (Inglese)', name: 'Inglese' },
  ro: { flag: '🇷🇴', label: 'Română (Rumeno)', name: 'Rumeno' },
  hr: { flag: '🇭🇷', label: 'Hrvatski (Croato)', name: 'Croato' },
  sq: { flag: '🇦🇱', label: 'Shqip (Albanese)', name: 'Albanese' },
  bn: { flag: '🇧🇩', label: 'বাংলা (Bengalese)', name: 'Bengalese' },
  tr: { flag: '🇹🇷', label: 'Türkçe (Turco)', name: 'Turco' }
};

const TRANSLATION_SEPARATOR = '\n\n══════════════════════════\n';

// Mantiene l'ordine cronologico esatto di selezione dei flag scelto dall'utente
let activeLanguagesOrder = [];

// Cache in memoria delle traduzioni per lingua per il testo italiano corrente
let translationsCache = {};
let lastTranslatedItalianText = '';
let autoTranslateDebounceTimer = null;

/**
 * Estrae solo il testo italiano dal composer (tutto ciò che precede il primo separatore di traduzione)
 */
function getItalianBaseText() {
  const composer = document.getElementById('composerText');
  if (!composer) return '';
  const val = composer.value;
  const sepIdx = val.indexOf(TRANSLATION_SEPARATOR);
  if (sepIdx !== -1) {
    return val.substring(0, sepIdx).trim();
  }
  return val.trim();
}

/**
 * Imposta il testo italiano di base preservando o riassemblando le traduzioni attive
 */
function setItalianBaseText(text) {
  const composer = document.getElementById('composerText');
  if (!composer) return;

  if (activeLanguagesOrder.length === 0) {
    composer.value = text;
  } else {
    assembleFullMessage(text);
    triggerAutoTranslationUpdate();
  }
}

/**
 * Assembla il messaggio finale combinando il testo italiano con le traduzioni attive
 * nell'ordine esatto con cui i flag sono stati attivati dall'utente.
 */
function assembleFullMessage(customItalianText = null) {
  const italianText = (customItalianText !== null) ? customItalianText : getItalianBaseText();
  let fullMessage = italianText;

  for (const langCode of activeLanguagesOrder) {
    if (translationsCache[langCode]) {
      const config = SUPPORTED_LANGUAGES[langCode];
      fullMessage += TRANSLATION_SEPARATOR + config.flag + ' *' + config.label + '*\n' + translationsCache[langCode];
    }
  }

  const composer = document.getElementById('composerText');
  if (composer) {
    composer.value = fullMessage;
  }
}

/**
 * Gestisce l'attivazione o disattivazione del flag di una lingua
 */
async function toggleLanguageTranslation(langCode, isChecked) {
  if (!SUPPORTED_LANGUAGES[langCode]) return;

  // Evidenzia visivamente l'etichetta del flag
  const labelEl = document.getElementById('label_lang_' + langCode);
  if (labelEl) {
    if (isChecked) {
      labelEl.style.background = 'var(--primary-light)';
      labelEl.style.borderColor = 'var(--primary)';
      labelEl.style.fontWeight = '600';
    } else {
      labelEl.style.background = 'var(--bg-surface)';
      labelEl.style.borderColor = 'var(--border-color)';
      labelEl.style.fontWeight = 'normal';
    }
  }

  if (isChecked) {
    // Aggiungi in coda all'ordine cronologico
    if (!activeLanguagesOrder.includes(langCode)) {
      activeLanguagesOrder.push(langCode);
    }
  } else {
    // Rimuovi dall'ordine
    activeLanguagesOrder = activeLanguagesOrder.filter(code => code !== langCode);
  }

  updateLanguagesSequenceUI();

  if (activeLanguagesOrder.length === 0) {
    // Nessun flag attivo: il messaggio è esclusivamente in italiano
    const italianText = getItalianBaseText();
    document.getElementById('composerText').value = italianText;
    updateStatus('Solo Italiano');
    return;
  }

  // Se il flag è stato attivato e manca la traduzione per il testo italiano corrente, richiedila
  const currentItalian = getItalianBaseText();
  if (isChecked) {
    if (!translationsCache[langCode] || lastTranslatedItalianText !== currentItalian) {
      await fetchTranslationForLang(langCode, currentItalian);
    }
  }

  assembleFullMessage();
}

/**
 * Chiama l'endpoint server api/translate.php per tradurre il testo
 */
async function fetchTranslationForLang(langCode, italianText) {
  if (!italianText) return;
  const langConfig = SUPPORTED_LANGUAGES[langCode];
  updateStatus(`Traduzione in corso (${langConfig.name})...`, true);

  try {
    const formData = new FormData();
    formData.append('text', italianText);
    formData.append('lang', langCode);
    formData.append('csrf_token', csrfToken);

    const res = await fetch('api/translate.php', {
      method: 'POST',
      body: formData
    });

    const data = await res.json();
    if (data.success) {
      translationsCache[langCode] = data.translatedText;
      lastTranslatedItalianText = italianText;
      updateStatus('Traduzioni pronte');
    } else {
      if (typeof showToast === 'function') {
        showToast(data.message || 'Errore nella traduzione.', 'danger');
      }
      updateStatus('Errore traduzione');
    }
  } catch (err) {
    console.error('Translation network error:', err);
    updateStatus('Errore di connessione');
  }
}

/**
 * Aggiorna tutte le traduzioni attive (es. quando l'utente modifica i parametri o clicca il pulsante)
 */
async function refreshTranslations(force = false) {
  if (activeLanguagesOrder.length === 0) return;

  const currentItalian = getItalianBaseText();
  if (!currentItalian) return;

  if (!force && lastTranslatedItalianText === currentItalian) {
    assembleFullMessage();
    return;
  }

  updateStatus('Aggiornamento traduzioni...', true);

  for (const langCode of activeLanguagesOrder) {
    await fetchTranslationForLang(langCode, currentItalian);
  }

  assembleFullMessage();
  updateStatus('Traduzioni aggiornate con successo!');
}

/**
 * Avvia l'aggiornamento automatico con debounce per evitare chiamate ripetute mentre si digita
 */
function triggerAutoTranslationUpdate() {
  if (activeLanguagesOrder.length === 0) return;
  clearTimeout(autoTranslateDebounceTimer);
  autoTranslateDebounceTimer = setTimeout(() => {
    refreshTranslations(true);
  }, 1000);
}

/**
 * Aggiorna la barra informativa che mostra la sequenza esatta delle lingue
 */
function updateLanguagesSequenceUI() {
  const container = document.getElementById('activeLanguagesBadgeContainer');
  const listEl = document.getElementById('activeLanguagesBadgeList');
  if (!container || !listEl) return;

  if (activeLanguagesOrder.length === 0) {
    container.style.display = 'none';
  } else {
    container.style.display = 'flex';
    const items = ['🇮🇹 Italiano'];
    activeLanguagesOrder.forEach(code => {
      const cfg = SUPPORTED_LANGUAGES[code];
      if (cfg) {
        items.push(`${cfg.flag} ${cfg.name}`);
      }
    });
    listEl.innerHTML = items.join(' &rarr; ');
  }
}

/**
 * Aggiorna la dicitura di stato del pannello traduzioni
 */
function updateStatus(text, isLoading = false) {
  const statusEl = document.getElementById('translationStatus');
  if (!statusEl) return;
  if (isLoading) {
    statusEl.innerHTML = `⏳ <span style="color: var(--primary); font-weight: 600;">${text}</span>`;
  } else {
    statusEl.innerText = text;
  }
}

// Rileva modifiche manuali sul composerText per avvisare l'utente
const composerEl = document.getElementById('composerText');
if (composerEl) {
  composerEl.addEventListener('input', () => {
    if (activeLanguagesOrder.length > 0) {
      updateStatus('Testo modificato (clicca 🔄 per ri-tradurre)');
    }
  });
}

// Aggiorna dinamicamente il testo delle quote/cassa in base ai metodi di pagamento selezionati
function updateComposerPaymentMethods() {
  const checkboxes = document.querySelectorAll('.composer-payment-method');
  const selectedMethods = [];
  const methodDescriptions = {
    contanti: '- In contanti (in bustina con nome dell\'alunno)',
    bonifico: '- Bonifico bancario (dati IBAN forniti in privato)',
    satispay: '- Satispay (contattatemi per link/numero)',
    paypal: '- PayPal (invio ad amici e familiari)',
    pagopa: '- Avviso PagoPA (tramite registro elettronico)'
  };

  checkboxes.forEach(cb => {
    if (cb.checked && methodDescriptions[cb.value]) {
      selectedMethods.push(methodDescriptions[cb.value]);
    }
  });

  let methodsBlock = "Modalità di versamento disponibili:\n";
  if (selectedMethods.length > 0) {
    methodsBlock += selectedMethods.join('\n');
  } else {
    methodsBlock += "- Contattare i rappresentanti per concordare la modalità";
  }

  let text = getItalianBaseText();
  if (/Modalità di versamento disponibili:[\s\S]*?(?=\n\n(?:Chi non avesse|Grazie|I Rappresentanti|$))/i.test(text)) {
    text = text.replace(/Modalità di versamento disponibili:[\s\S]*?(?=\n\n(?:Chi non avesse|Grazie|I Rappresentanti|$))/i, methodsBlock);
  } else if (/(\n\n(?:Chi non avesse|Grazie|I Rappresentanti))/i.test(text)) {
    text = text.replace(/(\n\n(?:Chi non avesse|Grazie|I Rappresentanti))/i, '\n\n' + methodsBlock + '$1');
  } else {
    text += '\n\n' + methodsBlock;
  }

  setItalianBaseText(text);
}

// Applica selezione da menu a tendina compleanno/festa
function applyBirthdaySource(sel) {
  const opt = sel.options[sel.selectedIndex];
  if (!opt || !opt.value) return;

  const name = opt.getAttribute('data-name') || '';
  const date = opt.getAttribute('data-date') || '';
  const time = opt.getAttribute('data-time') || '16:30';
  const location = opt.getAttribute('data-location') || 'Presso la sala feste';

  if (document.getElementById('b_festeggiato')) document.getElementById('b_festeggiato').value = name;
  if (document.getElementById('b_data')) document.getElementById('b_data').value = date;
  if (document.getElementById('b_ora')) document.getElementById('b_ora').value = time;
  if (document.getElementById('b_luogo')) document.getElementById('b_luogo').value = location;

  syncBirthdayText();
}

// Sincronizza i dati del compleanno nel testo WhatsApp
function syncBirthdayText() {
  const fest = document.getElementById('b_festeggiato') ? document.getElementById('b_festeggiato').value.trim() : 'Nome Alunno';
  const data = document.getElementById('b_data') ? document.getElementById('b_data').value.trim() : 'dd/mm/aaaa';
  const ora = document.getElementById('b_ora') ? document.getElementById('b_ora').value.trim() : '16:30';
  const luogo = document.getElementById('b_luogo') ? document.getElementById('b_luogo').value.trim() : 'Presso la sala feste';

  let text = baseRawTemplateContent;
  text = text.replace(/\n*Vi preghiamo di[^\n]*/gi, '');

  text = text.replace(/\{CLASSE\}/g, activeClassName);
  text = text.replace(/\{FESTEGGIATO\}/g, fest || 'Nome Alunno');
  text = text.replace(/\{DATA_EVENTO\}/g, data || 'dd/mm/aaaa');
  text = text.replace(/\{ORA_EVENTO\}/g, ora || '16:30');
  text = text.replace(/\{LUOGO\}/g, luogo || 'Presso la sala feste');

  setItalianBaseText(text);
}

// Applica selezione da menu a tendina gita scolastica
function applyGitaSource(sel) {
  const opt = sel.options[sel.selectedIndex];
  if (!opt || !opt.value) return;

  const dest = opt.getAttribute('data-dest') || '';
  const date = opt.getAttribute('data-date') || '';
  const ritrovo = opt.getAttribute('data-ritrovo') || '08:30';
  const rientro = opt.getAttribute('data-rientro') || '16:30';
  const quota = opt.getAttribute('data-quota') || '10,00';
  const scadenza = opt.getAttribute('data-scadenza') || 'prossima settimana';
  const desc = opt.getAttribute('data-desc') || "- Partenza dal piazzale della scuola con pullman GT\n- Visita guidata didattica e laboratori\n- Pausa pranzo al sacco\n- Rientro a scuola previsto nel pomeriggio";

  if (document.getElementById('g_luogo')) document.getElementById('g_luogo').value = dest;
  if (document.getElementById('g_data')) document.getElementById('g_data').value = date;
  if (document.getElementById('g_ritrovo')) document.getElementById('g_ritrovo').value = ritrovo;
  if (document.getElementById('g_rientro')) document.getElementById('g_rientro').value = rientro;
  if (document.getElementById('g_quota')) document.getElementById('g_quota').value = quota;
  if (document.getElementById('g_scadenza')) document.getElementById('g_scadenza').value = scadenza;
  if (document.getElementById('g_desc')) document.getElementById('g_desc').value = desc;

  syncGitaText();
}

// Sincronizza i parametri della gita nel testo WhatsApp
function syncGitaText() {
  const luogo = document.getElementById('g_luogo') ? document.getElementById('g_luogo').value.trim() : 'Destinazione Gita';
  const data = document.getElementById('g_data') ? document.getElementById('g_data').value.trim() : 'dd/mm/aaaa';
  const ritrovo = document.getElementById('g_ritrovo') ? document.getElementById('g_ritrovo').value.trim() : '08:30';
  const rientro = document.getElementById('g_rientro') ? document.getElementById('g_rientro').value.trim() : '16:30';
  const quota = document.getElementById('g_quota') ? document.getElementById('g_quota').value.trim() : '10,00';
  const scadenza = document.getElementById('g_scadenza') ? document.getElementById('g_scadenza').value.trim() : 'prossima settimana';
  const desc = document.getElementById('g_desc') ? document.getElementById('g_desc').value.trim() : '';

  let text = baseRawTemplateContent;
  text = text.replace(/\{CLASSE\}/g, activeClassName);
  text = text.replace(/\{LUOGO\}/g, luogo);
  text = text.replace(/\{DATA_EVENTO\}/g, data);
  text = text.replace(/\{ORA_EVENTO\}/g, ritrovo);
  text = text.replace(/\{ORA_RIENTRO\}/g, rientro);
  text = text.replace(/\{QUOTA\}/g, quota);
  text = text.replace(/\{SCADENZA\}/g, scadenza);
  text = text.replace(/\{DESCRIZIONE_EVENTO\}/g, desc || "- Partenza dal piazzale della scuola con pullman GT\n- Visita guidata didattica e laboratori\n- Pausa pranzo al sacco\n- Rientro a scuola previsto nel pomeriggio");

  setItalianBaseText(text);
}

// Applica selezione da menu a tendina riunione / consiglio
function applyRiunioneSource(sel) {
  const opt = sel.options[sel.selectedIndex];
  if (!opt || !opt.value) return;

  const nome = opt.getAttribute('data-nome') || '';
  const date = opt.getAttribute('data-date') || '';
  const time = opt.getAttribute('data-time') || '17:00';
  const location = opt.getAttribute('data-location') || 'Presso i locali della scuola / Aula Magna';
  const desc = opt.getAttribute('data-desc') || "- Andamento didattico della classe\n- Programmazione attività e progetti\n- Varie ed eventuali";

  if (document.getElementById('r_nome')) document.getElementById('r_nome').value = nome;
  if (document.getElementById('r_data')) document.getElementById('r_data').value = date;
  if (document.getElementById('r_ora')) document.getElementById('r_ora').value = time;
  if (document.getElementById('r_luogo')) document.getElementById('r_luogo').value = location;
  if (document.getElementById('r_desc')) document.getElementById('r_desc').value = desc;

  syncRiunioneText();
}

// Sincronizza i parametri della riunione/consiglio nel testo WhatsApp
function syncRiunioneText() {
  const nome = document.getElementById('r_nome') ? document.getElementById('r_nome').value.trim() : 'Assemblea di Classe con Docenti';
  const data = document.getElementById('r_data') ? document.getElementById('r_data').value.trim() : 'dd/mm/aaaa';
  const ora = document.getElementById('r_ora') ? document.getElementById('r_ora').value.trim() : '17:00';
  const luogo = document.getElementById('r_luogo') ? document.getElementById('r_luogo').value.trim() : 'Presso i locali della scuola / Aula Magna';
  const desc = document.getElementById('r_desc') ? document.getElementById('r_desc').value.trim() : '';

  let text = baseRawTemplateContent;
  text = text.replace(/\{CLASSE\}/g, activeClassName);
  text = text.replace(/\{NOME_EVENTO\}/g, nome);
  text = text.replace(/\{DATA_EVENTO\}/g, data);
  text = text.replace(/\{ORA_EVENTO\}/g, ora);
  text = text.replace(/\{LUOGO\}/g, luogo);
  text = text.replace(/\{DESCRIZIONE_EVENTO\}/g, desc || "- Andamento didattico della classe\n- Programmazione attività e progetti\n- Varie ed eventuali");

  setItalianBaseText(text);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
