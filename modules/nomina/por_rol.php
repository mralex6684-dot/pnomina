<?php
require_once __DIR__ . '/../../includes/auth.php';
requiereLogin();
if (!tienePermiso('nomina')) {
    header('Location: ' . APP_URL . '/index.php?error=sin_permiso'); exit;
}
$db = getDB();
$pageTitle = 'Nómina por Rol';

$mes  = (int)($_GET['mes']  ?? date('n'));
$anio = (int)($_GET['anio'] ?? date('Y'));
$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

// Nóminas agrupadas por cargo
$porCargo = $db->prepare("
    SELECT c.nombre as cargo, c.departamento,
           COUNT(n.id) as total_nominas,
           SUM(n.total_neto) as total_pago,
           SUM(n.horas_extra) as total_horas_extra,
           SUM(CASE WHEN n.estado='pagado' THEN 1 ELSE 0 END) as pagados,
           SUM(CASE WHEN n.estado='pendiente' THEN 1 ELSE 0 END) as pendientes,
           SUM(CASE WHEN n.estado='aprobado' THEN 1 ELSE 0 END) as aprobados
    FROM nominas n
    JOIN empleados e ON n.empleado_id=e.id
    JOIN cargos c ON e.cargo_id=c.id
    WHERE n.periodo_mes=? AND n.periodo_anio=?
    GROUP BY c.id
    ORDER BY total_pago DESC
");
$porCargo->execute([$mes,$anio]);
$porCargo = $porCargo->fetchAll();

// Detalle por cargo
$detalleCargo = $db->prepare("
    SELECT n.*, CONCAT(e.nombres,' ',e.apellidos) as empleado_nombre, c.nombre as cargo_nombre
    FROM nominas n
    JOIN empleados e ON n.empleado_id=e.id
    JOIN cargos c ON e.cargo_id=c.id
    WHERE n.periodo_mes=? AND n.periodo_anio=? AND c.nombre=?
    ORDER BY n.total_neto DESC
");

include __DIR__ . '/../../includes/header.php';
?>
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div>
    <h1 class="page-title">Nómina por Rol</h1>
    <p class="page-subtitle">Resumen agrupado por cargo — <?=$meses[$mes]?> <?=$anio?></p>
  </div>
  <form method="GET" style="display:flex;gap:8px">
    <select name="mes" class="form-control" style="width:130px">
      <?php for($i=1;$i<=12;$i++): ?><option value="<?=$i?>" <?=$mes==$i?'selected':''?>><?=$meses[$i]?></option><?php endfor; ?>
    </select>
    <select name="anio" class="form-control" style="width:90px">
      <?php for($a=2024;$a<=2027;$a++): ?><option value="<?=$a?>" <?=$anio==$a?'selected':''?>><?=$a?></option><?php endfor; ?>
    </select>
    <button type="submit" class="btn btn-secondary">Ver</button>
  </form>
</div>

<?php if(empty($porCargo)): ?>
<div class="card"><div class="card-body"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/></svg><p>No hay nóminas generadas para este período</p></div></div></div>
<?php else: ?>

<!-- Cards por cargo -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-bottom:24px">
<?php foreach($porCargo as $c): ?>
<div class="card" style="cursor:pointer;transition:all 0.2s" onmouseenter="this.style.borderColor='var(--accent-red)'" onmouseleave="this.style.borderColor='var(--border)'" onclick="verDetalleCargo('<?=htmlspecialchars($c['cargo'])?>')">
  <div class="card-header" style="border-bottom-color:var(--border-light)">
    <div>
      <div class="card-title"><?=htmlspecialchars($c['cargo'])?></div>
      <div style="font-size:11px;color:var(--text-muted)"><?=htmlspecialchars($c['departamento'])?></div>
    </div>
    <span class="badge badge-blue"><?=$c['total_nominas']?> emp.</span>
  </div>
  <div class="card-body" style="padding:16px 20px">
    <div style="font-size:22px;font-weight:700;font-family:Syne,sans-serif;color:var(--accent-green);margin-bottom:12px"><?=formatCOP($c['total_pago'])?></div>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <?php if($c['pagados']>0): ?><span class="badge badge-green"><?=$c['pagados']?> pagado<?=$c['pagados']>1?'s':''?></span><?php endif; ?>
      <?php if($c['aprobados']>0): ?><span class="badge badge-blue"><?=$c['aprobados']?> aprobado<?=$c['aprobados']>1?'s':''?></span><?php endif; ?>
      <?php if($c['pendientes']>0): ?><span class="badge badge-yellow"><?=$c['pendientes']?> pendiente<?=$c['pendientes']>1?'s':''?></span><?php endif; ?>
    </div>
    <?php if($c['total_horas_extra']>0): ?>
    <div style="margin-top:10px;font-size:12px;color:var(--text-muted)"><?=$c['total_horas_extra']?> horas extra registradas</div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Tabla resumen -->
<div class="card">
  <div class="card-header"><span class="card-title">Resumen consolidado</span></div>
  <div class="card-body table-wrap">
    <table>
      <thead><tr><th>Cargo</th><th>Departamento</th><th>Empleados</th><th>H. Extra</th><th>Total a Pagar</th><th>Pagados</th><th>Pendientes</th></tr></thead>
      <tbody>
      <?php
        $grandTotal = 0;
        foreach($porCargo as $c):
          $grandTotal += $c['total_pago'];
      ?>
      <tr>
        <td class="fw-600"><?=htmlspecialchars($c['cargo'])?></td>
        <td class="text-muted"><?=htmlspecialchars($c['departamento'])?></td>
        <td><span class="badge badge-blue"><?=$c['total_nominas']?></span></td>
        <td><?=$c['total_horas_extra']>0?'<span class="badge badge-purple">'.$c['total_horas_extra'].' hrs</span>':'<span class="text-muted">—</span>'?></td>
        <td class="fw-600 text-green"><?=formatCOP($c['total_pago'])?></td>
        <td><?=$c['pagados']>0?'<span class="badge badge-green">'.$c['pagados'].'</span>':'<span class="text-muted">0</span>'?></td>
        <td><?=$c['pendientes']>0?'<span class="badge badge-yellow">'.$c['pendientes'].'</span>':'<span class="text-muted">0</span>'?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:var(--bg-secondary)">
          <td colspan="4" class="fw-600" style="padding:14px 20px">TOTAL PERÍODO</td>
          <td class="fw-600" style="padding:14px 20px;color:var(--accent-green);font-size:16px"><?=formatCOP($grandTotal)?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- MODAL DETALLE CARGO -->
<div class="modal-overlay" id="modalDetalleCargo">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <span class="modal-title" id="titDetalleCargo">Detalle</span>
      <button class="btn btn-ghost btn-icon" onclick="document.getElementById('modalDetalleCargo').classList.remove('active')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body" id="contentDetalleCargo"><div style="text-align:center;padding:40px;color:var(--text-muted)">Cargando...</div></div>
  </div>
</div>

<script>
async function verDetalleCargo(cargo) {
  document.getElementById('titDetalleCargo').textContent = 'Cargo: ' + cargo;
  document.getElementById('modalDetalleCargo').classList.add('active');
  const res = await fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=detalle&cargo='+encodeURIComponent(cargo)+'&mes=<?=$mes?>&anio=<?=$anio?>'});
  const html = await res.text();
  document.getElementById('contentDetalleCargo').innerHTML = html;
}
</script>

<?php
// AJAX detalle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'')==='detalle') {
    $cargo = $_POST['cargo'] ?? '';
    $m = (int)($_POST['mes'] ?? $mes);
    $a = (int)($_POST['anio'] ?? $anio);
    $detalleCargo->execute([$m,$a,$cargo]);
    $rows = $detalleCargo->fetchAll();
    $badges=['pagado'=>'badge-green','aprobado'=>'badge-blue','pendiente'=>'badge-yellow','rechazado'=>'badge-red'];
    $labels=['pagado'=>'Pagado','aprobado'=>'Aprobado','pendiente'=>'Pendiente','rechazado'=>'Rechazado'];
    echo '<div class="table-wrap"><table>';
    echo '<thead><tr><th>Empleado</th><th>Salario Base</th><th>H. Extra</th><th>Total Neto</th><th>Estado</th></tr></thead><tbody>';
    foreach($rows as $r) {
        echo '<tr>';
        echo '<td class="fw-600">'.htmlspecialchars($r['empleado_nombre']).'</td>';
        echo '<td>'.formatCOP($r['salario_base']).'</td>';
        echo '<td>'.($r['horas_extra']>0?'<span class="badge badge-purple">'.$r['horas_extra'].' hrs</span>':'<span class="text-muted">—</span>').'</td>';
        echo '<td class="fw-600 text-green">'.formatCOP($r['total_neto']).'</td>';
        echo '<td><span class="badge '.($badges[$r['estado']]??'badge-blue').'">'.($labels[$r['estado']]??$r['estado']).'</span></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    exit;
}
include __DIR__ . '/../../includes/footer.php';
?>
