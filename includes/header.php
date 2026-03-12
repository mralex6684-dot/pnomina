<?php
require_once __DIR__ . '/../includes/auth.php';
requiereLogin();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$userName = $_SESSION['usuario_nombre'] ?? 'Usuario';
$userRole = $_SESSION['rol_nombre'] ?? '';
$initials = strtoupper(substr($userName, 0, 1) . (strpos($userName, ' ') !== false ? substr(strstr($userName, ' '), 1, 1) : ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? 'pnomina' ?> — NominaRest</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
</head>
<body>
<div class="app-wrapper">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-text">Nomina<span>Rest</span></div>
      <div class="logo-sub">Panel Administrativo</div>
    </div>

    <nav class="sidebar-nav">
      <a href="<?= APP_URL ?>/index.php" class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>

      <?php if(tienePermiso('empleados')): ?>
      <a href="<?= APP_URL ?>/modules/empleados/lista.php" class="nav-link <?= strpos($currentPage, 'empleado') !== false ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Empleados
      </a>
      <?php endif; ?>

      <?php if(tienePermiso('roles')): ?>
      <a href="<?= APP_URL ?>/modules/roles/lista.php" class="nav-link <?= strpos($currentPage, 'rol') !== false ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M6 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M19 11l2 2-2 2"/><path d="M5 11l-2 2 2 2"/></svg>
        Roles y Cargos
      </a>
      <?php endif; ?>

      <?php if(tienePermiso('nomina')): ?>
      <a href="<?= APP_URL ?>/modules/dashboard/index.php" class="nav-link">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        Generar Nómina
      </a>

      <a href="<?= APP_URL ?>/modules/dashboard/index.php" class="nav-link">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        Nómina por Rol
      </a>
      <?php endif; ?>

      <?php if(tienePermiso('pagos')): ?>
      <a href="<?= APP_URL ?>/modules/dashboard/index.php" class="nav-link">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        Pagos
      </a>
      <?php endif; ?>

      <a href="<?= APP_URL ?>/modules/dashboard/index.php" class="nav-link">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Historial
      </a>

      <?php if(tienePermiso('reportes')): ?>
      <a href="<?= APP_URL ?>/modules/dashboard/index.php" class="nav-link">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        Reportes
      </a>
      <?php endif; ?>

      <a href="<?= APP_URL ?>/modules/dashboard/index.php" class="nav-link">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 2h18v20H3z"/><path d="M3 8h18"/><path d="M9 2v6"/><path d="M15 2v6"/></svg>
        Restaurante
      </a>
    </nav>

    <div class="sidebar-footer">
      <a href="<?= APP_URL ?>/logout.php" class="nav-link" style="color:var(--accent-red)">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Cerrar sesión
      </a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <div class="main-content">
    <!-- TOPBAR -->
    <header class="topbar">
      <div class="topbar-search">
        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="Buscar...">
      </div>
      <div class="topbar-right">
        <div class="topbar-notif">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span class="notif-badge">3</span>
        </div>
        <div class="topbar-user">
          <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($userName) ?></div>
            <div class="user-role"><?= htmlspecialchars($userRole) ?></div>
          </div>
          <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
        </div>
      </div>
    </header>

    <div class="page-content">
