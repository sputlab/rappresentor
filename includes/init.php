<?php
declare(strict_types=1);

/**
 * Inizializzazione applicativa di base
 * Versione SemVer: 1.3.0
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

        // Chiusura automatica pulita della connessione al termine dello script per evitare sovraccarichi pool MySQL
        register_shutdown_function(function() {
            \Rappresentor\Core\Database::closeConnection();
        });

        // Esegue le verifiche di migrazione schema/template solo una volta per sessione (evita overhead e lock multipli su DB ad ogni richiesta HTTP)
        if (empty($_SESSION['_db_migrated_v101'])) {
            try {
                // Assicura colonna relation supporti nonna, nonno, ecc. (v1.0.1)
                $db->exec("ALTER TABLE `rappresentor_guardians` MODIFY `relation` VARCHAR(50) NOT NULL DEFAULT 'madre'");

                // Assicura esistenza tabella assegnazione classi (v0.5.0)
                $db->exec("CREATE TABLE IF NOT EXISTS `rappresentor_user_classes` (
                    `user_id` INT NOT NULL,
                    `class_id` INT NOT NULL,
                    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `assigned_by` INT NULL,
                    PRIMARY KEY (`user_id`, `class_id`),
                    INDEX `idx_uc_class` (`class_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                // Aggiorna template predefiniti per includere tutte le quote attive (v0.5.1)
                $db->exec("UPDATE `rappresentor_templates` 
                    SET `content` = '👋 Ciao a tutti i genitori di {CLASSE}!\n\nVi riepiloghiamo le raccolte quote attualmente attive per la nostra classe:\n{DETTAGLIO_QUOTE}\n\nModalità di versamento disponibili:\n- In contanti (in bustina con nome dell alunno)\n- Satispay / Bonifico (contattatemi in privato per i dettagli)\n\nGrazie a tutti per la collaborazione!\nI Rappresentanti di Classe'
                    WHERE `title` = 'Raccolta Quote Fondo Cassa' AND `is_default` = 1 AND `content` LIKE '%Abbiamo avviato la raccolta quote%'");

                $db->exec("UPDATE `rappresentor_templates` 
                    SET `content` = '📢 *Promemoria per i genitori di {CLASSE}*\n\nVi ricordiamo le raccolte quote attualmente in corso per la nostra classe:\n{DETTAGLIO_QUOTE}\n\nModalità di versamento disponibili:\n- In contanti (in bustina con nome dell alunno)\n- Satispay / Bonifico (contattatemi in privato per i dettagli)\n\nChi non avesse ancora provveduto può farlo nei prossimi giorni. Se avete dubbi o difficoltà scrivetemi pure in privato!\nGrazie mille 😊'
                    WHERE `title` = 'Promemoria Pagamento Quota' AND `is_default` = 1 AND `content` NOT LIKE '%Modalità di versamento%'");

                // Template Gita Scolastica / Uscita Didattica
                $checkGita = (int)$db->query("SELECT COUNT(*) FROM `rappresentor_templates` WHERE `title` = 'Gita Scolastica / Uscita Didattica'")->fetchColumn();
                if ($checkGita === 0) {
                    $db->exec("INSERT INTO `rappresentor_templates` (`title`, `category`, `content`, `is_default`) VALUES (
                        'Gita Scolastica / Uscita Didattica',
                        'Gite',
                        '🚌 *Uscita Didattica / Gita Scolastica - {CLASSE}* 🎒\n\nGentili genitori,\nvi comunichiamo che è in programma un uscita didattica per la classe:\n📍 *Destinazione:* {LUOGO}\n📅 *Data:* {DATA_EVENTO}\n⏰ *Orario:* ritrovo ore *{ORA_EVENTO}* (rientro previsto ore *{ORA_RIENTRO}*)\n💰 *Quota per alunno:* {QUOTA} €\n⏳ *Termine adesione e versamento:* {SCADENZA}\n\n*Programma e dettagli:*\n{DESCRIZIONE_EVENTO}\n\nRaccomandiamo abbigliamento comodo e pranzo al sacco (se previsto).\nPer qualsiasi chiarimento siamo a disposizione!\nI Rappresentanti di Classe',
                        1
                    )");
                }

                // Aggiorna template Compleanno rimuovendo la parte finale
                $db->exec("UPDATE `rappresentor_templates` 
                    SET `content` = '🎉 *Festa di Compleanno in arrivo!* 🎂\n\nFesteggiamo il compleanno di *{FESTEGGIATO}* ({CLASSE})!\n📅 Data: *{DATA_EVENTO}* ore *{ORA_EVENTO}*\n📍 Dove: *{LUOGO}*\n\n⚠️ *Attenzione allergie*: vi ricordiamo di rispettare le indicazioni alimentari condivise per la sicurezza di tutti i bambini.'
                    WHERE `title` = 'Compleanno / Festa di Classe' AND `is_default` = 1 AND `content` LIKE '%Vi preghiamo di confermare%'");

                $_SESSION['_db_migrated_v101'] = true;
            } catch (\Throwable $e) {
                // Silenzioso se la tabella non è ancora presente
            }
        }
    } catch (\PDOException $e) {
        if (!$isInstallPage) {
            die("Errore di connessione al database. Verifica i parametri in config/config.php o esegui install.php.<br>Dettagli: " . htmlspecialchars($e->getMessage()));
        }
    }
}
