<?php
require_once __DIR__ . '/../../includes/auth.php';
requiereLogin();
if (!tienePermiso('nomina')) {
    header('Location: ' . APP_URL . '/index.php?error=sin_permiso'); exit;
}

$db = getDB();
$pageTitle = 'Generar Nómina';

$mesActual  = (int)date('n');
$anioActual = (int)date('Y');
$mes  = (int)($_GET['mes']  ?? $mesActual);
$anio = (int)($_GET['anio'] ?? $anioActual);

// AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'generar') {
        $empleado_id = (int)$_POST['empleado_id'];
        $periodo_mes  = (int)$_POST['periodo_mes'];
        $periodo_anio = (int)$_POST['periodo_anio'];

        // Verificar si ya existe
        $existe = $db->prepare("SELECT id FROM nominas WHERE empleado_id=? AND periodo_mes=? AND periodo_anio=?");
        $existe->execute([$empleado_id, $periodo_mes, $periodo_anio]);
        if ($existe->fetch()) {
            echo json_encode(['ok'=>false,'msg'=>'Ya existe una nómina para este empleado en ese período']);
            exit;
        }

        $emp = $db->prepare("SELECT * FROM empleados WHERE id=?");
        $emp->execute([$empleado_id]);
        $empleado = $emp->fetch();

        $salario         = (float)$empleado['salario'];
        $horas_extra     = (int)($_POST['horas_extra'] ?? 0);
        $bonificaciones  = (float)str_replace(['.', ','], ['', '.'], $_POST['bonificaciones'] ?? '0');
        $otras_ded       = (float)str_replace(['.', ','], ['', '.'], $_POST['otras_deducciones'] ?? '0');

        $valor_hora_extra  = ($salario / 240) * 1.25;
        $valor_horas_extra = round($valor_hora_extra * $horas_extra);
        $ded_salud         = round($salario * 0.04);
        $ded_pension       = round($salario * 0.04);
        $total_devengado   = $salario + $valor_horas_extra + $bonificaciones;
        $total_deducciones = $ded_salud + $ded_pension + $otras_ded;
        $total_neto        = $total_devengado - $total_deducciones;

        try {
            $db->prepare("INSERT INTO nominas (empleado_id,periodo_mes,periodo_anio,salario_base,horas_extra,valor_horas_extra,bonificaciones,deducciones_salud,deducciones_pension,otras_deducciones,total_devengado,total_deducciones,total_neto,estado,observaciones) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'pendiente',?)")
               ->execute([$empleado_id,$periodo_mes,$periodo_anio,$salario,$horas_extra,$valor_horas_extra,$bonificaciones,$ded_salud,$ded_pension,$otras_ded,$total_devengado,$total_deducciones,$total_neto,$_POST['observaciones']??'']);
            registrarHistorial('generar_nomina','nomina','Nómina generada: empleado '.$empleado_id.' período '.$periodo_mes.'/'.$periodo_anio);
            echo json_encode(['ok'=>true,'msg'=>'Nómina generada correctamente por '.formatCOP($total_neto).' COP']);
        } catch (PDOException $e) {
            echo json_encode(['ok'=>false,'msg'=>'Error: '.$e->getMessage()]);
        }
        exit;
    }

    if ($action === 'aprobar') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE nominas SET estado='aprobado', aprobado_por=? WHERE id=?")->execute([$_SESSION['usuario_id'],$id]);
        registrarHistorial('aprobar_nomina','nomina','Nómina aprobada ID:'.$id);
        echo json_encode(['ok'=>true,'msg'=>'Nómina aprobada']);
        exit;
    }

    if ($action === 'rechazar') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE nominas SET estado='rechazado' WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true,'msg'=>'Nómina rechazada']);
        exit;
    }

    if ($action === 'eliminar') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM nominas WHERE id=? AND estado='pendiente'")->execute([$id]);
        echo json_encode(['ok'=>true]);
        exit;
    }
}

// Empleados sin nómina en el período
$empleadosSin = $db->prepare("
    SELECT e.*, c.nombre as cargo_nombre FROM empleados e
    JOIN cargos c ON e.cargo_id = c.id
    WHERE e.activo=1 AND e.id NOT IN (
        SELECT empleado_id FROM nominas WHERE periodo_mes=? AND periodo_anio=?
    ) ORDER BY e.nombres
");
$empleadosSin->execute([$mes,$anio]);
$empleadosSin = $empleadosSin->fetchAll();

// Nóminas del período
$nominasPeriodo = $db->prepare("
    SELECT n.*, CONCAT(e.nombres,' ',e.apellidos) as empleado_nombre, c.nombre as cargo_nombre
    FROM nominas n
    JOIN empleados e ON n.empleado_id=e.id
    JOIN cargos c ON e.cargo_id=c.id
    WHERE n.periodo_mes=? AND n.periodo_anio=?
    ORDER BY n.created_at DESC
");
$nominasPeriodo->execute([$mes,$anio]);
$nominasPeriodo = $nominasPeriodo->fetchAll();

$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div>
    <h1 class="page-title">Generar Nómina</h1>
    <p class="page-subtitle">Período: <?= $meses[$mes] ?> <?= $anio ?></p>
  </div>
  <div style="display:flex;gap:10px;align-items:center">
    <form method="GET" style="display:flex;gap:8px;align-items:center">
      <select name="mes" class="form-control" style="width:130px">
        <?php for($i=1;$i<=12;$i++): ?>
        <option value="<?=$i?>" <?=$mes==$i?'selected':''?>><?=$meses[$i]?></option>
        <?php endfor; ?>
      </select>
      <select name="anio" class="form-control" style="width:90px">
        <?php for($a=2024;$a<=2027;$a++): ?>
        <option value="<?=$a?>" <?=$anio==$a?'selected':''?>><?=$a?></option>
        <?php endfor; ?>
      </select>
      <button type="submit" class="btn btn-secondary">Ir</button>
    </form>
    <button class="btn btn-primary" onclick="document.getElementById('modalGenerar').classList.add('active')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nueva Nómina
    </button>
  </div>
</div>

<!-- Stats rápidas -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
  <?php
    $totales = ['pendiente'=>0,'aprobado'=>0,'pagado'=>0,'rechazado'=>0];
    foreach($nominasPeriodo as $n) $totales[$n['estado']] = ($totales[$n['estado']]??0)+1;
    $totalNeto = array_sum(array_column($nominasPeriodo,'total_neto'));
  ?>
  <div class="stat-card"><div><div class="stat-label">Generadas</div><div class="stat-value"><?=count($nominasPeriodo)?></div></div><div class="stat-icon blue"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">Pendientes</div><div class="stat-value text-yellow"><?=$totales['pendiente']?></div></div><div class="stat-icon yellow"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">Pagadas</div><div class="stat-value text-green"><?=$totales['pagado']?></div></div><div class="stat-icon green"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">Total Período</div><div class="stat-value cop"><?=formatCOP($totalNeto)?></div></div><div class="stat-icon red"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
</div>

<!-- Tabla nóminas -->
<div class="card">
  <div class="card-header"><span class="card-title">Nóminas — <?=$meses[$mes]?> <?=$anio?></span></div>
  <div class="card-body table-wrap">
    <?php if(empty($nominasPeriodo)): ?>
    <div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><p>No hay nóminas generadas para este período</p></div>
    <?php else: ?>
    <table>
      <thead><tr><th>Empleado</th><th>Cargo</th><th>Salario Base</th><th>H. Extra</th><th>Deducciones</th><th>Total Neto</th><th>Estado</th><th style="text-align:center">Acciones</th></tr></thead>
      <tbody>
      <?php foreach($nominasPeriodo as $n):
        $badges=['pagado'=>'badge-green','aprobado'=>'badge-blue','pendiente'=>'badge-yellow','rechazado'=>'badge-red'];
        $labels=['pagado'=>'Pagado','aprobado'=>'Aprobado','pendiente'=>'Pendiente','rechazado'=>'Rechazado'];
      ?>
      <tr>
        <td class="fw-600"><?=htmlspecialchars($n['empleado_nombre'])?></td>
        <td class="text-muted"><?=htmlspecialchars($n['cargo_nombre'])?></td>
        <td><?=formatCOP($n['salario_base'])?></td>
        <td><?=$n['horas_extra']>0 ? '<span class="badge badge-purple">'.$n['horas_extra'].' hrs</span>' : '<span class="text-muted">—</span>'?></td>
        <td class="text-red"><?=formatCOP($n['total_deducciones'])?></td>
        <td class="fw-600 text-green"><?=formatCOP($n['total_neto'])?></td>
        <td><span class="badge <?=$badges[$n['estado']]??'badge-blue'?>"><?=$labels[$n['estado']]??$n['estado']?></span></td>
        <td style="text-align:center">
          <div style="display:flex;gap:6px;justify-content:center">
            <?php if($n['estado']==='pendiente'): ?>
            <button class="btn btn-ghost btn-icon btn-sm" title="Aprobar" onclick="cambiarEstado(<?=$n['id']?>,'aprobar')" style="color:var(--accent-green)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            </button>
            <button class="btn btn-ghost btn-icon btn-sm" title="Rechazar" onclick="cambiarEstado(<?=$n['id']?>,'rechazar')" style="color:var(--accent-red)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <?php endif; ?>
            <button class="btn btn-ghost btn-icon btn-sm" title="Ver detalle" onclick="verDetalle(<?=htmlspecialchars(json_encode($n))?>)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL GENERAR -->
<div class="modal-overlay" id="modalGenerar">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <span class="modal-title">Generar Nueva Nómina</span>
      <button class="btn btn-ghost btn-icon" onclick="document.getElementById('modalGenerar').classList.remove('active')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <form id="formGenerar" onsubmit="generarNomina(event)">
      <div class="modal-body">
        <input type="hidden" name="action" value="generar">
        <div class="form-group">
          <label class="form-label">Empleado *</label>
          <select name="empleado_id" id="sel_empleado" class="form-control" required onchange="cargarSalario(this)">
            <option value="">Seleccionar empleado...</option>
            <?php foreach($empleadosSin as $e): ?>
            <option value="<?=$e['id']?>" data-salario="<?=$e['salario']?>"><?=htmlspecialchars($e['nombres'].' '.$e['apellidos'])?> — <?=htmlspecialchars($e['cargo_nombre'])?></option>
            <?php endforeach; ?>
          </select>
          <?php if(empty($empleadosSin)): ?>
          <small style="color:var(--accent-green);font-size:11px;margin-top:4px;display:block">✅ Todos los empleados tienen nómina en este período</small>
          <?php endif; ?>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Período mes</label>
            <select name="periodo_mes" class="form-control">
              <?php for($i=1;$i<=12;$i++): ?><option value="<?=$i?>" <?=$mes==$i?'selected':''?>><?=$meses[$i]?></option><?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Año</label>
            <select name="periodo_anio" class="form-control">
              <?php for($a=2024;$a<=2027;$a++): ?><option value="<?=$a?>" <?=$anio==$a?'selected':''?>><?=$a?></option><?php endfor; ?>
            </select>
          </div>
        </div>
        <div id="info_salario" style="background:var(--bg-secondary);border:1px solid var(--border);border-radius:8px;padding:12px 16px;margin-bottom:16px;display:none">
          <div style="font-size:11px;color:var(--text-muted)">Salario base</div>
          <div class="fw-600 text-green" id="txt_salario"></div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Horas extra</label>
            <input type="number" name="horas_extra" class="form-control" value="0" min="0" max="120">
          </div>
          <div class="form-group">
            <label class="form-label">Bonificaciones (COP)</label>
            <input type="number" name="bonificaciones" class="form-control" value="0" min="0" step="1000">
          </div>
          <div class="form-group">
            <label class="form-label">Otras deducciones (COP)</label>
            <input type="number" name="otras_deducciones" class="form-control" value="0" min="0" step="1000">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Observaciones</label>
          <input type="text" name="observaciones" class="form-control" placeholder="Opcional...">
        </div>
        <div id="generarMsg" style="display:none;margin-top:8px"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalGenerar').classList.remove('active')">Cancelar</button>
        <button type="submit" class="btn btn-primary">Generar Nómina</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL DETALLE -->
<div class="modal-overlay" id="modalDetalle">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <span class="modal-title">Detalle de Nómina</span>
      <button class="btn btn-ghost btn-icon" onclick="document.getElementById('modalDetalle').classList.remove('active')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body" id="detalleContent"></div>
  </div>
</div>

<script>
function cargarSalario(sel) {
  const opt = sel.options[sel.selectedIndex];
  const sal = opt.dataset.salario;
  const box = document.getElementById('info_salario');
  if (sal && sel.value) {
    document.getElementById('txt_salario').textContent = '$ ' + parseInt(sal).toLocaleString('es-CO') + ' COP';
    box.style.display = 'block';
  } else { box.style.display = 'none'; }
}

async function generarNomina(e) {
  e.preventDefault();
  const btn = e.submitter; btn.disabled=true; btn.textContent='Generando...';
  const res = await fetch('', {method:'POST', body: new URLSearchParams(new FormData(document.getElementById('formGenerar')))});
  const data = await res.json();
  const m = document.getElementById('generarMsg');
  m.style.display='block'; m.className = data.ok ? 'alert-item green' : 'alert-error'; m.textContent = data.msg;
  btn.disabled=false; btn.textContent='Generar Nómina';
  if (data.ok) setTimeout(()=>location.reload(), 1500);
}

async function cambiarEstado(id, action) {
  const msg = action==='aprobar' ? '¿Aprobar esta nómina?' : '¿Rechazar esta nómina?';
  if (!confirm(msg)) return;
  const res = await fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action='+action+'&id='+id});
  const data = await res.json();
  if (data.ok) location.reload();
}

function verDetalle(n) {
  const fmt = v => '$ ' + parseInt(v).toLocaleString('es-CO') + ' COP';
  const badges = {pagado:'badge-green',aprobado:'badge-blue',pendiente:'badge-yellow',rechazado:'badge-red'};
  const labels = {pagado:'Pagado',aprobado:'Aprobado',pendiente:'Pendiente',rechazado:'Rechazado'};
  document.getElementById('detalleContent').innerHTML = `
    <div style="margin-bottom:16px">
      <div style="font-size:18px;font-weight:700;font-family:Syne,sans-serif">${n.empleado_nombre}</div>
      <div style="color:var(--text-muted);font-size:13px">${n.cargo_nombre} — ${n.periodo_mes}/${n.periodo_anio}</div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light)"><span class="text-muted">Salario base</span><span class="fw-600">${fmt(n.salario_base)}</span></div>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light)"><span class="text-muted">Horas extra (${n.horas_extra} hrs)</span><span class="fw-600 text-blue">+ ${fmt(n.valor_horas_extra)}</span></div>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light)"><span class="text-muted">Bonificaciones</span><span class="fw-600 text-blue">+ ${fmt(n.bonificaciones)}</span></div>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light)"><span class="text-muted">Total devengado</span><span class="fw-600">${fmt(n.total_devengado)}</span></div>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light)"><span class="text-muted">Salud (4%)</span><span style="color:var(--accent-red)">− ${fmt(n.deducciones_salud)}</span></div>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light)"><span class="text-muted">Pensión (4%)</span><span style="color:var(--accent-red)">− ${fmt(n.deducciones_pension)}</span></div>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light)"><span class="text-muted">Otras deducciones</span><span style="color:var(--accent-red)">− ${fmt(n.otras_deducciones)}</span></div>
      <div style="display:flex;justify-content:space-between;padding:12px 0;background:rgba(63,185,80,0.08);border-radius:8px;padding:12px 16px;margin-top:4px">
        <span style="font-weight:700;font-size:15px">TOTAL NETO</span>
        <span style="font-weight:700;font-size:18px;color:var(--accent-green)">${fmt(n.total_neto)}</span>
      </div>
    </div>
    <div style="margin-top:12px;display:flex;align-items:center;justify-content:space-between">
      <span class="badge ${badges[n.estado]||'badge-blue'}">${labels[n.estado]||n.estado}</span>
      ${n.observaciones ? '<span style="font-size:12px;color:var(--text-muted)">'+n.observaciones+'</span>' : ''}
    </div>
  `;
  document.getElementById('modalDetalle').classList.add('active');
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
