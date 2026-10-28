<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;

Auth::requireAuth('login.php');

$currentUser = Auth::user();
$activeClass = Helper::getActiveClass($db);
$activeClassId = $activeClass['id'] ?? null;

// Gestione aggiunta nuovo evento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Helper::verifyCsrf()) {
        Helper::setFlash('danger', 'Richiesta non valida.');
    } else {
        if ($activeClassId && $_POST['action'] === 'add_event') {
            $title = trim($_POST['title'] ?? '');
            $type = $_POST['event_type'] ?? 'riunione';
            $date = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
            $time = !empty($_POST['event_time']) ? $_POST['event_time'] : null;
            $location = trim($_POST['location'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($title) || empty($date)) {
                Helper::setFlash('danger', 'Titolo e data dell\'evento sono obbligatori.');
            } else {
                $stmt = $db->prepare("INSERT INTO rappresentor_events (class_id, title, event_type, event_date, event_time, location, description) 
                    VALUES (:cid, :ti, :ty, :da, :tm, :lo, :de)");
                $stmt->execute([
                    'cid' => $activeClassId,
                    'ti' => $title,
                    'ty' => $type,
                    'da' => $date,
                    'tm' => $time,
                    'lo' => $location,
                    'de' => $description
                ]);
                Helper::setFlash('success', 'Evento inserito a calendario con successo!');
                header('Location: calendario.php');
                exit;
            }
        }

        if ($activeClassId && $_POST['action'] === 'delete_event') {
            $eventId = (int)($_POST['event_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM rappresentor_events WHERE id = :id AND class_id = :cid");
            $stmt->execute(['id' => $eventId, 'cid' => $activeClassId]);
            Helper::setFlash('info', 'Evento rimosso dal calendario.');
            header('Location: calendario.php');
            exit;
        }
    }
}

// Carica eventi futuri e passati se c'è una classe attiva
$upcomingEvents = [];
$pastEvents = [];
$birthdaysThisMonth = [];

if ($activeClassId) {
    $stmt = $db->prepare("SELECT * FROM rappresentor_events WHERE class_id = :cid AND event_date >= CURDATE() ORDER BY event_date ASC, event_time ASC");
    $stmt->execute(['cid' => $activeClassId]);
    $upcomingEvents = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT * FROM rappresentor_events WHERE class_id = :cid AND event_date < CURDATE() ORDER BY event_date DESC LIMIT 15");
    $stmt->execute(['cid' => $activeClassId]);
    $pastEvents = $stmt->fetchAll();

    // Compleanni degli alunni del mese corrente
    $currentMonth = (int)date('m');
    $stmt = $db->prepare("SELECT first_name, last_name, birth_date FROM rappresentor_students WHERE class_id = :cid AND is_active = 1 AND birth_date IS NOT NULL AND MONTH(birth_date) = :m ORDER BY DAY(birth_date) ASC");
    $stmt->execute(['cid' => $activeClassId, 'm' => $currentMonth]);
    $birthdaysThisMonth = $stmt->fetchAll();
}

$pageTitle = 'Scadenze & Eventi';
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
      <span>📅</span>
      <span>Scadenze & Calendario Eventi</span>
    </h1>
    <p class="page-subtitle">Consigli di classe, uscite didattiche, scadenze pagamenti e compleanni.</p>
  </div>
  <div>
    <button class="btn btn-primary" data-modal-target="modalAddEvent">+ Nuovo Evento / Scadenza</button>
  </div>
</div>

<div class="grid-3">
  <!-- Eventi in Programma (2 colonne) -->
  <div style="grid-column: span 2;">
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">⏰ Prossimi Eventi & Scadenze</h2>
        <span class="badge badge-primary"><?= count($upcomingEvents) ?> in arrivo</span>
      </div>
      <div class="card-body">
        <?php if (empty($upcomingEvents)): ?>
          <p style="color: var(--text-muted); text-align: center; padding: 24px 0; margin: 0;">
            Nessun evento futuro in programma. Clicca su "+ Nuovo Evento" per aggiungere una riunione o scadenza.
          </p>
        <?php else: ?>
          <div style="display: flex; flex-direction: column; gap: 14px;">
            <?php foreach ($upcomingEvents as $ev): ?>
              <div style="border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; background: var(--bg-surface);">
                <div style="display: flex; gap: 14px;">
                  <div style="font-size: 1.8rem;">
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
                    <h3 style="margin: 0; font-size: 1.05rem; font-weight: 600;"><?= Helper::e($ev['title']) ?></h3>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px;">
                      📅 <strong><?= Helper::formatDate($ev['event_date']) ?></strong>
                      <?php if ($ev['event_time']): ?>
                        • ore <strong><?= substr($ev['event_time'], 0, 5) ?></strong>
                      <?php endif; ?>
                      <?php if (!empty($ev['location'])): ?>
                        • 📍 <?= Helper::e($ev['location']) ?>
                      <?php endif; ?>
                    </div>
                    <?php if (!empty($ev['description'])): ?>
                      <p style="margin: 8px 0 0; font-size: 0.88rem; color: var(--text-secondary);">
                        <?= nl2br(Helper::e($ev['description'])) ?>
                      </p>
                    <?php endif; ?>
                  </div>
                </div>

                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                  <span class="badge badge-info" style="text-transform: capitalize;"><?= Helper::e($ev['event_type']) ?></span>
                  <form method="POST" action="calendario.php" onsubmit="return confirm('Vuoi rimuovere questo evento?');">
                    <?= Helper::csrfField() ?>
                    <input type="hidden" name="action" value="delete_event">
                    <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-secondary" style="color: var(--danger);" title="Elimina evento">🗑️</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Eventi Passati -->
    <?php if (!empty($pastEvents)): ?>
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">📜 Eventi Passati</h2>
        </div>
        <div class="card-body" style="padding: 0;">
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Data</th>
                  <th>Tipo</th>
                  <th>Titolo</th>
                  <th>Luogo</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pastEvents as $pe): ?>
                  <tr style="opacity: 0.75;">
                    <td><?= Helper::formatDate($pe['event_date']) ?></td>
                    <td><span class="badge badge-secondary"><?= Helper::e($pe['event_type']) ?></span></td>
                    <td><strong><?= Helper::e($pe['title']) ?></strong></td>
                    <td><?= Helper::e($pe['location'] ?? '-') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Barra Laterale: Compleanni del Mese -->
  <div>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">🎂 Compleanni del Mese</h2>
      </div>
      <div class="card-body">
        <?php if (empty($birthdaysThisMonth)): ?>
          <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0;">
            Nessun compleanno registrato per questo mese.
          </p>
        <?php else: ?>
          <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ($birthdaysThisMonth as $b): 
              $day = date('d', strtotime($b['birth_date']));
              $ageNext = (int)date('Y') - (int)date('Y', strtotime($b['birth_date']));
            ?>
              <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px; background: var(--bg-surface-alt); border-radius: var(--radius-md);">
                <div>
                  <div style="font-weight: 600; font-size: 0.92rem;">
                    <?= Helper::e($b['last_name']) ?> <?= Helper::e($b['first_name']) ?>
                  </div>
                  <small style="color: var(--text-secondary);">Compie <?= $ageNext ?> anni</small>
                </div>
                <span class="badge badge-warning" style="font-weight: 700;">
                  <?= $day ?> <?= date('M') ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal Aggiungi Evento -->
<div class="modal-backdrop" id="modalAddEvent">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Aggiungi Evento / Scadenza</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="calendario.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="add_event">

      <div class="modal-body">
        <div class="form-group">
          <label for="ev_title">Titolo dell'Evento o Scadenza *</label>
          <input type="text" id="ev_title" name="title" class="form-control" placeholder="es. Consiglio d'Interclasse, Gita Fattoria Didattica..." required>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="ev_type">Tipologia</label>
            <select id="ev_type" name="event_type" class="form-control">
              <option value="riunione">👥 Riunione / Consiglio di Classe</option>
              <option value="gita">🚌 Gita / Uscita Didattica</option>
              <option value="scadenza">⏰ Scadenza (PagoPA, quota, consegna)</option>
              <option value="festa">🎈 Festa di Classe</option>
              <option value="compleanno">🎂 Compleanno</option>
              <option value="altro">📌 Altro</option>
            </select>
          </div>

          <div class="form-group">
            <label for="ev_date">Data *</label>
            <input type="date" id="ev_date" name="event_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="ev_time">Ora (opzionale)</label>
            <input type="time" id="ev_time" name="event_time" class="form-control">
          </div>

          <div class="form-group">
            <label for="ev_loc">Luogo</label>
            <input type="text" id="ev_loc" name="location" class="form-control" placeholder="es. Aula Magna, Plesso Principale">
          </div>
        </div>

        <div class="form-group">
          <label for="ev_desc">Descrizione / Ordine del Giorno</label>
          <textarea id="ev_desc" name="description" class="form-control" rows="3" placeholder="Punti da discutere, materiale da portare..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Salva a Calendario</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
