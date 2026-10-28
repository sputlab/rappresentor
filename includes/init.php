<?php
declare(strict_types=1);

/**
 * Inizializzazione applicativa di base
 * Versione SemVer: 0.1.0
 */

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
    } catch (\PDOException $e) {
        if (!$isInstallPage) {
            die("Errore di connessione al database. Verifica i parametri in config/config.php o esegui install.php.<br>Dettagli: " . htmlspecialchars($e->getMessage()));
        }
    }
}
