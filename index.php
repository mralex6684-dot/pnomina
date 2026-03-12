<?php
require_once __DIR__ . '/includes/auth.php';
requiereLogin();

$db = getDB();
$pageTitle = 'Dashboard';

// Stats
$totalEmpleados = $db->query("SELECT COUNT(*) FROM empleados WHERE activo = 1")->fetchColumn();
$nominasPendientes = $db->query("SELECT COUNT(*) FROM nominas WHERE estado IN ('pendiente','aprobado')")->fetchColumn();
$totalPagadoMes = $db->query("
    SELECT COALESCE(SUM(total_neto),0) FROM nominas 
    WHERE estado = 'pagado' AND periodo_mes = MONTH(NOW()) AND periodo_anio = YEAR(NOW())
")->fetchColumn();
$horasExtra = $db->query("
    SELECT COALESCE(SUM(horas_extra),0) FROM nominas 
    WHERE periodo_mes = MONTH(NOW()) AND periodo_anio = YEAR(NOW())
")->fetchColumn();

// Últimas nóminas
$ultimasNominas = $db->query("
    SELECT n.*, 
           CONCAT(e.nombres,' ',e.apellidos) as empleado_nombre,
           c.nombre as cargo_nombre
    FROM nominas n
    JOIN empleados e ON n.empleado_id = e.id
    JOIN cargos c ON e.cargo_id = c.id
    ORDER BY n.created_at DESC
    LIMIT 8
")->fetchAll();

// Alertas
$sinPagoMes = $db->query("
    SELECT COUNT(*) FROM empleados e
    WHERE e.activo = 1 AND e.id NOT IN (
        SELECT empleado_id FROM nominas 
        WHERE periodo_mes = MONTH(NOW()) AND periodo_anio = YEAR(NOW()) AND estado = 'pagado'
    )
")->fetchColumn();

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Dashboard</h1>
  <p class="page-subtitle">Bienvenido al sistema de gestión de nómina</p>
</div>

<!-- STAT CARDS -->
<div class="stats-grid">
  <div class="stat-card">
    <div>
      <div class="stat-label">Total Empleados</div>
      <div class="stat-value"><?= $totalEmpleados ?></div>
    </div>
    <div class="stat-icon blue">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
  </div>

  <div class="stat-card">
    <div>
      <div class="stat-label">Nóminas Pendientes</div>
      <div class="stat-value"><?= $nominasPendientes ?></div>
    </div>
    <div class="stat-icon yellow">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    </div>
  </div>

  <div class="stat-card">
    <div>
      <div class="stat-label">Total Pagado Este Mes</div>
      <div class="stat-value cop"><?= formatCOP($totalPagadoMes) ?> COP</div>
    </div>
    <div class="stat-icon green">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    </div>
  </div>

  <div class="stat-card">
    <div>
      <div class="stat-label">Horas Extra Registradas</div>
      <div class="stat-value"><?= $horasExtra ?></div>
    </div>
    <div class="stat-icon red">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    </div>
  </div>
</div>

<!-- MAIN GRID -->
<div class="dashboard-grid">

  <!-- ÚLTIMAS NÓMINAS -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Últimas Nóminas Generadas</span>
      <?php if(tienePermiso('nomina')): ?>
      <a href="<?= APP_URL ?>/modules/empleados/lista.php" class="btn btn-secondary btn-sm">Ver todas</a>
      <?php endif; ?>
    </div>
    <div class="card-body table-wrap">
      <?php if (empty($ultimasNominas)): ?>
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <p>No hay nóminas generadas aún</p>
        </div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Empleado</th>
            <th>Cargo</th>
            <th>Mes</th>
            <th>Total</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ultimasNominas as $n): ?>
          <tr>
            <td class="fw-600"><?= htmlspecialchars($n['empleado_nombre']) ?></td>
            <td class="text-muted"><?= htmlspecialchars($n['cargo_nombre']) ?></td>
            <td class="text-muted"><?= getNombreMes($n['periodo_mes']) . ' ' . $n['periodo_anio'] ?></td>
            <td class="fw-600"><?= formatCOP($n['total_neto']) ?> COP</td>
            <td>
              <?php
                $badges = ['pagado'=>'badge-green','aprobado'=>'badge-blue','pendiente'=>'badge-yellow','rechazado'=>'badge-red'];
                $labels = ['pagado'=>'Pagado','aprobado'=>'Aprobado','pendiente'=>'Pendiente','rechazado'=>'Rechazado'];
                $est = $n['estado'];
              ?>
              <span class="badge <?= $badges[$est] ?? 'badge-blue' ?>"><?= $labels[$est] ?? $est ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- ALERTAS -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Alertas</span>
    </div>
    <div class="card-body">
      <div class="alerts-list">
        <?php if ($sinPagoMes > 0): ?>
        <div class="alert-item">
          <?= $sinPagoMes ?> empleado<?= $sinPagoMes > 1 ? 's' : '' ?> sin pago del mes actual
        </div>
        <?php endif; ?>
        <?php if ($nominasPendientes > 0): ?>
        <div class="alert-item yellow">
          <?= $nominasPendientes ?> nómina<?= $nominasPendientes > 1 ? 's' : '' ?> pendiente<?= $nominasPendientes > 1 ? 's' : '' ?> de aprobación
        </div>
        <?php endif; ?>
        <div class="alert-item green">
          Reporte mensual listo para generar
        </div>
        <div class="alert-item yellow">
          Verificar horas extra del período actual
        </div>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
