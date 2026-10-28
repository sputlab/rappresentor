<?php
declare(strict_types=1);

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

use Rappresentor\Core\Helper;

if (!$activeClassId) {
    // Nessuna classe presente, invita a crearne una
    ?>
    <div class="card" style="text-align: center; padding: 48px 24px;">
      <div style="font-size: 3rem; margin-bottom: 12px;">🏫</div>
      <h2 style="margin: 0 0 8px;">Benvenuto su Rappresentor!</h2>
      <p style="color: var(--text-secondary); max-width: 500px; margin: 0 auto 24px;">
        Per iniziare a gestire le attività di rappresentanza, crea la tua prima classe o sezione (dall'asilo nido alle medie).
      </p>
      <div>
        <a href="classi.php" class="btn btn-primary btn-lg">+ Crea la tua Prima Classe</a>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// 1. Dati anagrafica classe attiva
$stmt = $db->prepare("SELECT COUNT(*) FROM rappresentor_students WHERE class_id = :cid AND is_active = 1");
$stmt->execute(['cid' => $activeClassId]);
$totalStudents = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM rappresentor_guardians g JOIN rappresentor_students s ON g.student_id = s.id WHERE s.class_id = :cid AND s.is_active = 1");
$stmt->execute(['cid' => $activeClassId]);
$totalGuardians = (int)$stmt->fetchColumn();

// 2. Cassa di classe: entrate, uscite, saldo
$stmt = $db->prepare("SELECT 
    COALESCE(SUM(CASE WHEN type = 'entrata' THEN amount ELSE 0 END), 0) as total_entrate,
    COALESCE(SUM(CASE WHEN type = 'uscita' THEN amount ELSE 0 END), 0) as total_uscite
    FROM rappresentor_cashbox_transactions WHERE class_id = :cid");
$stmt->execute(['cid' => $activeClassId]);
$cashbox = $stmt->fetch();
$totalEntrate = (float)($cashbox['total_entrate'] ?? 0);
$totalUscite = (float)($cashbox['total_uscite'] ?? 0);
$saldoCassa = $totalEntrate - $totalUscite;

// 3. Campagne quote aperte
$stmt = $db->prepare("SELECT c.*, 
    (SELECT COUNT(*) FROM rappresentor_payments p WHERE p.campaign_id = c.id AND p.status = 'pagato') as pagati_count,
    (SELECT COUNT(*) FROM rappresentor_students s WHERE s.class_id = c.class_id AND s.is_active = 1) as totale_alunni
    FROM rappresentor_campaigns c 
    WHERE c.class_id = :cid AND c.is_closed = 0 
    ORDER BY c.deadline ASC, c.id DESC LIMIT 3");
$stmt->execute(['cid' => $activeClassId]);
$activeCampaigns = $stmt->fetchAll();

// 4. Prossimi eventi e scadenze (nei prossimi 30 giorni)
$stmt = $db->prepare("SELECT * FROM rappresentor_events 
    WHERE class_id = :cid AND event_date >= CURDATE() 
    ORDER BY event_date ASC, event_time ASC LIMIT 4");
$stmt->execute(['cid' => $activeClassId]);
$upcomingEvents = $stmt->fetchAll();

// 5. Argomenti aperti per il Consiglio d'Interclasse
$stmt = $db->prepare("SELECT * FROM rappresentor_interclasse_topics 
    WHERE class_id = :cid AND status IN ('proposto', 'in_discussione') 
    ORDER BY id DESC LIMIT 4");
$stmt->execute(['cid' => $activeClassId]);
$openTopics = $stmt->fetchAll();

$classTypeInfo = Helper::schoolTypeDetails($activeClass['school_type'] ?? 'primaria');
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <span><?= $classTypeInfo['icon'] ?></span>
      <span><?= Helper::e($activeClass['name']) ?></span>
      <span class="badge <?= $classTypeInfo['badge'] ?>"><?= $classTypeInfo['label'] ?></span>
    </h1>
    <p class="page-subtitle">
      <?= Helper::e($activeClass['school_name'] ?? 'Istituto Scolastico') ?> • A.S. <?= Helper::e($activeClass['school_year']) ?>
    </p>
  </div>
  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <a href="alunni.php" class="btn btn-secondary btn-sm">+ Alunno</a>
    <a href="cassa.php" class="btn btn-secondary btn-sm">+ Movimento Cassa</a>
    <a href="comunicazioni.php" class="btn btn-whatsapp btn-sm">💬 Messaggio WhatsApp</a>
  </div>
</div>

<!-- Statistiche Rapide -->
<div class="grid-4">
  <div class="stat-card">
    <div class="stat-icon" style="background: var(--color-primaria-bg); color: var(--color-primaria);">🎒</div>
    <div class="stat-info">
      <div class="stat-label">Alunni Iscritti</div>
      <div class="stat-value"><?= $totalStudents ?></div>
      <div class="stat-hint"><?= $totalGuardians ?> contatti genitori</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background: <?= $saldoCassa >= 0 ? 'var(--success-bg)' : 'var(--danger-bg)' ?>; color: <?= $saldoCassa >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">💰</div>
    <div class="stat-info">
      <div class="stat-label">Saldo Cassa</div>
      <div class="stat-value" style="color: <?= $saldoCassa >= 0 ? 'var(--success-text)' : 'var(--danger-text)' ?>;">
        <?= Helper::formatMoney($saldoCassa) ?>
      </div>
      <div class="stat-hint">Entrate: <?= Helper::formatMoney($totalEntrate) ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background: var(--warning-bg); color: var(--warning);">📅</div>
    <div class="stat-info">
      <div class="stat-label">Prossimi Eventi</div>
      <div class="stat-value"><?= count($upcomingEvents) ?></div>
      <div class="stat-hint">In programma</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background: var(--info-bg); color: var(--info);">📋</div>
    <div class="stat-info">
      <div class="stat-label">Note Consiglio</div>
      <div class="stat-value"><?= count($openTopics) ?></div>
      <div class="stat-hint">Punti da discutere</div>
    </div>
  </div>
</div>

<div class="grid-2">
  <!-- Campagne Quote Attive -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">💰 Raccolte Quote in Corso</h2>
      <a href="cassa.php" class="btn btn-sm btn-secondary">Vedi Cassa &rarr;</a>
    </div>
    <div class="card-body">
      <?php if (empty($activeCampaigns)): ?>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">Nessuna raccolta quote attiva al momento.</p>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 16px;">
          <?php foreach ($activeCampaigns as $camp): 
            $tot = (int)$camp['totale_alunni'];
            $pag = (int)$camp['pagati_count'];
            $pct = $tot > 0 ? round(($pag / $tot) * 100) : 0;
          ?>
            <div style="border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px;">
              <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                <div>
                  <strong style="font-size: 0.95rem;"><?= Helper::e($camp['title']) ?></strong>
                  <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px;">
                    Quota: <strong><?= Helper::formatMoney($camp['quota_per_student']) ?></strong>
                    <?php if ($camp['deadline']): ?>
                      • Scadenza: <strong><?= Helper::formatDate($camp['deadline']) ?></strong>
                    <?php endif; ?>
                  </div>
                </div>
                <span class="badge <?= $pct >= 100 ? 'badge-success' : 'badge-warning' ?>">
                  <?= $pag ?> / <?= $tot ?> pagati (<?= $pct ?>%)
                </span>
              </div>

              <!-- Progress bar -->
              <div style="background: #e2e8f0; height: 8px; border-radius: 99px; overflow: hidden; margin-top: 10px;">
                <div style="background: var(--primary); height: 100%; width: <?= $pct ?>%; transition: width 0.3s ease;"></div>
              </div>

              <div style="margin-top: 10px; text-align: right;">
                <a href="cassa.php?campaign_id=<?= (int)$camp['id'] ?>" class="btn btn-sm btn-secondary" style="font-size: 0.75rem;">
                  Gestisci Versamenti &rarr;
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Prossimi Eventi e Scadenze -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">📅 Prossime Scadenze & Riunioni</h2>
      <a href="calendario.php" class="btn btn-sm btn-secondary">Calendario &rarr;</a>
    </div>
    <div class="card-body">
      <?php if (empty($upcomingEvents)): ?>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">Nessun evento o scadenza programmata per i prossimi giorni.</p>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 12px;">
          <?php foreach ($upcomingEvents as $ev): ?>
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; background: var(--bg-surface-alt); border-radius: var(--radius-md);">
              <div style="display: flex; align-items: center; gap: 12px;">
                <div style="font-size: 1.4rem;">
                  <?= match($ev['event_type']) {
                    'riunione' => '👥',
                    'gita' => '🚌',
                    'scadenza' => '⏰',
                    'festa' => '🎈',
                    'compleanno' => '🎂',
                    default => '📌'
                  } ?>
                </div>
                <div>
                  <div style="font-weight: 600; font-size: 0.92rem;"><?= Helper::e($ev['title']) ?></div>
                  <div style="font-size: 0.78rem; color: var(--text-secondary);">
                    <?= Helper::formatDate($ev['event_date']) ?>
                    <?= $ev['event_time'] ? ' ore ' . substr($ev['event_time'], 0, 5) : '' ?>
                    <?= $ev['location'] ? ' • ' . Helper::e($ev['location']) : '' ?>
                  </div>
                </div>
              </div>
              <span class="badge badge-info" style="text-transform: capitalize;"><?= Helper::e($ev['event_type']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Accesso Rapido Template WhatsApp -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">💬 Comunicazioni Rapide Genitori</h2>
    <a href="comunicazioni.php" class="btn btn-sm btn-whatsapp">Tutti i Template WhatsApp &rarr;</a>
  </div>
  <div class="card-body">
    <p style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 0;">
      Hai bisogno di avvisare i genitori per una scadenza, il saldo cassa o un consiglio d'interclasse?
      Usa i template preimpostati con un click:
    </p>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
      <a href="comunicazioni.php?tpl=1" class="btn btn-secondary btn-sm">💰 Raccolta Quote</a>
      <a href="comunicazioni.php?tpl=2" class="btn btn-secondary btn-sm">⏳ Promemoria Versamento</a>
      <a href="comunicazioni.php?tpl=3" class="btn btn-secondary btn-sm">📊 Rendiconto Cassa Trasparente</a>
      <a href="comunicazioni.php?tpl=4" class="btn btn-secondary btn-sm">📣 Convocazione Riunione</a>
      <a href="comunicazioni.php?tpl=5" class="btn btn-secondary btn-sm">⚠️ Avviso Sciopero</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
