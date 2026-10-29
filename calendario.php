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

        // Modifica evento esistente
        if ($activeClassId && $_POST['action'] === 'edit_event') {
            $eventId = (int)($_POST['event_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $type = $_POST['event_type'] ?? 'riunione';
            $date = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
            $time = !empty($_POST['event_time']) ? $_POST['event_time'] : null;
            $location = trim($_POST['location'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($title) || empty($date) || $eventId <= 0) {
                Helper::setFlash('danger', 'Titolo e data dell\'evento sono obbligatori.');
            } else {
                $stmt = $db->prepare("UPDATE rappresentor_events 
                    SET title = :ti, event_type = :ty, event_date = :da, event_time = :tm, location = :lo, description = :de 
                    WHERE id = :id AND class_id = :cid");
                $stmt->execute([
                    'ti' => $title,
                    'ty' => $type,
                    'da' => $date,
                    'tm' => $time,
                    'lo' => $location,
                    'de' => $description,
                    'id' => $eventId,
                    'cid' => $activeClassId
                ]);
                Helper::setFlash('success', 'Evento aggiornato con successo!');
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
$deadlinesList = [];
$eventsList = [];
$birthdayEventsList = [];
$currentMonth = (int)date('m');

if ($activeClassId) {
    $stmt = $db->prepare("SELECT * FROM rappresentor_events WHERE class_id = :cid AND event_date >= CURDATE() ORDER BY event_date ASC, event_time ASC");
    $stmt->execute(['cid' => $activeClassId]);
    $upcomingEvents = $stmt->fetchAll();

    foreach ($upcomingEvents as $ev) {
        if ($ev['event_type'] === 'scadenza' || $ev['event_type'] === 'pagopa') {
            $deadlinesList[] = $ev;
        } elseif ($ev['event_type'] === 'compleanno') {
            $birthdayEventsList[] = $ev;
        } else {
            $eventsList[] = $ev;
        }
    }

    $stmt = $db->prepare("SELECT * FROM rappresentor_events WHERE class_id = :cid AND event_date < CURDATE() ORDER BY event_date DESC LIMIT 15");
    $stmt->execute(['cid' => $activeClassId]);
    $pastEvents = $stmt->fetchAll();

    // Compleanni degli alunni del mese corrente
    $stmt = $db->prepare("SELECT id, first_name, last_name, birth_date FROM rappresentor_students WHERE class_id = :cid AND is_active = 1 AND birth_date IS NOT NULL AND MONTH(birth_date) = :m ORDER BY DAY(birth_date) ASC");
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
    <p class="page-subtitle">Pianificazione ordinata di scadenze quote/PagoPA, eventi scolastici e compleanni.</p>
  </div>
  <div>
    <button class="btn btn-primary" data-modal-target="modalAddEvent">+ Nuovo Evento / Scadenza</button>
  </div>
</div>

<div style="display: flex; flex-direction: column; gap: 20px;">
  <!-- 1. SEZIONE SCADENZE IN ALTO -->
  <div class="card" style="border-top: 4px solid #ef4444;">
    <div class="card-header">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 1.25rem;">⏰</span>
        <h2 class="card-title" style="margin: 0;">Scadenze & Termini Ultimi</h2>
      </div>
      <span class="badge <?= !empty($deadlinesList) ? 'badge-danger' : 'badge-secondary' ?>">
        <?= count($deadlinesList) ?> scadenze attive
      </span>
    </div>
    <div class="card-body">
      <?php if (empty($deadlinesList)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 20px 0; margin: 0; font-size: 0.9rem;">
          Nessuna scadenza in sospeso. Quando crei una raccolta quote con termine di versamento o aggiungi una scadenza, comparirà qui.
        </p>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <?php foreach ($deadlinesList as $sc): 
            $diffDays = (int)(new DateTime($sc['event_date']))->diff(new DateTime(date('Y-m-d')))->format('%r%a');
            $isToday = ($diffDays === 0);
            $isUrgent = ($diffDays <= 3 && $diffDays >= 0);
          ?>
            <div style="border: 1px solid var(--border-color); border-left: 4px solid <?= $isToday ? '#dc2626' : ($isUrgent ? '#f59e0b' : '#3b82f6') ?>; border-radius: var(--radius-md); padding: 12px 14px; display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; background: var(--bg-surface);">
              <div style="flex: 1; min-width: 240px;">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px;">
                  <span class="badge <?= $isToday ? 'badge-danger' : ($isUrgent ? 'badge-warning' : 'badge-primary') ?>" style="font-weight: 700;">
                    <?= $isToday ? '🚨 SCADE OGGI' : ($diffDays > 0 ? "⏳ Mancano {$diffDays} giorni" : "Entro oggi") ?>
                  </span>
                  <span style="font-size: 0.84rem; font-weight: 600; color: var(--text-secondary);">
                    📅 Termine: <?= Helper::formatDate($sc['event_date']) ?>
                    <?php if ($sc['event_time']): ?>
                      ore <?= substr($sc['event_time'], 0, 5) ?>
                    <?php endif; ?>
                  </span>
                </div>
                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700;"><?= Helper::e($sc['title']) ?></h3>
                <?php if (!empty($sc['description'])): ?>
                  <p style="margin: 6px 0 0; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.35;">
                    <?= nl2br(Helper::e($sc['description'])) ?>
                  </p>
                <?php endif; ?>
              </div>

              <div style="display: flex; align-items: center; gap: 6px;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="openEditEventModal(<?= htmlspecialchars(json_encode([
                    'id' => (int)$sc['id'],
                    'title' => $sc['title'],
                    'event_type' => $sc['event_type'],
                    'event_date' => $sc['event_date'],
                    'event_time' => $sc['event_time'],
                    'location' => $sc['location'],
                    'description' => $sc['description']
                ]), ENT_QUOTES, 'UTF-8') ?>)" title="Modifica scadenza">
                  ✏️ Modifica
                </button>
                <form method="POST" action="calendario.php" onsubmit="return confirm('Vuoi rimuovere questa scadenza?');" style="display: inline;">
                  <?= Helper::csrfField() ?>
                  <input type="hidden" name="action" value="delete_event">
                  <input type="hidden" name="event_id" value="<?= (int)$sc['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-secondary" style="color: var(--danger);" title="Elimina">🗑️</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 2. SEZIONE EVENTI DI CLASSE AL CENTRO -->
  <div class="card" style="border-top: 4px solid var(--primary);">
    <div class="card-header">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 1.25rem;">📅</span>
        <h2 class="card-title" style="margin: 0;">Eventi & Incontri di Classe</h2>
      </div>
      <span class="badge badge-primary"><?= count($eventsList) ?> in programma</span>
    </div>
    <div class="card-body">
      <?php if (empty($eventsList)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 24px 0; margin: 0; font-size: 0.9rem;">
          Nessun evento futuro in programma. Clicca su "+ Nuovo Evento / Scadenza" per programmare riunioni, consigli di classe o gite.
        </p>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 12px;">
          <?php foreach ($eventsList as $ev): ?>
            <div style="border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; background: var(--bg-surface);">
              <div style="display: flex; gap: 14px; flex: 1;">
                <div style="font-size: 1.8rem; line-height: 1;">
                  <?= match($ev['event_type']) {
                    'riunione' => '👥',
                    'gita' => '🚌',
                    'festa' => '🎈',
                    default => '📌'
                  } ?>
                </div>
                <div>
                  <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px;">
                    <span class="badge badge-info" style="text-transform: capitalize; font-size: 0.72rem;">
                      <?= Helper::e($ev['event_type']) ?>
                    </span>
                    <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);">
                      📅 <?= Helper::formatDate($ev['event_date']) ?>
                      <?php if ($ev['event_time']): ?>
                        • ore <?= substr($ev['event_time'], 0, 5) ?>
                      <?php endif; ?>
                      <?php if (!empty($ev['location'])): ?>
                        • 📍 <?= Helper::e($ev['location']) ?>
                      <?php endif; ?>
                    </span>
                  </div>
                  <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700;"><?= Helper::e($ev['title']) ?></h3>
                  <?php if (!empty($ev['description'])): ?>
                    <p style="margin: 6px 0 0; font-size: 0.88rem; color: var(--text-secondary); line-height: 1.35;">
                      <?= nl2br(Helper::e($ev['description'])) ?>
                    </p>
                  <?php endif; ?>
                </div>
              </div>

              <div style="display: flex; align-items: center; gap: 6px;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="openEditEventModal(<?= htmlspecialchars(json_encode([
                    'id' => (int)$ev['id'],
                    'title' => $ev['title'],
                    'event_type' => $ev['event_type'],
                    'event_date' => $ev['event_date'],
                    'event_time' => $ev['event_time'],
                    'location' => $ev['location'],
                    'description' => $ev['description']
                ]), ENT_QUOTES, 'UTF-8') ?>)" title="Modifica evento">
                  ✏️ Modifica
                </button>
                <form method="POST" action="calendario.php" onsubmit="return confirm('Vuoi rimuovere questo evento?');" style="display: inline;">
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

  <!-- 3. SEZIONE COMPLEANNI IN BASSO -->
  <div class="card" style="border-top: 4px solid #f59e0b;">
    <div class="card-header">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 1.25rem;">🎂</span>
        <h2 class="card-title" style="margin: 0;">Compleanni del Mese (<?= Helper::monthName($currentMonth) ?>)</h2>
      </div>
      <span class="badge badge-warning"><?= count($birthdaysThisMonth) + count($birthdayEventsList) ?> festeggiati</span>
    </div>
    <div class="card-body">
      <?php if (empty($birthdaysThisMonth) && empty($birthdayEventsList)): ?>
        <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0; text-align: center; padding: 16px 0;">
          Nessun compleanno registrato per questo mese.
        </p>
      <?php else: ?>
        <div class="grid-2">
          <!-- Compleanni degli alunni del mese -->
          <?php foreach ($birthdaysThisMonth as $b): 
            $day = date('d', strtotime($b['birth_date']));
            $ageNext = (int)date('Y') - (int)date('Y', strtotime($b['birth_date']));
          ?>
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: var(--bg-surface-alt); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
              <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.5rem;">🎂</span>
                <div>
                  <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-primary);">
                    <?= Helper::e($b['last_name']) ?> <?= Helper::e($b['first_name']) ?>
                  </div>
                  <small style="color: var(--text-secondary);">Compie <?= $ageNext ?> anni</small>
                </div>
              </div>
              <span class="badge badge-warning" style="font-weight: 700; font-size: 0.82rem;">
                <?= $day ?> <?= Helper::monthName($currentMonth) ?>
              </span>
            </div>
          <?php endforeach; ?>

          <!-- Eventuali feste a calendario -->
          <?php foreach ($birthdayEventsList as $be): ?>
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #fefce8; border: 1px solid #fef08a; border-radius: var(--radius-md);">
              <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.5rem;">🎈</span>
                <div>
                  <div style="font-weight: 700; font-size: 0.95rem; color: #854d0e;">
                    <?= Helper::e($be['title']) ?>
                  </div>
                  <small style="color: #a16207;">
                    📍 <?= Helper::e($be['location'] ?? 'Festa in programma') ?>
                  </small>
                </div>
              </div>
              <div style="display: flex; align-items: center; gap: 6px;">
                <span class="badge badge-warning" style="font-weight: 700;">
                  <?= Helper::formatDate($be['event_date']) ?>
                </span>
                <button type="button" class="btn btn-sm btn-secondary" onclick="openEditEventModal(<?= htmlspecialchars(json_encode([
                    'id' => (int)$be['id'],
                    'title' => $be['title'],
                    'event_type' => $be['event_type'],
                    'event_date' => $be['event_date'],
                    'event_time' => $be['event_time'],
                    'location' => $be['location'],
                    'description' => $be['description']
                ]), ENT_QUOTES, 'UTF-8') ?>)" title="Modifica">✏️</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 4. SEZIONE EVENTI PASSATI IN FONDO (Accordion Chiuso di Default) -->
  <?php if (!empty($pastEvents)): ?>
    <details class="card accordion-card" style="opacity: 0.9; margin-top: 24px;">
      <summary class="card-header">
        <div style="display: flex; align-items: center; gap: 8px;">
          <span class="accordion-icon">▶</span>
          <h2 class="card-title" style="margin: 0;">📜 Storico Eventi Passati</h2>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <span class="badge badge-secondary"><?= count($pastEvents) ?> trascorsi</span>
          <span style="font-size: 0.8rem; color: var(--text-muted);">(clicca per visualizzare)</span>
        </div>
      </summary>
      <div class="card-body" style="padding: 0;">
        <div class="desktop-table-view">
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Data</th>
                  <th>Tipo</th>
                  <th>Titolo</th>
                  <th>Luogo</th>
                  <th style="text-align: right;">Azioni</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pastEvents as $pe): ?>
                  <tr>
                    <td><?= Helper::formatDate($pe['event_date']) ?></td>
                    <td><span class="badge badge-secondary"><?= Helper::e($pe['event_type']) ?></span></td>
                    <td><strong><?= Helper::e($pe['title']) ?></strong></td>
                    <td><?= Helper::e($pe['location'] ?? '-') ?></td>
                    <td style="text-align: right;">
                      <form method="POST" action="calendario.php" onsubmit="return confirm('Vuoi eliminare definitivamente questo evento dallo storico?');" style="display: inline;">
                        <?= Helper::csrfField() ?>
                        <input type="hidden" name="action" value="delete_event">
                        <input type="hidden" name="event_id" value="<?= (int)$pe['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-secondary" style="color: var(--danger); padding: 3px 8px; font-size: 0.78rem;" title="Elimina definitivamente">🗑️ Elimina</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="mobile-cards-view">
          <?php foreach ($pastEvents as $pe): ?>
            <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 8px 10px; display: flex; flex-direction: column; gap: 4px;">
              <div style="display: flex; align-items: center; justify-content: space-between;">
                <strong><?= Helper::e($pe['title']) ?></strong>
                <span class="badge badge-secondary" style="font-size: 0.72rem;"><?= Helper::e($pe['event_type']) ?></span>
              </div>
              <div style="font-size: 0.78rem; color: var(--text-muted); display: flex; justify-content: space-between;">
                <span>📅 <?= Helper::formatDate($pe['event_date']) ?></span>
                <span>📍 <?= Helper::e($pe['location'] ?? 'Nessun luogo') ?></span>
              </div>
              <div style="display: flex; justify-content: flex-end; margin-top: 4px; border-top: 1px dashed var(--border-color); padding-top: 4px;">
                <form method="POST" action="calendario.php" onsubmit="return confirm('Vuoi eliminare definitivamente questo evento dallo storico?');" style="display: inline;">
                  <?= Helper::csrfField() ?>
                  <input type="hidden" name="action" value="delete_event">
                  <input type="hidden" name="event_id" value="<?= (int)$pe['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-secondary" style="color: var(--danger); font-size: 0.75rem; padding: 2px 8px;" title="Elimina definitivamente">🗑️ Elimina</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </details>
  <?php endif; ?>
</div>

<!-- Modal Aggiungi Evento -->
<div class="modal-backdrop" id="modalAddEvent">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Aggiungi Evento o Scadenza</h3>
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
              <option value="scadenza">⏰ Scadenza (PagoPA, quota, consegna)</option>
              <option value="riunione">👥 Riunione / Consiglio di Classe</option>
              <option value="gita">🚌 Gita / Uscita Didattica</option>
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
          <label for="ev_desc">Descrizione / Dettagli</label>
          <textarea id="ev_desc" name="description" class="form-control" rows="3" placeholder="Punti da discutere, importo, materiale da portare..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Salva a Calendario</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Modifica Evento Esistente -->
<div class="modal-backdrop" id="modalEditEvent">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Modifica Evento / Scadenza</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="calendario.php">
      <?= Helper::csrfField() ?>
      <input type="hidden" name="action" value="edit_event">
      <input type="hidden" name="event_id" id="edit_event_id">

      <div class="modal-body">
        <div class="form-group">
          <label for="edit_ev_title">Titolo dell'Evento o Scadenza *</label>
          <input type="text" id="edit_ev_title" name="title" class="form-control" required>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="edit_ev_type">Tipologia</label>
            <select id="edit_ev_type" name="event_type" class="form-control">
              <option value="scadenza">⏰ Scadenza (PagoPA, quota, consegna)</option>
              <option value="riunione">👥 Riunione / Consiglio di Classe</option>
              <option value="gita">🚌 Gita / Uscita Didattica</option>
              <option value="festa">🎈 Festa di Classe</option>
              <option value="compleanno">🎂 Compleanno</option>
              <option value="altro">📌 Altro</option>
            </select>
          </div>

          <div class="form-group">
            <label for="edit_ev_date">Data *</label>
            <input type="date" id="edit_ev_date" name="event_date" class="form-control" required>
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="edit_ev_time">Ora</label>
            <input type="time" id="edit_ev_time" name="event_time" class="form-control">
          </div>

          <div class="form-group">
            <label for="edit_ev_loc">Luogo</label>
            <input type="text" id="edit_ev_loc" name="location" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label for="edit_ev_desc">Descrizione / Dettagli</label>
          <textarea id="edit_ev_desc" name="description" class="form-control" rows="3"></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annulla</button>
        <button type="submit" class="btn btn-primary">Aggiorna Evento</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditEventModal(eventData) {
  document.getElementById('edit_event_id').value = eventData.id;
  document.getElementById('edit_ev_title').value = eventData.title;
  document.getElementById('edit_ev_type').value = eventData.event_type;
  document.getElementById('edit_ev_date').value = eventData.event_date;
  document.getElementById('edit_ev_time').value = eventData.event_time ? eventData.event_time.substring(0, 5) : '';
  document.getElementById('edit_ev_loc').value = eventData.location || '';
  document.getElementById('edit_ev_desc').value = eventData.description || '';
  openModal('modalEditEvent');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
