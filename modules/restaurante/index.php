<?php
require_once __DIR__ . '/../../includes/auth.php';
requiereLogin();
$db = getDB();
$pageTitle = 'Restaurante';

// Crear tabla si no existe
function crearTablaConfig($db) {
    if (!$db->query("SHOW TABLES LIKE 'restaurante_config'")->fetch()) {
        $db->exec("CREATE TABLE restaurante_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) DEFAULT 'Mi Restaurante',
            nit VARCHAR(30),
            direccion VARCHAR(200),
            telefono VARCHAR(30),
            email VARCHAR(100),
            ciudad VARCHAR(100),
            representante VARCHAR(100),
            periodicidad_nomina ENUM('mensual','quincenal','semanal') DEFAULT 'mensual',
            salario_minimo DECIMAL(15,2) DEFAULT 1300000,
            auxilio_transporte DECIMAL(15,2) DEFAULT 162000,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $db->exec("INSERT INTO restaurante_config (nombre) VALUES ('Mi Restaurante')");
    }
}

crearTablaConfig($db);

// AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'guardar_config') {
        $nombre              = trim($_POST['nombre'] ?? '');
        $nit                 = trim($_POST['nit'] ?? '');
        $direccion           = trim($_POST['direccion'] ?? '');
        $telefono            = trim($_POST['telefono'] ?? '');
        $email               = trim($_POST['email'] ?? '');
        $ciudad              = trim($_POST['ciudad'] ?? '');
        $representante       = trim($_POST['representante'] ?? '');
        $periodicidad        = $_POST['periodicidad_nomina'] ?? 'mensual';
        $salario_minimo      = (float)str_replace(['.', ','], ['', '.'], $_POST['salario_minimo'] ?? '1300000');
        $auxilio_transporte  = (float)str_replace(['.', ','], ['', '.'], $_POST['auxilio_transporte'] ?? '162000');

        try {
            $db->prepare("UPDATE restaurante_config SET nombre=?,nit=?,direccion=?,telefono=?,email=?,ciudad=?,representante=?,periodicidad_nomina=?,salario_minimo=?,auxilio_transporte=? LIMIT 1")
               ->execute([$nombre, $nit, $direccion, $telefono, $email, $ciudad, $representante, $periodicidad, $salario_minimo, $auxilio_transporte]);
            registrarHistorial('config_restaurante', 'restaurante', 'Configuración actualizada');
            echo json_encode(['ok' => true, 'msg' => 'Configuración guardada correctamente']);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
}

$config   = $db->query("SELECT * FROM restaurante_config LIMIT 1")->fetch();
$totalEmp = $db->query("SELECT COUNT(*) FROM empleados WHERE activo=1")->fetchColumn();
$totalCargos  = $db->query("SELECT COUNT(*) FROM cargos WHERE activo=1")->fetchColumn();
$masaCostoMes = $db->query("SELECT COALESCE(SUM(salario),0) FROM empleados WHERE activo=1")->fetchColumn();
$porDepto     = $db->query("SELECT c.departamento, COUNT(e.id) as total FROM empleados e JOIN cargos c ON e.cargo_id=c.id WHERE e.activo=1 GROUP BY c.departamento ORDER BY total DESC")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Restaurante</h1>
  <p class="page-subtitle">Configuración general del establecimiento</p>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px">

<!-- Formulario configuración -->
<div class="card">
  <div class="card-header"><span class="card-title">Datos del Restaurante</span></div>
  <form id="formConfig" onsubmit="guardarConfig(event)">
    <div style="padding:24px">
      <input type="hidden" name="action" value="guardar_config">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Nombre del restaurante *</label>
          <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($config['nombre'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">NIT</label>
          <input type="text" name="nit" class="form-control" placeholder="900.123.456-7" value="<?= htmlspecialchars($config['nit'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Representante Legal</label>
          <input type="text" name="representante" class="form-control" value="<?= htmlspecialchars($config['representante'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Ciudad</label>
          <input type="text" name="ciudad" class="form-control" placeholder="Bogotá, Colombia" value="<?= htmlspecialchars($config['ciudad'] ?? '') ?>">
        </div>
        <div class="form-group" style="grid-column:span 2">
          <label class="form-label">Dirección</label>
          <input type="text" name="direccion" class="form-control" placeholder="Calle 123 # 45-67" value="<?= htmlspecialchars($config['direccion'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Teléfono</label>
          <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($config['telefono'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($config['email'] ?? '') ?>">
        </div>
      </div>

      <div style="border-top:1px solid var(--border);margin:20px 0;padding-top:20px">
        <div style="font-size:13px;font-weight:700;margin-bottom:16px;color:var(--text-secondary)">PARÁMETROS DE NÓMINA</div>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Periodicidad de pago</label>
            <select name="periodicidad_nomina" class="form-control">
              <option value="mensual"   <?= ($config['periodicidad_nomina'] ?? '') === 'mensual'   ? 'selected' : '' ?>>Mensual</option>
              <option value="quincenal" <?= ($config['periodicidad_nomina'] ?? '') === 'quincenal' ? 'selected' : '' ?>>Quincenal</option>
              <option value="semanal"   <?= ($config['periodicidad_nomina'] ?? '') === 'semanal'   ? 'selected' : '' ?>>Semanal</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Salario mínimo vigente (COP)</label>
            <input type="number" name="salario_minimo" class="form-control" value="<?= $config['salario_minimo'] ?? 1300000 ?>" step="1000">
          </div>
          <div class="form-group">
            <label class="form-label">Auxilio de transporte (COP)</label>
            <input type="number" name="auxilio_transporte" class="form-control" value="<?= $config['auxilio_transporte'] ?? 162000 ?>" step="1000">
          </div>
        </div>
      </div>
      <div id="configMsg" style="display:none;margin-bottom:12px"></div>
    </div>
    <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end">
      <button type="submit" class="btn btn-primary">Guardar configuración</button>
    </div>
  </form>
</div>

<!-- Panel derecho -->
<div style="display:flex;flex-direction:column;gap:16px">

  <div class="card">
    <div class="card-header"><span class="card-title">Resumen de planta</span></div>
    <div class="card-body" style="padding:16px 20px;display:flex;flex-direction:column;gap:12px">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span class="text-muted">Empleados activos</span>
        <span class="fw-600 text-blue" style="font-size:18px"><?= $totalEmp ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span class="text-muted">Cargos activos</span>
        <span class="fw-600" style="font-size:18px"><?= $totalCargos ?></span>
      </div>
      <div style="border-top:1px solid var(--border);padding-top:12px">
        <div class="text-muted" style="font-size:12px;margin-bottom:4px">Masa salarial mensual estimada</div>
        <div class="fw-600 text-green" style="font-size:20px;font-family:Syne,sans-serif"><?= formatCOP($masaCostoMes) ?></div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">Por departamento</span></div>
    <div class="card-body" style="padding:0">
      <?php foreach ($porDepto as $d): ?>
      <div style="padding:12px 20px;border-bottom:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:13px"><?= htmlspecialchars($d['departamento']) ?></span>
        <span class="badge badge-blue"><?= $d['total'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title" style="font-size:13px">Parámetros legales 2026</span></div>
    <div class="card-body" style="padding:16px 20px;font-size:12px;color:var(--text-muted);display:flex;flex-direction:column;gap:8px">
      <div style="display:flex;justify-content:space-between"><span>Salud empleado</span><span class="fw-600">4%</span></div>
      <div style="display:flex;justify-content:space-between"><span>Pensión empleado</span><span class="fw-600">4%</span></div>
      <div style="display:flex;justify-content:space-between"><span>H. extra diurna</span><span class="fw-600">+25%</span></div>
      <div style="display:flex;justify-content:space-between"><span>H. extra nocturna</span><span class="fw-600">+75%</span></div>
      <div style="display:flex;justify-content:space-between"><span>H. festiva</span><span class="fw-600">+75%</span></div>
    </div>
  </div>

</div>
</div>

<script>
async function guardarConfig(e) {
  e.preventDefault();
  const btn = e.submitter;
  btn.disabled = true;
  btn.textContent = 'Guardando...';
  const res = await fetch('', {
    method: 'POST',
    body: new URLSearchParams(new FormData(document.getElementById('formConfig')))
  });
  const data = await res.json();
  const m = document.getElementById('configMsg');
  m.style.display = 'block';
  m.className = data.ok ? 'alert-item green' : 'alert-error';
  m.textContent = data.msg;
  btn.disabled = false;
  btn.textContent = 'Guardar configuración';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
