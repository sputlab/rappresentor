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
        Helper::setFlash('danger', 'Richiesta non valida. Riprova.');
        header('Location: cassa.php');
        exit;
    }

    if ($activeClassId) {
        // 1. Nuova Campagna di Raccolta Quote
        if ($_POST['action'] === 'add_campaign') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $quota = (float)str_replace(',', '.', $_POST['quota_per_student'] ?? '0');
            $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

            if (empty($title) || $quota <= 0) {
                Helper::setFlash('danger', 'Titolo della raccolta e quota valida sono obbligatori.');
            } else {
                $stmt = $db->prepare("INSERT INTO rappresentor_campaigns (class_id, title, description, quota_per_student, deadline) VALUES (:cid, :ti, :de, :qu, :dl)");
                $stmt->execute([
                    'cid' => $activeClassId,
                    'ti' => $title,
                    'de' => $description,
                    'qu' => $quota,
                    'dl' => $deadline
                ]);
                $campaignId = (int)$db->lastInsertId();

                // Inizializza riga di pagamento per tutti gli alunni attivi della classe
                $stmtStu = $db->prepare("SELECT id FROM rappresentor_students WHERE class_id = :cid AND is_active = 1");
                $stmtStu->execute(['cid' => $activeClassId]);
                $students = $stmtStu->fetchAll();

                $insertPay = $db->prepare("INSERT INTO rappresentor_payments (campaign_id, student_id, status, amount_paid) VALUES (:cid, :sid, 'non_pagato', 0.00)");
                foreach ($students as $s) {
                    $insertPay->execute(['cid' => $campaignId, 'sid' => $s['id']]);
                }

                // Se è stata impostata una data di scadenza, crea automaticamente un evento nella sezione Scadenze
                if (!empty($deadline)) {
                    $evTitle = "Scadenza Quota: {$title}";
                    $evDesc = "Termine ultimo versamento per la raccolta '{$title}' (Quota: " . Helper::formatMoney($quota) . " ad alunno). [Raccolta #{$campaignId}]";
                    $stmtEv = $db->prepare("INSERT INTO rappresentor_events (class_id, title, event_type, event_date, description) VALUES (:cid, :ti, 'scadenza', :da, :de)");
                    $stmtEv->execute([
                        'cid' => $activeClassId,
                        'ti' => $evTitle,
                        'da' => $deadline,
                        'de' => $evDesc
                    ]);
                }

                Helper::setFlash('success', "Raccolta quote '{$title}' creata con successo per tutti gli alunni!");
                header("Location: cassa.php?campaign_id={$campaignId}");
                exit;
            }
        }

        // 1b. Modifica Campagna di Raccolta Quote
        if ($_POST['action'] === 'edit_campaign') {
            $campaignId = (int)($_POST['campaign_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $quota = (float)str_replace(',', '.', $_POST['quota_per_student'] ?? '0');
            $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

            if (empty($title) || $quota <= 0 || $campaignId <= 0) {
                Helper::setFlash('danger', 'Titolo della raccolta e quota valida sono obbligatori.');
            } else {
                $stmt = $db->prepare("UPDATE rappresentor_campaigns 
                    SET title = :ti, description = :de, quota_per_student = :qu, deadline = :dl 
                    WHERE id = :id AND class_id = :cid");
                $stmt->execute([
                    'ti' => $title,
                    'de' => $description,
                    'qu' => $quota,
                    'dl' => $deadline,
                    'id' => $campaignId,
                    'cid' => $activeClassId
                ]);

                // Sincronizza l'evento corrispondente a calendario se presente
                $evTag = "[Raccolta #{$campaignId}]";
                $checkEv = $db->prepare("SELECT id FROM rappresentor_events WHERE class_id = :cid AND description LIKE :tag LIMIT 1");
                $checkEv->execute(['cid' => $activeClassId, 'tag' => "%{$evTag}%"]);
                $existingEvId = $checkEv->fetchColumn();

                $evTitle = "Scadenza Quota: {$title}";
                $evDesc = "Termine ultimo versamento per la raccolta '{$title}' (Quota: " . Helper::formatMoney($quota) . " ad alunno). [Raccolta #{$campaignId}]";

                if ($existingEvId) {
                    if (!empty($deadline)) {
                        $stmtEv = $db->prepare("UPDATE rappresentor_events SET title = :ti, event_date = :da, description = :de WHERE id = :id");
                        $stmtEv->execute(['ti' => $evTitle, 'da' => $deadline, 'de' => $evDesc, 'id' => $existingEvId]);
                    } else {
                        $stmtEv = $db->prepare("DELETE FROM rappresentor_events WHERE id = :id");
                        $stmtEv->execute(['id' => $existingEvId]);
                    }
                } elseif (!empty($deadline)) {
                    $stmtEv = $db->prepare("INSERT INTO rappresentor_events (class_id, title, event_type, event_date, description) VALUES (:cid, :ti, 'scadenza', :da, :de)");
                    $stmtEv->execute([
                        'cid' => $activeClassId,
                        'ti' => $evTitle,
                        'da' => $deadline,
                        'de' => $evDesc
                    ]);
                }

                Helper::setFlash('success', "Raccolta quote '{$title}' modificata con successo!");
                header("Location: cassa.php?campaign_id={$campaignId}");
                exit;
            }
        }

        // 2. Nuovo Movimento Cassa (Entrata / Uscita)
        if ($_POST['action'] === 'add_transaction') {
            $type = $_POST['type'] ?? 'uscita';
            $amount = (float)str_replace(',', '.', $_POST['amount'] ?? '0');
            $category = trim($_POST['category'] ?? 'Varie');
            $description = trim($_POST['description'] ?? '');
            $date = !empty($_POST['transaction_date']) ? $_POST['transaction_date'] : date('Y-m-d');
            $receipt = trim($_POST['receipt_ref'] ?? '');
            $campaignId = !empty($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : null;

            if ($amount <= 0 || empty($description)) {
                Helper::setFlash('danger', 'Importo e descrizione sono obbligatori.');
            } else {
                $stmt = $db->prepare("INSERT INTO rappresentor_cashbox_transactions (class_id, campaign_id, type, amount, category, description, transaction_date, receipt_ref, created_by) 
                    VALUES (:cid, :cmp, :ty, :am, :cat, :de, :td, :rc, :cb)");
                $stmt->execute([
                    'cid' => $activeClassId,
                    'cmp' => $campaignId,
                    'ty' => $type,
                    'am' => $amount,
                    'cat' => $category,
                    'de' => $description,
                    'td' => $date,
                    'rc' => $receipt,
                    'cb' => Auth::id()
                ]);
                Helper::setFlash('success', 'Movimento registrato con successo nel libro cassa!');
                header('Location: cassa.php');
                exit;
            }
        }

        // 3. Aggiornamento Singolo Pagamento Alunno (versione form standard)
        if ($_POST['action'] === 'update_single_payment') {
            $paymentId = (int)($_POST['payment_id'] ?? 0);
            $campaignId = (int)($_POST['campaign_id'] ?? 0);
            $status = $_POST['status'] ?? 'non_pagato';
            $amount = (float)str_replace(',', '.', $_POST['amount_paid'] ?? '0');
            $method = $_POST['payment_method'] ?? 'contanti';
            $notes = trim($_POST['notes'] ?? '');

            if ($status === 'non_pagato' || $status === 'esonerato') {
                $amount = 0.00;
                $paidAt = null;
            } else {
                $paidAt = ($status === 'pagato') ? date('Y-m-d') : null;
            }

            $stmt = $db->prepare("UPDATE rappresentor_payments SET status = :st, amount_paid = :am, payment_method = :pm, paid_at = :pa, notes = :nt WHERE id = :id");
            $stmt->execute([
                'st' => $status,
                'am' => $amount,
                'pm' => $method,
                'pa' => $paidAt,
                'nt' => $notes,
                'id' => $paymentId
            ]);

            Helper::setFlash('success', 'Pagamento aggiornato. I totali cassa sono stati aggiornati automaticamente!');
            header("Location: cassa.php?campaign_id={$campaignId}");
            exit;
        }

        // 3b. Elimina Movimento Manuale dal Registro Cassa
        if ($_POST['action'] === 'delete_transaction') {
            $txId = (int)($_POST['transaction_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM rappresentor_cashbox_transactions WHERE id = :id AND class_id = :cid");
            $stmt->execute(['id' => $txId, 'cid' => $activeClassId]);
            Helper::setFlash('info', 'Movimento cassa eliminato.');
            header('Location: cassa.php');
            exit;
        }

        // 4. Chiusura / Riapertura Campagna
        if ($_POST['action'] === 'toggle_campaign_status') {
            $campaignId = (int)($_POST['campaign_id'] ?? 0);
            $isClosed = (int)($_POST['is_closed'] ?? 0);
            $stmt = $db->prepare("UPDATE rappresentor_campaigns SET is_closed = :cl WHERE id = :id AND class_id = :cid");
            $stmt->execute(['cl' => $isClosed, 'id' => $campaignId, 'cid' => $activeClassId]);
            Helper::setFlash('info', 'Stato raccolta aggiornato.');
            header("Location: cassa.php?campaign_id={$campaignId}");
            exit;
        }

        // 5. Eliminazione Definitiva Raccolta Quote Archiviata
        if ($_POST['action'] === 'delete_campaign') {
            $campaignId = (int)($_POST['campaign_id'] ?? 0);
            if ($campaignId > 0 && $activeClassId) {
                // Rimuove eventuale scadenza sincronizzata a calendario
                $evTag = "[Raccolta #{$campaignId}]";
                $stmtEv = $db->prepare("DELETE FROM rappresentor_events WHERE class_id = :cid AND description LIKE :tag");
                $stmtEv->execute(['cid' => $activeClassId, 'tag' => "%{$evTag}%"]);

                // Elimina la campagna (i versamenti collegati cadono via CASCADE)
                $stmt = $db->prepare("DELETE FROM rappresentor_campaigns WHERE id = :id AND class_id = :cid");
                $stmt->execute(['id' => $campaignId, 'cid' => $activeClassId]);

                Helper::setFlash('success', 'Raccolta quote archiviata eliminata definitivamente.');
                header('Location: cassa.php');
                exit;
            }
        }
    }
}

// Dati Cassa e Campagne
$totalEntrate = 0.0;
$totalUscite = 0.0;
$entrateQuote = 0.0;
$entrateManuali = 0.0;
$saldoCassa = 0.0;
$campaigns = [];
$selectedCampaignId = 0;
$selectedCampaign = null;
$campaignPayments = [];
$transactions = [];
$rendicontoWa = '';
$quoteWa = '';

if ($activeClassId) {
    // 1. Calcolo Saldo Cassa Totale (aggiornato automaticamente in base a chi ha versato le quote + movimenti manuali)
    $cashSummary = Helper::getClassCashSummary($db, $activeClassId);
    $entrateQuote = $cashSummary['entrate_quote'];
    $entrateManuali = $cashSummary['entrate_manuali'];
    $totalEntrate = $cashSummary['total_entrate'];
    $totalUscite = $cashSummary['total_uscite'];
    $saldoCassa = $cashSummary['saldo'];

    // 2. Carica Campagne Quote
    $stmt = $db->prepare("SELECT c.*, 
        (SELECT COUNT(*) FROM rappresentor_payments p WHERE p.campaign_id = c.id AND p.status = 'pagato') as pagati_count,
        (SELECT COUNT(*) FROM rappresentor_payments p WHERE p.campaign_id = c.id) as totale_alunni,
        (SELECT COALESCE(SUM(amount_paid), 0) FROM rappresentor_payments p WHERE p.campaign_id = c.id) as totale_raccolto
        FROM rappresentor_campaigns c 
        WHERE c.class_id = :cid 
        ORDER BY c.is_closed ASC, c.id DESC");
    $stmt->execute(['cid' => $activeClassId]);
    $campaigns = $stmt->fetchAll();

    // Suddivide campagne attive e archiviate/chiuse
    $activeCampaigns = [];
    $archivedCampaigns = [];
    foreach ($campaigns as $camp) {
        if (!empty($camp['is_closed'])) {
            $archivedCampaigns[] = $camp;
        } else {
            $activeCampaigns[] = $camp;
        }
    }

    // Campagna selezionata (privilegia la prima attiva se non indicata esplicitamente in URL)
    if (isset($_GET['campaign_id'])) {
        $selectedCampaignId = (int)$_GET['campaign_id'];
    } else {
        $selectedCampaignId = !empty($activeCampaigns) ? (int)$activeCampaigns[0]['id'] : (!empty($archivedCampaigns) ? (int)$archivedCampaigns[0]['id'] : 0);
    }

    if ($selectedCampaignId > 0) {
        foreach ($campaigns as $camp) {
            if ((int)$camp['id'] === $selectedCampaignId) {
                $selectedCampaign = $camp;
                break;
            }
        }

        if ($selectedCampaign) {
            // Assicura che tutti gli alunni attivi abbiano una riga di pagamento per questa campagna
            $db->prepare("INSERT IGNORE INTO rappresentor_payments (campaign_id, student_id, status, amount_paid)
                SELECT :cmp, id, 'non_pagato', 0.00 
                FROM rappresentor_students 
                WHERE class_id = :cid AND is_active = 1")
                ->execute(['cmp' => $selectedCampaignId, 'cid' => $activeClassId]);

            // Carica elenco alunni e relativo pagamento per la campagna
            $stmt = $db->prepare("SELECT p.*, s.first_name, s.last_name 
                FROM rappresentor_payments p 
                JOIN rappresentor_students s ON p.student_id = s.id 
                WHERE p.campaign_id = :cmp AND s.is_active = 1 
                ORDER BY s.last_name ASC, s.first_name ASC");
            $stmt->execute(['cmp' => $selectedCampaignId]);
            $campaignPayments = $stmt->fetchAll();
        }
    }

    // 3. Movimenti Registro Cassa Recenti
    $stmt = $db->prepare("SELECT ct.*, u.name as user_name 
        FROM rappresentor_cashbox_transactions ct 
        LEFT JOIN rappresentor_users u ON ct.created_by = u.id 
        WHERE ct.class_id = :cid 
        ORDER BY ct.transaction_date DESC, ct.id DESC LIMIT 50");
    $stmt->execute(['cid' => $activeClassId]);
    $transactions = $stmt->fetchAll();

    // 4. Riepilogo WhatsApp per TUTTE le campagne quote attive della classe selezionata
    $campSummary = Helper::getActiveCampaignsSummary($db, $activeClassId);
    $activeCampaignsList = $campSummary['campaigns'];

    $quoteWa = "📢 *RACCOLTE QUOTE ATTIVE - " . ($activeClass['name'] ?? 'Classe') . "*\n";
    $quoteWa .= "🏫 " . ($activeClass['school_name'] ?? 'Scuola') . " - A.S. " . ($activeClass['school_year'] ?? '') . "\n";
    $quoteWa .= "📅 Aggiornato al: " . date('d/m/Y') . "\n\n";

    if (empty($activeCampaignsList)) {
        $quoteWa .= "Al momento non risultano raccolte quote attive per la nostra classe.\n";
    } else {
        $quoteWa .= "👋 Ciao a tutti i genitori!\nVi riepiloghiamo le raccolte quote attualmente aperte per la nostra classe:\n\n";
        $quoteWa .= $campSummary['formatted_block'] . "\n\n";
        $quoteWa .= "Modalità di versamento disponibili:\n";
        $quoteWa .= "- In contanti (in bustina con nome dell'alunno)\n";
        $quoteWa .= "- Satispay / Bonifico (contattatemi in privato per i dettagli)\n\n";
        $quoteWa .= "Grazie a tutti per la collaborazione!\nI Rappresentanti di Classe";
    }

    // 5. Costruisci testo rendiconto cassa WhatsApp
    $rendicontoWa = "📊 *RENDICONTO CASSA DI CLASSE*\n";
    $rendicontoWa .= "🏫 " . ($activeClass['name'] ?? 'Classe') . " - A.S. " . ($activeClass['school_year'] ?? '') . "\n";
    $rendicontoWa .= "📅 Aggiornato al: " . date('d/m/Y') . "\n\n";
    $rendicontoWa .= "💰 *SALDO ATTUALE:* " . Helper::formatMoney($saldoCassa) . "\n";
    $rendicontoWa .= "📥 Totale Entrate: " . Helper::formatMoney($totalEntrate);
    if ($entrateQuote > 0 && $entrateManuali > 0) {
        $rendicontoWa .= " (Quote alunni: " . Helper::formatMoney($entrateQuote) . " | Altre entrate: " . Helper::formatMoney($entrateManuali) . ")";
    } elseif ($entrateQuote > 0) {
        $rendicontoWa .= " (Quote versate dagli alunni)";
    }
    $rendicontoWa .= "\n📤 Totale Uscite: " . Helper::formatMoney($totalUscite) . "\n\n";
    $rendicontoWa .= "*Ultime uscite registrate:*\n";

    $recentUsciteCount = 0;
    foreach ($transactions as $t) {
        if ($t['type'] === 'uscita' && $recentUsciteCount < 5) {
            $rendicontoWa .= "- " . Helper::formatDate($t['transaction_date']) . ": " . Helper::e($t['description']) . " (" . Helper::formatMoney($t['amount']) . ")\n";
            $recentUsciteCount++;
        }
    }
    if ($recentUsciteCount === 0) {
        $rendicontoWa .= "Nessuna spesa recente.\n";
    }
    $rendicontoWa .= "\nI giustificativi e scontrini sono sempre disponibili per consultazione. Grazie a tutti i genitori!";
}

$pageTitle = 'Cassa & Quote';
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
      <span>💰</span>
      <span>Cassa & Quote di Classe</span>
    </h1>
    <p class="page-subtitle">Gestione trasparente del fondo cassa, quote alunni, entrate e scontrini.</p>
  </div>
  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <button class="btn btn-primary" data-modal-target="modalAddCampaign">+ Nuova Raccolta Quote</button>
    <button class="btn btn-secondary" data-modal-target="modalAddTransaction">+ Registra Movimento</button>
    <button class="btn btn-whatsapp" data-modal-target="modalQuoteWa">📱 Avviso Quote WA</button>
    <button class="btn btn-whatsapp" data-modal-target="modalRendicontoWa">📱 Copia Rendiconto WA</button>
  </div>
</div>

<!-- Schede Statistiche Cassa -->
<div class="grid-3">
  <div class="stat-card">
    <div class="stat-icon" style="background: <?= $saldoCassa >= 0 ? 'var(--success-bg)' : 'var(--danger-bg)' ?>; color: <?= $saldoCassa >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
      <?= $saldoCassa >= 0 ? '💵' : '⚠️' ?>
    </div>
    <div class="stat-info">
      <div class="stat-label">Saldo Cassa Disponibile</div>
      <div class="stat-value" style="color: <?= $saldoCassa >= 0 ? 'var(--success-text)' : 'var(--danger-text)' ?>;">
        <?= Helper::formatMoney($saldoCassa) ?>
      </div>
      <div class="stat-hint">Fondo comune classe</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background: var(--info-bg); color: var(--info);">📥</div>
    <div class="stat-info">
      <div class="stat-label">Totale Entrate</div>
      <div class="stat-value" style="color: var(--success-text);"><?= Helper::formatMoney($totalEntrate) ?></div>
      <div class="stat-hint">
        Quote: <?= Helper::formatMoney($entrateQuote) ?> • Manuali: <?= Helper::formatMoney($entrateManuali) ?>
      </div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background: var(--warning-bg); color: var(--warning);">📤</div>
    <div class="stat-info">
      <div class="stat-label">Totale Uscite</div>
      <div class="stat-value" style="color: var(--danger-text);"><?= Helper::formatMoney($totalUscite) ?></div>
      <div class="stat-hint">Movimenti registrati nel libro cassa</div>
    </div>
  </div>
</div>

<!-- Sezione Raccolte Quote Attive e Dettaglio Pagamenti -->
<div class="card">
  <div class="card-header">
    <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
      <h2 class="card-title">🎯 Monitoraggio Versamenti Quote</h2>
      <?php if (!empty($campaigns)): ?>
        <select onchange="window.location.href='cassa.php?campaign_id='+this.value" class="form-control" style="width: auto; padding: 4px 8px; font-size: 0.88rem;">
          <?php if (!empty($activeCampaigns)): ?>
            <optgroup label="🟢 Raccolte Quote Attive">
              <?php foreach ($activeCampaigns as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === $selectedCampaignId) ? 'selected' : '' ?>>
                  <?= Helper::e($c['title']) ?> (Quota: <?= Helper::formatMoney($c['quota_per_student']) ?>)
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endif; ?>
          <?php if (!empty($archivedCampaigns)): ?>
            <optgroup label="🔒 Raccolte Chiuse / Archiviate">
              <?php foreach ($archivedCampaigns as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === $selectedCampaignId) ? 'selected' : '' ?>>
                  🔒 [Chiusa] <?= Helper::e($c['title']) ?> (Quota: <?= Helper::formatMoney($c['quota_per_student']) ?>)
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endif; ?>
        </select>
      <?php endif; ?>
    </div>

    <?php if ($selectedCampaign): ?>
      <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <button type="button" class="btn btn-sm btn-primary" onclick="openEditCampaignModal()">
          ✏️ Modifica Raccolta
        </button>
        <form method="POST" action="cassa.php" style="display: inline;">
          <?= Helper::csrfField() ?>
          <input type="hidden" name="action" value="toggle_campaign_status">
          <input type="hidden" name="campaign_id" value="<?= (int)$selectedCampaign['id'] ?>">
          <input type="hidden" name="is_closed" value="<?= $selectedCampaign['is_closed'] ? '0' : '1' ?>">
          <button type="submit" class="btn btn-sm btn-secondary">
            <?= $selectedCampaign['is_closed'] ? '🔓 Riapri Raccolta' : '🔒 Chiudi Raccolta' ?>
          </button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <div class="card-body">
    <?php if (!$selectedCampaign): ?>
      <p style="color: var(--text-muted); margin: 0; padding: 20px 0; text-align: center;">
        Nessuna raccolta quote creata. Clicca su "+ Nuova Raccolta Quote" per avviare la raccolta (es. regalo maestre, cancelleria, gita).
      </p>
    <?php else: 
      $totAlunni = (int)$selectedCampaign['totale_alunni'];
      $pagCount = (int)$selectedCampaign['pagati_count'];
      $pct = $totAlunni > 0 ? round(($pagCount / $totAlunni) * 100) : 0;
    ?>
      <?php if (!empty($selectedCampaign['is_closed'])): ?>
        <div class="alert alert-secondary" style="margin-bottom: 14px; display: flex; align-items: center; gap: 10px;">
          <span style="font-size: 1.2rem;">🔒</span>
          <div>
            <strong>Raccolta Chiusa & Archiviata:</strong> I versamenti e lo stato delle quote sono conservati per storico e rendicontazione. Puoi riaprirla in qualsiasi momento con il tasto "🔓 Riapri Raccolta".
          </div>
        </div>
      <?php endif; ?>
      <div style="background: var(--bg-surface-alt); border-radius: var(--radius-md); padding: 16px; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
          <div>
            <h3 style="margin: 0; font-size: 1.15rem;"><?= Helper::e($selectedCampaign['title']) ?></h3>
            <div style="font-size: 0.88rem; color: var(--text-secondary); margin-top: 4px;">
              Quota richiesta per alunno: <strong><?= Helper::formatMoney($selectedCampaign['quota_per_student']) ?></strong>
              <?php if ($selectedCampaign['deadline']): ?>
                • Scadenza entro: <strong><?= Helper::formatDate($selectedCampaign['deadline']) ?></strong>
              <?php endif; ?>
            </div>
            <?php if (!empty($selectedCampaign['description'])): ?>
              <p style="margin: 6px 0 0; font-size: 0.85rem; color: var(--text-secondary);">
                <?= Helper::e($selectedCampaign['description']) ?>
              </p>
            <?php endif; ?>
          </div>

          <div style="text-align: right;">
            <div style="font-size: 1.25rem; font-weight: 700; color: var(--primary);">
              <?= Helper::formatMoney($selectedCampaign['totale_raccolto']) ?> raccolti
            </div>
            <div style="font-size: 0.85rem; color: var(--text-secondary);">
              <strong><?= $pagCount ?></strong> su <strong><?= $totAlunni ?></strong> alunni hanno versato (<?= $pct ?>%)
            </div>
          </div>
        </div>

        <!-- Barra di progresso -->
        <div style="background: #e2e8f0; height: 10px; border-radius: 99px; overflow: hidden; margin-top: 14px;">
          <div style="background: var(--primary); height: 100%; width: <?= $pct ?>%;"></div>
        </div>
      </div>

      <!-- Tabella Pagamenti Alunno per Alunno -->
      <div class="desktop-table-view">
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Alunno</th>
                <th>Stato Pagamento</th>
                <th>Importo Versato</th>
                <th>Modalità</th>
                <th>Data Versamento</th>
                <th>Note</th>
                <th style="text-align: right;">Azioni</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($campaignPayments as $pay): ?>
                <tr>
                  <td>
                    <strong><?= Helper::e($pay['last_name']) ?> <?= Helper::e($pay['first_name']) ?></strong>
                  </td>
                  <td>
                    <?php if ($pay['status'] === 'pagato'): ?>
                      <span class="badge badge-success">✅ Pagato</span>
                    <?php elseif ($pay['status'] === 'parziale'): ?>
                      <span class="badge badge-info">⚖️ Parziale</span>
                    <?php elseif ($pay['status'] === 'esonerato'): ?>
                      <span class="badge badge-secondary">⚪ Esonerato</span>
                    <?php else: ?>
                      <span class="badge badge-danger">⏳ Non pagato</span>
                    <?php endif; ?>
                  </td>
                  <td><?= Helper::formatMoney($pay['amount_paid']) ?></td>
                  <td style="text-transform: capitalize;"><?= Helper::e($pay['payment_method']) ?></td>
                  <td><?= Helper::formatDate($pay['paid_at']) ?></td>
                  <td style="font-size: 0.82rem; color: var(--text-secondary);"><?= Helper::e($pay['notes'] ?? '-') ?></td>
                  <td style="text-align: right;">
                    <button class="btn btn-sm <?= $pay['status'] === 'pagato' ? 'btn-secondary' : 'btn-success' ?>" 
                            onclick="openEditPaymentModal(<?= (int)$pay['id'] ?>, '<?= Helper::e(addslashes($pay['last_name'] . ' ' . $pay['first_name'])) ?>', '<?= $pay['status'] ?>', <?= (float)$pay['amount_paid'] ?>, '<?= $pay['payment_method'] ?>', '<?= Helper::e(addslashes($pay['notes'] ?? '')) ?>', <?= (float)$selectedCampaign['quota_per_student'] ?>)">
                      <?= $pay['status'] === 'pagato' ? 'Modifica' : '✓ Segna Pagato' ?>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Vista Schede Pagamenti Mobile (Zero Scorrimento Orizzontale) -->
      <div class="mobile-cards-view">
        <?php foreach ($campaignPayments as $pay): ?>
          <div class="payment-mobile-card">
            <div class="payment-card-header">
              <span class="payment-student-name"><?= Helper::e($pay['last_name']) ?> <?= Helper::e($pay['first_name']) ?></span>
              <?php if ($pay['status'] === 'pagato'): ?>
                <span class="badge badge-success">✅ Pagato</span>
              <?php elseif ($pay['status'] === 'parziale'): ?>
                <span class="badge badge-info">⚖️ Parziale</span>
              <?php elseif ($pay['status'] === 'esonerato'): ?>
                <span class="badge badge-secondary">⚪ Esonerato</span>
              <?php else: ?>
                <span class="badge badge-danger">⏳ Non pagato</span>
              <?php endif; ?>
            </div>

            <div class="payment-card-body">
              <div class="payment-meta">
                <span>Richiesto: <strong><?= Helper::formatMoney($selectedCampaign['quota_per_student']) ?></strong></span>
                <?php if ($pay['status'] === 'pagato' || $pay['status'] === 'parziale'): ?>
                  <span><?= Helper::e(ucfirst($pay['payment_method'] ?: 'Contanti')) ?> • <?= Helper::formatDate($pay['paid_at']) ?></span>
                <?php endif; ?>
              </div>
              <div class="payment-amount" style="color: <?= ($pay['status'] === 'pagato') ? 'var(--success-text)' : (($pay['status'] === 'parziale') ? 'var(--primary)' : 'var(--text-secondary)') ?>;">
                Versato: <?= Helper::formatMoney($pay['amount_paid']) ?>
              </div>
            </div>

            <div class="payment-card-footer">
              <div class="payment-notes">
                <?= !empty($pay['notes']) ? '📝 ' . Helper::e($pay['notes']) : '<span style="color:var(--text-muted); font-size:0.75rem;">Nessuna nota</span>' ?>
              </div>
              <button class="btn btn-sm <?= $pay['status'] === 'pagato' ? 'btn-secondary' : 'btn-success' ?>" 
                      onclick="openEditPaymentModal(<?= (int)$pay['id'] ?>, '<?= Helper::e(addslashes($pay['last_name'] . ' ' . $pay['first_name'])) ?>', '<?= $pay['status'] ?>', <?= (float)$pay['amount_paid'] ?>, '<?= $pay['payment_method'] ?>', '<?= Helper::e(addslashes($pay['notes'] ?? '')) ?>', <?= (float)$selectedCampaign['quota_per_student'] ?>)">
                <?= $pay['status'] === 'pagato' ? '✏️ Modifica' : '✓ Segna Pagato' ?>
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Libro Cassa: Registro Entrate e Uscite -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">📖 Registro Entrate & Uscite Cassa</h2>
    <span style="font-size: 0.85rem; color: var(--text-secondary);"><?= count($transactions) ?> registrazioni</span>
  </div>
  <div style="padding: 10px 16px; background: #f8fafc; border-bottom: 1px solid var(--border-color); font-size: 0.83rem; color: var(--text-secondary); display: flex; align-items: center; gap: 8px;">
    <span>💡</span>
    <span>I versamenti quote degli alunni confluiscono <strong>automaticamente</strong> nel Totale Entrate e Saldo Cassa. Qui puoi registrare e consultare i movimenti manuali della classe (scontrini spese, acquisti o entrate straordinarie).</span>
  </div>
  <div class="card-body" style="padding: 0;">
    <!-- Vista Tabella Desktop -->
    <div class="desktop-table-view">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Data</th>
              <th>Tipo</th>
              <th>Categoria</th>
              <th>Descrizione & Note</th>
              <th>Scontrino / Riferimento</th>
              <th style="text-align: right;">Importo</th>
              <th style="text-align: right; width: 60px;">Azione</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($transactions)): ?>
              <tr>
                <td colspan="7" style="text-align: center; padding: 32px; color: var(--text-muted);">
                  Nessun movimento manuale registrato. Clicca su "+ Registra Movimento" per inserire un'uscita (es. spesa) o un'entrata straordinaria.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($transactions as $t): 
                $isEntrata = $t['type'] === 'entrata';
              ?>
                <tr>
                  <td><?= Helper::formatDate($t['transaction_date']) ?></td>
                  <td>
                    <span class="badge <?= $isEntrata ? 'badge-success' : 'badge-danger' ?>">
                      <?= $isEntrata ? '⬇ Entrata' : '⬆ Uscita' ?>
                    </span>
                  </td>
                  <td><strong><?= Helper::e($t['category']) ?></strong></td>
                  <td><?= Helper::e($t['description']) ?></td>
                  <td>
                    <?php if (!empty($t['receipt_ref'])): ?>
                      <span class="badge badge-secondary">🧾 <?= Helper::e($t['receipt_ref']) ?></span>
                    <?php else: ?>
                      <span style="color: var(--text-muted); font-size: 0.8rem;">-</span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align: right; font-weight: 700; color: <?= $isEntrata ? 'var(--success-text)' : 'var(--danger-text)' ?>;">
                    <?= $isEntrata ? '+' : '-' ?> <?= Helper::formatMoney($t['amount']) ?>
                  </td>
                  <td style="text-align: right;">
                    <form method="POST" action="cassa.php" style="display: inline;" data-confirm="Sei sicuro di voler eliminare questo movimento dal registro cassa?" data-confirm-title="Elimina Movimento Cassa" data-confirm-type="danger" data-confirm-btn="Elimina">
                      <?= Helper::csrfField() ?>
                      <input type="hidden" name="action" value="delete_transaction">
                      <input type="hidden" name="transaction_id" value="<?= (int)$t['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-secondary" style="padding: 2px 6px; font-size: 0.75rem; color: var(--danger);" title="Elimina movimento">🗑️</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Vista Schede Movimenti Mobile (Zero Scorrimento Orizzontale) -->
    <div class="mobile-cards-view">
      <?php if (empty($transactions)): ?>
        <div style="text-align: center; padding: 24px 16px; color: var(--text-muted); font-size: 0.85rem;">
          Nessun movimento manuale registrato. Clicca su "+ Registra Movimento" per inserire un'uscita o un'entrata.
        </div>
      <?php else: ?>
        <?php foreach ($transactions as $t): 
          $isEntrata = $t['type'] === 'entrata';
        ?>
          <div class="tx-mobile-card">
            <div class="tx-card-header">
              <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <span class="badge <?= $isEntrata ? 'badge-success' : 'badge-danger' ?>" style="font-size: 0.74rem;">
                  <?= $isEntrata ? '⬇ Entrata' : '⬆ Uscita' ?>
                </span>
                <span class="tx-category-badge"><?= Helper::e($t['category']) ?></span>
              </div>
              <div class="tx-amount <?= $isEntrata ? 'entrata' : 'uscita' ?>">
                <?= $isEntrata ? '+' : '-' ?> <?= Helper::formatMoney($t['amount']) ?>
              </div>
            </div>

            <?php if (!empty($t['description'])): ?>
              <div class="tx-card-desc"><?= Helper::e($t['description']) ?></div>
            <?php endif; ?>

            <div class="tx-card-footer">
              <div style="display: flex; align-items: center; gap: 8px;">
                <span>📅 <?= Helper::formatDate($t['transaction_date']) ?></span>
                <?php if (!empty($t['receipt_ref'])): ?>
                  <span class="badge badge-secondary" style="font-size: 0.7rem;">🧾 <?= Helper::e($t['receipt_ref']) ?></span>
                <?php endif; ?>
              </div>
              <form method="POST" action="cassa.php" style="display: inline; margin: 0;" data-confirm="Sei sicuro di voler eliminare questo movimento dal registro cassa?" data-confirm-title="Elimina Movimento Cassa" data-confirm-type="danger" data-confirm-btn="Elimina">
                <?= Helper::csrfField() ?>
                <input type="hidden" name="action" value="delete_transaction">
                <input type="hidden" name="transaction_id" value="<?= (int)$t['id'] ?>">
                <button type="submit" class="btn btn-sm btn-secondary" style="padding: 3px 8px; font-size: 0.76rem; color: var(--danger);" title="Elimina movimento">🗑️ Elimina</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Sezione Raccolte Quote Chiuse & Archiviate in fondo (Accordion Chiuso di Default) -->
<?php if (!empty($archivedCampaigns)): ?>
  <details class="card accordion-card" style="margin-top: 24px;">
    <summary class="card-header">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span class="accordion-icon">▶</span>
        <h2 class="card-title" style="margin: 0;">📁 Raccolte Quote Chiuse & Archiviate</h2>
      </div>
      <div style="display: flex; align-items: center; gap: 8px;">
        <span class="badge badge-secondary"><?= count($archivedCampaigns) ?> archiviate</span>
        <span style="font-size: 0.8rem; color: var(--text-muted);">(clicca per visualizzare)</span>
      </div>
    </summary>
    <div class="card-body">
      <div style="display: flex; flex-direction: column; gap: 10px;">
        <?php foreach ($archivedCampaigns as $ac): 
          $acPct = $ac['totale_alunni'] > 0 ? round(($ac['pagati_count'] / $ac['totale_alunni']) * 100) : 0;
          $isCurrent = (int)$ac['id'] === $selectedCampaignId;
        ?>
          <div style="background: var(--bg-surface-alt); border: 1px solid <?= $isCurrent ? 'var(--primary)' : 'var(--border-color)' ?>; border-radius: var(--radius-md); padding: 12px 14px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div>
              <div style="font-weight: 700; font-size: 0.98rem; display: flex; align-items: center; gap: 6px;">
                <span>🔒</span>
                <span><?= Helper::e($ac['title']) ?></span>
                <span class="badge badge-secondary" style="font-size: 0.72rem;">Chiusa</span>
                <?php if ($isCurrent): ?>
                  <span class="badge badge-primary" style="font-size: 0.72rem;">In consultazione</span>
                <?php endif; ?>
              </div>
              <div style="font-size: 0.82rem; color: var(--text-secondary); margin-top: 4px;">
                Quota: <strong><?= Helper::formatMoney($ac['quota_per_student']) ?></strong> • 
                Raccolti: <strong><?= Helper::formatMoney($ac['totale_raccolto']) ?></strong> (<?= $ac['pagati_count'] ?>/<?= $ac['totale_alunni'] ?> alunni - <?= $acPct ?>%)
                <?php if ($ac['deadline']): ?>
                  • Conclusa il: <?= Helper::formatDate($ac['deadline']) ?>
                <?php endif; ?>
              </div>
            </div>

            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
              <a href="cassa.php?campaign_id=<?= (int)$ac['id'] ?>" class="btn btn-sm btn-secondary" title="Visualizza versamenti e quote">
                👁️ Consulta Quote
              </a>
              <form method="POST" action="cassa.php" style="display: inline;">
                <?= Helper::csrfField() ?>
                <input type="hidden" name="action" value="toggle_campaign_status">
                <input type="hidden" name="campaign_id" value="<?= (int)$ac['id'] ?>">
                <input type="hidden" name="is_closed" value="0">
                <button type="submit" class="btn btn-sm btn-primary" title="Riapri questa raccolta">
                  🔓 Riapri
                </button>
              </form>
              <form method="POST" action="cassa.php" data-confirm="Sei sicuro di voler eliminare DEFINITIVAMENTE questa raccolta quote archiviata? Verranno eliminati anche tutti i versamenti registrati. L'operazione non è reversibile." data-confirm-title="Eliminazione Definitiva Raccolta" data-confirm-type="danger" data-confirm-btn="Elimina Definitivamente" style="display: inline;">
                <?= Helper::csrfField() ?>
                <input type="hidden" name="action" value="delete_campaign">
                <input type="hidden" name="campaign_id" value="<?= (int)$ac['id'] ?>">
                <button type="submit" class="btn btn-sm btn-secondary" style="color: var(--danger);" title="Elimina definitivamente questa raccolta archiviata">
                  🗑️ Elimina
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </details>
<?php endif; ?>
<div class="modal-backdrop" id="modalAddCampaign">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Nuova Raccolta Quote</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="cassa.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="add_campaign">

      <div class="modal-body">
        <div class="form-group">
          <label for="camp_title">Titolo Raccolta *</label>
          <input type="text" id="camp_title" name="title" class="form-control" placeholder="es. Regalo Maestre Natale, Cassa Cancelleria..." required>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="quota_per_student">Quota richiesta per alunno (€) *</label>
            <input type="number" step="0.50" min="0.50" id="quota_per_student" name="quota_per_student" class="form-control" placeholder="es. 10.00" required>
          </div>
          <div class="form-group">
            <label for="camp_deadline">Termine ultimo versamento</label>
            <input type="date" id="camp_deadline" name="deadline" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label for="camp_desc">Note o spiegazione per i genitori</label>
          <textarea id="camp_desc" name="description" class="form-control" rows="2" placeholder="Cosa verrà acquistato o modalità di consegna..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Avvia Raccolta</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Modifica Raccolta Quote -->
<div class="modal-backdrop" id="modalEditCampaign">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Modifica Raccolta Quote</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="cassa.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="edit_campaign">
      <input type="hidden" name="campaign_id" id="editCampaignId" value="<?= (int)($selectedCampaign['id'] ?? 0) ?>">

      <div class="modal-body">
        <div class="form-group">
          <label for="edit_camp_title">Titolo Raccolta *</label>
          <input type="text" id="edit_camp_title" name="title" class="form-control" required>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="edit_quota_per_student">Quota richiesta per alunno (€) *</label>
            <input type="number" step="0.50" min="0.50" id="edit_quota_per_student" name="quota_per_student" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="edit_camp_deadline">Termine ultimo versamento</label>
            <input type="date" id="edit_camp_deadline" name="deadline" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label for="edit_camp_desc">Note o spiegazione per i genitori</label>
          <textarea id="edit_camp_desc" name="description" class="form-control" rows="3"></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Salva Modifiche</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Registra Movimento Cassa -->
<div class="modal-backdrop" id="modalAddTransaction">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Registra Movimento nel Libro Cassa</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="cassa.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="add_transaction">

      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label for="tx_type">Tipo Movimento *</label>
            <select id="tx_type" name="type" class="form-control" required>
              <option value="uscita">⬆ Spesa / Uscita (es. acquisto regalo, scontrino)</option>
              <option value="entrata">⬇ Entrata (es. versamento quote, donazione)</option>
            </select>
          </div>
          <div class="form-group">
            <label for="tx_amount">Importo (€) *</label>
            <input type="number" step="0.01" min="0.01" id="tx_amount" name="amount" class="form-control" placeholder="es. 35.50" required>
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="tx_category">Categoria *</label>
            <select id="tx_category" name="category" class="form-control" required>
              <option value="Quote Alunni">Quote Alunni</option>
              <option value="Regalo Maestre/Educatrici">Regalo Maestre/Educatrici</option>
              <option value="Materiale Cancelleria">Materiale Cancelleria</option>
              <option value="Gita / Biglietti">Gita / Biglietti</option>
              <option value="Festa di Classe">Festa di Classe</option>
              <option value="Altro">Altro</option>
            </select>
          </div>
          <div class="form-group">
            <label for="tx_date">Data Movimento</label>
            <input type="date" id="tx_date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="tx_desc">Descrizione *</label>
          <input type="text" id="tx_desc" name="description" class="form-control" placeholder="es. Acquisto carta per fotocopie e pennarelli colorati" required>
        </div>

        <div class="form-group">
          <label for="tx_receipt">Riferimento Scontrino / Ricevuta</label>
          <input type="text" id="tx_receipt" name="receipt_ref" class="form-control" placeholder="es. Scontrino n. 45 Cartoleria ABC del 12/10">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Registra Movimento</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Modifica Pagamento Alunno -->
<div class="modal-backdrop" id="modalEditPayment">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Aggiorna Quota Alunno</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="cassa.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="update_single_payment">
      <input type="hidden" name="payment_id" id="editPaymentId" value="">
      <input type="hidden" name="campaign_id" value="<?= (int)$selectedCampaignId ?>">

      <div class="modal-body">
        <p style="font-size: 0.95rem; margin-top: 0;">Alunno: <strong id="editPaymentStudentName"></strong></p>

        <div class="form-grid">
          <div class="form-group">
            <label for="edit_status">Stato Versamento *</label>
            <select id="edit_status" name="status" class="form-control" onchange="handleStatusChange(this.value)">
              <option value="pagato">✅ Pagato</option>
              <option value="non_pagato">⏳ Non pagato</option>
              <option value="parziale">⚖️ Pagamento Parziale</option>
              <option value="esonerato">⚪ Esonerato</option>
            </select>
          </div>

          <div class="form-group">
            <label for="edit_amount">Importo Versato (€) *</label>
            <input type="number" step="0.50" min="0" id="edit_amount" name="amount_paid" class="form-control" required>
          </div>
        </div>

        <div class="form-group">
          <label for="edit_method">Modalità di Pagamento</label>
          <select id="edit_method" name="payment_method" class="form-control">
            <option value="contanti">Contanti</option>
            <option value="bonifico">Bonifico Bancario</option>
            <option value="satispay">Satispay</option>
            <option value="paypal">PayPal</option>
            <option value="altro">Altro</option>
          </select>
        </div>

        <div class="form-group">
          <label for="edit_notes">Note</label>
          <input type="text" id="edit_notes" name="notes" class="form-control" placeholder="es. Consegnati alla mamma di Marco">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Salva Pagamento</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Avviso Quote WhatsApp -->
<div class="modal-backdrop" id="modalQuoteWa">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Avviso Quote Attive per WhatsApp</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <p style="font-size: 0.88rem; color: var(--text-secondary); margin-top: 0; margin-bottom: 10px;">
        Messaggio pronto da inviare nella chat dei genitori, comprensivo di <strong>tutte le raccolte quote aperte</strong> per la classe e del totale complessivo ad alunno:
      </p>

      <!-- Selezione Modalità di Pagamento tramite Flag -->
      <div style="background: var(--bg-surface-alt); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 10px 12px; margin-bottom: 12px;">
        <div style="font-weight: 700; font-size: 0.82rem; margin-bottom: 6px; color: var(--text-primary);">
          💳 Modalità di versamento da includere nel messaggio:
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 10px; font-size: 0.82rem;">
          <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
            <input type="checkbox" class="quote-wa-method" value="contanti" checked onchange="updateQuoteWaText()"> 💶 Contanti
          </label>
          <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
            <input type="checkbox" class="quote-wa-method" value="bonifico" checked onchange="updateQuoteWaText()"> 🏦 Bonifico
          </label>
          <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
            <input type="checkbox" class="quote-wa-method" value="satispay" checked onchange="updateQuoteWaText()"> 📱 Satispay
          </label>
          <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
            <input type="checkbox" class="quote-wa-method" value="paypal" onchange="updateQuoteWaText()"> 💳 PayPal
          </label>
          <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
            <input type="checkbox" class="quote-wa-method" value="pagopa" onchange="updateQuoteWaText()"> 🏛️ PagoPA
          </label>
        </div>
      </div>

      <textarea id="textQuoteWa" class="form-control" rows="10"><?= Helper::e($quoteWa) ?></textarea>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-modal-close>Chiudi</button>
      <button type="button" class="btn btn-whatsapp" data-copy-target="#textQuoteWa" data-copy-success="Avviso quote copiato! Incollalo nella chat WhatsApp dei genitori.">
        📋 Copia per WhatsApp
      </button>
    </div>
  </div>
</div>

<!-- Modal Rendiconto WhatsApp -->
<div class="modal-backdrop" id="modalRendicontoWa">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Rendiconto Cassa per WhatsApp</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <p style="font-size: 0.88rem; color: var(--text-secondary); margin-top: 0;">
        Testo trasparente con emoji già pronto da copiare e incollare nella chat WhatsApp di classe:
      </p>
      <textarea id="textRendicontoWa" class="form-control" rows="10" readonly><?= Helper::e($rendicontoWa) ?></textarea>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-modal-close>Chiudi</button>
      <button type="button" class="btn btn-whatsapp" data-copy-target="#textRendicontoWa" data-copy-success="Rendiconto copiato! Incollalo nella chat dei genitori.">
        📋 Copia per WhatsApp
      </button>
    </div>
  </div>
</div>

<script>
let defaultCampaignQuota = 0;

function openEditCampaignModal() {
  <?php if ($selectedCampaign): ?>
  document.getElementById('editCampaignId').value = <?= (int)$selectedCampaign['id'] ?>;
  document.getElementById('edit_camp_title').value = <?= json_encode($selectedCampaign['title']) ?>;
  document.getElementById('edit_quota_per_student').value = <?= (float)$selectedCampaign['quota_per_student'] ?>;
  document.getElementById('edit_camp_deadline').value = <?= json_encode($selectedCampaign['deadline'] ?? '') ?>;
  document.getElementById('edit_camp_desc').value = <?= json_encode($selectedCampaign['description'] ?? '') ?>;
  openModal('modalEditCampaign');
  <?php endif; ?>
}

function openEditPaymentModal(id, studentName, status, amount, method, notes, quota) {
  defaultCampaignQuota = quota;
  document.getElementById('editPaymentId').value = id;
  document.getElementById('editPaymentStudentName').innerText = studentName;
  document.getElementById('edit_status').value = status;
  document.getElementById('edit_amount').value = (amount > 0) ? amount : (status === 'pagato' ? quota : 0);
  document.getElementById('edit_method').value = method || 'contanti';
  document.getElementById('edit_notes').value = notes || '';
  openModal('modalEditPayment');
}

function handleStatusChange(status) {
  const amountInput = document.getElementById('edit_amount');
  if (status === 'pagato' && parseFloat(amountInput.value) === 0) {
    amountInput.value = defaultCampaignQuota;
  } else if (status === 'non_pagato' || status === 'esonerato') {
    amountInput.value = 0;
  }
}

// Aggiorna dinamicamente il testo WhatsApp delle quote in base ai metodi selezionati
const baseQuoteWaTemplate = <?= json_encode($quoteWa) ?>;
function updateQuoteWaText() {
  const checkboxes = document.querySelectorAll('.quote-wa-method');
  const selectedMethods = [];
  const methodDescriptions = {
    contanti: '- In contanti (in bustina con nome dell\'alunno)',
    bonifico: '- Bonifico bancario (dati IBAN forniti in privato)',
    satispay: '- Satispay (contattatemi per link/numero)',
    paypal: '- PayPal (invio amici e familiari)',
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

  // Sostituisce il blocco modalità nel messaggio base
  let updatedText = baseQuoteWaTemplate.replace(
    /Modalità di versamento disponibili:[\s\S]*?(?=\n\nGrazie a tutti)/,
    methodsBlock
  );

  document.getElementById('textQuoteWa').value = updatedText;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
