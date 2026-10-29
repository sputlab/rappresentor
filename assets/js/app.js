/**
 * Rappresentor - Client JavaScript
 * Versione SemVer: 0.2.0
 */

document.addEventListener('DOMContentLoaded', () => {
  initMobileNav();
  initModals();
  initClipboardButtons();
  initQuickFilters();
  initServiceWorker();
  initPwaInstallPrompt();
  initScrollTopButton();
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

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `<span>${message}</span>`;

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

  const toggleScrollBtn = () => {
    if (window.scrollY > 300) {
      btn.classList.add('visible');
    } else {
      btn.classList.remove('visible');
    }
  };

  window.addEventListener('scroll', toggleScrollBtn, { passive: true });
  toggleScrollBtn();

  btn.addEventListener('click', (e) => {
    e.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });
}
