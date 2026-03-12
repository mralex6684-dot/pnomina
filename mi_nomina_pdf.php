<?php
require_once __DIR__ . '/includes/auth.php';
iniciarSesion();
if (!estaAutenticado()) { header('Location: ' . APP_URL . '/login.php'); exit; }

$db = getDB();
$nominaId   = (int)($_GET['id'] ?? 0);
$empleadoId = $_SESSION['empleado_id'] ?? null;

$where = esUsuarioNormal() ? "n.id=? AND n.empleado_id=?" : "n.id=?";
$stmt  = $db->prepare("
    SELECT n.*, CONCAT(e.nombres,' ',e.apellidos) as empleado_nombre,
           e.cedula, e.email as empleado_email, e.cuenta_bancaria, e.banco,
           c.nombre as cargo_nombre, c.departamento
    FROM nominas n
    JOIN empleados e ON n.empleado_id=e.id
    JOIN cargos c ON e.cargo_id=c.id
    WHERE $where
");
esUsuarioNormal() ? $stmt->execute([$nominaId,$empleadoId]) : $stmt->execute([$nominaId]);
$nomina = $stmt->fetch();

if (!$nomina) { http_response_code(403); die('Nómina no encontrada o sin permiso.'); }

$config = [];
try { $cfg=$db->query("SELECT * FROM restaurante_config LIMIT 1")->fetch(); if($cfg) $config=$cfg; } catch(Exception $e){}

$restaurante = $config['nombre']    ?? 'NominaRest';
$nit         = $config['nit']       ?? '';
$direccion   = $config['direccion'] ?? '';
$ciudad      = $config['ciudad']    ?? '';
$telefono    = $config['telefono']  ?? '';

$meses  = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$periodo = $meses[$nomina['periodo_mes']] . ' ' . $nomina['periodo_anio'];
$colorEstado = ['pagado'=>'#22c55e','aprobado'=>'#3b82f6','pendiente'=>'#f59e0b','rechazado'=>'#ef4444'];
$colorBadge  = $colorEstado[$nomina['estado']] ?? '#3b82f6';
$estadoLabel = strtoupper($nomina['estado']);

$partes = explode(' ', $nomina['empleado_nombre']);
$avatarLetras = strtoupper(substr($partes[0],0,1).(isset($partes[1])?substr($partes[1],0,1):''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Nómina <?= htmlspecialchars($periodo) ?> — <?= htmlspecialchars($nomina['empleado_nombre']) ?></title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap');
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'DM Sans',sans-serif;background:#f1f5f9;color:#1e293b;padding:24px;min-height:100vh}
    .wrap{max-width:780px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.1)}
    .hdr{background:#0f172a;color:#fff;padding:28px 36px;display:flex;justify-content:space-between;align-items:flex-start}
    .hdr-logo{font-size:22px;font-weight:800;letter-spacing:-.5px}
    .hdr-logo span{color:#ef4444}
    .hdr-sub{font-size:11px;color:#94a3b8;margin-top:3px}
    .hdr-info{font-size:12px;color:#cbd5e1;margin-top:6px}
    .hdr-r{text-align:right}
    .periodo-v{font-size:18px;font-weight:700;margin-top:2px}
    .ebadge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;margin-top:8px;background:<?= $colorBadge ?>;color:#fff}
    .emp-sec{padding:24px 36px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;gap:20px;align-items:center}
    .avatar{width:52px;height:52px;background:linear-gradient(135deg,#ef4444,#f97316);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;color:#fff;flex-shrink:0}
    .emp-n{font-size:18px;font-weight:700}
    .emp-c{font-size:13px;color:#64748b;margin-top:2px}
    .pill{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#e2e8f0;color:#475569;display:inline-block;margin-top:6px;margin-right:6px}
    .body{padding:28px 36px}
    h3{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;font-weight:700;margin-bottom:14px}
    .row2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px}
    .col-c{background:#f8fafc;border-radius:10px;padding:18px 20px;border:1px solid #e2e8f0}
    table.dt{width:100%;border-collapse:collapse;margin-bottom:0}
    table.dt td{padding:9px 0;font-size:13px;border-bottom:1px solid #f1f5f9}
    table.dt td:first-child{color:#64748b}
    table.dt td:last-child{text-align:right;font-weight:600}
    table.dt tr:last-child td{border-bottom:none}
    .tg{color:#22c55e}.tr{color:#ef4444}.tb{color:#3b82f6}
    .total-box{background:#0f172a;color:#fff;border-radius:10px;padding:22px 28px;display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
    .total-lbl{font-size:13px;color:#94a3b8}
    .total-v{font-size:30px;font-weight:800;color:#22c55e}
    .banco-row{display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap}
    .banco-i{flex:1;min-width:160px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px}
    .banco-l{font-size:11px;color:#16a34a;font-weight:600;margin-bottom:3px}
    .banco-v{font-size:13px;font-weight:700;color:#166534}
    .obs{background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;font-size:13px;color:#854d0e;margin-bottom:24px}
    .ftr{padding:16px 36px;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;font-size:11px;color:#94a3b8}
    .actions{max-width:780px;margin:20px auto;display:flex;gap:12px;justify-content:flex-end}
    .btn{padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
    .btn-p{background:#ef4444;color:#fff}
    .btn-s{background:#e2e8f0;color:#1e293b}
    @media print{body{background:#fff;padding:0}.actions{display:none}.wrap{box-shadow:none;border-radius:0}}
  </style>
</head>
<body>

<div class="actions">
  <a href="<?= esUsuarioNormal() ? APP_URL.'/mi_dashboard.php' : 'javascript:history.back()' ?>" class="btn btn-s">← Volver</a>
  <button class="btn btn-p" onclick="window.print()">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
    Imprimir / Guardar PDF
  </button>
</div>

<div class="wrap">
  <!-- HEADER -->
  <div class="hdr">
    <div>
      <div class="hdr-logo">Nomina<span>Rest</span></div>
      <div class="hdr-sub">Comprobante de Nómina</div>
      <div class="hdr-info"><?= htmlspecialchars($restaurante) ?><?= $nit?' · NIT: '.htmlspecialchars($nit):'' ?></div>
      <?php if($direccion||$ciudad): ?><div style="font-size:11px;color:#94a3b8;margin-top:2px"><?= htmlspecialchars(trim($direccion.', '.$ciudad,', ')) ?><?= $telefono?' · Tel: '.$telefono:'' ?></div><?php endif; ?>
    </div>
    <div class="hdr-r">
      <div style="font-size:11px;color:#94a3b8">Período</div>
      <div class="periodo-v"><?= htmlspecialchars($periodo) ?></div>
      <div><span class="ebadge"><?= $estadoLabel ?></span></div>
      <?php if($nomina['fecha_pago']): ?><div style="font-size:11px;color:#94a3b8;margin-top:6px">Pagado el <?= date('d/m/Y',strtotime($nomina['fecha_pago'])) ?></div><?php endif; ?>
    </div>
  </div>

  <!-- EMPLEADO -->
  <div class="emp-sec">
    <div class="avatar"><?= $avatarLetras ?></div>
    <div>
      <div class="emp-n"><?= htmlspecialchars($nomina['empleado_nombre']) ?></div>
      <div class="emp-c"><?= htmlspecialchars($nomina['cargo_nombre']) ?> — <?= htmlspecialchars($nomina['departamento']) ?></div>
      <span class="pill">C.C. <?= htmlspecialchars($nomina['cedula']) ?></span>
      <?php if($nomina['empleado_email']): ?><span class="pill"><?= htmlspecialchars($nomina['empleado_email']) ?></span><?php endif; ?>
    </div>
  </div>

  <!-- CUERPO -->
  <div class="body">
    <div class="row2">
      <div class="col-c">
        <h3>Devengado</h3>
        <table class="dt">
          <tr><td>Salario base</td><td><?= formatCOP($nomina['salario_base']) ?></td></tr>
          <?php if($nomina['horas_extra']>0): ?><tr><td>Horas extra (<?= $nomina['horas_extra'] ?> hrs)</td><td class="tb"><?= formatCOP($nomina['valor_horas_extra']) ?></td></tr><?php endif; ?>
          <?php if($nomina['bonificaciones']>0): ?><tr><td>Bonificaciones</td><td class="tb"><?= formatCOP($nomina['bonificaciones']) ?></td></tr><?php endif; ?>
          <tr style="border-top:2px solid #e2e8f0"><td><strong>Total devengado</strong></td><td class="tg"><strong><?= formatCOP($nomina['total_devengado']) ?></strong></td></tr>
        </table>
      </div>
      <div class="col-c">
        <h3>Deducciones</h3>
        <table class="dt">
          <tr><td>Salud (4%)</td><td class="tr">− <?= formatCOP($nomina['deducciones_salud']) ?></td></tr>
          <tr><td>Pensión (4%)</td><td class="tr">− <?= formatCOP($nomina['deducciones_pension']) ?></td></tr>
          <?php if($nomina['otras_deducciones']>0): ?><tr><td>Otras deducciones</td><td class="tr">− <?= formatCOP($nomina['otras_deducciones']) ?></td></tr><?php endif; ?>
          <tr style="border-top:2px solid #e2e8f0"><td><strong>Total deducciones</strong></td><td class="tr"><strong>− <?= formatCOP($nomina['total_deducciones']) ?></strong></td></tr>
        </table>
      </div>
    </div>

    <div class="total-box">
      <div>
        <div class="total-lbl">Total a pagar (neto)</div>
        <div style="font-size:12px;color:#64748b;margin-top:2px"><?= htmlspecialchars($periodo) ?> · <?= htmlspecialchars($nomina['cargo_nombre']) ?></div>
      </div>
      <div class="total-v"><?= formatCOP($nomina['total_neto']) ?></div>
    </div>

    <?php if($nomina['cuenta_bancaria']||$nomina['banco']): ?>
    <h3>Información de Pago</h3>
    <div class="banco-row">
      <?php if($nomina['banco']): ?><div class="banco-i"><div class="banco-l">Banco</div><div class="banco-v"><?= htmlspecialchars($nomina['banco']) ?></div></div><?php endif; ?>
      <?php if($nomina['cuenta_bancaria']): ?><div class="banco-i"><div class="banco-l">Cuenta bancaria</div><div class="banco-v"><?= htmlspecialchars($nomina['cuenta_bancaria']) ?></div></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if(!empty($nomina['observaciones'])): ?>
    <h3>Observaciones</h3>
    <div class="obs"><?= nl2br(htmlspecialchars($nomina['observaciones'])) ?></div>
    <?php endif; ?>
  </div>

  <div class="ftr">
    <div>Generado el <?= date('d/m/Y H:i') ?> · Sistema NominaRest v<?= APP_VERSION ?></div>
    <div>Nómina ID #<?= $nomina['id'] ?> · <?= htmlspecialchars($periodo) ?></div>
  </div>
</div>
</body>
</html>
