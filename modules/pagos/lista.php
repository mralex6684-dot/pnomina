<?php
require_once __DIR__ . '/../../includes/auth.php';
requiereLogin();
if (!tienePermiso('pagos')) {
    header('Location: ' . APP_URL . '/index.php?error=sin_permiso'); exit;
}
$db = getDB();
$pageTitle = 'Pagos';

// AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'registrar_pago') {
        $nomina_id   = (int)$_POST['nomina_id'];
        $metodo      = $_POST['metodo_pago'] ?? 'transferencia';
        $referencia  = trim($_POST['referencia'] ?? '');

        $nom = $db->prepare("SELECT * FROM nominas WHERE id=? AND estado='aprobado'");
        $nom->execute([$nomina_id]);
        $nomina = $nom->fetch();
        if (!$nomina) { echo json_encode(['ok'=>false,'msg'=>'Nómina no encontrada o no está aprobada']); exit; }

        try {
            $db->prepare("INSERT INTO pagos (nomina_id,monto,metodo_pago,referencia,registrado_por) VALUES (?,?,?,?,?)")
               ->execute([$nomina_id,$nomina['total_neto'],$metodo,$referencia,$_SESSION['usuario_id']]);
            $db->prepare("UPDATE nominas SET estado='pagado', fecha_pago=NOW() WHERE id=?")->execute([$nomina_id]);
            registrarHistorial('registrar_pago','pagos','Pago registrado nómina ID:'.$nomina_id);
            echo json_encode(['ok'=>true,'msg'=>'Pago registrado correctamente por '.formatCOP($nomina['total_neto'])]);
        } catch(PDOException $e) {
            echo json_encode(['ok'=>false,'msg'=>'Error: '.$e->getMessage()]);
        }
        exit;
    }
}

$mes  = (int)($_GET['mes']  ?? date('n'));
$anio = (int)($_GET['anio'] ?? date('Y'));
$meses=['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

// Nóminas aprobadas pendientes de pago
$aprobadas = $db->prepare("
    SELECT n.*, CONCAT(e.nombres,' ',e.apellidos) as empleado_nombre, c.nombre as cargo_nombre
    FROM nominas n JOIN empleados e ON n.empleado_id=e.id JOIN cargos c ON e.cargo_id=c.id
    WHERE n.estado='aprobado' AND n.periodo_mes=? AND n.periodo_anio=?
    ORDER BY e.nombres
");
$aprobadas->execute([$mes,$anio]);
$aprobadas = $aprobadas->fetchAll();

// Historial de pagos
$historialPagos = $db->prepare("
    SELECT p.*, CONCAT(e.nombres,' ',e.apellidos) as empleado_nombre,
           c.nombre as cargo_nombre, n.periodo_mes, n.periodo_anio,
           u.nombre as registrado_nombre
    FROM pagos p
    JOIN nominas n ON p.nomina_id=n.id
    JOIN empleados e ON n.empleado_id=e.id
    JOIN cargos c ON e.cargo_id=c.id
    LEFT JOIN usuarios u ON p.registrado_por=u.id
    WHERE n.periodo_mes=? AND n.periodo_anio=?
    ORDER BY p.fecha_pago DESC
");
$historialPagos->execute([$mes,$anio]);
$historialPagos = $historialPagos->fetchAll();

$totalPagado = array_sum(array_column($historialPagos,'monto'));

include __DIR__ . '/../../includes/header.php';
?>
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div>
    <h1 class="page-title">Pagos</h1>
    <p class="page-subtitle">Registrar y gestionar pagos de nómina — <?=$meses[$mes]?> <?=$anio?></p>
  </div>
  <form method="GET" style="display:flex;gap:8px">
    <select name="mes" class="form-control" style="width:130px"><?php for($i=1;$i<=12;$i++): ?><option value="<?=$i?>" <?=$mes==$i?'selected':''?>><?=$meses[$i]?></option><?php endfor; ?></select>
    <select name="anio" class="form-control" style="width:90px"><?php for($a=2024;$a<=2027;$a++): ?><option value="<?=$a?>" <?=$anio==$a?'selected':''?>><?=$a?></option><?php endfor; ?></select>
    <button type="submit" class="btn btn-secondary">Ver</button>
  </form>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card"><div><div class="stat-label">Pendientes de pago</div><div class="stat-value text-yellow"><?=count($aprobadas)?></div></div><div class="stat-icon yellow"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">Pagos realizados</div><div class="stat-value text-green"><?=count($historialPagos)?></div></div><div class="stat-icon green"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">Total pagado</div><div class="stat-value cop"><?=formatCOP($totalPagado)?></div></div><div class="stat-icon green"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
<!-- Pendientes de pago -->
<div class="card">
  <div class="card-header"><span class="card-title">Pendientes de Pago</span><span class="badge badge-yellow"><?=count($aprobadas)?></span></div>
  <div class="card-body table-wrap">
    <?php if(empty($aprobadas)): ?>
    <div class="empty-state" style="padding:30px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:36px;height:36px"><polyline points="20 6 9 17 4 12"/></svg><p>Sin pagos pendientes</p></div>
    <?php else: ?>
    <table>
      <thead><tr><th>Empleado</th><th>Total</th><th style="text-align:center">Pagar</th></tr></thead>
      <tbody>
      <?php foreach($aprobadas as $n): ?>
      <tr>
        <td><div class="fw-600"><?=htmlspecialchars($n['empleado_nombre'])?></div><div style="font-size:11px;color:var(--text-muted)"><?=htmlspecialchars($n['cargo_nombre'])?></div></td>
        <td class="fw-600 text-green"><?=formatCOP($n['total_neto'])?></td>
        <td style="text-align:center"><button class="btn btn-primary btn-sm" onclick="abrirPago(<?=$n['id']?>, '<?=htmlspecialchars(addslashes($n['empleado_nombre']))?>', <?=$n['total_neto']?>)">Registrar pago</button></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Historial pagos -->
<div class="card">
  <div class="card-header"><span class="card-title">Pagos Realizados</span></div>
  <div class="card-body table-wrap">
    <?php if(empty($historialPagos)): ?>
    <div class="empty-state" style="padding:30px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:36px;height:36px"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><p>Sin pagos en este período</p></div>
    <?php else: ?>
    <table>
      <thead><tr><th>Empleado</th><th>Monto</th><th>Método</th><th>Fecha</th></tr></thead>
      <tbody>
      <?php foreach($historialPagos as $p):
        $metodos=['transferencia'=>'Transferencia','efectivo'=>'Efectivo','cheque'=>'Cheque'];
      ?>
      <tr>
        <td><div class="fw-600"><?=htmlspecialchars($p['empleado_nombre'])?></div><div style="font-size:11px;color:var(--text-muted)"><?=htmlspecialchars($p['cargo_nombre'])?></div></td>
        <td class="fw-600 text-green"><?=formatCOP($p['monto'])?></td>
        <td><span class="badge badge-blue" style="font-size:10px"><?=$metodos[$p['metodo_pago']]??$p['metodo_pago']?></span></td>
        <td style="font-size:12px;color:var(--text-muted)"><?=date('d/m/Y',strtotime($p['fecha_pago']))?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
</div>

<!-- MODAL PAGO -->
<div class="modal-overlay" id="modalPago">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <span class="modal-title">Registrar Pago</span>
      <button class="btn btn-ghost btn-icon" onclick="document.getElementById('modalPago').classList.remove('active')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <form id="formPago" onsubmit="registrarPago(event)">
      <div class="modal-body">
        <input type="hidden" name="action" value="registrar_pago">
        <input type="hidden" name="nomina_id" id="pago_nomina_id">
        <div style="background:var(--bg-secondary);border-radius:8px;padding:14px 16px;margin-bottom:18px">
          <div style="font-size:12px;color:var(--text-muted)">Empleado</div>
          <div class="fw-600" id="pago_empleado_nombre" style="font-size:15px"></div>
          <div class="fw-600 text-green" id="pago_total" style="font-size:20px;font-family:Syne,sans-serif;margin-top:4px"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Método de pago</label>
          <select name="metodo_pago" class="form-control">
            <option value="transferencia">Transferencia bancaria</option>
            <option value="efectivo">Efectivo</option>
            <option value="cheque">Cheque</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Referencia / Comprobante</label>
          <input type="text" name="referencia" class="form-control" placeholder="Número de transacción o referencia...">
        </div>
        <div id="pagoMsg" style="display:none;margin-top:8px"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalPago').classList.remove('active')">Cancelar</button>
        <button type="submit" class="btn btn-primary">Confirmar Pago</button>
      </div>
    </form>
  </div>
</div>

<script>
function abrirPago(id, nombre, total) {
  document.getElementById('pago_nomina_id').value = id;
  document.getElementById('pago_empleado_nombre').textContent = nombre;
  document.getElementById('pago_total').textContent = '$ ' + parseInt(total).toLocaleString('es-CO') + ' COP';
  document.getElementById('pagoMsg').style.display = 'none';
  document.getElementById('modalPago').classList.add('active');
}
async function registrarPago(e) {
  e.preventDefault();
  const btn = e.submitter; btn.disabled=true; btn.textContent='Procesando...';
  const res = await fetch('', {method:'POST', body: new URLSearchParams(new FormData(document.getElementById('formPago')))});
  const data = await res.json();
  const m = document.getElementById('pagoMsg');
  m.style.display='block'; m.className=data.ok?'alert-item green':'alert-error'; m.textContent=data.msg;
  btn.disabled=false; btn.textContent='Confirmar Pago';
  if (data.ok) setTimeout(()=>location.reload(),1500);
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
