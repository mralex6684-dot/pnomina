<?php
require_once __DIR__ . '/../../includes/auth.php';
requiereLogin();
$db = getDB();
$pageTitle = 'Historial';

$filtroModulo = $_GET['modulo'] ?? '';
$filtroUsuario = (int)($_GET['usuario'] ?? 0);
$buscar = trim($_GET['buscar'] ?? '');

$where = ['1=1']; $params = [];
if ($filtroModulo) { $where[] = 'h.modulo=?'; $params[] = $filtroModulo; }
if ($filtroUsuario) { $where[] = 'h.usuario_id=?'; $params[] = $filtroUsuario; }
if ($buscar) { $where[] = '(h.accion LIKE ? OR h.descripcion LIKE ?)'; $params[] = "%$buscar%"; $params[] = "%$buscar%"; }

$stmt = $db->prepare("
    SELECT h.*, u.nombre as usuario_nombre
    FROM historial h
    LEFT JOIN usuarios u ON h.usuario_id=u.id
    WHERE ".implode(' AND ',$where)."
    ORDER BY h.created_at DESC LIMIT 200
");
$stmt->execute($params);
$registros = $stmt->fetchAll();

$usuarios = $db->query("SELECT id, nombre FROM usuarios ORDER BY nombre")->fetchAll();
$modulos = $db->query("SELECT DISTINCT modulo FROM historial ORDER BY modulo")->fetchAll(PDO::FETCH_COLUMN);

$iconos = [
    'auth'=>'🔐','empleados'=>'👥','roles'=>'🏷️','nomina'=>'📄','pagos'=>'💰','reportes'=>'📊'
];
$colores = [
    'login'=>'badge-green','logout'=>'badge-red','crear_empleado'=>'badge-blue',
    'editar_empleado'=>'badge-yellow','generar_nomina'=>'badge-blue','aprobar_nomina'=>'badge-green',
    'registrar_pago'=>'badge-green','crear_cargo'=>'badge-purple','editar_cargo'=>'badge-yellow'
];

include __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <h1 class="page-title">Historial de Actividad</h1>
  <p class="page-subtitle">Registro de todas las acciones realizadas en el sistema</p>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin:0;flex:1;min-width:180px">
        <label class="form-label">Buscar</label>
        <input type="text" name="buscar" class="form-control" placeholder="Acción o descripción..." value="<?=htmlspecialchars($buscar)?>">
      </div>
      <div class="form-group" style="margin:0;min-width:140px">
        <label class="form-label">Módulo</label>
        <select name="modulo" class="form-control">
          <option value="">Todos</option>
          <?php foreach($modulos as $m): ?><option value="<?=$m?>" <?=$filtroModulo===$m?'selected':''?>><?=ucfirst($m)?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:160px">
        <label class="form-label">Usuario</label>
        <select name="usuario" class="form-control">
          <option value="">Todos</option>
          <?php foreach($usuarios as $u): ?><option value="<?=$u['id']?>" <?=$filtroUsuario==$u['id']?'selected':''?>><?=htmlspecialchars($u['nombre'])?></option><?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-secondary">Filtrar</button>
      <a href="?" class="btn btn-ghost">Limpiar</a>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Actividad reciente</span>
    <span class="badge badge-blue"><?=count($registros)?> registros</span>
  </div>
  <div class="card-body table-wrap">
    <?php if(empty($registros)): ?>
    <div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><p>No hay actividad registrada</p></div>
    <?php else: ?>
    <table>
      <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Módulo</th><th>Acción</th><th>Descripción</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach($registros as $r): ?>
      <tr>
        <td style="font-size:12px;color:var(--text-muted);white-space:nowrap"><?=date('d/m/Y H:i:s',strtotime($r['created_at']))?></td>
        <td class="fw-600" style="font-size:13px"><?=htmlspecialchars($r['usuario_nombre']??'Sistema')?></td>
        <td><span style="font-size:13px"><?=($iconos[$r['modulo']]??'📌').' '.ucfirst($r['modulo'])?></span></td>
        <td><span class="badge <?=$colores[$r['accion']]??'badge-blue'?>" style="font-size:10px"><?=htmlspecialchars($r['accion'])?></span></td>
        <td style="font-size:12px;color:var(--text-muted)"><?=htmlspecialchars($r['descripcion']??'—')?></td>
        <td style="font-size:11px;color:var(--text-muted)"><?=htmlspecialchars($r['ip']??'')?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
