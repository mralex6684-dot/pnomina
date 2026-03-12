<?php
require_once __DIR__ . '/../../includes/auth.php';
requiereLogin();
if (!tienePermiso('empleados')) {
    header('Location: ' . APP_URL . '/index.php?error=sin_permiso');
    exit;
}

$db = getDB();
$pageTitle = 'Empleados';

// Búsqueda y filtros
$buscar  = trim($_GET['buscar'] ?? '');
$filtroC = (int)($_GET['cargo'] ?? 0);
$filtroE = $_GET['estado'] ?? 'activo';

$where = ['1=1'];
$params = [];
if ($buscar) {
    $where[] = "(e.nombres LIKE ? OR e.apellidos LIKE ? OR e.cedula LIKE ?)";
    $params = array_merge($params, ["%$buscar%", "%$buscar%", "%$buscar%"]);
}
if ($filtroC) {
    $where[] = "e.cargo_id = ?";
    $params[] = $filtroC;
}
if ($filtroE === 'activo')   { $where[] = "e.activo = 1"; }
if ($filtroE === 'inactivo') { $where[] = "e.activo = 0"; }

$sql = "SELECT e.*, c.nombre as cargo_nombre, c.departamento 
        FROM empleados e 
        JOIN cargos c ON e.cargo_id = c.id 
        WHERE " . implode(' AND ', $where) . "
        ORDER BY e.nombres, e.apellidos";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$empleados = $stmt->fetchAll();

$cargos = $db->query("SELECT * FROM cargos WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    header('Content-Type: application/json');

    if ($action === 'crear' || $action === 'editar') {
        $data = [
            'cedula'        => trim($_POST['cedula'] ?? ''),
            'nombres'       => trim($_POST['nombres'] ?? ''),
            'apellidos'     => trim($_POST['apellidos'] ?? ''),
            'email'         => trim($_POST['email'] ?? ''),
            'telefono'      => trim($_POST['telefono'] ?? ''),
            'cargo_id'      => (int)($_POST['cargo_id'] ?? 0),
            'fecha_ingreso' => $_POST['fecha_ingreso'] ?? '',
            'salario'       => (float)str_replace(['.', ','], ['', '.'], $_POST['salario'] ?? '0'),
            'tipo_contrato' => $_POST['tipo_contrato'] ?? 'indefinido',
            'cuenta_bancaria'=> trim($_POST['cuenta_bancaria'] ?? ''),
            'banco'         => trim($_POST['banco'] ?? ''),
        ];
        try {
            if ($action === 'crear') {
                $db->prepare("INSERT INTO empleados (cedula,nombres,apellidos,email,telefono,cargo_id,fecha_ingreso,salario,tipo_contrato,cuenta_bancaria,banco) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute(array_values($data));
                registrarHistorial('crear_empleado', 'empleados', 'Empleado creado: ' . $data['nombres'] . ' ' . $data['apellidos']);
                echo json_encode(['ok' => true, 'msg' => 'Empleado creado correctamente']);
            } else {
                $id = (int)$_POST['id'];
                $db->prepare("UPDATE empleados SET cedula=?,nombres=?,apellidos=?,email=?,telefono=?,cargo_id=?,fecha_ingreso=?,salario=?,tipo_contrato=?,cuenta_bancaria=?,banco=? WHERE id=?")
                   ->execute([...array_values($data), $id]);
                registrarHistorial('editar_empleado', 'empleados', 'Empleado editado ID: ' . $id);
                echo json_encode(['ok' => true, 'msg' => 'Empleado actualizado correctamente']);
            }
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'toggle_estado') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE empleados SET activo = NOT activo WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'get') {
        $id = (int)$_POST['id'];
        $emp = $db->prepare("SELECT * FROM empleados WHERE id = ?");
        $emp->execute([$id]);
        echo json_encode($emp->fetch());
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div>
    <h1 class="page-title">Empleados</h1>
    <p class="page-subtitle"><?= count($empleados) ?> empleado<?= count($empleados) !== 1 ? 's' : '' ?> encontrado<?= count($empleados) !== 1 ? 's' : '' ?></p>
  </div>
  <button class="btn btn-primary" onclick="abrirModalCrear()">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nuevo Empleado
  </button>
</div>

<!-- FILTROS -->
<div class="card mb-16" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin:0;flex:1;min-width:200px">
        <label class="form-label">Buscar</label>
        <input type="text" name="buscar" class="form-control" placeholder="Nombre, apellido o cédula..." value="<?= htmlspecialchars($buscar) ?>">
      </div>
      <div class="form-group" style="margin:0;min-width:160px">
        <label class="form-label">Cargo</label>
        <select name="cargo" class="form-control">
          <option value="">Todos los cargos</option>
          <?php foreach ($cargos as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $filtroC == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:140px">
        <label class="form-label">Estado</label>
        <select name="estado" class="form-control">
          <option value="todos"   <?= $filtroE === 'todos'    ? 'selected' : '' ?>>Todos</option>
          <option value="activo"  <?= $filtroE === 'activo'   ? 'selected' : '' ?>>Activos</option>
          <option value="inactivo"<?= $filtroE === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
        </select>
      </div>
      <button type="submit" class="btn btn-secondary">Filtrar</button>
      <a href="?" class="btn btn-ghost">Limpiar</a>
    </form>
  </div>
</div>

<!-- TABLE -->
<div class="card">
  <div class="card-body table-wrap">
    <?php if (empty($empleados)): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <p>No se encontraron empleados</p>
      </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Cédula</th>
          <th>Nombre</th>
          <th>Cargo</th>
          <th>Departamento</th>
          <th>Salario</th>
          <th>Contrato</th>
          <th>Estado</th>
          <th style="text-align:center">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($empleados as $e): ?>
        <tr>
          <td class="text-muted"><?= htmlspecialchars($e['cedula']) ?></td>
          <td>
            <div class="fw-600"><?= htmlspecialchars($e['nombres'] . ' ' . $e['apellidos']) ?></div>
            <?php if ($e['email']): ?>
            <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($e['email']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($e['cargo_nombre']) ?></td>
          <td class="text-muted"><?= htmlspecialchars($e['departamento']) ?></td>
          <td class="fw-600"><?= formatCOP($e['salario']) ?></td>
          <td>
            <?php $contratos = ['indefinido'=>'Indefinido','fijo'=>'Fijo','temporal'=>'Temporal','obra'=>'Por obra']; ?>
            <span class="badge badge-blue"><?= $contratos[$e['tipo_contrato']] ?? $e['tipo_contrato'] ?></span>
          </td>
          <td>
            <span class="badge <?= $e['activo'] ? 'badge-green' : 'badge-red' ?>">
              <?= $e['activo'] ? 'Activo' : 'Inactivo' ?>
            </span>
          </td>
          <td style="text-align:center">
            <div style="display:flex;gap:6px;justify-content:center">
              <button class="btn btn-ghost btn-icon btn-sm" title="Editar" onclick="editarEmpleado(<?= $e['id'] ?>)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button class="btn btn-ghost btn-icon btn-sm" title="<?= $e['activo'] ? 'Desactivar' : 'Activar' ?>" onclick="toggleEstado(<?= $e['id'] ?>, <?= $e['activo'] ?>)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
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

<!-- MODAL CREAR/EDITAR -->
<div class="modal-overlay" id="modalEmpleado">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="modalTitle">Nuevo Empleado</span>
      <button class="btn btn-ghost btn-icon" onclick="cerrarModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="formEmpleado" onsubmit="guardarEmpleado(event)">
      <div class="modal-body">
        <input type="hidden" id="emp_id" name="id">
        <input type="hidden" id="emp_action" name="action" value="crear">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Cédula *</label>
            <input type="text" name="cedula" id="emp_cedula" class="form-control" placeholder="1234567890" required>
          </div>
          <div class="form-group">
            <label class="form-label">Cargo *</label>
            <select name="cargo_id" id="emp_cargo" class="form-control" required>
              <option value="">Seleccionar cargo...</option>
              <?php foreach ($cargos as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Nombres *</label>
            <input type="text" name="nombres" id="emp_nombres" class="form-control" placeholder="Juan" required>
          </div>
          <div class="form-group">
            <label class="form-label">Apellidos *</label>
            <input type="text" name="apellidos" id="emp_apellidos" class="form-control" placeholder="Pérez" required>
          </div>
          <div class="form-group">
            <label class="form-label">Correo electrónico</label>
            <input type="email" name="email" id="emp_email" class="form-control" placeholder="juan@email.com">
          </div>
          <div class="form-group">
            <label class="form-label">Teléfono</label>
            <input type="text" name="telefono" id="emp_telefono" class="form-control" placeholder="3001234567">
          </div>
          <div class="form-group">
            <label class="form-label">Salario (COP) *</label>
            <input type="number" name="salario" id="emp_salario" class="form-control" placeholder="1160000" step="1000" min="0" required>
          </div>
          <div class="form-group">
            <label class="form-label">Tipo de contrato</label>
            <select name="tipo_contrato" id="emp_contrato" class="form-control">
              <option value="indefinido">Indefinido</option>
              <option value="fijo">Término fijo</option>
              <option value="temporal">Temporal</option>
              <option value="obra">Por obra</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Fecha de ingreso *</label>
            <input type="date" name="fecha_ingreso" id="emp_fecha" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Banco</label>
            <input type="text" name="banco" id="emp_banco" class="form-control" placeholder="Bancolombia">
          </div>
          <div class="form-group" style="grid-column: span 2">
            <label class="form-label">Número de cuenta</label>
            <input type="text" name="cuenta_bancaria" id="emp_cuenta" class="form-control" placeholder="123-456789-00">
          </div>
        </div>
        <div id="modalMsg" style="display:none;margin-top:12px"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
function abrirModalCrear() {
  document.getElementById('modalTitle').textContent = 'Nuevo Empleado';
  document.getElementById('emp_action').value = 'crear';
  document.getElementById('formEmpleado').reset();
  document.getElementById('modalMsg').style.display = 'none';
  document.getElementById('modalEmpleado').classList.add('active');
}

function cerrarModal() {
  document.getElementById('modalEmpleado').classList.remove('active');
}

async function editarEmpleado(id) {
  const res = await fetch('', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=get&id=' + id });
  const emp = await res.json();
  if (!emp) return;

  document.getElementById('modalTitle').textContent = 'Editar Empleado';
  document.getElementById('emp_action').value = 'editar';
  document.getElementById('emp_id').value = emp.id;
  document.getElementById('emp_cedula').value = emp.cedula;
  document.getElementById('emp_nombres').value = emp.nombres;
  document.getElementById('emp_apellidos').value = emp.apellidos;
  document.getElementById('emp_email').value = emp.email ?? '';
  document.getElementById('emp_telefono').value = emp.telefono ?? '';
  document.getElementById('emp_cargo').value = emp.cargo_id;
  document.getElementById('emp_salario').value = emp.salario;
  document.getElementById('emp_contrato').value = emp.tipo_contrato;
  document.getElementById('emp_fecha').value = emp.fecha_ingreso;
  document.getElementById('emp_banco').value = emp.banco ?? '';
  document.getElementById('emp_cuenta').value = emp.cuenta_bancaria ?? '';
  document.getElementById('modalMsg').style.display = 'none';
  document.getElementById('modalEmpleado').classList.add('active');
}

async function guardarEmpleado(e) {
  e.preventDefault();
  const btn = document.getElementById('btnGuardar');
  btn.disabled = true; btn.textContent = 'Guardando...';
  const formData = new FormData(document.getElementById('formEmpleado'));
  const res = await fetch('', { method: 'POST', body: new URLSearchParams(formData) });
  const data = await res.json();
  const msgEl = document.getElementById('modalMsg');
  msgEl.style.display = 'block';
  msgEl.className = data.ok ? 'alert-item green' : 'alert-error';
  msgEl.textContent = data.msg;
  btn.disabled = false; btn.textContent = 'Guardar';
  if (data.ok) setTimeout(() => location.reload(), 1200);
}

async function toggleEstado(id, activo) {
  if (!confirm(activo ? '¿Desactivar este empleado?' : '¿Activar este empleado?')) return;
  await fetch('', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=toggle_estado&id=' + id });
  location.reload();
}

document.getElementById('modalEmpleado').addEventListener('click', function(e) {
  if (e.target === this) cerrarModal();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
