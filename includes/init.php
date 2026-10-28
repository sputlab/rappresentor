<?php
declare(strict_types=1);

/**
 * Inizializzazione applicativa di base
 * Versione SemVer: 0.5.0
 */

// Avvia output buffering per prevenire problemi di headers already sent con redirect HTTP
if (ob_get_level() === 0) {
    ob_start();
}

// Autoloader per classi in core/
spl_autoload_register(function ($class) {
    $prefix = 'Rappresentor\\Core\\';
    $baseDir = __DIR__ . '/../core/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Controllo installazione
$configFile = __DIR__ . '/../config/config.php';
$isInstallPage = str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', 'install.php');

if (!file_exists($configFile) && !$isInstallPage) {
    header('Location: install.php');
    exit;
}

if (file_exists($configFile)) {
    try {
        $db = \Rappresentor\Core\Database::getConnection();
        \Rappresentor\Core\Auth::initSession();

        // Assicura esistenza tabella assegnazione classi (v0.5.0)
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS `rappresentor_user_classes` (
                `user_id` INT NOT NULL,
                `class_id` INT NOT NULL,
                `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `assigned_by` INT NULL,
                PRIMARY KEY (`user_id`, `class_id`),
                INDEX `idx_uc_class` (`class_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Throwable $e) {
            // Tabella già presente o permessi limitati
        }
    } catch (\PDOException $e) {
        if (!$isInstallPage) {
            die("Errore di connessione al database. Verifica i parametri in config/config.php o esegui install.php.<br>Dettagli: " . htmlspecialchars($e->getMessage()));
        }
    }
}
