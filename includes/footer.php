    </main>
  </div>

  <!-- Mobile Bottom Navigation Bar (Mobile First) -->
  <nav class="mobile-bottom-nav" aria-label="Navigazione Rapida Smartphone">
    <a href="index.php" class="bottom-nav-item <?= $currentScript === 'index.php' ? 'active' : '' ?>">
      <span class="bottom-nav-icon">📊</span>
      <span class="bottom-nav-label">Home</span>
    </a>
    <a href="alunni.php" class="bottom-nav-item <?= ($currentScript === 'alunni.php') ? 'active' : '' ?>">
      <span class="bottom-nav-icon">🎒</span>
      <span class="bottom-nav-label">Alunni</span>
    </a>
    <a href="cassa.php" class="bottom-nav-item <?= ($currentScript === 'cassa.php') ? 'active' : '' ?>">
      <span class="bottom-nav-icon">💰</span>
      <span class="bottom-nav-label">Cassa</span>
    </a>
    <a href="comunicazioni.php" class="bottom-nav-item <?= ($currentScript === 'comunicazioni.php') ? 'active' : '' ?>">
      <span class="bottom-nav-icon">💬</span>
      <span class="bottom-nav-label">WhatsApp</span>
    </a>
  </nav>
</div>

<!-- Backdrop per Drawer Mobile -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<script src="assets/js/app.js?v=<?= \Rappresentor\Core\Helper::getAppVersion() ?>"></script>
</body>
</html>
