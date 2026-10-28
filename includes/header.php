<?php
declare(strict_types=1);

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;

require_once __DIR__ . '/init.php';

// Tutte le pagine che includono l'header sono protette da autenticazione
Auth::requireAuth('login.php');

$currentUser = Auth::user();
$allClasses = Helper::getAllClasses($db);
$activeClass = Helper::getActiveClass($db);
$activeClassId = $activeClass['id'] ?? null;
$appVersion = Helper::getAppVersion();
$flash = Helper::getFlash();

$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? Helper::e($pageTitle) . ' - ' : '' ?>Rappresentor</title>

  <!-- PWA Manifest & Icons -->
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="icon" type="image/svg+xml" href="assets/icons/favicon.svg">
  <link rel="alternate icon" type="image/png" href="assets/icons/favicon-32x32.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
  <meta name="theme-color" content="#2563eb">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="Rappresentor">

  <link rel="stylesheet" href="assets/css/style.css?v=<?= Helper::e($appVersion) ?>">
</head>
<body>
<div class="app-wrapper">
  <!-- Top Navigation -->
  <header class="top-nav">
    <div style="display: flex; align-items: center; gap: 14px;">
      <button class="mobile-nav-toggle" aria-label="Apri menu">☰</button>
      <a href="index.php" class="brand">
        <img src="assets/icons/favicon.svg" alt="Logo" width="28" height="28" style="vertical-align: middle;">
        <span>Rappresentor</span>
      </a>
    </div>

    <!-- Selettore Sezione / Classe Attiva (Visibile su Desktop) -->
    <?php if (!empty($allClasses)): ?>
      <div class="class-switcher desktop-only">
        <span>🏫</span>
        <select onchange="window.location.href='?switch_class='+this.value" title="Seleziona la classe da gestire">
          <?php foreach ($allClasses as $cls): 
            $typeInfo = Helper::schoolTypeDetails($cls['school_type']);
          ?>
            <option value="<?= (int)$cls['id'] ?>" <?= ((int)$cls['id'] === (int)$activeClassId) ? 'selected' : '' ?>>
              <?= $typeInfo['icon'] ?> <?= Helper::e($cls['name']) ?> (<?= Helper::e($cls['school_year']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php else: ?>
      <div class="desktop-only" style="font-size: 0.85rem; color: var(--text-muted);">
        <a href="classi.php" class="btn btn-sm btn-primary">+ Crea Prima Classe</a>
      </div>
    <?php endif; ?>

    <!-- User Menu & Logout -->
    <div class="nav-actions">
      <div class="user-pill" title="Ruolo: <?= Helper::e(ucfirst($currentUser['role'])) ?>">
        <div class="user-avatar"><?= strtoupper(substr($currentUser['name'], 0, 1)) ?></div>
        <span style="font-weight: 500;"><?= Helper::e($currentUser['name']) ?></span>
      </div>
      <a href="logout.php" class="btn btn-sm btn-secondary" title="Disconnettiti">Esci</a>
    </div>
  </header>

  <div class="app-container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <!-- Selettore Sezione / Classe Attiva (Visibile su Mobile all'interno del Burger Menu) -->
      <?php if (!empty($allClasses)): ?>
        <div class="sidebar-class-picker mobile-only">
          <label class="sidebar-class-label">Classe Attiva</label>
          <div class="sidebar-class-select-wrap">
            <span style="font-size: 1.1rem;">🏫</span>
            <select class="sidebar-class-select" onchange="window.location.href='?switch_class='+this.value" title="Seleziona la classe da gestire">
              <?php foreach ($allClasses as $cls): 
                $typeInfo = Helper::schoolTypeDetails($cls['school_type']);
              ?>
                <option value="<?= (int)$cls['id'] ?>" <?= ((int)$cls['id'] === (int)$activeClassId) ? 'selected' : '' ?>>
                  <?= $typeInfo['icon'] ?> <?= Helper::e($cls['name']) ?> (<?= Helper::e($cls['school_year']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      <?php else: ?>
        <div class="sidebar-class-picker mobile-only">
          <a href="classi.php" class="btn btn-sm btn-primary" style="display: block; text-align: center;">+ Crea Prima Classe</a>
        </div>
      <?php endif; ?>

      <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?= $currentScript === 'index.php' ? 'active' : '' ?>">
          <span class="nav-icon">📊</span>
          <span>Dashboard</span>
        </a>

        <a href="alunni.php" class="nav-item <?= ($currentScript === 'alunni.php' || $currentScript === 'alunno_dettaglio.php') ? 'active' : '' ?>">
          <span class="nav-icon">🎒</span>
          <span>Alunni & Famiglie</span>
        </a>

        <a href="cassa.php" class="nav-item <?= ($currentScript === 'cassa.php' || $currentScript === 'campagna_dettaglio.php') ? 'active' : '' ?>">
          <span class="nav-icon">💰</span>
          <span>Cassa & Quote</span>
        </a>

        <a href="comunicazioni.php" class="nav-item <?= $currentScript === 'comunicazioni.php' ? 'active' : '' ?>">
          <span class="nav-icon">📢</span>
          <span>Bacheca WhatsApp</span>
        </a>

        <a href="calendario.php" class="nav-item <?= $currentScript === 'calendario.php' ? 'active' : '' ?>">
          <span class="nav-icon">📅</span>
          <span>Scadenze & Eventi</span>
        </a>

        <a href="consigli.php" class="nav-item <?= $currentScript === 'consigli.php' ? 'active' : '' ?>">
          <span class="nav-icon">📋</span>
          <span>Consigli & Note</span>
        </a>

        <a href="classi.php" class="nav-item <?= $currentScript === 'classi.php' ? 'active' : '' ?>">
          <span class="nav-icon">🏫</span>
          <span>Gestione Classi</span>
        </a>

        <?php if (Auth::isAdmin()): ?>
          <a href="utenti.php" class="nav-item <?= $currentScript === 'utenti.php' ? 'active' : '' ?>">
            <span class="nav-icon">👥</span>
            <span>Collaboratori</span>
          </a>

          <a href="impostazioni.php" class="nav-item <?= $currentScript === 'impostazioni.php' ? 'active' : '' ?>">
            <span class="nav-icon">⚙️</span>
            <span>Impostazioni & Mail</span>
          </a>
        <?php endif; ?>

        <button type="button" class="nav-item" data-pwa-install style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--primary); font-family: inherit;">
          <span class="nav-icon">📲</span>
          <span>Installa App</span>
        </button>
      </nav>

      <div class="sidebar-footer">
        <span>Rappresentor <a href="changelog.php" title="Visualizza Changelog e novità">v<?= Helper::e($appVersion) ?></a></span>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <!-- Banner di Installazione PWA -->
      <div class="pwa-banner" id="pwaInstallBanner">
        <div class="pwa-banner-content">
          <img src="assets/icons/favicon.svg" alt="App Icon" width="28" height="28">
          <div>
            <strong>Installa Rappresentor</strong>
            <div style="font-size: 0.78rem; opacity: 0.9;">Accedi rapidamente con un tocco dalla schermata Home</div>
          </div>
        </div>
        <button type="button" class="pwa-banner-btn" data-pwa-install>Installa</button>
      </div>

      <?php if ($flash): ?>
        <div class="alert alert-<?= Helper::e($flash['type']) ?>">
          <div>
            <?php if ($flash['type'] === 'success'): ?>✅
            <?php elseif ($flash['type'] === 'danger'): ?>❌
            <?php elseif ($flash['type'] === 'warning'): ?>⚠️
            <?php else: ?>ℹ️<?php endif; ?>
          </div>
          <div style="flex: 1;"><?= Helper::e($flash['message']) ?></div>
        </div>
      <?php endif; ?>
