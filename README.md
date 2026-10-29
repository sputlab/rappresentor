# 🎓 Rappresentor

**Versione Corrente:** `0.11.0` (Aderente a [Semantic Versioning 2.0.0](https://semver.org/lang/it/))  
**Stack Tecnologico:** HTML5 • PHP 8.1+ (Nativo/Zero dipendenze esterne pesanti) • JavaScript (Vanilla Mobile-First & Service Worker) • Progressive Web App (PWA) • MySQL / MariaDB (prefisso tabelle `rappresentor_`)  
**Modello di Utilizzo:** Micro-SaaS Self-Hosted su dominio proprietario (supporto fino a un massimo di 5 collaboratori attivi in team).

---

## 📌 Cos'è Rappresentor?

**Rappresentor** è una Progressive Web App (PWA) progettata specificamente per supportare i **rappresentanti di classe** delle scuole italiane nello svolgimento delle loro tipiche attività di coordinamento. L'interfaccia è progettata con un approccio **Mobile-First** e può essere **installata direttamente come applicazione nativa** sulla schermata Home di smartphone iOS e Android o su desktop.

L'applicazione è concepita per essere adatta e modulabile per tutti i gradi scolastici:
- 👶 **Asilo Nido** (0-3 anni: sezioni lattanti, semidivezzi, divezzi)
- 🎨 **Scuola dell'Infanzia / Materna** (3-6 anni: sezioni eterogenee o omogenee)
- 🎒 **Scuola Primaria / Elementari** (6-11 anni: classi 1ª-5ª)
- 📐 **Scuola Secondaria di I Grado / Medie** (11-14 anni: classi 1ª-3ª)

---

## 📲 Funzionalità Progressive Web App (PWA)

- **Installabile con un click**: supporto nativo per l'installazione su Android, iOS (Aggiungi a Schermata Home) e Desktop (Chrome/Edge/Safari).
- **Nuova Icona Ufficiale & Set Completo PWA**: Nuova icona (`assets/icons/master-icon.png`, `assets/icons/master-icon.svg`), icone 512x512, 192x192, versioni *Maskable* per Android (`icon-maskable-512x512.png`, `icon-maskable-192x192.png`), `apple-touch-icon.png` per iPhone/iPad e favicons multi-risoluzione. Raffigura la collaborazione tra scuola, rappresentante e famiglie con il dialogo continuo della comunità scolastica.
- **Supporto Offline & Service Worker (`sw.js`)**: caching intelligente degli asset applicativi e pagina di cortesia dedicata ([`offline.html`](file:///Users/stefanosoranzio/Sputlab/WebApps/Rappresentor/offline.html)) nel caso in cui la connessione cada all'interno degli edifici scolastici.
- **Web App Manifest (`manifest.webmanifest`)**: definisce colori del tema (`#2563eb`), modalità *standalone* a tutto schermo e **scorciatoie rapide** (Alunni, Cassa, WhatsApp, Calendario) accessibili tenendo premuta l'icona dell'app sullo smartphone.

---

## 🚀 Caratteristiche Principali

### 📱 Esperienza Mobile-First & Responsive
- **Barra di Navigazione Inferiore Fissa (Mobile Bottom Bar)**: pensata per l'uso immediato a una mano su smartphone, offre accesso istantaneo a Home, Alunni, Cassa, WhatsApp e Altro.
- **Slide-Over Drawer**: menu laterale a scomparsa per le funzioni avanzate e le impostazioni su schermi piccoli.
- **Target Touch Ergonomici**: tutti i pulsanti e gli input rispettano le linee guida di accessibilità touch (minimo 44px di altezza) con font a 16px per evitare lo sgradevole auto-zoom di Safari su iPhone.
- **Supporto Safe Area**: gestione integrata dei bordi arrotondati e barre di sistema dei moderni dispositivi mobili (`env(safe-area-inset-bottom)`).
- **Progressive Enhancement su Desktop**: su schermi larghi la barra inferiore scompare automaticamente, trasformandosi in una sidebar completa a due o tre colonne.

### 1. 🎒 Anagrafica Alunni & Famiglie (Rubrica WhatsApp)
- Elenco alunni con calcolo automatico dell'età e gestione **allergie/intolleranze alimentari** (cruciali per feste e merende).
- Rubrica genitori/tutori con indicazione del contatto principale e flag consenso WhatsApp.
- **Pulsante rapido WhatsApp (`wa.me`)** per aprire subito la chat con il genitore senza dover salvare il numero in rubrica.
- **Generatore Rubrica WhatsApp**: estrae e formatta tutti i numeri cellulari della classe per creare rapidamente il gruppo WhatsApp o una lista broadcast.
- Esportazione istantanea di tutti i dati in formato **CSV** compatibile con Excel.

### 2. 💰 Cassa & Quote di Classe (Trasparenza Totale)
- **Campagne di Raccolta Quote**: creazione raccolte per regali maestre/educatrici, cassa materiale di cancelleria, gite, biglietti teatro o feste.
- **Griglia Pagamenti Alunni**: monitoraggio in tempo reale di chi ha pagato, con indicazione del metodo di pagamento (*Contanti, Bonifico, Satispay, PayPal*).
- **Libro Cassa Entrate & Uscite**: registrazione spese con data, categoria e riferimento scontrino/ricevuta.
- **Generatore Rendiconto WhatsApp**: un clic per generare e copiare un report trasparente del saldo cassa e delle ultime spese da inviare sulla chat dei genitori.

### 3. 📢 Bacheca Comunicazioni & WhatsApp Template Builder
- Modelli predefiniti per le situazioni più frequenti:
  - Raccolta quote fondo cassa
  - Promemoria scadenza versamenti
  - Rendiconto periodico trasparente della cassa
  - Convocazione assemblea di classe / riunione con docenti
  - Avviso sciopero scolastico o orario ridotto
  - Invito e gestione allergie per feste di compleanno
- Sostituzione automatica di variabili dinamiche (`{CLASSE}`, `{SALDO_CASSA}`, `{QUOTA}`, `{SCADENZA}`, ecc.).
- Editor e anteprima visiva del messaggio con copia immediata negli appunti.
- Possibilità di salvare nuovi modelli personalizzati.

### 4. 📅 Calendario & Scadenze
- Promemoria per riunioni, uscite didattiche, scadenze PagoPA, feste e compleanni.
- Widget laterale con i compleanni degli alunni del mese corrente per coordinare per tempo gli auguri o la festa comune.

### 5. 📋 Consigli d'Interclasse / Classe (Quaderno delle Note)
- Spazio dedicato per annotare le richieste e le problematiche sollevate dai genitori (mensa, riscaldamento, compiti, sicurezza, gite).
- Flusso di stato: `Proposto` ➔ `In discussione con docenti` ➔ `Accolto / Risolto` ➔ `Archiviato`.
- Registrazione delle risposte ufficiali fornite dalla scuola e generatore di resoconto formattato per i genitori.

### 6. 👥 Gestione Utenti & Limite Collaboratori (Max 5 Utenti)
- Pensato per consentire a una piccola squadra di collaborare (es. Rappresentante titolare, Vice-rappresentante, Tesoriere, Rappresentante mensa).
- **Controllo del limite massimo a 5 utenti attivi** imposto a livello logico e applicativo.
- Ruoli e permessi:
  - `Admin`: gestione completa, utenti, configurazione scuola e mail.
  - `Rappresentante`: gestione operativa di cassa, alunni, eventi e comunicazioni.
  - `Collaboratore`: inserimento e visualizzazione dati.
- Invito nuovi collaboratori via email con password temporanea generata automaticamente.

### 7. 🔐 Autenticazione & Notifiche Email
- Hashing sicuro delle password con algoritmo nativo `bcrypt` (`password_hash`).
- **Recupero Password via Email**: token crittografico casuale di 64 caratteri con scadenza automatica a 60 minuti e protezione contro enumerazione utenti.
- **Motore Mail Ibrido**:
  - Supporto nativo per **SMTP autenticato via socket** (STARTTLS porta 587, SSL porta 465) senza necessità di Composer o librerie terze.
  - Fallback automatico su `mail()` standard di PHP per ambienti hosting condivisi (es. Aruba, Register, cPanel).
  - Strumento integrato per testare l'invio email con un clic.

---

## 🗄️ Struttura Tabelle Database (Prefisso `rappresentor_`)

Tutte le tabelle del database MySQL utilizzano il prefisso dedicato `rappresentor_`:
- `rappresentor_settings`: impostazioni applicative e configurazione SMTP.
- `rappresentor_users`: collaboratori (massimo 5 attivi) e token di reset password.
- `rappresentor_classes`: sezioni e classi dall'asilo nido alle medie.
- `rappresentor_students`: anagrafica alunni, allergie e note speciali.
- `rappresentor_guardians`: genitori e contatti telefonici/email con consenso WhatsApp.
- `rappresentor_campaigns`: raccolte quote di classe.
- `rappresentor_payments`: stato versamento quota per ciascun alunno.
- `rappresentor_cashbox_transactions`: movimenti di entrata e uscita del fondo cassa.
- `rappresentor_events`: calendario eventi, scadenze e riunioni.
- `rappresentor_interclasse_topics`: istanze dei genitori per i consigli con i docenti.
- `rappresentor_templates`: modelli preimpostati e personalizzati per WhatsApp.

---

## 🛠️ Struttura del Progetto

```
Rappresentor/
├── .htaccess                 # Protezione directory sensibili e hardening HTTP
├── VERSION                   # File SemVer (0.1.0)
├── CHANGELOG.md              # Storico versioni e modifiche
├── README.md                 # Documentazione del progetto
├── install.php               # Wizard grafico di installazione automatica
├── login.php                 # Autenticazione utente
├── logout.php                # Chiusura sessione
├── password_dimenticata.php  # Richiesta link di ripristino password
├── reset_password.php       # Impostazione nuova password tramite token
├── index.php                 # Dashboard riassuntiva di classe
├── alunni.php                # Anagrafica alunni, genitori e rubrica WA
├── cassa.php                 # Cassa di classe, quote e rendiconti
├── comunicazioni.php         # Bacheca e generatore messaggi WhatsApp
├── calendario.php            # Scadenze, eventi e compleanni
├── consigli.php              # Quaderno note Consiglio d'Interclasse
├── classi.php                # Gestione classi/sezioni multi-grado
├── utenti.php                # Gestione collaboratori (fino a 5 persone)
├── impostazioni.php          # Configurazione scuola, SMTP e test mail
├── api/
│   └── update_payment.php    # Endpoint AJAX per aggiornamenti rapidi quote
├── config/
│   ├── config.example.php    # Modello di configurazione
│   └── config.php            # Generato automaticamente dall'installer
├── core/
│   ├── Auth.php              # Login, ruoli, sessioni, token reset e limite 5 utenti
│   ├── Database.php          # Connessione singleton PDO MySQL (utf8mb4)
│   ├── Helper.php            # CSRF, formattazione valuta/date, utilità WA
│   └── Mailer.php            # Socket SMTP TLS/SSL + PHP mail() + template HTML
├── database/
│   └── schema.sql            # Schema MySQL relazionale completo
├── includes/
│   ├── init.php              # Autoloader e controllo ambiente
│   ├── header.php            # Top bar, selettore classe e navigazione responsive
│   └── footer.php            # Chiusura pagina e script
└── assets/
    ├── css/
    │   └── style.css         # UI moderna, responsive, mobile-first
    └── js/
        └── app.js            # Interattività client, clipboard, modal e filtri
```

---

## 💻 Requisiti di Sistema

- **Web Server:** Apache (con `mod_rewrite` e `mod_headers`) o Nginx / LiteSpeed.
- **PHP:** Versione **8.1 o superiore** con estensioni:
  - `pdo`
  - `pdo_mysql`
  - `mbstring`
  - `session`
  - `openssl` (per connessioni SMTP TLS/SSL)
- **Database:** MySQL 5.7+ / MySQL 8.0+ o MariaDB 10.3+.

---

## 📦 Installazione Rapida

### Metodo 1: Installazione Guidata via Web (Consigliato)
1. Carica i file di Rappresentor sul tuo dominio o in una sottocartella (es. `https://tuodominio.it/rappresentor`).
2. Crea un database MySQL vuoto dal pannello di controllo del tuo hosting (cPanel, Plesk, phpMyAdmin).
3. Apri il browser all'indirizzo dell'app: verrai automaticamente indirizzato a `install.php`.
4. Compila i dati richiesti:
   - Parametri di connessione MySQL (Host, Nome DB, Utente, Password).
   - Nome della prima classe e grado scolastico (es. *Classe 2ªA Primaria*, oppure *Sezione Coccinelle Nido*).
   - Dati del primo account Amministratore (Nome, Email, Password).
5. Clicca su **"Avvia Installazione"**: il database verrà popolato e la configurazione `config/config.php` verrà generata automaticamente.

### Metodo 2: Installazione Manuale
1. Importa il file `database/schema.sql` nel tuo database MySQL tramite phpMyAdmin o riga di comando:
   ```bash
   mysql -u utente_db -p nome_database < database/schema.sql
   ```
2. Copia `config/config.example.php` in `config/config.php` e compila le credenziali di accesso al database:
   ```php
   return [
       'app' => [
           'name' => 'Rappresentor',
           'version' => '0.1.0',
           'env' => 'production'
       ],
       'db' => [
           'host' => 'localhost',
           'port' => 3306,
           'name' => 'tuo_database',
           'user' => 'tuo_utente',
           'pass' => 'tua_password',
           'charset' => 'utf8mb4'
       ]
   ];
   ```
3. Registra il primo utente o utilizza l'installer per completare la configurazione.

---

## 📧 Configurazione Email & SMTP

Per garantire che le email di **invito ai collaboratori** e i link di **reimpostazione della password** non finiscano nella cartella SPAM, si raccomanda l'uso di un server SMTP autenticato:
1. Accedi a Rappresentor come Amministratore.
2. Vai su **Impostazioni & Mail** nel menu laterale.
3. Seleziona **"Abilita invio tramite SMTP autenticato"**.
4. Inserisci i dati del tuo provider di posta (es. Aruba, Gmail, Microsoft 365, Mailgun, Brevo):
   - Host: `smtp.tuodominio.it`
   - Porta: `587` (STARTTLS) o `465` (SSL)
   - Utente e Password
   - Email mittente corrispondente al tuo dominio
5. Usa la funzione **"Invia Email di Test"** in fondo alla pagina per convalidare il recapito in tempo reale.

---

## 🔒 Sicurezza & Best Practices

- **Password Hashing:** Tutte le password sono cifrate con `password_hash()` utilizzando algoritmi crittografici moderni con salt dinamico.
- **CSRF Token:** Tutti i form POST includono un token crittografico univoco legato alla sessione utente.
- **Prepared Statements (PDO):** Tutte le query SQL utilizzano segnaposto parametrizzati per eliminare il rischio di SQL Injection.
- **Sanitizzazione XSS:** L'output a schermo è protetto tramite `Helper::e()` (`htmlspecialchars` con `ENT_QUOTES`).
- **Protezione File di Sistema:** Il file `.htaccess` blocca l'accesso HTTP diretto alle directory `config/`, `core/`, `database/` e a file sensibili come `.env` o script SQL.

---

## 🏷️ Versioning SemVer

Il progetto segue rigorosamente lo standard **Semantic Versioning 2.0.0** (`MAJOR.MINOR.PATCH`):
- `MAJOR`: modifiche architetturali incompatibili con le versioni precedenti.
- `MINOR`: aggiunta di nuove funzionalità retrocompatibili (es. nuove schede o integrazioni).
- `PATCH`: correzioni di bug e rifiniture.

Consulta il file [CHANGELOG.md](file:///Users/stefanosoranzio/Sputlab/WebApps/Rappresentor/CHANGELOG.md) per l'elenco dettagliato delle modifiche di ogni release.
