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
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
  <!-- Top Navigation -->
  <header class="top-nav">
    <div style="display: flex; align-items: center; gap: 14px;">
      <button class="mobile-nav-toggle" aria-label="Apri menu">☰</button>
      <a href="index.php" class="brand">
        <span class="brand-icon">🎓</span>
        <span>Rappresentor</span>
        <span class="brand-badge">v<?= Helper::e($appVersion) ?></span>
      </a>
    </div>

    <!-- Selettore Sezione / Classe Attiva -->
    <?php if (!empty($allClasses)): ?>
      <div class="class-switcher">
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
      <div style="font-size: 0.85rem; color: var(--text-muted);">
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
            <span>Collaboratori (max 5)</span>
          </a>

          <a href="impostazioni.php" class="nav-item <?= $currentScript === 'impostazioni.php' ? 'active' : '' ?>">
            <span class="nav-icon">⚙️</span>
            <span>Impostazioni & Mail</span>
          </a>
        <?php endif; ?>
      </nav>

      <div class="sidebar-footer">
        <span>SemVer v<?= Helper::e($appVersion) ?></span>
        <a href="https://semver.org" target="_blank" rel="noopener" style="color: var(--text-muted); font-size: 0.7rem;">info</a>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
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
