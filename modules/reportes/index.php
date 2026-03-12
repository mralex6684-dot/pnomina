<?php
require_once __DIR__ . '/../../includes/auth.php';
requiereLogin();
if (!tienePermiso('reportes')) {
    header('Location: ' . APP_URL . '/index.php?error=sin_permiso'); exit;
}
$db = getDB();
$pageTitle = 'Reportes';

$anio = (int)($_GET['anio'] ?? date('Y'));
$meses=['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

// Resumen anual mes a mes
$resumenAnual = $db->prepare("
    SELECT periodo_mes,
           COUNT(*) as total_nominas,
           SUM(total_neto) as total_pagado,
           SUM(horas_extra) as horas_extra,
           SUM(CASE WHEN estado='pagado' THEN 1 ELSE 0 END) as pagadas
    FROM nominas WHERE periodo_anio=?
    GROUP BY periodo_mes ORDER BY periodo_mes
");
$resumenAnual->execute([$anio]);
$resumenAnual = $resumenAnual->fetchAll();
$datosMeses = array_fill(1,12,['total_nominas'=>0,'total_pagado'=>0,'horas_extra'=>0,'pagadas'=>0]);
foreach($resumenAnual as $r) $datosMeses[$r['periodo_mes']] = $r;

// Top empleados por pago acumulado
$topEmpleados = $db->prepare("
    SELECT CONCAT(e.nombres,' ',e.apellidos) as nombre, c.nombre as cargo,
           SUM(n.total_neto) as total_anual, COUNT(n.id) as meses_pagados
    FROM nominas n JOIN empleados e ON n.empleado_id=e.id JOIN cargos c ON e.cargo_id=c.id
    WHERE n.periodo_anio=? AND n.estado IN ('pagado','aprobado')
    GROUP BY n.empleado_id ORDER BY total_anual DESC LIMIT 10
");
$topEmpleados->execute([$anio]);
$topEmpleados = $topEmpleados->fetchAll();

// Totales anuales
$totAnual = $db->prepare("SELECT COALESCE(SUM(total_neto),0) as t, COALESCE(SUM(deducciones_salud+deducciones_pension),0) as d, COALESCE(SUM(valor_horas_extra),0) as h FROM nominas WHERE periodo_anio=? AND estado IN ('pagado','aprobado')");
$totAnual->execute([$anio]);
$totAnual = $totAnual->fetch();

$maxPago = max(array_column($datosMeses,'total_pagado') ?: [1]);

include __DIR__ . '/../../includes/header.php';
?>
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div>
    <h1 class="page-title">Reportes</h1>
    <p class="page-subtitle">Análisis y estadísticas de nómina — <?=$anio?></p>
  </div>
  <form method="GET" style="display:flex;gap:8px">
    <select name="anio" class="form-control" style="width:100px"><?php for($a=2024;$a<=2027;$a++): ?><option value="<?=$a?>" <?=$anio==$a?'selected':''?>><?=$a?></option><?php endfor; ?></select>
    <button type="submit" class="btn btn-secondary">Ver</button>
  </form>
</div>

<!-- KPIs anuales -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card"><div><div class="stat-label">Total pagado <?=$anio?></div><div class="stat-value cop" style="color:var(--accent-green)"><?=formatCOP($totAnual['t'])?></div></div><div class="stat-icon green"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">Total deducciones</div><div class="stat-value cop" style="color:var(--accent-red)"><?=formatCOP($totAnual['d'])?></div></div><div class="stat-icon red"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">Valor horas extra</div><div class="stat-value cop" style="color:var(--accent-blue)"><?=formatCOP($totAnual['h'])?></div></div><div class="stat-icon blue"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
</div>

<!-- Gráfico de barras (CSS) -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header"><span class="card-title">Nómina mensual <?=$anio?></span></div>
  <div class="card-body" style="padding:24px">
    <div style="display:flex;align-items:flex-end;gap:8px;height:160px">
      <?php foreach($datosMeses as $m => $d):
        $pct = $maxPago > 0 ? ($d['total_pagado']/$maxPago)*100 : 0;
        $color = $d['total_pagado']>0 ? 'var(--accent-red)' : 'var(--border)';
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%">
        <div style="flex:1;width:100%;display:flex;align-items:flex-end">
          <div style="width:100%;height:<?=max(4,$pct)?>%;background:<?=$color?>;border-radius:4px 4px 0 0;transition:all 0.3s;min-height:4px" title="<?=$meses[$m]?>: <?=formatCOP($d['total_pagado'])?>"></div>
        </div>
        <div style="font-size:9px;color:var(--text-muted);text-align:center"><?=substr($meses[$m],0,3)?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <!-- Tabla debajo del gráfico -->
    <div style="margin-top:20px;overflow-x:auto">
      <table>
        <thead><tr><th>Mes</th><th>Nóminas</th><th>Pagadas</th><th>H. Extra</th><th>Total Pagado</th></tr></thead>
        <tbody>
        <?php foreach($datosMeses as $m => $d): if($d['total_pagado']<=0) continue; ?>
        <tr>
          <td class="fw-600"><?=$meses[$m]?></td>
          <td><span class="badge badge-blue"><?=$d['total_nominas']?></span></td>
          <td><span class="badge badge-green"><?=$d['pagadas']?></span></td>
          <td><?=$d['horas_extra']>0?'<span class="badge badge-purple">'.$d['horas_extra'].'</span>':'<span class="text-muted">—</span>'?></td>
          <td class="fw-600 text-green"><?=formatCOP($d['total_pagado'])?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Top empleados -->
<div class="card">
  <div class="card-header"><span class="card-title">Top Empleados por Pago Anual <?=$anio?></span></div>
  <div class="card-body table-wrap">
    <?php if(empty($topEmpleados)): ?>
    <div class="empty-state" style="padding:40px"><p>Sin datos para este año</p></div>
    <?php else:
      $maxTop = max(array_column($topEmpleados,'total_anual'));
    ?>
    <table>
      <thead><tr><th>#</th><th>Empleado</th><th>Cargo</th><th>Meses</th><th>Total Anual</th><th style="min-width:160px">Proporción</th></tr></thead>
      <tbody>
      <?php foreach($topEmpleados as $i => $t):
        $pct = $maxTop>0?($t['total_anual']/$maxTop)*100:0;
      ?>
      <tr>
        <td style="color:var(--text-muted);font-weight:700"><?=$i+1?></td>
        <td class="fw-600"><?=htmlspecialchars($t['nombre'])?></td>
        <td class="text-muted"><?=htmlspecialchars($t['cargo'])?></td>
        <td><span class="badge badge-blue"><?=$t['meses_pagados']?> mes<?=$t['meses_pagados']>1?'es':''?></span></td>
        <td class="fw-600 text-green"><?=formatCOP($t['total_anual'])?></td>
        <td>
          <div style="background:var(--bg-secondary);border-radius:4px;height:6px;overflow:hidden">
            <div style="width:<?=$pct?>%;height:100%;background:var(--accent-red);border-radius:4px"></div>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
