# Changelog

Tutti i cambiamenti degni di nota a questo progetto saranno documentati in questo file.
Il formato è basato su [Keep a Changelog](https://keepachangelog.com/it/1.0.0/),
e questo progetto aderisce a [Semantic Versioning](https://semver.org/lang/it/).

## [0.4.0] - 2026-10-29
### Aggiunto
- **Modifica Collaboratori Esistenti**: implementata la funzionalità completa di modifica in `utenti.php` con modale dedicato. Gli amministratori possono ora aggiornare nome, indirizzo email, ruolo (`admin`, `rappresentante`, `collaboratore`), stato di attivazione e reimpostare opzionalmente la password dei collaboratori.
- **Pagina Changelog & Novità (`changelog.php`)**: introdotta una pagina interna dedicata che visualizza la cronologia di tutti i rilasci dell'applicazione con schede visive, badge semantici per ogni categoria e indicazione della versione attualmente installata.
- **Selettore Classe Mobile nel Burger Menu**: su schermi mobile (< 992px), il selettore della classe/sezione attiva è stato spostato dalla barra di navigazione superiore all'interno del menu laterale (burger menu), eliminando il sovraffollamento visivo della testata su smartphone.

### Modificato
- **Pulizia Titolo Header**: rimosso il badge con la versione (`v...`) dal logo/titolo principale "Rappresentor" nella barra di navigazione superiore per una resa visiva più pulita.
- **Footer Burger Menu**: sostituita l'etichetta `SemVer` con `Rappresentor` e trasformato il numero di versione in un link interattivo che conduce direttamente a `changelog.php`.
- Aggiornato cache-busting asset (`?v=0.4.0`) e Service Worker PWA (`rappresentor-cache-v0.4.0`).

## [0.3.4] - 2026-10-28
### Corretto
- **Scorrimento Verticale Modali (Nuovo Alunno & Form)**: risolto il blocco dello scorrimento nei modali con form estesi (come *Nuovo Alunno* in `alunni.php` contenente dati studente, note, allergie e recapiti famiglia).
- Configurato `display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0;` per il tag `<form>` all'interno dei modali, trasmettendo correttamente il vincolo di altezza del contenitore a `.modal-body`.
- Aggiunto `min-height: 0;`, `flex: 1 1 auto;`, `-webkit-overflow-scrolling: touch;` e `overscroll-behavior: contain;` a `.modal-body` per garantire uno scorrimento fluido inerziale su iOS/Android e desktop.
- Fissato il `.modal-footer` (`flex-shrink: 0`) in fondo con supporto alla safe area (`env(safe-area-inset-bottom)`), garantendo che i pulsanti di azione ("Annulla" e "Salva") rimangano sempre visibili e raggiungibili.
- Implementata la chiusura dei modali con il tasto `Escape`, il riposizionamento dello scroll in cima all'apertura e il focus automatico sul primo campo.
- Aggiunto il versioning cache-busting per gli asset CSS e JS (`?v=0.3.4`) e aggiornata la cache del Service Worker.

## [0.3.3] - 2026-10-28
### Corretto
- **Headers Already Sent e Flusso Redirect**: risolto l'errore `Warning: Cannot modify header information - headers already sent by .../includes/header.php` durante i salvataggi e redirect HTTP (ad es. in `impostazioni.php`, `alunni.php`, `cassa.php`, `utenti.php`, `classi.php`, `calendario.php`, `consigli.php`, `comunicazioni.php`).
- Separata l'esecuzione della logica applicativa (controllo permessi utente, validazione token CSRF, salvataggi POST, export file CSV ed emissione di header di reindirizzamento HTTP `Location: ...`) dall'inclusione del template visivo (`includes/header.php`).
- Inizializzato l'output buffering (`ob_start()`) in `includes/init.php` per proteggere l'applicazione da flush precoci dell'output buffer su qualsiasi server di hosting condiviso.
- Garantita l'integrità dei download CSV in `alunni.php`, impedendo che frammenti di codice HTML precedessero l'output del flusso CSV.

## [0.3.2] - 2026-10-28
### Corretto
- **Errore PDO in Impostazioni**: risolta l'eccezione `SQLSTATE[HY093]: Invalid parameter number` durante il salvataggio dei parametri in `impostazioni.php` (e `install.php`). Con i prepared statements nativi di MySQL (`PDO::ATTR_EMULATE_PREPARES = false`), i parametri con lo stesso nome non possono essere riutilizzati due volte all'interno della clausola `ON DUPLICATE KEY UPDATE`; i parametri sono stati resi univoci (`:v` e `:v_update`).
- **Flessibilità Limite Collaboratori**: aumentato il limite massimo configurabile da 10 a 50 collaboratori nel campo SaaS, per supportare istituti comprensivi con più classi e sezioni.
- Rimosso il testo statico `(max 5)` dalla voce di menu laterale, rendendolo coerente con qualsiasi limite impostato dall'amministratore.

## [0.3.1] - 2026-10-28
### Corretto
- **Setup iniziale e importazione manuale**: risolto il problema per cui `install.php`, rilevando il file `config/config.php` creato manualmente o in seguito a import manuale di `schema.sql`, considerava l'applicazione già installata senza consentire la creazione del primo account amministratore.
- `install.php` ora controlla l'effettiva presenza di utenti admin attivi nel database: se il database è connesso ma non è presente alcun amministratore, viene mostrato il form dedicato per registrare il primo utente amministratore e la prima classe.
- `login.php` reindirizza automaticamente a `install.php` se rileva l'assenza di account amministratore nel database.

## [0.3.0] - 2026-10-28
### Aggiunto
- **Master Icon Ufficiale**:
  - Progettata l'icona master vettoriale SVG con simbolo del tocco accademico, scudo di rappresentanza scuola-famiglia e stella dorata (`assets/icons/master-icon.svg` e `assets/icons/master-icon-maskable.svg`).
  - Generato l'intero set di icone raster PNG ad alta definizione: `icon-512x512.png`, `icon-192x192.png`, versioni maskable adattive per Android (`icon-maskable-512x512.png`, `icon-maskable-192x192.png`), icona Apple iOS (`apple-touch-icon.png`), favicons multi-risoluzione (`favicon-32x32.png`, `favicon-16x16.png`, `favicon.svg`, `favicon.ico`).
- **Trasformazione in Progressive Web App (PWA)**:
  - Creato Web App Manifest standard W3C (`manifest.webmanifest` e `manifest.json`) con scorciatoie rapide per *Alunni*, *Cassa*, *WhatsApp* e *Calendario*.
  - Implementato Service Worker (`sw.js`) con cache versionata, pre-caching della shell applicativa e strategia di navigazione *Network-First* con fallback offline.
  - Pagina dedicata di fallback per assenza di connessione (`offline.html`) per garantire continuità di utilizzo nei plessi scolastici con scarsa ricezione.
  - Integrazione banner e trigger interattivo di installazione per smartphone (Android `beforeinstallprompt` ed istruzioni guidate per Safari iOS).
  - Configurazione MIME types e regole di caching specifiche in `.htaccess`.

## [0.2.0] - 2026-10-28
### Aggiunto
- **Prefisso tabelle Database**: implementato il prefisso obbligatorio `rappresentor_` su tutte le 11 tabelle del database MySQL e relative query applicative (`rappresentor_settings`, `rappresentor_users`, `rappresentor_classes`, `rappresentor_students`, `rappresentor_guardians`, `rappresentor_campaigns`, `rappresentor_payments`, `rappresentor_cashbox_transactions`, `rappresentor_events`, `rappresentor_interclasse_topics`, `rappresentor_templates`).
- **Interfaccia Mobile-First**:
  - Barra di navigazione fissa inferiore (`mobile-bottom-nav`) per accesso immediato con il pollice su smartphone alle sezioni chiave: Home, Alunni, Cassa, WhatsApp e Altro.
  - Menu a comparsa laterale (Slide-Over Drawer con backdrop) per la navigazione secondaria su schermi piccoli.
  - Dimensionamento touch-friendly con altezza minima interattiva di 44px e prevenzione dell'auto-zoom su browser mobili (Safari iOS / Chrome Android).
  - Gestione sicura del safe area inset (`env(safe-area-inset-bottom)`) per schermi moderni con indicatori di gesture.
  - Griglie fluide a colonna singola su mobile con espansione progressiva a 2, 3 e 4 colonne su tablet e desktop.
  - Finestre modali adattive visualizzate come Bottom Sheet su schermi ridotti.

## [0.1.0] - 2026-10-28
### Aggiunto
- Struttura base del progetto PHP 8+ nativo senza dipendenze esterne pesanti.
- Architettura multi-ordine scolastico: Asilo Nido, Scuola dell'Infanzia, Primaria (Elementari) e Secondaria di I Grado (Medie).
- Sistema di autenticazione con hashing sicuro delle password (bcrypt) e limite a max 5 utenti/collaboratori attivi.
- Meccanismo di recupero password tramite token sicuro inviato via email con validità temporale.
- Motore di invio email con supporto integrato per SMTP autenticato (SSL/TLS) e fallback su `mail()` nativo di PHP.
- Gestione anagrafica alunni e genitori (recapiti telefonici, email, consenso WhatsApp, note e allergie).
- Generatore rapido per rubrica WhatsApp e link diretti di chiamata/messaggio (`wa.me`).
- Gestione Cassa e Fondi di Classe:
  - Creazione campagne di raccolta quote (es. regalo maestre, materiale didattico, uscite).
  - Tracciamento rapido pagamenti per singolo alunno con modalità (contanti, bonifico, Satispay, PayPal).
  - Registro entrate e uscite con scontrini e categorie.
  - Generazione automatica di rendiconti di cassa formattati per chat WhatsApp dei genitori.
- Bacheca comunicazioni e generatore messaggi formattati con template personalizzabili.
- Calendario scadenze, riunioni (Consigli d'Interclasse/Classe), gite e compleanni alunni.
- Quaderno argomenti per i Consigli d'Interclasse/Classe (raccolta istanze dei genitori con tracciamento stato).
- Wizard di installazione automatica del database (`install.php`) e configurazione guidata.
- Interfaccia responsive mobile-first ottimizzata per smartphone, tablet e desktop.
