<?php
declare(strict_types=1);

/**
 * Rappresentor - Installatore Guidato Web & Setup Iniziale
 * Versione SemVer: 1.1.0
 */

$configFile = __DIR__ . '/config/config.php';
$hasConfigFile = file_exists($configFile);

$errors = [];
$successMessage = '';

// Controlli prerequisiti di sistema
$phpVersion = phpversion();
$phpOk = version_compare($phpVersion, '8.1.0', '>=');
$pdoOk = extension_loaded('pdo');
$pdoMysqlOk = extension_loaded('pdo_mysql');
$mbstringOk = extension_loaded('mbstring');
$configWritable = is_writable(__DIR__ . '/config') || (!file_exists($configFile) && is_writable(__DIR__));

// Analisi stato installazione
$pdo = null;
$dbConnected = false;
$tablesExist = false;
$adminCount = 0;
$classCount = 0;

if ($hasConfigFile) {
    try {
        $cfg = require $configFile;
        $dbCfg = $cfg['db'] ?? [];
        if (!empty($dbCfg['name'])) {
            $host = $dbCfg['host'] ?? 'localhost';
            $port = (int)($dbCfg['port'] ?? 3306);
            $dbname = $dbCfg['name'];
            $user = $dbCfg['user'] ?? '';
            $pass = $dbCfg['pass'] ?? '';
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            $dbConnected = true;

            // Verifica presenza tabella rappresentor_users
            $checkTable = $pdo->query("SHOW TABLES LIKE 'rappresentor_users'")->fetch();
            if ($checkTable) {
                $tablesExist = true;
                $stmt = $pdo->query("SELECT COUNT(*) FROM rappresentor_users WHERE role = 'admin' AND is_active = 1");
                $adminCount = (int)$stmt->fetchColumn();

                $stmtCls = $pdo->query("SELECT COUNT(*) FROM rappresentor_classes WHERE is_archived = 0");
                $classCount = (int)$stmtCls->fetchColumn();
            }
        }
    } catch (\Throwable $e) {
        $dbConnected = false;
        $errors[] = "File di configurazione presente ma connessione al database fallita: " . $e->getMessage();
    }
}

// GESTIONE RICHIESTE POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ($hasConfigFile && $dbConnected ? 'create_admin_only' : 'full_install');

    // CASO 1: Creazione account Admin e prima classe quando il DB è già configurato / importato manualmente
    if ($action === 'create_admin_only' && $dbConnected) {
        $adminName  = trim($_POST['admin_name'] ?? '');
        $adminEmail = trim(strtolower($_POST['admin_email'] ?? ''));
        $adminPass  = $_POST['admin_pass'] ?? '';

        $schoolName = trim($_POST['school_name'] ?? 'Istituto Comprensivo');
        $schoolYear = trim($_POST['school_year'] ?? '2026/2027');
        $className  = trim($_POST['class_name'] ?? 'Classe 1^A');
        $schoolType = trim($_POST['school_type'] ?? 'primaria');

        if (empty($adminName) || empty($adminEmail) || strlen($adminPass) < 8) {
            $errors[] = "Inserisci nome, email valida e una password di almeno 8 caratteri per l'amministratore.";
        }

        // Se le tabelle non esistono ancora, importale da schema.sql
        if (!$tablesExist && empty($errors)) {
            try {
                $schemaFile = __DIR__ . '/database/schema.sql';
                if (!file_exists($schemaFile)) {
                    throw new Exception("File database/schema.sql non trovato.");
                }
                $sql = file_get_contents($schemaFile);
                $pdo->exec($sql);
                $tablesExist = true;
            } catch (\Exception $e) {
                $errors[] = "Errore durante l'importazione automatica dello schema: " . $e->getMessage();
            }
        }

        if (empty($errors)) {
            try {
                // Se non ci sono classi, crea la prima classe
                if ($classCount === 0) {
                    $stmt = $pdo->prepare("INSERT INTO rappresentor_classes (name, school_type, school_year, school_name) VALUES (:name, :type, :year, :school)");
                    $stmt->execute([
                        'name' => !empty($className) ? $className : 'Classe 1^A',
                        'type' => $schoolType,
                        'year' => $schoolYear,
                        'school' => $schoolName
                    ]);
                    $classCount = 1;
                }

                // Inserimento Admin
                $passHash = password_hash($adminPass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO rappresentor_users (name, email, password_hash, role, is_active) VALUES (:name, :email, :pass, 'admin', 1)");
                $stmt->execute([
                    'name' => $adminName,
                    'email' => $adminEmail,
                    'pass' => $passHash
                ]);

                // Impostazioni
                $stmt = $pdo->prepare("INSERT INTO rappresentor_settings (key_name, key_value) VALUES ('school_name', :sname) ON DUPLICATE KEY UPDATE key_value = :sname_up");
                $stmt->execute(['sname' => $schoolName, 'sname_up' => $schoolName]);

                $adminCount = 1;
                $successMessage = "Account Amministratore e configurazione iniziale completati con successo!";
            } catch (\Exception $e) {
                $errors[] = "Errore durante la creazione dell'amministratore: " . $e->getMessage();
            }
        }
    }

    // CASO 2: Installazione completa (senza config.php preesistente)
    if ($action === 'full_install' && (!$hasConfigFile || $adminCount === 0)) {
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

                // 4. Inserimento Primo Utente Admin
                $passHash = password_hash($adminPass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO rappresentor_users (name, email, password_hash, role, is_active) VALUES (:name, :email, :pass, 'admin', 1)");
                $stmt->execute([
                    'name' => $adminName,
                    'email' => $adminEmail,
                    'pass' => $passHash
                ]);

                // 5. Aggiornamento impostazioni scuola
                $stmt = $pdo->prepare("INSERT INTO rappresentor_settings (key_name, key_value) VALUES ('school_name', :sname) ON DUPLICATE KEY UPDATE key_value = :sname_up");
                $stmt->execute(['sname' => $schoolName, 'sname_up' => $schoolName]);

                // 6. Scrittura file config/config.php
                $configContent = "<?php\n" .
                    "/**\n * Configurazione generata automaticamente dall'installatore Rappresentor\n */\n" .
                    "return [\n" .
                    "    'app' => [\n" .
                    "        'name' => 'Rappresentor',\n" .
                    "        'version' => '1.1.0',\n" .
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

                $hasConfigFile = true;
                $adminCount = 1;
                $successMessage = "Installazione completata con successo!";
            } catch (\Exception $e) {
                $errors[] = "Errore durante l'installazione: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setup Iniziale - Rappresentor v1.1.0</title>

  <!-- PWA Manifest & Icons -->
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="icon" type="image/svg+xml" href="assets/icons/favicon.svg">
  <link rel="alternate icon" type="image/png" href="assets/icons/favicon-32x32.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
  <meta name="theme-color" content="#2563eb">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="Rappresentor">

  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .install-container { max-width: 680px; margin: 30px auto; padding: 0 16px; }
    .install-card { background: var(--bg-surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); border: 1px solid var(--border-color); overflow: hidden; }
    .install-header { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; padding: 24px 20px; text-align: center; }
    .install-header h1 { margin: 0; font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 10px; }
    .install-header p { margin: 6px 0 0; opacity: 0.9; font-size: 0.9rem; }
    .install-body { padding: 24px; }
    .step-section { margin-bottom: 22px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color); }
    .step-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .step-title { font-size: 1.05rem; font-weight: 600; color: var(--text-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
  </style>
</head>
<body class="auth-body">
<div class="install-container">
  <div class="install-card">
    <div class="install-header">
      <h1>
        <img src="assets/icons/favicon.svg" alt="Logo" width="32" height="32" style="vertical-align: middle;">
        <span>Rappresentor</span>
        <span style="font-size:0.75rem; background:rgba(255,255,255,0.25); padding:2px 8px; border-radius:99px;">v1.1.0</span>
      </h1>
      <p>Configurazione e Inizializzazione della Piattaforma</p>
    </div>

    <div class="install-body">
      <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success">
          <div>
            <strong>🎉 <?= htmlspecialchars($successMessage) ?></strong>
            <p style="margin: 6px 0 0;">Puoi accedere ora con l'email e la password che hai appena impostato.</p>
          </div>
        </div>
        <div style="margin-top: 24px; text-align: center;">
          <a href="login.php" class="btn btn-primary btn-lg">Accedi subito al Pannello &rarr;</a>
        </div>

      <?php elseif ($hasConfigFile && $dbConnected && $adminCount > 0): ?>
        <!-- Già installato con almeno un admin -->
        <div class="alert alert-info">
          <div>
            <strong>ℹ️ Rappresentor è già installato e configurato!</strong>
            <p style="margin: 6px 0 0;">
              Il database è connesso ed è già presente un account Amministratore attivo.
            </p>
          </div>
        </div>
        <div style="text-align: center; margin-top: 24px; display: flex; flex-direction: column; gap: 12px; align-items: center;">
          <a href="login.php" class="btn btn-primary btn-lg">Vai alla schermata di Login &rarr;</a>
          <a href="password_dimenticata.php" style="font-size: 0.88rem; color: var(--text-secondary);">
            Hai dimenticato la password? Recuperala via email
          </a>
        </div>

      <?php elseif ($hasConfigFile && $dbConnected && $adminCount === 0): ?>
        <!-- Database connesso (es. import manuale) ma NESSUN admin presente -->
        <div class="alert alert-warning">
          <div>
            <strong>⚙️ Database connesso: creazione primo Amministratore</strong>
            <p style="margin: 6px 0 0;">
              È stato rilevato il file di configurazione con database collegato, ma non è ancora presente nessun account Amministratore (Rappresentante Principale). Crea subito il tuo utente per poter accedere.
            </p>
          </div>
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
          <input type="hidden" name="action" value="create_admin_only">

          <!-- Account Amministratore -->
          <div class="step-section">
            <div class="step-title">👤 Account Amministratore (Rappresentante Principale)</div>
            <div class="form-group">
              <label for="admin_name">Nome e Cognome *</label>
              <input type="text" id="admin_name" name="admin_name" class="form-control" placeholder="es. Maria Rossi" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
              <label for="admin_email">Email di Accesso *</label>
              <input type="email" id="admin_email" name="admin_email" class="form-control" placeholder="es. maria.rossi@email.it" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
              <small class="form-hint">Questa email servirà per il login e per ricevere notifiche e reset password.</small>
            </div>
            <div class="form-group">
              <label for="admin_pass">Password di Accesso (min. 8 caratteri) *</label>
              <input type="password" id="admin_pass" name="admin_pass" class="form-control" minlength="8" placeholder="••••••••" required>
            </div>
          </div>

          <!-- Se non ci sono classi create, chiedi la prima classe -->
          <?php if ($classCount === 0): ?>
            <div class="step-section">
              <div class="step-title">🏫 Prima Classe / Sezione da Gestire</div>
              <div class="form-group">
                <label for="school_name">Nome Istituto Scolastico / Plesso</label>
                <input type="text" id="school_name" name="school_name" class="form-control" placeholder="es. I.C. Don Milani" value="<?= htmlspecialchars($_POST['school_name'] ?? 'I.C. Don Milani') ?>">
              </div>
              <div class="form-grid">
                <div class="form-group">
                  <label for="class_name">Nome Sezione / Classe</label>
                  <input type="text" id="class_name" name="class_name" class="form-control" placeholder="es. 2^ A oppure Sezione Coccinelle" value="<?= htmlspecialchars($_POST['class_name'] ?? 'Classe 1^A') ?>" required>
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
                <input type="text" id="school_year" name="school_year" class="form-control" value="2026/2027" required>
              </div>
            </div>
          <?php endif; ?>

          <div style="text-align: right; margin-top: 20px;">
            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
              Crea Utente Admin e Completa Setup &rarr;
            </button>
          </div>
        </form>

      <?php else: ?>
        <!-- Installazione Completa (Nessun config.php o DB non ancora configurato) -->
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
          <input type="hidden" name="action" value="full_install">

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
              <input type="text" id="school_name" name="school_name" class="form-control" placeholder="es. I.C. Don Milani" value="<?= htmlspecialchars($_POST['school_name'] ?? 'I.C. Don Milani') ?>">
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label for="class_name">Nome Sezione / Classe</label>
                <input type="text" id="class_name" name="class_name" class="form-control" placeholder="es. 2^ A oppure Sezione Coccinelle" value="<?= htmlspecialchars($_POST['class_name'] ?? 'Classe 1^A') ?>" required>
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
              <input type="text" id="school_year" name="school_year" class="form-control" value="2026/2027" required>
            </div>
          </div>

          <!-- Amministratore -->
          <div class="step-section">
            <div class="step-title">4. Account Rappresentante Principale (Admin)</div>
            <div class="form-group">
              <label for="admin_name">Nome e Cognome *</label>
              <input type="text" id="admin_name" name="admin_name" class="form-control" placeholder="es. Maria Rossi" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label for="admin_email">Email (per il login e notifiche) *</label>
              <input type="email" id="admin_email" name="admin_email" class="form-control" placeholder="es. maria.rossi@email.it" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label for="admin_pass">Password (min. 8 caratteri) *</label>
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
