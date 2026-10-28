# Changelog

Tutti i cambiamenti degni di nota a questo progetto saranno documentati in questo file.
Il formato è basato su [Keep a Changelog](https://keepachangelog.com/it/1.0.0/),
e questo progetto aderisce a [Semantic Versioning](https://semver.org/lang/it/).

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
