<?php
// ============================================
// pnomina - Dashboard del Usuario
// Solo accesible por rol "Usuario"
// ============================================
require_once __DIR__ . '/includes/auth.php';
iniciarSesion();

if (!estaAutenticado()) {
    header('Location: ' . APP_URL . '/login.php'); exit;
}
if (!esUsuarioNormal()) {
    header('Location: ' . APP_URL . '/index.php'); exit;
}

$db = getDB();
$empleadoId = $_SESSION['empleado_id'] ?? null;

// Si no tiene empleado vinculado mostrar mensaje
if (!$empleadoId) {
    $pageTitle = 'Mi Dashboard';
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Mi Dashboard — NominaRest</title>
      <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
    </head>
    <body style="display:flex;align-items:center;justify-content:center;min-height:100vh">
      <div style="text-align:center;max-width:400px;padding:40px">
        <div style="font-family:Syne,sans-serif;font-size:28px;font-weight:800;margin-bottom:8px">Nomina<span style="color:var(--accent-red)">Rest</span></div>
        <p style="color:var(--text-secondary);margin-bottom:24px">Tu cuenta no está vinculada a ningún empleado. Contacta al administrador.</p>
        <a href="<?= APP_URL ?>/logout.php" class="btn btn-primary">Cerrar sesión</a>
      </div>
    </body>
    </html>
    <?php exit;
}

// Datos del empleado
$emp = $db->prepare("SELECT e.*, c.nombre as cargo_nombre, c.departamento FROM empleados e JOIN cargos c ON e.cargo_id=c.id WHERE e.id=?");
$emp->execute([$empleadoId]);
$empleado = $emp->fetch();

if (!$empleado) {
    header('Location: ' . APP_URL . '/logout.php'); exit;
}

// Todas las nóminas del empleado
$nominas = $db->prepare("
    SELECT * FROM nominas 
    WHERE empleado_id = ? 
    ORDER BY periodo_anio DESC, periodo_mes DESC
");
$nominas->execute([$empleadoId]);
$nominas = $nominas->fetchAll();

// Última nómina
$ultimaNomina = $nominas[0] ?? null;

// Totales
$totalAnual = $db->prepare("SELECT COALESCE(SUM(total_neto),0) FROM nominas WHERE empleado_id=? AND periodo_anio=? AND estado IN ('pagado','aprobado')");
$totalAnual->execute([$empleadoId, date('Y')]);
$totalAnual = $totalAnual->fetchColumn();

$totalHorasAnual = $db->prepare("SELECT COALESCE(SUM(horas_extra),0) FROM nominas WHERE empleado_id=? AND periodo_anio=?");
$totalHorasAnual->execute([$empleadoId, date('Y')]);
$totalHorasAnual = $totalHorasAnual->fetchColumn();

$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$userName = $_SESSION['usuario_nombre'] ?? '';
$initials = strtoupper(substr($userName,0,1) . (strpos($userName,' ')!==false ? substr(strstr($userName,' '),1,1) : ''));

$badges = ['pagado'=>'badge-green','aprobado'=>'badge-blue','pendiente'=>'badge-yellow','rechazado'=>'badge-red'];
$labels = ['pagado'=>'Pagado','aprobado'=>'Aprobado','pendiente'=>'Pendiente','rechazado'=>'Rechazado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mi Dashboard — NominaRest</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
</head>
<body>
<div class="app-wrapper">

  <!-- SIDEBAR simplificado -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-text">Nomina<span>Rest</span></div>
      <div class="logo-sub">Portal del Empleado</div>
    </div>
    <nav class="sidebar-nav">
      <a href="<?= APP_URL ?>/mi_dashboard.php" class="nav-link active">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Mi Dashboard
      </a>
      <div class="nav-section-label">Mis Nóminas</div>
      <?php foreach(array_slice($nominas,0,6) as $n): ?>
      <a href="<?= APP_URL ?>/mi_nomina_pdf.php?id=<?= $n['id'] ?>" class="nav-link" target="_blank">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <?= $meses[$n['periodo_mes']] ?> <?= $n['periodo_anio'] ?>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
      <a href="<?= APP_URL ?>/logout.php" class="nav-link" style="color:var(--accent-red)">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Cerrar sesión
      </a>
    </div>
  </aside>

  <div class="main-content">
    <!-- TOPBAR -->
    <header class="topbar">
      <div style="flex:1">
        <span style="font-size:13px;color:var(--text-muted)">Portal del empleado</span>
      </div>
      <div class="topbar-right">
        <div class="topbar-user">
          <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($userName) ?></div>
            <div class="user-role">Usuario</div>
          </div>
          <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
        </div>
      </div>
    </header>

    <div class="page-content">

      <!-- Encabezado con info del empleado -->
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:28px 32px;margin-bottom:24px;display:flex;align-items:center;gap:24px">
        <div style="width:64px;height:64px;background:linear-gradient(135deg,var(--accent-red),#ff6b6b);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:Syne,sans-serif;font-size:24px;font-weight:800;flex-shrink:0">
          <?= strtoupper(substr($empleado['nombres'],0,1).substr($empleado['apellidos'],0,1)) ?>
        </div>
        <div style="flex:1">
          <div style="font-family:Syne,sans-serif;font-size:22px;font-weight:700"><?= htmlspecialchars($empleado['nombres'].' '.$empleado['apellidos']) ?></div>
          <div style="color:var(--text-secondary);font-size:13px;margin-top:2px"><?= htmlspecialchars($empleado['cargo_nombre']) ?> — <?= htmlspecialchars($empleado['departamento']) ?></div>
          <div style="display:flex;gap:12px;margin-top:10px;flex-wrap:wrap">
            <span class="badge badge-blue">C.C. <?= htmlspecialchars($empleado['cedula']) ?></span>
            <span class="badge badge-green"><?= htmlspecialchars($empleado['tipo_contrato'] === 'indefinido' ? 'Contrato indefinido' : ucfirst($empleado['tipo_contrato'])) ?></span>
            <span class="badge badge-purple">Desde <?= date('d/m/Y', strtotime($empleado['fecha_ingreso'])) ?></span>
          </div>
        </div>
        <div style="text-align:right">
          <div style="font-size:11px;color:var(--text-muted)">Salario mensual</div>
          <div style="font-family:Syne,sans-serif;font-size:22px;font-weight:700;color:var(--accent-green)"><?= formatCOP($empleado['salario']) ?></div>
        </div>
      </div>

      <!-- Stats -->
      <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
        <div class="stat-card">
          <div>
            <div class="stat-label">Nóminas registradas</div>
            <div class="stat-value"><?= count($nominas) ?></div>
          </div>
          <div class="stat-icon blue">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          </div>
        </div>
        <div class="stat-card">
          <div>
            <div class="stat-label">Total cobrado <?= date('Y') ?></div>
            <div class="stat-value cop text-green"><?= formatCOP($totalAnual) ?></div>
          </div>
          <div class="stat-icon green">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
        </div>
        <div class="stat-card">
          <div>
            <div class="stat-label">Horas extra <?= date('Y') ?></div>
            <div class="stat-value"><?= $totalHorasAnual ?></div>
          </div>
          <div class="stat-icon red">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
        </div>
      </div>

      <!-- Última nómina destacada -->
      <?php if ($ultimaNomina): ?>
      <div style="margin-bottom:24px">
        <h2 style="font-family:Syne,sans-serif;font-size:16px;font-weight:700;margin-bottom:12px">Última Nómina</h2>
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:20px">
            <div>
              <div style="font-family:Syne,sans-serif;font-size:18px;font-weight:700"><?= $meses[$ultimaNomina['periodo_mes']] ?> <?= $ultimaNomina['periodo_anio'] ?></div>
              <span class="badge <?= $badges[$ultimaNomina['estado']] ?? 'badge-blue' ?>" style="margin-top:6px"><?= $labels[$ultimaNomina['estado']] ?? $ultimaNomina['estado'] ?></span>
            </div>
            <a href="<?= APP_URL ?>/mi_nomina_pdf.php?id=<?= $ultimaNomina['id'] ?>" target="_blank" class="btn btn-primary">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Descargar PDF
            </a>
          </div>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px">
            <?php
            $items = [
              ['Salario base', formatCOP($ultimaNomina['salario_base']), ''],
              ['Horas extra ('.$ultimaNomina['horas_extra'].' hrs)', formatCOP($ultimaNomina['valor_horas_extra']), 'text-blue'],
              ['Bonificaciones', formatCOP($ultimaNomina['bonificaciones']), 'text-blue'],
              ['Salud (4%)', '− '.formatCOP($ultimaNomina['deducciones_salud']), 'text-red'],
              ['Pensión (4%)', '− '.formatCOP($ultimaNomina['deducciones_pension']), 'text-red'],
              ['TOTAL NETO', formatCOP($ultimaNomina['total_neto']), 'text-green fw-600'],
            ];
            foreach($items as $item): ?>
            <div style="background:var(--bg-secondary);border-radius:var(--radius-md);padding:12px 16px">
              <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px"><?= $item[0] ?></div>
              <div style="font-size:14px;font-weight:600" class="<?= $item[2] ?>"><?= $item[1] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Historial de nóminas -->
      <div class="card">
        <div class="card-header"><span class="card-title">Historial de Nóminas</span></div>
        <div class="card-body table-wrap">
          <?php if(empty($nominas)): ?>
          <div class="empty-state"><p>No tienes nóminas registradas aún</p></div>
          <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Período</th>
                <th>Salario base</th>
                <th>H. Extra</th>
                <th>Deducciones</th>
                <th>Total neto</th>
                <th>Estado</th>
                <th style="text-align:center">Descargar</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($nominas as $n): ?>
            <tr>
              <td class="fw-600"><?= $meses[$n['periodo_mes']] ?> <?= $n['periodo_anio'] ?></td>
              <td><?= formatCOP($n['salario_base']) ?></td>
              <td><?= $n['horas_extra'] > 0 ? '<span class="badge badge-purple">'.$n['horas_extra'].' hrs</span>' : '<span class="text-muted">—</span>' ?></td>
              <td class="text-red"><?= formatCOP($n['total_deducciones']) ?></td>
              <td class="fw-600 text-green"><?= formatCOP($n['total_neto']) ?></td>
              <td><span class="badge <?= $badges[$n['estado']] ?? 'badge-blue' ?>"><?= $labels[$n['estado']] ?? $n['estado'] ?></span></td>
              <td style="text-align:center">
                <a href="<?= APP_URL ?>/mi_nomina_pdf.php?id=<?= $n['id'] ?>" target="_blank" class="btn btn-ghost btn-icon btn-sm" title="Descargar PDF">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
