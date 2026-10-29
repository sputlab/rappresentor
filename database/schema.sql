-- Rappresentor Database Schema
-- Versione: 0.3.0
-- Tutte le tabelle utilizzano il prefisso obbligatorio `rappresentor_`
-- Charset: utf8mb4 per supporto caratteri speciali ed emoji

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tabella Impostazioni Applicazione
CREATE TABLE IF NOT EXISTS `rappresentor_settings` (
  `key_name` VARCHAR(100) NOT NULL,
  `key_value` TEXT NULL,
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabella Utenti (Collaboratori, massimo 5 attivi)
CREATE TABLE IF NOT EXISTS `rappresentor_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'rappresentante', 'collaboratore') NOT NULL DEFAULT 'rappresentante',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `reset_token` VARCHAR(100) NULL,
  `reset_expires_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_token` (`reset_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabella Classi / Sezioni
CREATE TABLE IF NOT EXISTS `rappresentor_classes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL COMMENT 'es. Sezione Nido Lattanti, Classe 3A Primaria',
  `school_type` ENUM('nido', 'infanzia', 'primaria', 'secondaria_1') NOT NULL DEFAULT 'primaria',
  `school_year` VARCHAR(20) NOT NULL DEFAULT '2026/2027',
  `school_name` VARCHAR(150) NULL COMMENT 'Nome istituto comprensivo o plesso',
  `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_classes_type` (`school_type`),
  INDEX `idx_classes_year` (`school_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabella Alunni
CREATE TABLE IF NOT EXISTS `rappresentor_students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `birth_date` DATE NULL,
  `gender` ENUM('M', 'F', 'altro') NULL,
  `allergies` TEXT NULL COMMENT 'Allergie alimentari, intolleranze, indicazioni mediche per feste',
  `notes` TEXT NULL COMMENT 'Deleghe al ritiro, preferenze o note private',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_students_class` FOREIGN KEY (`class_id`) REFERENCES `rappresentor_classes` (`id`) ON DELETE CASCADE,
  INDEX `idx_students_class` (`class_id`),
  INDEX `idx_students_name` (`last_name`, `first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabella Genitori / Tutori
CREATE TABLE IF NOT EXISTS `rappresentor_guardians` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `relation` ENUM('madre', 'padre', 'tutore', 'altro') NOT NULL DEFAULT 'madre',
  `phone` VARCHAR(50) NULL,
  `email` VARCHAR(150) NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Contatto principale da chiamare',
  `consent_whatsapp` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Consenso inserimento gruppo WhatsApp genitori',
  `notes` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_guardians_student` FOREIGN KEY (`student_id`) REFERENCES `rappresentor_students` (`id`) ON DELETE CASCADE,
  INDEX `idx_guardians_student` (`student_id`),
  INDEX `idx_guardians_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabella Raccolte Fondi / Campagne Quote
CREATE TABLE IF NOT EXISTS `rappresentor_campaigns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL COMMENT 'es. Regalo Maestre Natale, Cassa Materiali Didattici',
  `description` TEXT NULL,
  `quota_per_student` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `deadline` DATE NULL,
  `is_closed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_campaigns_class` FOREIGN KEY (`class_id`) REFERENCES `rappresentor_classes` (`id`) ON DELETE CASCADE,
  INDEX `idx_campaigns_class` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabella Pagamenti / Quote Alunni per Campagna
CREATE TABLE IF NOT EXISTS `rappresentor_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `campaign_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `status` ENUM('non_pagato', 'pagato', 'parziale', 'esonerato') NOT NULL DEFAULT 'non_pagato',
  `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('contanti', 'bonifico', 'satispay', 'paypal', 'altro') NOT NULL DEFAULT 'contanti',
  `paid_at` DATE NULL,
  `notes` VARCHAR(255) NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_payments_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `rappresentor_campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_student` FOREIGN KEY (`student_id`) REFERENCES `rappresentor_students` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uniq_campaign_student` (`campaign_id`, `student_id`),
  INDEX `idx_payments_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Tabella Movimenti Cassa (Entrate e Uscite di Classe)
CREATE TABLE IF NOT EXISTS `rappresentor_cashbox_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `campaign_id` INT NULL,
  `type` ENUM('entrata', 'uscita') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `category` VARCHAR(100) NOT NULL COMMENT 'Quote alunni, Regalo, Materiale, Spettacolo, Altro',
  `description` VARCHAR(255) NOT NULL,
  `transaction_date` DATE NOT NULL,
  `receipt_ref` VARCHAR(100) NULL COMMENT 'Riferimento scontrino/ricevuta/fattura',
  `created_by` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_cashbox_class` FOREIGN KEY (`class_id`) REFERENCES `rappresentor_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cashbox_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `rappresentor_campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cashbox_user` FOREIGN KEY (`created_by`) REFERENCES `rappresentor_users` (`id`) ON DELETE SET NULL,
  INDEX `idx_cashbox_class` (`class_id`),
  INDEX `idx_cashbox_date` (`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Tabella Eventi e Scadenze
CREATE TABLE IF NOT EXISTS `rappresentor_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `event_type` ENUM('riunione', 'gita', 'scadenza', 'festa', 'compleanno', 'altro') NOT NULL DEFAULT 'riunione',
  `event_date` DATE NOT NULL,
  `event_time` TIME NULL,
  `location` VARCHAR(150) NULL,
  `description` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_events_class` FOREIGN KEY (`class_id`) REFERENCES `rappresentor_classes` (`id`) ON DELETE CASCADE,
  INDEX `idx_events_class_date` (`class_id`, `event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Tabella Argomenti per Consiglio d'Interclasse / Classe
CREATE TABLE IF NOT EXISTS `rappresentor_interclasse_topics` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `category` ENUM('didattica', 'mensa', 'struttura_sicurezza', 'gite_uscite', 'comportamento', 'varie') NOT NULL DEFAULT 'varie',
  `status` ENUM('proposto', 'in_discussione', 'approvato', 'risolto', 'archiviato') NOT NULL DEFAULT 'proposto',
  `description` TEXT NULL,
  `outcome_notes` TEXT NULL COMMENT 'Risposte fornite dai docenti o dal dirigente scolastico',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_topics_class` FOREIGN KEY (`class_id`) REFERENCES `rappresentor_classes` (`id`) ON DELETE CASCADE,
  INDEX `idx_topics_class` (`class_id`),
  INDEX `idx_topics_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Tabella Template Messaggi WhatsApp / Email
CREATE TABLE IF NOT EXISTS `rappresentor_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `content` TEXT NOT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Tabella Assegnazione Classi a Utenti (Rappresentanti e Collaboratori)
CREATE TABLE IF NOT EXISTS `rappresentor_user_classes` (
  `user_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` INT NULL,
  PRIMARY KEY (`user_id`, `class_id`),
  CONSTRAINT `fk_uc_user` FOREIGN KEY (`user_id`) REFERENCES `rappresentor_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uc_class` FOREIGN KEY (`class_id`) REFERENCES `rappresentor_classes` (`id`) ON DELETE CASCADE,
  INDEX `idx_uc_class` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dati Iniziali: Template Predefiniti per i Rappresentanti
INSERT INTO `rappresentor_templates` (`title`, `category`, `content`, `is_default`) VALUES
('Raccolta Quote Fondo Cassa', 'Cassa', '👋 Ciao a tutti i genitori di {CLASSE}!\n\nVi riepiloghiamo le raccolte quote attualmente attive per la nostra classe:\n{DETTAGLIO_QUOTE}\n\nModalità di versamento disponibili:\n- In contanti (in bustina con nome dell alunno)\n- Satispay / Bonifico (contattatemi in privato per i dettagli)\n\nGrazie a tutti per la collaborazione!\nI Rappresentanti di Classe', 1),

('Promemoria Pagamento Quota', 'Cassa', '📢 *Promemoria per i genitori di {CLASSE}*\n\nVi ricordiamo le raccolte quote attualmente in corso per la nostra classe:\n{DETTAGLIO_QUOTE}\n\nModalità di versamento disponibili:\n- In contanti (in bustina con nome dell alunno)\n- Satispay / Bonifico (contattatemi in privato per i dettagli)\n\nChi non avesse ancora provveduto può farlo nei prossimi giorni. Se avete dubbi o difficoltà scrivetemi pure in privato!\nGrazie mille 😊', 1),

('Rendiconto Cassa Trasparente', 'Cassa', '📊 *Rendiconto Cassa di Classe - {CLASSE}*\nAggiornato al: {DATA_OGGI}\n\n💰 *Saldo attuale in cassa:* {SALDO_CASSA} €\n\nUltime uscite sostenute:\n{LISTA_USCITE}\n\nGrazie a tutti per la trasparenza e il supporto continuo alle attività della classe! I giustificativi e scontrini sono a disposizione per chiunque desideri visionarli.', 1),

('Convocazione Assemblea / Consiglio', 'Riunioni', '📣 *Avviso ai Genitori - {CLASSE}*\n\nSi terrà a breve la riunione per: *{NOME_EVENTO}*.\n📅 Data: *{DATA_EVENTO}* alle ore *{ORA_EVENTO}*\n📍 Luogo: *{LUOGO}*\n\nOrdine del giorno / Punti da trattare:\n{DESCRIZIONE_EVENTO}\n\nSe avete domande o argomenti particolari da portare al tavolo dei docenti, segnalatemeli pure qui o in privato!\nVi aspettiamo numerosi.', 1),

('Avviso Sciopero / Orario Ridotto', 'Scuola', '⚠️ *Comunicazione Importante - {CLASSE}*\n\nSi comunica che per la giornata di *{DATA_EVENTO}* è stato proclamato uno sciopero del personale scolastico.\nSi raccomanda di accompagnare personalmente i bambini la mattina per verificare l effettivo ingresso e l orario garantito.\n\nBuona giornata!', 1),

('Gita Scolastica / Uscita Didattica', 'Gite', '🚌 *Uscita Didattica / Gita Scolastica - {CLASSE}* 🎒\n\nGentili genitori,\nvi comunichiamo che è in programma un uscita didattica per la classe:\n📍 *Destinazione:* {LUOGO}\n📅 *Data:* {DATA_EVENTO}\n⏰ *Orario:* ritrovo ore *{ORA_EVENTO}* (rientro previsto ore *{ORA_RIENTRO}*)\n💰 *Quota per alunno:* {QUOTA} €\n⏳ *Termine adesione e versamento:* {SCADENZA}\n\n*Programma e dettagli:*\n{DESCRIZIONE_EVENTO}\n\nRaccomandiamo abbigliamento comodo e pranzo al sacco (se previsto).\nPer qualsiasi chiarimento siamo a disposizione!\nI Rappresentanti di Classe', 1),

('Compleanno / Festa di Classe', 'Feste', '🎉 *Festa di Compleanno in arrivo!* 🎂\n\nFesteggiamo il compleanno di *{FESTEGGIATO}* ({CLASSE})!\n📅 Data: *{DATA_EVENTO}* ore *{ORA_EVENTO}*\n📍 Dove: *{LUOGO}*\n\n⚠️ *Attenzione allergie*: vi ricordiamo di rispettare le indicazioni alimentari condivise per la sicurezza di tutti i bambini.', 1);

-- Impostazioni di default
INSERT INTO `rappresentor_settings` (`key_name`, `key_value`) VALUES
('app_name', 'Rappresentor'),
('app_version', '0.3.0'),
('school_year', '2026/2027'),
('max_users', '5'),
('smtp_enabled', '0'),
('smtp_host', 'localhost'),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_secure', 'tls'),
('smtp_from_email', 'noreply@miodominio.it'),
('smtp_from_name', 'Rappresentor')
ON DUPLICATE KEY UPDATE `key_name`=`key_name`;

SET FOREIGN_KEY_CHECKS = 1;
