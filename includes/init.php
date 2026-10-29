<?php
declare(strict_types=1);

/**
 * Inizializzazione applicativa di base
 * Versione SemVer: 0.10.0
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

        // Aggiorna template predefiniti per includere tutte le quote attive (v0.5.1)
        try {
            $db->exec("UPDATE `rappresentor_templates` 
                SET `content` = '👋 Ciao a tutti i genitori di {CLASSE}!\n\nVi riepiloghiamo le raccolte quote attualmente attive per la nostra classe:\n{DETTAGLIO_QUOTE}\n\nModalità di versamento disponibili:\n- In contanti (in bustina con nome dell alunno)\n- Satispay / Bonifico (contattatemi in privato per i dettagli)\n\nGrazie a tutti per la collaborazione!\nI Rappresentanti di Classe'
                WHERE `title` = 'Raccolta Quote Fondo Cassa' AND `is_default` = 1 AND `content` LIKE '%Abbiamo avviato la raccolta quote%'");

            $db->exec("UPDATE `rappresentor_templates` 
                SET `content` = '📢 *Promemoria per i genitori di {CLASSE}*\n\nVi ricordiamo le raccolte quote attualmente in corso per la nostra classe:\n{DETTAGLIO_QUOTE}\n\nChi non avesse ancora provveduto può farlo nei prossimi giorni. Se avete dubbi o difficoltà scrivetemi pure in privato!\nGrazie mille 😊'
                WHERE `title` = 'Promemoria Pagamento Quota' AND `is_default` = 1 AND `content` LIKE '%Vi ricordiamo che è ancora in corso la raccolta quote%'");
        } catch (\Throwable $e) {
            // Silenzioso se la tabella non è ancora presente
        }
    } catch (\PDOException $e) {
        if (!$isInstallPage) {
            die("Errore di connessione al database. Verifica i parametri in config/config.php o esegui install.php.<br>Dettagli: " . htmlspecialchars($e->getMessage()));
        }
    }
}
