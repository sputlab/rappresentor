<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;

Auth::requireAuth('login.php');

$currentUser = Auth::user();
$activeClass = Helper::getActiveClass($db);
$activeClassId = $activeClass['id'] ?? null;
$currentVersion = Helper::getAppVersion();

$pageTitle = 'Changelog & Novità';
require_once __DIR__ . '/includes/header.php';

// Carica e analizza CHANGELOG.md
$changelogFile = __DIR__ . '/CHANGELOG.md';
$releases = [];

if (file_exists($changelogFile)) {
    $content = (string)file_get_contents($changelogFile);
    $lines = explode("\n", $content);
    $currentRelease = null;
    $currentSection = null;

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }

        // Titolo release: ## [0.3.4] - 2026-10-28
        if (preg_match('/^##\s+\[(.*?)\]\s*-\s*(.*)$/', $trimmed, $matches)) {
            if ($currentRelease !== null) {
                $releases[] = $currentRelease;
            }
            $currentRelease = [
                'version' => $matches[1],
                'date' => $matches[2],
                'sections' => []
            ];
            $currentSection = null;
            continue;
        }

        // Sezione categoria: ### Corretto, ### Aggiunto, ### Modificato
        if (preg_match('/^###\s+(.*)$/', $trimmed, $matches)) {
            $currentSection = $matches[1];
            if ($currentRelease !== null && !isset($currentRelease['sections'][$currentSection])) {
                $currentRelease['sections'][$currentSection] = [];
            }
            continue;
        }

        // Voce elenco: - testo
        if (preg_match('/^-\s+(.*)$/', $trimmed, $matches)) {
            if ($currentRelease !== null && $currentSection !== null) {
                $currentRelease['sections'][$currentSection][] = $matches[1];
            }
        }
    }

    if ($currentRelease !== null) {
        $releases[] = $currentRelease;
    }
}

// Funzione helper per formattare markdown inline (*, `, ecc.)
function formatInlineMarkdown(string $text): string {
    // Bold: **testo**
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    // Italic: *testo* o _testo_
    $text = preg_replace('/(?<!\*)\*(?!\*)(.*?)\*/', '<em>$1</em>', (string)$text);
    // Code: `code`
    $text = preg_replace('/`(.*?)`/', '<code style="background: var(--bg-surface-alt); padding: 2px 6px; border-radius: 4px; font-size: 0.85em; border: 1px solid var(--border-color);">$1</code>', (string)$text);
    return (string)$text;
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <span>📜</span>
      <span>Changelog & Registro Versioni</span>
      <span class="badge badge-primary">v<?= Helper::e($currentVersion) ?></span>
    </h1>
    <p class="page-subtitle">Cronologia dettagliata degli aggiornamenti, nuove funzionalità e correzioni applicate all'applicazione.</p>
  </div>
  <div>
    <a href="https://semver.org/lang/it/" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" title="Guida a Semantic Versioning 2.0.0">
      ℹ️ Specifiche SemVer 2.0.0
    </a>
  </div>
</div>

<?php if (empty($releases)): ?>
  <div class="card" style="padding: 32px; text-align: center;">
    <p style="color: var(--text-secondary); margin: 0;">Nessun registro changelog disponibile.</p>
  </div>
<?php else: ?>
  <div style="display: flex; flex-direction: column; gap: 20px;">
    <?php foreach ($releases as $index => $rel): 
      $isCurrent = ($rel['version'] === $currentVersion);
      // Formatta la data se valida (YYYY-MM-DD -> DD/MM/YYYY)
      $dateStr = $rel['date'];
      $ts = strtotime($dateStr);
      if ($ts) {
          $dateStr = date('d/m/Y', $ts);
      }
    ?>
      <div class="card" style="<?= $isCurrent ? 'border-color: var(--primary); box-shadow: 0 4px 16px rgba(37, 99, 235, 0.12);' : '' ?>">
        <div class="card-header" style="flex-wrap: wrap; gap: 10px;">
          <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <h2 class="card-title" style="margin: 0; font-size: 1.15rem;">
              Versione <?= Helper::e($rel['version']) ?>
            </h2>
            <?php if ($isCurrent): ?>
              <span class="badge badge-primary">Versione Attuale</span>
            <?php elseif ($index === 0): ?>
              <span class="badge badge-success">Ultima Release</span>
            <?php endif; ?>
          </div>
          <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">
            📅 Rilasciata il <?= Helper::e($dateStr) ?>
          </span>
        </div>

        <div class="card-body">
          <?php foreach ($rel['sections'] as $secTitle => $items): 
            $secLower = strtolower($secTitle);
            $badgeClass = 'badge-secondary';
            $badgeIcon = '📋';

            if (str_contains($secLower, 'aggiunt')) {
                $badgeClass = 'badge-success';
                $badgeIcon = '✨';
            } elseif (str_contains($secLower, 'corrett') || str_contains($secLower, 'fix')) {
                $badgeClass = 'badge-primary';
                $badgeIcon = '🛠️';
            } elseif (str_contains($secLower, 'modificat')) {
                $badgeClass = 'badge-warning';
                $badgeIcon = '🔄';
            } elseif (str_contains($secLower, 'rimoss')) {
                $badgeClass = 'badge-danger';
                $badgeIcon = '🗑️';
            }
          ?>
            <div style="margin-bottom: 16px;">
              <div style="margin-bottom: 8px;">
                <span class="badge <?= $badgeClass ?>" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px;">
                  <?= $badgeIcon ?> <?= Helper::e($secTitle) ?>
                </span>
              </div>
              <ul style="margin: 0; padding-left: 20px; color: var(--text-primary); font-size: 0.92rem; line-height: 1.6;">
                <?php foreach ($items as $item): ?>
                  <li style="margin-bottom: 6px;">
                    <?= formatInlineMarkdown(Helper::e($item)) ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
