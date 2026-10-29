<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;

Auth::requireAuth('login.php');

$currentUser = Auth::user();
$activeClass = Helper::getActiveClass($db);
$appVersion = Helper::getAppVersion();

$pageTitle = 'Guida Completa all\'Uso';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Hero Guida -->
<div class="guide-header-card">
  <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 12px; flex-wrap: wrap;">
    <span style="font-size: 2.2rem;">📖</span>
    <div>
      <h1 style="margin: 0; font-size: 1.8rem; font-weight: 800; letter-spacing: -0.02em;">Guida Ufficiale di Rappresentor</h1>
      <p style="margin: 4px 0 0; opacity: 0.92; font-size: 0.95rem;">
        Manuale d'istruzioni pratico e approfondito per rappresentanti di classe, collaboratori e docenti.
      </p>
    </div>
  </div>

  <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px;">
    <span class="badge" style="background: rgba(255,255,255,0.2); color: #fff; font-size: 0.8rem;">
      Versione: v<?= Helper::e($appVersion) ?>
    </span>
    <span class="badge" style="background: rgba(255,255,255,0.2); color: #fff; font-size: 0.8rem;">
      📱 Progressive Web App (PWA)
    </span>
    <span class="badge" style="background: rgba(255,255,255,0.2); color: #fff; font-size: 0.8rem;">
      🏫 Nido, Infanzia, Primaria e Medie
    </span>
  </div>
</div>

<!-- Indice della Guida (Table of Contents) -->
<div class="guide-toc-card">
  <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
    <h2 style="margin: 0; font-size: 1.25rem; display: flex; align-items: center; gap: 8px;">
      <span>📑</span>
      <span>Indice dei Contenuti</span>
    </h2>
    <span style="font-size: 0.82rem; color: var(--text-secondary);">12 sezioni tematiche</span>
  </div>

  <div class="guide-toc-grid">
    <a href="#installazione-pwa" class="guide-toc-item">
      <span>📲</span>
      <span>1. Come Installare l'App (PWA)</span>
    </a>
    <a href="#ruoli-permessi" class="guide-toc-item">
      <span>🛡️</span>
      <span>2. Ruoli & Permessi Utente</span>
    </a>
    <a href="#gestione-classi" class="guide-toc-item">
      <span>🏫</span>
      <span>3. Gestione Classi & Sezioni</span>
    </a>
    <a href="#anagrafica-alunni" class="guide-toc-item">
      <span>🎒</span>
      <span>4. Anagrafica Alunni & Famiglie</span>
    </a>
    <a href="#cassa-saldo" class="guide-toc-item">
      <span>💰</span>
      <span>5. Cassa & Totali Automatici</span>
    </a>
    <a href="#raccolte-quote" class="guide-toc-item">
      <span>🎯</span>
      <span>6. Creazione & Modifica Quote</span>
    </a>
    <a href="#registro-spese" class="guide-toc-item">
      <span>📖</span>
      <span>7. Registro Spese & Scontrini</span>
    </a>
    <a href="#bacheca-whatsapp" class="guide-toc-item">
      <span>📢</span>
      <span>8. Bacheca WhatsApp Dinamica</span>
    </a>
    <a href="#calendario-eventi" class="guide-toc-item">
      <span>📅</span>
      <span>9. Calendario & Scadenze</span>
    </a>
    <a href="#consigli-interclasse" class="guide-toc-item">
      <span>📋</span>
      <span>10. Consigli d'Interclasse & Note</span>
    </a>
    <a href="#collaboratori-team" class="guide-toc-item">
      <span>👥</span>
      <span>11. Gestione Team & Inviti</span>
    </a>
    <a href="#faq-consigli" class="guide-toc-item">
      <span>❓</span>
      <span>12. FAQ & Consigli Utili</span>
    </a>
  </div>
</div>

<!-- ==========================================
     SEZIONE 1: INSTALLAZIONE PWA
     ========================================== -->
<section id="installazione-pwa" class="guide-section-card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
    <h2 style="margin: 0; font-size: 1.35rem; color: var(--primary);">
      📲 1. Come Installare l'App (Progressive Web App)
    </h2>
    <span class="badge badge-primary">Mobile & Desktop</span>
  </div>

  <p style="font-size: 0.95rem; line-height: 1.6; color: var(--text-secondary);">
    <strong>Rappresentor</strong> è sviluppata come una moderna <strong>Progressive Web App (PWA)</strong>: non è necessario scaricare file pesanti dall'App Store o da Google Play. L'applicazione può essere installata istantaneamente sulla schermata Home del tuo smartphone o sul tuo computer, garantendo un'esperienza a schermo intero identica a un'app nativa e con supporto per l'utilizzo offline.
  </p>

  <div style="margin: 18px 0; padding: 16px; background: var(--primary-light); border: 1px solid var(--primary-border); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
    <div>
      <strong style="color: var(--primary); font-size: 1rem;">Sei su un dispositivo supportato?</strong>
      <div style="font-size: 0.85rem; color: var(--text-secondary);">Puoi avviare subito l'installazione guidata con un clic:</div>
    </div>
    <button type="button" class="btn btn-primary" data-pwa-install>
      📲 Installa Rappresentor Ora
    </button>
  </div>

  <h3 style="font-size: 1.1rem; margin-top: 24px;">Istruzioni Dettagliate per Dispositivo:</h3>

  <!-- iPhone / iPad (iOS Safari) -->
  <div class="guide-step-box">
    <div class="guide-step-number">🍎</div>
    <div>
      <h4 style="margin: 0 0 6px; font-size: 1rem;">Su iPhone e iPad (Browser Safari)</h4>
      <ol style="margin: 0; padding-left: 20px; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6;">
        <li>Apri il sito di Rappresentor con il browser predefinito <strong>Safari</strong>.</li>
        <li>Tocca l'icona <strong>Condividi</strong> nella barra inferiore di Safari (il quadrato con la freccia rivolta verso l'alto ⎋).</li>
        <li>Scorri il menu delle opzioni verso il basso e seleziona <strong>"Aggiungi alla schermata Home"</strong> (icona col simbolo <strong>+</strong>).</li>
        <li>Tocca <strong>"Aggiungi"</strong> in alto a destra: l'icona moderna di Rappresentor apparirà tra le tue app sul telefono!</li>
      </ol>
    </div>
  </div>

  <!-- Android (Chrome / Edge / Samsung) -->
  <div class="guide-step-box">
    <div class="guide-step-number">🤖</div>
    <div>
      <h4 style="margin: 0 0 6px; font-size: 1rem;">Su Smartphone e Tablet Android (Chrome o Samsung Internet)</h4>
      <ol style="margin: 0; padding-left: 20px; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6;">
        <li>Apri Rappresentor con il browser <strong>Google Chrome</strong>.</li>
        <li>Tocca il banner che appare in alto <strong>"Installa Rappresentor"</strong> oppure apri il menu dei tre puntini verticali (⋮) in alto a destra.</li>
        <li>Tocca la voce <strong>"Installa app"</strong> o <strong>"Aggiungi a schermata Home"</strong>.</li>
        <li>Conferma premendo su <strong>"Installa"</strong>: l'app verrà aggiunta al drawer e alla tua Home con supporto alle notifiche e scorciatoie rapide.</li>
      </ol>
    </div>
  </div>

  <!-- Computer PC / Mac -->
  <div class="guide-step-box">
    <div class="guide-step-number">💻</div>
    <div>
      <h4 style="margin: 0 0 6px; font-size: 1rem;">Su Computer (Windows, Mac o Linux tramite Chrome / Edge)</h4>
      <ol style="margin: 0; padding-left: 20px; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6;">
        <li>Apri l'applicazione con Google Chrome o Microsoft Edge.</li>
        <li>Nella barra degli indirizzi in alto a destra, clicca sull'icona <strong>Installa</strong> (il computerino con la freccia giù).</li>
        <li>Conferma l'installazione: Rappresentor si aprirà in una finestra desktop dedicata e potrai fissarla alla barra delle applicazioni o al Dock del Mac.</li>
      </ol>
    </div>
  </div>
</section>

<!-- ==========================================
     SEZIONE 2: RUOLI & PERMESSI
     ========================================== -->
<section id="ruoli-permessi" class="guide-section-card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
    <h2 style="margin: 0; font-size: 1.35rem; color: var(--primary);">
      🛡️ 2. Ruoli & Permessi Utente (Controllo Accessi)
    </h2>
    <span class="badge badge-secondary">Sicurezza & Privacy</span>
  </div>

  <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6;">
    Rappresentor implementa un sistema di autorizzazione granulare multi-classe basato su tre ruoli distinti:
  </p>

  <div style="display: grid; grid-template-columns: 1fr; gap: 14px; margin-top: 14px;">
    <div style="background: var(--bg-surface-alt); border-left: 4px solid var(--danger); padding: 14px 18px; border-radius: var(--radius-md);">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 1.2rem;">🛡️</span>
        <strong style="font-size: 1.05rem;">Amministratore (Admin)</strong>
      </div>
      <p style="margin: 6px 0 0; font-size: 0.88rem; color: var(--text-secondary);">
        Ha il controllo completo della piattaforma: configura le impostazioni di sistema e il server SMTP per le email, può creare nuove classi, ha accesso a tutte le sezioni e può assegnare le classi a qualsiasi utente (rappresentante o collaboratore).
      </p>
    </div>

    <div style="background: var(--bg-surface-alt); border-left: 4px solid var(--primary); padding: 14px 18px; border-radius: var(--radius-md);">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 1.2rem;">⭐</span>
        <strong style="font-size: 1.05rem;">Rappresentante di Classe</strong>
      </div>
      <p style="margin: 6px 0 0; font-size: 0.88rem; color: var(--text-secondary);">
        È il coordinatore eletto per una o più classi. Ha piena gestione dell'anagrafica alunni, della cassa, dei messaggi WhatsApp e del calendario per le <strong>sole classi a cui è assegnato</strong>. Può inoltre invitare collaboratori e assegnare loro le classi di propria competenza.
      </p>
    </div>

    <div style="background: var(--bg-surface-alt); border-left: 4px solid var(--success); padding: 14px 18px; border-radius: var(--radius-md);">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 1.2rem;">👤</span>
        <strong style="font-size: 1.05rem;">Collaboratore di Classe</strong>
      </div>
      <p style="margin: 6px 0 0; font-size: 0.88rem; color: var(--text-secondary);">
        Genitore di supporto o tesoriere nominato per assistere il rappresentante. Può consultare e registrare versamenti, consultare la rubrica e inserire movimenti, ma non può creare né archiviare intere classi.
      </p>
    </div>
  </div>
</section>

<!-- ==========================================
     SEZIONE 3: GESTIONE CLASSI
     ========================================== -->
<section id="gestione-classi" class="guide-section-card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
    <h2 style="margin: 0; font-size: 1.35rem; color: var(--primary);">
      🏫 3. Gestione Classi & Sezioni
    </h2>
    <span class="badge badge-info">Plessi & Ordini Scolastici</span>
  </div>

  <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6;">
    L'app supporta la gestione contemporanea di più classi appartenenti a diversi ordini di scuola:
  </p>
  <ul style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.8;">
    <li>👶 <strong>Asilo Nido</strong> (0-3 anni: lattanti, semidivezzi, divezzi)</li>
    <li>🎨 <strong>Scuola dell'Infanzia</strong> (3-6 anni: sezioni colori, eterogenee o omogenee)</li>
    <li>🎒 <strong>Scuola Primaria</strong> (6-11 anni: classi dalla 1ª alla 5ª)</li>
    <li>📐 <strong>Scuola Secondaria di 1° Grado</strong> (11-14 anni: classi 1ª-3ª medie)</li>
  </ul>

  <div class="alert alert-info" style="margin-top: 14px;">
    <div>💡</div>
    <div>
      <strong>Come cambiare la classe attiva:</strong>
      Su desktop puoi selezionare la classe dal menu a tendina in alto nella barra blu; su smartphone, per non occupare spazio prezioso sullo schermo, il selettore della classe è comodamente posizionato all'interno del <strong>Burger Menu (☰)</strong> laterale.
    </div>
  </div>
</section>

<!-- ==========================================
     SEZIONE 4: ANAGRAFICA ALUNNI & WHATSAPP
     ========================================== -->
<section id="anagrafica-alunni" class="guide-section-card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
    <h2 style="margin: 0; font-size: 1.35rem; color: var(--primary);">
      🎒 4. Anagrafica Alunni, Genitori & Rubrica WhatsApp
    </h2>
    <span class="badge badge-success">Rubrica Sicura</span>
  </div>

  <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6;">
    La sezione <strong>Alunni</strong> costituisce il cuore informativo della classe:
  </p>
  <ul style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.8;">
    <li><strong>Scheda Alunno Completa:</strong> nome, cognome, data di nascita con calcolo automatico dell'età (mesi per il nido, anni per le altre classi), note e soprattutto il campo critico <strong>Allergie & Intolleranze</strong> (evidenziato con badge d'allerta per feste e merende).</li>
    <li><strong>Genitori & Tutori:</strong> recapiti telefonici, email e indicazione del contatto principale della famiglia.</li>
    <li><strong>Consenso WhatsApp:</strong> flag esplicito per la conformità alla privacy sull'inserimento nel gruppo genitori.</li>
    <li><strong>Pulsante Chat Rapida (<code>wa.me</code>):</strong> tocca l'icona verde WhatsApp accanto al nome del genitore per avviare subito una chat privata senza dover salvare il numero nella rubrica personale del telefono.</li>
    <li><strong>📱 Copia Rubrica WhatsApp:</strong> clicca sul pulsante verde in cima alla pagina per copiare in un colpo solo tutti i numeri dei genitori consenzienti formattati internazionalmente (+39), pronti da incollare per creare il gruppo WhatsApp o una lista broadcast.</li>
    <li><strong>Esportazione CSV:</strong> scarica con un clic il foglio Excel compatibile con tutti i dati anagrafici della classe.</li>
  </ul>
</section>

<!-- ==========================================
     SEZIONE 5 & 6: CASSA & RACCOLTE QUOTE
     ========================================== -->
<section id="cassa-saldo" class="guide-section-card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
    <h2 style="margin: 0; font-size: 1.35rem; color: var(--primary);">
      💰 5. Cassa di Classe & Calcolo Automatico del Saldo
    </h2>
    <span class="badge badge-primary">Fondo Comune</span>
  </div>

  <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6;">
    In Rappresentor la gestione contabile è trasparente e completamente automatizzata:
  </p>

  <div style="background: var(--bg-surface-alt); border-radius: var(--radius-md); padding: 16px; margin: 14px 0;">
    <strong style="font-size: 0.95rem; color: var(--primary); display: block; margin-bottom: 6px;">
      ⚡ Come funziona il calcolo automatico in tempo reale:
    </strong>
    <ul style="margin: 0; padding-left: 20px; font-size: 0.88rem; color: var(--text-secondary); line-height: 1.7;">
      <li>Ogni volta che contrassegni un alunno come <strong>"Pagato"</strong> in una qualsiasi raccolta quote attiva, l'importo versato <strong>confluisce automaticamente</strong> nel <strong>Totale Entrate</strong> e incrementa il <strong>Saldo Cassa Disponibile</strong>.</li>
      <li>Non dovrai mai registrare manualmente somme già conteggiate nei versamenti alunni: l'app fa la quadratura contabile istantaneamente!</li>
      <li>Se un versamento viene modificato, azzerato o impostato su parziale, i totali si riallineano immediatamente in tutta l'applicazione.</li>
    </ul>
  </div>

  <div id="raccolte-quote" style="margin-top: 28px; padding-top: 18px; border-top: 1px dashed var(--border-color);">
    <h3 style="margin: 0 0 10px; font-size: 1.2rem; color: var(--text-primary);">
      🎯 6. Creazione & Modifica delle Raccolte Quote
    </h3>
    <p style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6;">
      Puoi avviare tutte le raccolte necessarie (es. fondo cassa comune, regalo maestre, materiale cancelleria, quota gita scolastica, biglietti teatro):
    </p>
    <ul style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.7;">
      <li><strong>Nuova Raccolta:</strong> clicca su <code>+ Nuova Raccolta Quote</code>, inserisci titolo, quota richiesta per alunno, scadenza e descrizione. L'app creerà automaticamente una riga di versamento per ciascun alunno della classe.</li>
      <li><strong>✏️ Modifica Raccolta Quote:</strong> hai commesso un refuso o la quota è cambiata? Puoi cliccare sul pulsante <code>✏️ Modifica Raccolta</code> in qualsiasi momento per aggiornare titolo, importo, data di scadenza e note della campagna selezionata.</li>
      <li><strong>Chiusura Raccolta:</strong> al termine della raccolta puoi cliccare su <code>🔒 Chiudi Raccolta</code> per archiviarla senza perdere lo storico dei versamenti.</li>
    </ul>
  </div>
</section>

<!-- ==========================================
     SEZIONE 7: REGISTRO SPESE & SCONTRINI
     ========================================== -->
<section id="registro-spese" class="guide-section-card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
    <h2 style="margin: 0; font-size: 1.35rem; color: var(--primary);">
      📖 7. Registro Spese, Scontrini & Movimenti Manuali
    </h2>
    <span class="badge badge-secondary">Libro Cassa</span>
  </div>

  <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6;">
    Nel <strong>Registro Entrate & Uscite</strong> della Cassa puoi registrare tutti i movimenti fisici del denaro:
  </p>
  <ul style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.7;">
    <li><strong>Uscite per Acquisti:</strong> clicca su <code>+ Registra Movimento</code>, seleziona <em>Uscita</em>, inserisci l'importo speso, la categoria (Materiale, Regalo, Spettacolo, Altro) e il riferimento dello scontrino o ricevuta (es. <em>Scontrino n. 42 Cartoleria ABC</em>).</li>
    <li><strong>Entrate Straordinarie:</strong> puoi registrare entrate manuali non legate alle quote ordinarie degli alunni (ad esempio l'avanzo di cassa dell'anno precedente o rimborsi).</li>
    <li><strong>Cancellazione Movimenti:</strong> se inserisci un movimento per errore, puoi eliminarlo cliccando sull'icona 🗑️ con conferma immediata.</li>
  </ul>
</section>

<!-- ==========================================
     SEZIONE 8: BACHECA WHATSAPP
     ========================================== -->
<section id="bacheca-whatsapp" class="guide-section-card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
    <h2 style="margin: 0; font-size: 1.35rem; color: var(--primary);">
      📢 8. Bacheca WhatsApp & Modelli Dinamici
    </h2>
    <span class="badge" style="background:#25D366; color:#fff;">Comunicazione 1-Click</span>
  </div>

  <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6;">
    Comunicare con decine di famiglie su WhatsApp non è mai stato così veloce e professionale:
  </p>
  <ul style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.7;">
    <li><strong>Tutte le Quote Attive Incluse:</strong> i modelli per le quote raggruppano <strong>tutte le campagne attualmente aperte</strong> per la classe, formattando titolo, quota per ciascuna voce e calcolando la somma complessiva richiesta ad alunno.</li>
    <li><strong>Pulsante "📱 Avviso Quote WA" in Cassa:</strong> ti basta un tocco sul pulsante verde nella pagina Cassa per visualizzare e copiare l'avviso pronto per il gruppo WhatsApp.</li>
    <li><strong>Pulsante "📱 Copia Rendiconto WA":</strong> genera istantaneamente un report trasparente con saldo attuale, totale entrate, totale uscite e ultime 5 spese con scontrino da condividere con i genitori.</li>
    <li><strong>Variabili Dinamiche Automatiche:</strong> nei modelli puoi usare <code>{CLASSE}</code>, <code>{DATA_OGGI}</code>, <code>{SALDO_CASSA}</code>, <code>{DETTAGLIO_QUOTE}</code>, <code>{LISTA_USCITE}</code>, <code>{TITOLO_RACCOLTA}</code>, <code>{QUOTA}</code>, <code>{SCADENZA}</code>, <code>{NOME_EVENTO}</code>, <code>{DATA_EVENTO}</code>.</li>
  </ul>
</section>

<!-- ==========================================
     SEZIONE 9 & 10: CALENDARIO & INTERCLASSE
     ========================================== -->
<section id="calendario-eventi" class="guide-section-card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
    <h2 style="margin: 0; font-size: 1.35rem; color: var(--primary);">
      📅 9. Calendario Scolastico, Eventi & Scadenze
    </h2>
    <span class="badge badge-warning">Scadenze & Riunioni</span>
  </div>

  <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6;">
    Non dimenticare mai una riunione o un pagamento PagoPA:
  </p>
  <ul style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.7;">
    <li>Tracciamento scadenze per <strong>PagoPA / Contributo scolastico</strong>, assemblee con i docenti, consigli di classe, uscite didattiche e feste.</li>
    <li>Visualizzazione cronologica con conteggio dei giorni mancanti (es. <em>Tra 3 giorni</em>, <em>Oggi</em>).</li>
  </ul>

  <div id="consigli-interclasse" style="margin-top: 24px; padding-top: 18px; border-top: 1px dashed var(--border-color);">
    <h3 style="margin: 0 0 10px; font-size: 1.2rem; color: var(--text-primary);">
      📋 10. Consigli d'Interclasse & Note dei Genitori
    </h3>
    <p style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6;">
      Il rappresentante è la voce delle famiglie davanti ai docenti e alla dirigenza:
    </p>
    <ul style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.7;">
      <li>Raccogli durante l'anno i punti e le segnalazioni dei genitori (didattica, mensa, orari, sicurezza).</li>
      <li>Registra le risposte dei docenti e le decisioni prese durante il consiglio.</li>
      <li>Usa il pulsante <strong>"📱 Copia Resoconto WA"</strong> per inviare alle famiglie un riepilogo pulito ed elegante dell'incontro.</li>
    </ul>
  </div>
</section>

<!-- ==========================================
     SEZIONE 11: TEAM & COLLABORATORI
     ========================================== -->
<section id="collaboratori-team" class="guide-section-card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
    <h2 style="margin: 0; font-size: 1.35rem; color: var(--primary);">
      👥 11. Gestione Team, Collaboratori & Inviti
    </h2>
    <span class="badge badge-info">Lavoro di Squadra</span>
  </div>

  <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6;">
    Essere rappresentanti è più facile se si collabora:
  </p>
  <ul style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.7;">
    <li>Gli <strong>Amministratori</strong> e i <strong>Rappresentanti</strong> possono accedere alla voce <em>Collaboratori</em> nel menu.</li>
    <li>Puoi invitare altri genitori selezionando le classi che potranno gestire tramite comode checkbox responsive.</li>
    <li>Se il server SMTP è configurato, il collaboratore riceverà un'email di benvenuto con il link di accesso e una password temporanea generata in modo sicuro.</li>
    <li>Puoi modificare in qualsiasi momento le classi assegnate a ciascun collaboratore o disattivare un account senza cancellarne lo storico.</li>
  </ul>
</section>

<!-- ==========================================
     SEZIONE 12: FAQ & CONSIGLI UTILI
     ========================================== -->
<section id="faq-consigli" class="guide-section-card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
    <h2 style="margin: 0; font-size: 1.35rem; color: var(--primary);">
      ❓ 12. Domande Frequenti (FAQ) & Consigli Pratici
    </h2>
    <span class="badge badge-secondary">Supporto</span>
  </div>

  <div style="display: flex; flex-direction: column; gap: 14px; margin-top: 14px;">
    <div style="background: var(--bg-surface-alt); padding: 14px 18px; border-radius: var(--radius-md);">
      <strong style="color: var(--primary); font-size: 0.95rem;">D: Cosa succede se perdo la connessione a scuola?</strong>
      <p style="margin: 6px 0 0; font-size: 0.88rem; color: var(--text-secondary);">
        Grazie alla tecnologia Service Worker della PWA, l'applicazione rimane accessibile nella cache dello smartphone e non mostrerà la schermata di errore del browser.
      </p>
    </div>

    <div style="background: var(--bg-surface-alt); padding: 14px 18px; border-radius: var(--radius-md);">
      <strong style="color: var(--primary); font-size: 0.95rem;">D: Posso modificare una raccolta quote già avviata?</strong>
      <p style="margin: 6px 0 0; font-size: 0.88rem; color: var(--text-secondary);">
        Assolutamente sì! Nella sezione Cassa seleziona la raccolta e tocca il pulsante <strong>"✏️ Modifica Raccolta"</strong> per variare titolo, quota richiesta, scadenza o note descrittive.
      </p>
    </div>

    <div style="background: var(--bg-surface-alt); padding: 14px 18px; border-radius: var(--radius-md);">
      <strong style="color: var(--primary); font-size: 0.95rem;">D: Chi può vedere i dati delle famiglie?</strong>
      <p style="margin: 6px 0 0; font-size: 0.88rem; color: var(--text-secondary);">
        Esclusivamente gli utenti registrati e autenticati (amministratori e rappresentanti assegnati a quella specifica classe). Nessun dato è pubblico su internet.
      </p>
    </div>
  </div>
</section>

<!-- Pulsante Floating "Torna in Cima" -->
<button type="button" class="btn-scroll-top" id="btnScrollTop" aria-label="Torna in cima alla guida">
  <span>↑</span>
  <span>Torna in Cima</span>
</button>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
