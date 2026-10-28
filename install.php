<?php
declare(strict_types=1);

/**
 * Rappresentor - Installatore Guidato Web
 * Versione SemVer: 0.2.0
 */

$configFile = __DIR__ . '/config/config.php';
$isInstalled = file_exists($configFile);

$errors = [];
$success = false;

// Controlli prerequisiti di sistema
$phpVersion = phpversion();
$phpOk = version_compare($phpVersion, '8.1.0', '>=');
$pdoOk = extension_loaded('pdo');
$pdoMysqlOk = extension_loaded('pdo_mysql');
$mbstringOk = extension_loaded('mbstring');
$configWritable = is_writable(__DIR__ . '/config') || (!file_exists($configFile) && is_writable(__DIR__));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isInstalled) {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbPort = (int)($_POST['db_port'] ?? 3306);
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';

    $schoolName = trim($_POST['school_name'] ?? 'Istituto Comprensivo');
    $schoolYear = trim($_POST['school_year'] ?? '2026/2027');
    $className  = trim($_POST['class_name'] ?? 'Sezione A');
    $schoolType = trim($_POST['school_type'] ?? 'primaria');

    $adminName  = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim(strtolower($_POST['admin_email'] ?? ''));
    $adminPass  = $_POST['admin_pass'] ?? '';

    // Validazioni
    if (empty($dbName) || empty($dbUser)) {
        $errors[] = "Nome database e utente database sono obbligatori.";
    }
    if (empty($className)) {
        $errors[] = "Inserisci il nome della prima classe o sezione.";
    }
    if (empty($adminName) || empty($adminEmail) || strlen($adminPass) < 8) {
        $errors[] = "Inserisci nome, email valida e password di almeno 8 caratteri per l'amministratore.";
    }

    if (empty($errors)) {
        try {
            // 1. Test connessione MySQL
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);

            // 2. Importazione Schema SQL
            $schemaFile = __DIR__ . '/database/schema.sql';
            if (!file_exists($schemaFile)) {
                throw new Exception("File database/schema.sql non trovato.");
            }

            $sql = file_get_contents($schemaFile);
            $pdo->exec($sql);

            // 3. Inserimento Prima Classe
            $stmt = $pdo->prepare("INSERT INTO rappresentor_classes (name, school_type, school_year, school_name) VALUES (:name, :type, :year, :school)");
            $stmt->execute([
                'name' => $className,
                'type' => $schoolType,
                'year' => $schoolYear,
                'school' => $schoolName
            ]);
            $firstClassId = (int)$pdo->lastInsertId();

            // 4. Inserimento Primo Utente Admin
            $passHash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO rappresentor_users (name, email, password_hash, role, is_active) VALUES (:name, :email, :pass, 'admin', 1)");
            $stmt->execute([
                'name' => $adminName,
                'email' => $adminEmail,
                'pass' => $passHash
            ]);

            // 5. Aggiornamento impostazioni scuola
            $stmt = $pdo->prepare("INSERT INTO rappresentor_settings (key_name, key_value) VALUES ('school_name', :sname) ON DUPLICATE KEY UPDATE key_value = :sname");
            $stmt->execute(['sname' => $schoolName]);

            // 6. Scrittura file config/config.php
            $configContent = "<?php\n" .
                "/**\n * Configurazione generata automaticamente dall'installatore Rappresentor\n */\n" .
                "return [\n" .
                "    'app' => [\n" .
                "        'name' => 'Rappresentor',\n" .
                "        'version' => '0.2.0',\n" .
                "        'env' => 'production'\n" .
                "    ],\n" .
                "    'db' => [\n" .
                "        'host' => " . var_export($dbHost, true) . ",\n" .
                "        'port' => " . (int)$dbPort . ",\n" .
                "        'name' => " . var_export($dbName, true) . ",\n" .
                "        'user' => " . var_export($dbUser, true) . ",\n" .
                "        'pass' => " . var_export($dbPass, true) . ",\n" .
                "        'charset' => 'utf8mb4'\n" .
                "    ]\n" .
                "];\n";

            if (!file_put_contents($configFile, $configContent)) {
                throw new Exception("Impossibile salvare il file config/config.php. Verifica i permessi della cartella.");
            }

            $success = true;
        } catch (Exception $e) {
            $errors[] = "Errore durante l'installazione: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Installazione Rappresentor v0.2.0</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .install-container { max-width: 680px; margin: 40px auto; padding: 0 16px; }
    .install-card { background: var(--bg-surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); border: 1px solid var(--border-color); overflow: hidden; }
    .install-header { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; padding: 28px 24px; text-align: center; }
    .install-header h1 { margin: 0; font-size: 1.6rem; font-weight: 700; }
    .install-header p { margin: 8px 0 0; opacity: 0.9; font-size: 0.95rem; }
    .install-body { padding: 28px; }
    .step-section { margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color); }
    .step-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .step-title { font-size: 1.1rem; font-weight: 600; color: var(--text-primary); margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
  </style>
</head>
<body class="auth-body">
<div class="install-container">
  <div class="install-card">
    <div class="install-header">
      <h1>🎓 Rappresentor <span style="font-size:0.8rem; background:rgba(255,255,255,0.25); padding:2px 8px; border-radius:99px;">v0.2.0</span></h1>
      <p>Piattaforma Web per Rappresentanti di Classe (Asilo Nido - Medie)</p>
    </div>

    <div class="install-body">
      <?php if ($isInstalled && !$success): ?>
        <div class="alert alert-info">
          <strong>ℹ️ Rappresentor è già installato!</strong>
          <p style="margin: 8px 0 0;">Il file di configurazione <code>config/config.php</code> è presente.</p>
        </div>
        <div style="text-align: center; margin-top: 24px;">
          <a href="login.php" class="btn btn-primary btn-lg">Vai alla schermata di Login &rarr;</a>
        </div>
      <?php elseif ($success): ?>
        <div class="alert alert-success">
          <strong>🎉 Installazione completata con successo!</strong>
          <p style="margin: 8px 0 0;">Il database è stato inizializzato e l'account amministratore è pronto.</p>
        </div>
        <div style="margin-top: 24px; text-align: center;">
          <a href="login.php" class="btn btn-primary btn-lg">Accedi come Rappresentante &rarr;</a>
        </div>
      <?php else: ?>

        <!-- Controllo Requisiti -->
        <div class="step-section">
          <div class="step-title">1. Verifica Requisiti di Sistema</div>
          <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9rem;">
            <li style="margin-bottom: 6px;">
              <?= $phpOk ? '✅' : '❌' ?> PHP 8.1 o superiore (Attuale: <?= htmlspecialchars($phpVersion) ?>)
            </li>
            <li style="margin-bottom: 6px;">
              <?= $pdoMysqlOk ? '✅' : '❌' ?> Estensione PHP PDO MySQL (<?= $pdoMysqlOk ? 'Presente' : 'Mancante' ?>)
            </li>
            <li style="margin-bottom: 6px;">
              <?= $configWritable ? '✅' : '❌' ?> Permessi di scrittura cartella configurazione
            </li>
          </ul>
          <?php if (!$phpOk || !$pdoMysqlOk): ?>
            <div class="alert alert-danger" style="margin-top: 12px;">
              Il tuo server non soddisfa tutti i requisiti minimi indicati sopra.
            </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
            <ul style="margin: 0; padding-left: 20px;">
              <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="POST" action="install.php">
          <!-- Database -->
          <div class="step-section">
            <div class="step-title">2. Connessione Database MySQL</div>
            <div class="form-grid">
              <div class="form-group">
                <label for="db_host">Host MySQL</label>
                <input type="text" id="db_host" name="db_host" class="form-control" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
              </div>
              <div class="form-group">
                <label for="db_port">Porta</label>
                <input type="number" id="db_port" name="db_port" class="form-control" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" required>
              </div>
            </div>
            <div class="form-group">
              <label for="db_name">Nome Database</label>
              <input type="text" id="db_name" name="db_name" class="form-control" placeholder="es. rappresentor_db" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required>
              <small class="form-hint">Crea prima il database vuoto nel tuo cPanel / phpMyAdmin se non esiste.</small>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label for="db_user">Utente Database</label>
                <input type="text" id="db_user" name="db_user" class="form-control" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label for="db_pass">Password Database</label>
                <input type="password" id="db_pass" name="db_pass" class="form-control" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>">
              </div>
            </div>
          </div>

          <!-- Prima Classe -->
          <div class="step-section">
            <div class="step-title">3. Scuola & Prima Classe / Sezione</div>
            <div class="form-group">
              <label for="school_name">Nome Istituto Scolastico / Plesso</label>
              <input type="text" id="school_name" name="school_name" class="form-control" placeholder="es. I.C. Alessandro Manzoni" value="<?= htmlspecialchars($_POST['school_name'] ?? 'I.C. Don Milani') ?>">
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label for="class_name">Nome Sezione / Classe</label>
                <input type="text" id="class_name" name="class_name" class="form-control" placeholder="es. 3^ B oppure Sezione Coccinelle" value="<?= htmlspecialchars($_POST['class_name'] ?? 'Classe 2^A') ?>" required>
              </div>
              <div class="form-group">
                <label for="school_type">Grado Scolastico</label>
                <select id="school_type" name="school_type" class="form-control" required>
                  <option value="nido">👶 Asilo Nido (0-3 anni)</option>
                  <option value="infanzia">🎨 Scuola dell'Infanzia / Materna (3-6 anni)</option>
                  <option value="primaria" selected>🎒 Scuola Primaria / Elementari (6-11 anni)</option>
                  <option value="secondaria_1">📐 Secondaria 1° Grado / Medie (11-14 anni)</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label for="school_year">Anno Scolastico</label>
              <input type="text" id="school_year" name="school_year" class="form-control" value="<?= htmlspecialchars($_POST['school_year'] ?? '2026/2027') ?>" required>
            </div>
          </div>

          <!-- Amministratore -->
          <div class="step-section">
            <div class="step-title">4. Account Rappresentante Principale (Admin)</div>
            <div class="form-group">
              <label for="admin_name">Nome e Cognome</label>
              <input type="text" id="admin_name" name="admin_name" class="form-control" placeholder="es. Maria Rossi" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label for="admin_email">Email (per il login e notifiche)</label>
              <input type="email" id="admin_email" name="admin_email" class="form-control" placeholder="es. maria.rossi@email.it" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label for="admin_pass">Password (min. 8 caratteri)</label>
              <input type="password" id="admin_pass" name="admin_pass" class="form-control" minlength="8" required>
            </div>
          </div>

          <div style="text-align: right; margin-top: 24px;">
            <button type="submit" class="btn btn-primary btn-lg" <?= (!$phpOk || !$pdoMysqlOk) ? 'disabled' : '' ?>>
              Avvia Installazione &rarr;
            </button>
          </div>
        </form>

      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
