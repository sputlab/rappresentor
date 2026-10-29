# Changelog

Tutti i cambiamenti degni di nota a questo progetto saranno documentati in questo file.
Il formato è basato su [Keep a Changelog](https://keepachangelog.com/it/1.0.0/),
e questo progetto aderisce a [Semantic Versioning](https://semver.org/lang/it/).

## [0.9.0] - 2026-10-29
### Aggiunto
- **Nuovo Set Ufficiale Icone per WebApp e PWA**:
  - Implementata la nuova icona ufficiale dell'applicazione con edificio scolastico centrale, portale blu, figure di rappresentante e alunno e fumetto verde per il dialogo scuola-famiglia su gradiente ciano-smeraldo.
  - Generato il set completo di formati e risoluzioni grafiche:
    - Master icon ad altissima definizione (`assets/icons/master-icon.png` a 1024x1024)
    - Icone standard PWA (`assets/icons/icon-512x512.png` e `assets/icons/icon-192x192.png`)
    - Versioni Android *Maskable* con margini per icone adattive (`assets/icons/icon-maskable-512x512.png` e `assets/icons/icon-maskable-192x192.png`)
    - Icona Apple Touch per iOS e iPadOS (`assets/icons/apple-touch-icon.png` a 180x180)
    - Favicons multi-risoluzione (`assets/icons/favicon-32x32.png`, `assets/icons/favicon-16x16.png`, `assets/icons/favicon.ico` e root `favicon.ico`)
    - Versioni vettoriali SVG per WebApp (`assets/icons/master-icon.svg`, `assets/icons/master-icon-maskable.svg`, `assets/icons/favicon.svg`, `assets/img/logo.svg`).
  - Sostituita la nuova icona in tutta l'applicazione (login, installazione, reset password, testata, drawer, PWA, guida utente).

### Modificato
- Aggiornato Service Worker PWA (`rappresentor-cache-v0.9.0`) e cache-busting per tutti gli asset grafici.
- Aggiornata la documentazione in `README.md` e la versione applicativa globale a `0.9.0`.

## [0.8.0] - 2026-10-29
### Aggiunto
- **Nuova Icona Ufficiale dell'Applicazione**:
  - Implementata la nuova icona ufficiale dell'applicazione, raffigurante la scuola, le figure stilizzate del rappresentante di classe e dell'alunno, e il fumetto di dialogo della comunità scuola-famiglia su gradiente ciano-verde smeraldo.
  - Generato il set completo di icone per tutte le risoluzioni e piattaforme:
    - Master icon ad altissima definizione (`assets/icons/master-icon.png` a 1024x1024)
    - Icone standard PWA (`icon-512x512.png` e `icon-192x192.png`)
    - Versioni *Maskable* per Android con margini di sicurezza per adaptive icon (`icon-maskable-512x512.png` e `icon-maskable-192x192.png`)
    - Icona per iPhone e iPad (`apple-touch-icon.png` a 180x180)
    - Favicons multi-risoluzione (`favicon-32x32.png`, `favicon-16x16.png`, `favicon.ico` in root e assets)
    - Versioni vettoriali SVG per WebApp (`master-icon.svg`, `master-icon-maskable.svg`, `favicon.svg`, `logo.svg`).
  - Sostituita la nuova icona in tutta l'applicazione: schermate di login, recupero e reimpostazione password, installazione, testata dell'applicazione, banner PWA, guida utente e documentazione.

### Modificato
- Aggiornato Service Worker PWA (`rappresentor-cache-v0.8.0`) e cache-busting per tutti gli asset grafici.
- Aggiornata la documentazione in `README.md` e la testata della `guida.php` con la nuova icona ufficiale.

## [0.7.0] - 2026-10-29
### Aggiunto
- **Modifica Raccolta Quote Esistente (`cassa.php`)**:
  - Aggiunto il pulsante `✏️ Modifica Raccolta` e il modale dedicato `#modalEditCampaign` per aggiornare in qualsiasi momento titolo, quota richiesta per alunno, scadenza e note descrittive della campagna selezionata.
  - Implementata l'azione di backend `edit_campaign` con verifica di sicurezza sull'appartenenza alla classe attiva e token CSRF.
- **Nuovo Set Icone Moderno per WebApp e PWA**:
  - Riprogettata l'icona master vettoriale SVG (`assets/icons/master-icon.svg` e `assets/icons/master-icon-maskable.svg`) con uno stile contemporaneo ed elegante: gradiente vibrante indaco-azzurro, tocco accademico tridimensionale con nappa dorata, libro/ali di connessione scuola-famiglia e stelle guida luminose.
  - Rigenerati tutti gli asset grafici raster ad alta definizione: `icon-512x512.png`, `icon-192x192.png`, `icon-maskable-512x512.png`, `icon-maskable-192x192.png`, `apple-touch-icon.png` (180x180), `favicon-32x32.png`, `favicon-16x16.png`, `favicon.svg` e `favicon.ico`.
  - Aggiornato `assets/img/logo.svg` e tutte le schermate applicative (login, password dimenticata, installazione, testata desktop e burger menu).
- **Documentazione Estesa & Pagina Guida (`guida.php`)**:
  - Creata la pagina dedicata `guida.php` con manuale d'istruzioni esteso suddiviso in 12 sezioni tematiche.
  - Sostituito il link "Installa App" nel menu laterale burger con il link `📖 Guida`.
  - Istruzioni pratiche passo-passo su come installare l'applicazione come PWA su iPhone/iPad (Safari), Android (Chrome/Samsung) e computer desktop (Chrome/Edge).
  - Indice interattivo dei contenuti (TOC) con salto rapido alle sezioni tramite ancore.
  - Pulsante floating responsive **"↑ Torna in Cima"** (`#btnScrollTop`) per risalire agevolmente alla sommità della guida.

### Modificato
- Aggiornato Service Worker PWA (`rappresentor-cache-v0.7.0`) e cache-busting asset.

## [0.6.0] - 2026-10-29
### Aggiunto
- **Messaggi WhatsApp con Tutte le Campagne Quote Attive**:
  - Risolto il limite per cui i messaggi e promemoria WhatsApp riportavano solamente l'ultima campagna creata.
  - Implementata la funzione `Helper::getActiveCampaignsSummary()` che aggrega tutte le campagne quote aperte per la classe attiva, elencando per ciascuna titolo, quota per alunno, scadenza e calcolando il totale complessivo richiesto ad alunno.
  - Introdotto il segnaposto dinamico `{DETTAGLIO_QUOTE}` (con alias `{CAMPAGNE_ATTIVE}` e `{LISTA_QUOTE}`) per includere nei modelli WhatsApp l'elenco completo formattato per chat di gruppo.
  - Aggiornati i template predefiniti di sistema ("Raccolta Quote Fondo Cassa" e "Promemoria Pagamento Quota") con migrazione automatica in `includes/init.php`.
  - Nuovo pulsante e modale **"📱 Avviso Quote WA"** nella pagina Cassa (`cassa.php`) per copiare istantaneamente il testo di riepilogo con tutte le raccolte attive della classe.
- **Aggiornamento Automatico Totali Cassa (Entrate, Uscite e Saldo)**:
  - Implementato in `Helper::getClassCashSummary()` il calcolo finanziario in tempo reale: i totali in entrata e il saldo disponibile della cassa si aggiornano automaticamente ogni volta che viene registrato il versamento di una quota alunno.
  - Mantenuta la completa possibilità di registrare manualmente movimenti in entrata (es. avanzo anni precedenti, donazioni) e in uscita (scontrini, cancelleria, gite, regali docenti).
  - Dettaglio chiaro nella scheda "Totale Entrate" di `cassa.php` con distinzione tra quote versate dagli alunni ed entrate manuali.
  - Aggiunta la possibilità di eliminare movimenti manuali registrati nel registro cassa con conferma di sicurezza.
  - Allineato il calcolo del saldo in tempo reale nella Dashboard (`index.php`), nella Bacheca WhatsApp (`comunicazioni.php`) e nella Gestione Classi (`classi.php`).
  - Auto-inizializzazione dei pagamenti per le campagne aperte all'aggiunta di un nuovo alunno in `alunni.php`.

### Modificato
- Aggiornato Service Worker PWA (`rappresentor-cache-v0.6.0`) e cache-busting asset.

## [0.5.0] - 2026-10-29
### Aggiunto
- **Assegnazione Classi a Rappresentanti e Collaboratori (Multi-classe Role-Based Access Control)**:
  - Nuova tabella `rappresentor_user_classes` per associare in modo granulare gli utenti alle classi di loro competenza.
  - **Amministratori**: possono assegnare qualsiasi classe sia ai Rappresentanti che ai Collaboratori.
  - **Rappresentanti**: possono gestire i collaboratori per le classi a cui sono assegnati, invitando nuovi collaboratori o aggiornando l'assegnazione delle sole classi di loro competenza, senza alterare altre assegnazioni.
  - **Visibilità Contestuale Ristretta**: i Rappresentanti e Collaboratori visualizzano esclusivamente le classi assegnate nel selettore di classe (barra desktop e burger menu mobile) e nelle rispettive aree applicative (Alunni, Cassa, Calendario, Consigli, Comunicazioni).
  - **Team Assegnato nelle Schede Classe (`classi.php`)**: visualizzazione visiva con badge colorati di rappresentanti, collaboratori e amministratori assegnati a ciascuna classe.
  - Auto-associazione della classe creata al profilo del Rappresentante che l'ha generata.
  - Auto-migrazione automatica dello schema in `includes/init.php` (`CREATE TABLE IF NOT EXISTS rappresentor_user_classes`) al primo avvio su database esistenti.
- **Interfaccia Collaboratori per Rappresentanti (`utenti.php`)**:
  - Accesso abilitato alla pagina collaboratori sia per gli amministratori che per i rappresentanti (`Auth::canManageUsers()`).
  - Griglia responsive con checkbox (`.class-checkbox-grid`) per la selezione intuitiva delle classi assegnabili sia in creazione che in modifica utente.
  - Badge identificativi delle classi assegnate per ogni riga utente nella tabella.

### Modificato
- Aggiornato cache-busting asset e Service Worker PWA (`rappresentor-cache-v0.5.0`).
- Risolto avviso di deprecazione PHP 8.5 su `PDO::MYSQL_ATTR_INIT_COMMAND` con fallback retrocompatibile.

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
