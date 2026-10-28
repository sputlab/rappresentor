# Changelog

Tutti i cambiamenti degni di nota a questo progetto saranno documentati in questo file.
Il formato è basato su [Keep a Changelog](https://keepachangelog.com/it/1.0.0/),
e questo progetto aderisce a [Semantic Versioning](https://semver.org/lang/it/).

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
