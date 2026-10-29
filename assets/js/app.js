/**
 * Rappresentor - Client JavaScript
 * Versione SemVer: 1.2.0
 */

document.addEventListener('DOMContentLoaded', () => {
  initMobileNav();
  initModals();
  initClipboardButtons();
  initQuickFilters();
  initServiceWorker();
  initPwaInstallPrompt();
  initScrollTopButton();
  initSmoothAnchorLinks();
  initConfirmDialogs();
});

/**
 * Gestione Sidebar Drawer & Bottom Nav su Mobile
 */
function initMobileNav() {
  const toggleBtns = document.querySelectorAll('.mobile-nav-toggle');
  const sidebar = document.querySelector('.sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');

  if (sidebar) {
    toggleBtns.forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const isOpen = sidebar.classList.contains('open');
        if (isOpen) {
          sidebar.classList.remove('open');
          if (backdrop) backdrop.classList.remove('active');
        } else {
          sidebar.classList.add('open');
          if (backdrop) backdrop.classList.add('active');
        }
      });
    });

    if (backdrop) {
      backdrop.addEventListener('click', () => {
        sidebar.classList.remove('open');
        backdrop.classList.remove('active');
      });
    }

    // Chiudi la sidebar quando viene cliccato un link
    sidebar.querySelectorAll('.nav-item').forEach((item) => {
      item.addEventListener('click', () => {
        if (window.innerWidth < 992) {
          sidebar.classList.remove('open');
          if (backdrop) backdrop.classList.remove('active');
        }
      });
    });
  }
}

/**
 * Gestione Modal Dialogs
 */
function initModals() {
  // Apertura modal tramite pulsanti data-modal-target
  document.querySelectorAll('[data-modal-target]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const targetId = btn.getAttribute('data-modal-target');
      openModal(targetId);
    });
  });

  // Chiusura tramite data-modal-close o click su backdrop
  document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) {
        closeModal(backdrop.id);
      }
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.modal-backdrop');
      if (modal) {
        closeModal(modal.id);
      }
    });
  });

  // Chiusura con tasto Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const activeModal = document.querySelector('.modal-backdrop.active');
      if (activeModal) {
        closeModal(activeModal.id);
      }
    }
  });
}

function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Riporta lo scroll in cima all'apertura del modale
    const modalBody = modal.querySelector('.modal-body');
    if (modalBody) {
      modalBody.scrollTop = 0;
    }

    // Autofocus sul primo campo utile
    const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
    if (firstInput) {
      setTimeout(() => firstInput.focus(), 80);
    }
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('active');
    if (!document.querySelector('.modal-backdrop.active')) {
      document.body.style.overflow = '';
    }
  }
}

/**
 * Copia rapida negli appunti (per messaggi WhatsApp o rubrica)
 */
function initClipboardButtons() {
  document.querySelectorAll('[data-copy-target]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const targetSelector = btn.getAttribute('data-copy-target');
      const targetEl = document.querySelector(targetSelector);
      if (!targetEl) return;

      const textToCopy = targetEl.value !== undefined ? targetEl.value : targetEl.innerText;
      copyToClipboard(textToCopy, btn.getAttribute('data-copy-success') || 'Testo copiato negli appunti! Pronto da incollare.');
    });
  });
}

function copyToClipboard(text, successMessage = 'Copiato negli appunti!') {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(() => {
      showToast(successMessage, 'success');
    }).catch(() => {
      fallbackCopy(text, successMessage);
    });
  } else {
    fallbackCopy(text, successMessage);
  }
}

function fallbackCopy(text, successMessage) {
  const textarea = document.createElement('textarea');
  textarea.value = text;
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();
  try {
    document.execCommand('copy');
    showToast(successMessage, 'success');
  } catch (err) {
    showToast('Impossibile copiare il testo. Selezionalo manualmente.', 'danger');
  }
  document.body.removeChild(textarea);
}

/**
 * Toast fluttuante di notifica (ottimizzato per mobile e desktop)
 */
function showToast(message, type = 'info') {
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const icons = {
    success: '✅',
    danger: '❌',
    warning: '⚠️',
    info: 'ℹ️'
  };
  const icon = icons[type] || 'ℹ️';

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `<span style="font-size: 1.1rem; flex-shrink: 0; line-height: 1;">${icon}</span><span style="flex: 1; line-height: 1.4;">${message}</span>`;

  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

/**
 * Filtro rapido in tempo reale su tabelle ed elenchi
 */
function initQuickFilters() {
  const searchInputs = document.querySelectorAll('[data-filter-table]');
  searchInputs.forEach((input) => {
    input.addEventListener('input', () => {
      const targetTableId = input.getAttribute('data-filter-table');
      const table = document.getElementById(targetTableId);
      if (!table) return;

      const term = input.value.toLowerCase().trim();
      const rows = table.querySelectorAll('tbody tr');

      rows.forEach((row) => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
      });

      // Filtra anche le schede mobile associate alla tabella (se presenti)
      const mobileContainer = document.querySelector(`[data-filter-target="${targetTableId}"]`) || document.querySelector('.mobile-cards-view');
      if (mobileContainer) {
        const cards = mobileContainer.querySelectorAll('.mobile-filter-card');
        cards.forEach((card) => {
          const text = card.innerText.toLowerCase();
          card.style.display = text.includes(term) ? '' : 'none';
        });
      }
    });
  });
}

/**
 * Aggiornamento rapido pagamento quota via AJAX
 */
async function updatePaymentStatus(paymentId, status, csrfToken) {
  try {
    const formData = new FormData();
    formData.append('payment_id', paymentId);
    formData.append('status', status);
    formData.append('csrf_token', csrfToken);

    const res = await fetch('api/update_payment.php', {
      method: 'POST',
      body: formData
    });

    const data = await res.json();
    if (data.success) {
      showToast('Stato pagamento aggiornato con successo!', 'success');
    } else {
      showToast(data.message || 'Errore durante l\'aggiornamento.', 'danger');
    }
  } catch (e) {
    showToast('Errore di connessione di rete.', 'danger');
  }
}

/**
 * Registrazione Service Worker per Progressive Web App (PWA)
 */
function initServiceWorker() {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('./sw.js')
        .then((reg) => {
          // Service worker registrato con successo
        })
        .catch((err) => {
          console.warn('Registrazione ServiceWorker fallita:', err);
        });
    });
  }
}

/**
 * Gestione banner di installazione PWA (Aggiungi alla Schermata Home)
 */
let deferredPrompt = null;

function initPwaInstallPrompt() {
  window.addEventListener('beforeinstallprompt', (e) => {
    // Previeni apertura automatica prompt predefinito del browser
    e.preventDefault();
    deferredPrompt = e;

    // Mostra banner o pulsante installa se presente
    const installBanner = document.getElementById('pwaInstallBanner');
    if (installBanner) {
      installBanner.style.display = 'flex';
    }
  });

  document.querySelectorAll('[data-pwa-install]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (deferredPrompt) {
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        if (outcome === 'accepted') {
          showToast('Installazione avviata! Troverai Rappresentor tra le tue app.', 'success');
        }
        deferredPrompt = null;
        const installBanner = document.getElementById('pwaInstallBanner');
        if (installBanner) installBanner.style.display = 'none';
      } else {
        // Se iOS Safari o browser senza prompt automatico
        showToast('Su iOS: tocca il tasto Condividi (icona con freccia in su) e seleziona "Aggiungi a schermata Home".', 'info');
      }
    });
  });

  window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    showToast('Rappresentor installato con successo sulla tua schermata Home! 🎉', 'success');
  });
}

/**
 * Gestione pulsante floating "Torna in Cima"
 */
function initScrollTopButton() {
  const btn = document.getElementById('btnScrollTop');
  if (!btn) return;

  const mainContent = document.querySelector('.main-content');

  const getScrollY = () => {
    if (window.innerWidth >= 992 && mainContent) {
      return mainContent.scrollTop;
    }
    return window.scrollY;
  };

  const toggleScrollBtn = () => {
    if (getScrollY() > 300) {
      btn.classList.add('visible');
    } else {
      btn.classList.remove('visible');
    }
  };

  window.addEventListener('scroll', toggleScrollBtn, { passive: true });
  if (mainContent) {
    mainContent.addEventListener('scroll', toggleScrollBtn, { passive: true });
  }
  toggleScrollBtn();

  btn.addEventListener('click', (e) => {
    e.preventDefault();
    if (window.innerWidth >= 992 && mainContent) {
      mainContent.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    } else {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    }
  });
}

/**
 * Gestione navigazione ancore interne con scroll morbido nel container principale
 */
function initSmoothAnchorLinks() {
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', function(e) {
      const targetId = this.getAttribute('href').substring(1);
      if (!targetId) return;
      const targetEl = document.getElementById(targetId);
      if (targetEl) {
        const mainContent = document.querySelector('.main-content');
        if (window.innerWidth >= 992 && mainContent) {
          e.preventDefault();
          targetEl.scrollIntoView({ behavior: 'smooth' });
        }
      }
    });
  });
}

/**
 * ==========================================================================
 * Custom App Dialog & Alert System (Popups, Conferme & Avvisi Grafici)
 * Sostituisce nativamente window.alert, window.confirm e onsubmit="return confirm(...)"
 * ==========================================================================
 */
const AppDialog = (() => {
  let backdropEl = null;
  let cardEl = null;
  let iconWrapperEl = null;
  let iconEl = null;
  let titleEl = null;
  let messageEl = null;
  let inputWrapperEl = null;
  let inputEl = null;
  let cancelBtn = null;
  let confirmBtn = null;

  let currentResolve = null;
  let previousActiveElement = null;

  function ensureDOMElements() {
    if (backdropEl) return;

    backdropEl = document.createElement('div');
    backdropEl.id = 'appDialogBackdrop';
    backdropEl.className = 'app-dialog-backdrop';
    backdropEl.setAttribute('role', 'dialog');
    backdropEl.setAttribute('aria-modal', 'true');
    backdropEl.setAttribute('aria-hidden', 'true');

    backdropEl.innerHTML = `
      <div class="app-dialog-card" role="document">
        <div class="app-dialog-icon-wrapper" id="appDialogIconWrapper">
          <span id="appDialogIcon">⚠️</span>
        </div>
        <h3 class="app-dialog-title" id="appDialogTitle"></h3>
        <p class="app-dialog-message" id="appDialogMessage"></p>
        <div class="app-dialog-input-wrapper" id="appDialogInputWrapper" style="display: none;">
          <input type="text" class="form-control" id="appDialogInput" autocomplete="off">
        </div>
        <div class="app-dialog-actions" id="appDialogActions">
          <button type="button" class="btn btn-secondary" id="appDialogCancelBtn">Annulla</button>
          <button type="button" class="btn btn-primary" id="appDialogConfirmBtn">Conferma</button>
        </div>
      </div>
    `;

    document.body.appendChild(backdropEl);

    cardEl = backdropEl.querySelector('.app-dialog-card');
    iconWrapperEl = backdropEl.querySelector('#appDialogIconWrapper');
    iconEl = backdropEl.querySelector('#appDialogIcon');
    titleEl = backdropEl.querySelector('#appDialogTitle');
    messageEl = backdropEl.querySelector('#appDialogMessage');
    inputWrapperEl = backdropEl.querySelector('#appDialogInputWrapper');
    inputEl = backdropEl.querySelector('#appDialogInput');
    cancelBtn = backdropEl.querySelector('#appDialogCancelBtn');
    confirmBtn = backdropEl.querySelector('#appDialogConfirmBtn');

    backdropEl.addEventListener('click', (e) => {
      if (e.target === backdropEl) {
        handleDismiss();
      }
    });

    cancelBtn.addEventListener('click', () => handleDismiss());
    confirmBtn.addEventListener('click', () => handleConfirm());

    inputEl.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        handleConfirm();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && backdropEl && backdropEl.classList.contains('active')) {
        e.preventDefault();
        handleDismiss();
      }
    });
  }

  function handleDismiss() {
    if (!backdropEl || !backdropEl.classList.contains('active')) return;
    close();
    if (currentResolve) {
      const res = currentResolve;
      currentResolve = null;
      res(false);
    }
  }

  function handleConfirm() {
    if (!backdropEl || !backdropEl.classList.contains('active')) return;
    const isPrompt = inputWrapperEl.style.display !== 'none';
    const val = isPrompt ? inputEl.value : true;
    close();
    if (currentResolve) {
      const res = currentResolve;
      currentResolve = null;
      res(val);
    }
  }

  function open() {
    previousActiveElement = document.activeElement;
    backdropEl.setAttribute('aria-hidden', 'false');
    backdropEl.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function close() {
    if (!backdropEl) return;
    backdropEl.classList.remove('active');
    backdropEl.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.modal-backdrop.active')) {
      document.body.style.overflow = '';
    }
    if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
      try {
        previousActiveElement.focus();
      } catch (e) {}
    }
  }

  function setupTypeAndIcon(type, customIcon) {
    iconWrapperEl.className = `app-dialog-icon-wrapper ${type}`;
    if (customIcon) {
      iconEl.textContent = customIcon;
    } else if (type === 'danger') {
      iconEl.textContent = '🗑️';
    } else if (type === 'warning') {
      iconEl.textContent = '⚠️';
    } else if (type === 'success') {
      iconEl.textContent = '✅';
    } else {
      iconEl.textContent = 'ℹ️';
    }

    if (type === 'danger') {
      confirmBtn.className = 'btn btn-danger';
    } else if (type === 'warning') {
      confirmBtn.className = 'btn btn-primary';
    } else if (type === 'success') {
      confirmBtn.className = 'btn btn-success';
    } else {
      confirmBtn.className = 'btn btn-primary';
    }
  }

  /**
   * Finestra modale di conferma (Promise<boolean>)
   */
  function confirm(opts) {
    ensureDOMElements();
    let title = 'Richiesta di conferma';
    let message = '';
    let type = 'warning';
    let confirmText = 'Conferma';
    let cancelText = 'Annulla';
    let customIcon = null;

    if (typeof opts === 'string') {
      message = opts;
      const lower = message.toLowerCase();
      if (lower.includes('elimina') || lower.includes('rimuov') || lower.includes('cancella')) {
        type = 'danger';
        title = 'Conferma Eliminazione';
        confirmText = 'Elimina';
      }
    } else if (opts && typeof opts === 'object') {
      message = opts.message || '';
      type = opts.type || (opts.message && /elimina|rimuov|cancella/i.test(opts.message) ? 'danger' : 'warning');
      title = opts.title || (type === 'danger' ? 'Conferma Eliminazione' : 'Richiesta di conferma');
      confirmText = opts.confirmText || (type === 'danger' ? 'Elimina' : 'Conferma');
      cancelText = opts.cancelText || 'Annulla';
      customIcon = opts.icon || null;
    }

    setupTypeAndIcon(type, customIcon);
    titleEl.textContent = title;
    messageEl.textContent = message;
    inputWrapperEl.style.display = 'none';

    cancelBtn.style.display = 'inline-flex';
    cancelBtn.textContent = cancelText;
    confirmBtn.textContent = confirmText;

    open();
    setTimeout(() => {
      if (type === 'danger') {
        cancelBtn.focus();
      } else {
        confirmBtn.focus();
      }
    }, 60);

    return new Promise((resolve) => {
      currentResolve = resolve;
    });
  }

  /**
   * Finestra modale di avviso/alert (Promise<void>)
   */
  function alert(opts) {
    ensureDOMElements();
    let title = 'Avviso';
    let message = '';
    let type = 'info';
    let confirmText = 'Ho capito';
    let customIcon = null;

    if (typeof opts === 'string') {
      message = opts;
      const lower = message.toLowerCase();
      if (lower.includes('errore') || lower.includes('fallit') || lower.includes('impossibile')) {
        type = 'danger';
        title = 'Attenzione';
      } else if (lower.includes('successo') || lower.includes('completat') || lower.includes('salvat')) {
        type = 'success';
        title = 'Operazione Riuscita';
      }
    } else if (opts && typeof opts === 'object') {
      message = opts.message || '';
      type = opts.type || 'info';
      title = opts.title || (type === 'danger' ? 'Attenzione' : (type === 'success' ? 'Operazione Riuscita' : 'Avviso'));
      confirmText = opts.confirmText || 'Ho capito';
      customIcon = opts.icon || null;
    }

    setupTypeAndIcon(type, customIcon);
    titleEl.textContent = title;
    messageEl.textContent = message;
    inputWrapperEl.style.display = 'none';

    cancelBtn.style.display = 'none';
    confirmBtn.textContent = confirmText;

    open();
    setTimeout(() => confirmBtn.focus(), 60);

    return new Promise((resolve) => {
      currentResolve = () => resolve();
    });
  }

  /**
   * Finestra modale di prompt per inserimento testo (Promise<string|null>)
   */
  function prompt(opts) {
    ensureDOMElements();
    let title = 'Inserisci valore';
    let message = '';
    let defaultValue = '';
    let placeholder = '';
    let confirmText = 'Conferma';
    let cancelText = 'Annulla';

    if (typeof opts === 'string') {
      message = opts;
    } else if (opts && typeof opts === 'object') {
      message = opts.message || '';
      title = opts.title || 'Inserisci valore';
      defaultValue = opts.defaultValue || '';
      placeholder = opts.placeholder || '';
      confirmText = opts.confirmText || 'Conferma';
      cancelText = opts.cancelText || 'Annulla';
    }

    setupTypeAndIcon('info', '✏️');
    titleEl.textContent = title;
    messageEl.textContent = message;
    inputWrapperEl.style.display = 'block';
    inputEl.value = defaultValue;
    inputEl.placeholder = placeholder;

    cancelBtn.style.display = 'inline-flex';
    cancelBtn.textContent = cancelText;
    confirmBtn.textContent = confirmText;

    open();
    setTimeout(() => {
      inputEl.focus();
      inputEl.select();
    }, 60);

    return new Promise((resolve) => {
      currentResolve = (res) => {
        resolve(res === false ? null : res);
      };
    });
  }

  return {
    confirm,
    alert,
    prompt
  };
})();

/**
 * Inizializza l'intercettazione dichiarativa di form, bottoni e link con data-confirm
 * e migra automaticamente qualsiasi onsubmit="return confirm(...)" legacy.
 */
function initConfirmDialogs() {
  // 1. Riconversione automatica per form inline legacy
  document.querySelectorAll('form[onsubmit]').forEach((form) => {
    const onsubmitStr = form.getAttribute('onsubmit') || '';
    const match = onsubmitStr.match(/confirm\s*\(\s*(['"])(.*?)\1\s*\)/);
    if (match && match[2]) {
      const msg = match[2].replace(/\\'/g, "'").replace(/\\"/g, '"');
      form.removeAttribute('onsubmit');
      form.setAttribute('data-confirm', msg);
      if (!form.getAttribute('data-confirm-title')) {
        const isDelete = /elimina|rimuov|cancella/i.test(msg);
        form.setAttribute('data-confirm-title', isDelete ? 'Conferma Eliminazione' : 'Richiesta di Conferma');
        form.setAttribute('data-confirm-type', isDelete ? 'danger' : 'warning');
        form.setAttribute('data-confirm-btn', isDelete ? 'Elimina' : 'Conferma');
      }
    }
  });

  // 2. Ascoltatore globale eventi submit per form con data-confirm
  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.hasAttribute('data-confirm')) {
      // Fallback proattivo: intercetta anche form dinamici con onsubmit="return confirm(...)"
      const onsubmitStr = form.getAttribute('onsubmit') || '';
      const match = onsubmitStr.match(/confirm\s*\(\s*(['"])(.*?)\1\s*\)/);
      if (match && match[2]) {
        form.removeAttribute('onsubmit');
        form.setAttribute('data-confirm', match[2].replace(/\\'/g, "'").replace(/\\"/g, '"'));
      } else {
        return;
      }
    }
    if (form._isAppConfirmed) {
      // Se già confermato, consentiamo il submit nativo e ripristiniamo il flag subito dopo
      setTimeout(() => { form._isAppConfirmed = false; }, 400);
      return;
    }

    e.preventDefault();
    e.stopImmediatePropagation();

    const submitter = e.submitter;
    const message = form.getAttribute('data-confirm');
    const title = form.getAttribute('data-confirm-title');
    const type = form.getAttribute('data-confirm-type') || (/elimina|rimuov|cancella/i.test(message) ? 'danger' : 'warning');
    const confirmText = form.getAttribute('data-confirm-btn') || (type === 'danger' ? 'Elimina' : 'Conferma');
    const cancelText = form.getAttribute('data-confirm-cancel') || 'Annulla';

    const confirmed = await AppDialog.confirm({
      title,
      message,
      type,
      confirmText,
      cancelText
    });

    if (confirmed) {
      form._isAppConfirmed = true;
      if (submitter && submitter.name && !form.querySelector(`input[type="hidden"][name="${submitter.name}"]`)) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = submitter.name;
        hidden.value = submitter.value;
        form.appendChild(hidden);
      }
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit(submitter);
      } else {
        form.submit();
      }
    }
  }, true);

  // 3. Ascoltatore per elementi clickabili con data-confirm (link o bottoni fuori da form)
  document.addEventListener('click', async (e) => {
    const target = e.target.closest('[data-confirm]');
    if (!target) return;
    if (target.tagName.toLowerCase() === 'form') return;
    if (target.tagName.toLowerCase() === 'button' && target.type === 'submit' && target.closest('form')) return;

    e.preventDefault();
    e.stopImmediatePropagation();

    const message = target.getAttribute('data-confirm');
    const title = target.getAttribute('data-confirm-title');
    const type = target.getAttribute('data-confirm-type') || (/elimina|rimuov|cancella/i.test(message) ? 'danger' : 'warning');
    const confirmText = target.getAttribute('data-confirm-btn') || (type === 'danger' ? 'Elimina' : 'Conferma');
    const cancelText = target.getAttribute('data-confirm-cancel') || 'Annulla';

    const confirmed = await AppDialog.confirm({
      title,
      message,
      type,
      confirmText,
      cancelText
    });

    if (confirmed) {
      if (target.tagName.toLowerCase() === 'a' && target.href) {
        window.location.href = target.href;
      } else if (typeof target.onclick === 'function') {
        target.onclick();
      }
    }
  }, true);
}

// Esporta globalmente sul window
window.AppDialog = AppDialog;
window.alert = (message) => AppDialog.alert(message);
window.confirmAsync = (message, options) => AppDialog.confirm(options ? Object.assign({ message }, options) : message);
window.promptAsync = (message, defaultValue) => AppDialog.prompt({ message, defaultValue });

