<?php
require_once __DIR__ . '/../../includes/auth.php';
requiereLogin();
if (!tienePermiso('roles')) {
    header('Location: ' . APP_URL . '/index.php?error=sin_permiso');
    exit;
}

$db = getDB();
$pageTitle = 'Roles y Cargos';

// AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    header('Content-Type: application/json');

    if ($action === 'crear_cargo' || $action === 'editar_cargo') {
        $data = [
            'nombre'       => trim($_POST['nombre'] ?? ''),
            'descripcion'  => trim($_POST['descripcion'] ?? ''),
            'salario_base' => (float)str_replace(['.', ','], ['', '.'], $_POST['salario_base'] ?? '0'),
            'departamento' => trim($_POST['departamento'] ?? ''),
        ];
        try {
            if ($action === 'crear_cargo') {
                $db->prepare("INSERT INTO cargos (nombre,descripcion,salario_base,departamento) VALUES (?,?,?,?)")
                   ->execute(array_values($data));
                registrarHistorial('crear_cargo', 'roles', 'Cargo creado: ' . $data['nombre']);
                echo json_encode(['ok' => true, 'msg' => 'Cargo creado correctamente']);
            } else {
                $id = (int)$_POST['id'];
                $db->prepare("UPDATE cargos SET nombre=?,descripcion=?,salario_base=?,departamento=? WHERE id=?")
                   ->execute([...array_values($data), $id]);
                echo json_encode(['ok' => true, 'msg' => 'Cargo actualizado correctamente']);
            }
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_cargo') {
        $stmt = $db->prepare("SELECT * FROM cargos WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        echo json_encode($stmt->fetch());
        exit;
    }

    if ($action === 'toggle_cargo') {
        $db->prepare("UPDATE cargos SET activo = NOT activo WHERE id = ?")->execute([(int)$_POST['id']]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'crear_rol' || $action === 'editar_rol') {
        $permisos = [
            'dashboard' => isset($_POST['p_dashboard']),
            'empleados' => isset($_POST['p_empleados']),
            'roles'     => isset($_POST['p_roles']),
            'nomina'    => isset($_POST['p_nomina']),
            'pagos'     => isset($_POST['p_pagos']),
            'historial' => isset($_POST['p_historial']),
            'reportes'  => isset($_POST['p_reportes']),
        ];
        try {
            if ($action === 'crear_rol') {
                $db->prepare("INSERT INTO roles_usuario (nombre,descripcion,permisos) VALUES (?,?,?)")
                   ->execute([trim($_POST['nombre']), trim($_POST['descripcion']), json_encode($permisos)]);
                echo json_encode(['ok' => true, 'msg' => 'Rol creado correctamente']);
            } else {
                $id = (int)$_POST['id'];
                $db->prepare("UPDATE roles_usuario SET nombre=?,descripcion=?,permisos=? WHERE id=?")
                   ->execute([trim($_POST['nombre']), trim($_POST['descripcion']), json_encode($permisos), $id]);
                echo json_encode(['ok' => true, 'msg' => 'Rol actualizado correctamente']);
            }
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_rol') {
        $stmt = $db->prepare("SELECT * FROM roles_usuario WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $rol = $stmt->fetch();
        if ($rol) $rol['permisos'] = json_decode($rol['permisos'], true);
        echo json_encode($rol);
        exit;
    }
}

$cargos = $db->query("SELECT c.*, (SELECT COUNT(*) FROM empleados e WHERE e.cargo_id = c.id AND e.activo = 1) as total_empleados FROM cargos c ORDER BY c.nombre")->fetchAll();
$roles  = $db->query("SELECT r.*, (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id AND u.activo = 1) as total_usuarios FROM roles_usuario r ORDER BY r.id")->fetchAll();
$permisosLabels = ['dashboard'=>'Dashboard','empleados'=>'Empleados','roles'=>'Roles y Cargos','nomina'=>'Nómina','pagos'=>'Pagos','historial'=>'Historial','reportes'=>'Reportes'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Roles y Cargos</h1>
  <p class="page-subtitle">Administra los roles de usuario y los cargos laborales</p>
</div>

<!-- TABS -->
<div style="display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:0">
  <button class="btn btn-ghost" id="tabCargosBtn" onclick="switchTab('cargos')" style="border-bottom:2px solid var(--accent-red);border-radius:0;padding-bottom:10px;color:var(--text-primary)">
    Cargos Laborales
  </button>
  <button class="btn btn-ghost" id="tabRolesBtn" onclick="switchTab('roles')" style="border-bottom:2px solid transparent;border-radius:0;padding-bottom:10px">
    Roles de Usuario
  </button>
</div>

<!-- CARGOS -->
<div id="tabCargos">
  <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
    <button class="btn btn-primary" onclick="abrirModalCargo()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo Cargo
    </button>
  </div>
  <div class="card">
    <div class="card-body table-wrap">
      <table>
        <thead>
          <tr>
            <th>Cargo</th>
            <th>Departamento</th>
            <th>Salario Base</th>
            <th>Empleados</th>
            <th>Estado</th>
            <th style="text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cargos as $c): ?>
          <tr>
            <td>
              <div class="fw-600"><?= htmlspecialchars($c['nombre']) ?></div>
              <?php if ($c['descripcion']): ?>
              <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars(substr($c['descripcion'],0,60)) ?></div>
              <?php endif; ?>
            </td>
            <td class="text-muted"><?= htmlspecialchars($c['departamento']) ?></td>
            <td class="fw-600"><?= formatCOP($c['salario_base']) ?></td>
            <td><span class="badge badge-blue"><?= $c['total_empleados'] ?> empleado<?= $c['total_empleados'] != 1 ? 's' : '' ?></span></td>
            <td><span class="badge <?= $c['activo'] ? 'badge-green' : 'badge-red' ?>"><?= $c['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
            <td style="text-align:center">
              <div style="display:flex;gap:6px;justify-content:center">
                <button class="btn btn-ghost btn-icon btn-sm" onclick="editarCargo(<?= $c['id'] ?>)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="btn btn-ghost btn-icon btn-sm" onclick="toggleCargo(<?= $c['id'] ?>, <?= $c['activo'] ?>)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ROLES -->
<div id="tabRoles" style="display:none">
  <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
    <button class="btn btn-primary" onclick="abrirModalRol()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo Rol
    </button>
  </div>
  <div class="card">
    <div class="card-body table-wrap">
      <table>
        <thead>
          <tr>
            <th>Rol</th>
            <th>Descripción</th>
            <th>Permisos</th>
            <th>Usuarios</th>
            <th style="text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($roles as $r):
            $perms = json_decode($r['permisos'], true) ?? [];
            $permActivos = array_keys(array_filter($perms));
          ?>
          <tr>
            <td class="fw-600"><?= htmlspecialchars($r['nombre']) ?></td>
            <td class="text-muted" style="font-size:12px"><?= htmlspecialchars($r['descripcion']) ?></td>
            <td>
              <div style="display:flex;flex-wrap:wrap;gap:4px">
                <?php foreach ($permActivos as $p): ?>
                <span class="badge badge-purple" style="font-size:10px"><?= $permisosLabels[$p] ?? $p ?></span>
                <?php endforeach; ?>
              </div>
            </td>
            <td><span class="badge badge-blue"><?= $r['total_usuarios'] ?></span></td>
            <td style="text-align:center">
              <button class="btn btn-ghost btn-icon btn-sm" onclick="editarRol(<?= $r['id'] ?>)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODAL CARGO -->
<div class="modal-overlay" id="modalCargo">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="modalCargoTitle">Nuevo Cargo</span>
      <button class="btn btn-ghost btn-icon" onclick="document.getElementById('modalCargo').classList.remove('active')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="formCargo" onsubmit="guardarCargo(event)">
      <div class="modal-body">
        <input type="hidden" id="cargo_id" name="id">
        <input type="hidden" id="cargo_action" name="action" value="crear_cargo">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Nombre del cargo *</label>
            <input type="text" name="nombre" id="cargo_nombre" class="form-control" placeholder="Ej: Cocinero" required>
          </div>
          <div class="form-group">
            <label class="form-label">Departamento</label>
            <input type="text" name="departamento" id="cargo_depto" class="form-control" placeholder="Ej: Cocina">
          </div>
          <div class="form-group" style="grid-column:span 2">
            <label class="form-label">Descripción</label>
            <input type="text" name="descripcion" id="cargo_desc" class="form-control" placeholder="Descripción breve del cargo">
          </div>
          <div class="form-group" style="grid-column:span 2">
            <label class="form-label">Salario base (COP)</label>
            <input type="number" name="salario_base" id="cargo_salario" class="form-control" placeholder="1160000" step="1000" min="0">
          </div>
        </div>
        <div id="cargoMsg" style="display:none;margin-top:12px"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalCargo').classList.remove('active')">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL ROL -->
<div class="modal-overlay" id="modalRol">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="modalRolTitle">Nuevo Rol</span>
      <button class="btn btn-ghost btn-icon" onclick="document.getElementById('modalRol').classList.remove('active')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="formRol" onsubmit="guardarRol(event)">
      <div class="modal-body">
        <input type="hidden" id="rol_id" name="id">
        <input type="hidden" id="rol_action" name="action" value="crear_rol">
        <div class="form-group">
          <label class="form-label">Nombre del rol *</label>
          <input type="text" name="nombre" id="rol_nombre" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Descripción</label>
          <input type="text" name="descripcion" id="rol_desc" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label" style="margin-bottom:12px">Permisos del rol</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <?php foreach ($permisosLabels as $key => $label): ?>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
              <input type="checkbox" name="p_<?= $key ?>" id="p_<?= $key ?>" style="accent-color:var(--accent-red);width:15px;height:15px">
              <?= $label ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div id="rolMsg" style="display:none;margin-top:12px"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalRol').classList.remove('active')">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
function switchTab(tab) {
  document.getElementById('tabCargos').style.display = tab === 'cargos' ? '' : 'none';
  document.getElementById('tabRoles').style.display  = tab === 'roles'  ? '' : 'none';
  document.getElementById('tabCargosBtn').style.borderBottomColor = tab === 'cargos' ? 'var(--accent-red)' : 'transparent';
  document.getElementById('tabRolesBtn').style.borderBottomColor  = tab === 'roles'  ? 'var(--accent-red)' : 'transparent';
  document.getElementById('tabCargosBtn').style.color = tab === 'cargos' ? 'var(--text-primary)' : '';
  document.getElementById('tabRolesBtn').style.color  = tab === 'roles'  ? 'var(--text-primary)' : '';
}

function abrirModalCargo() {
  document.getElementById('modalCargoTitle').textContent = 'Nuevo Cargo';
  document.getElementById('cargo_action').value = 'crear_cargo';
  document.getElementById('formCargo').reset();
  document.getElementById('cargoMsg').style.display = 'none';
  document.getElementById('modalCargo').classList.add('active');
}

async function editarCargo(id) {
  const res = await fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=get_cargo&id='+id });
  const c = await res.json();
  document.getElementById('modalCargoTitle').textContent = 'Editar Cargo';
  document.getElementById('cargo_action').value = 'editar_cargo';
  document.getElementById('cargo_id').value = c.id;
  document.getElementById('cargo_nombre').value = c.nombre;
  document.getElementById('cargo_depto').value = c.departamento ?? '';
  document.getElementById('cargo_desc').value = c.descripcion ?? '';
  document.getElementById('cargo_salario').value = c.salario_base;
  document.getElementById('cargoMsg').style.display = 'none';
  document.getElementById('modalCargo').classList.add('active');
}

async function guardarCargo(e) {
  e.preventDefault();
  const res = await fetch('', { method:'POST', body: new URLSearchParams(new FormData(document.getElementById('formCargo'))) });
  const data = await res.json();
  const m = document.getElementById('cargoMsg');
  m.style.display='block'; m.className = data.ok ? 'alert-item green' : 'alert-error'; m.textContent = data.msg;
  if (data.ok) setTimeout(() => location.reload(), 1200);
}

async function toggleCargo(id, activo) {
  if (!confirm(activo ? '¿Desactivar este cargo?' : '¿Activar este cargo?')) return;
  await fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=toggle_cargo&id='+id });
  location.reload();
}

function abrirModalRol() {
  document.getElementById('modalRolTitle').textContent = 'Nuevo Rol';
  document.getElementById('rol_action').value = 'crear_rol';
  document.getElementById('formRol').reset();
  document.getElementById('rolMsg').style.display = 'none';
  document.getElementById('modalRol').classList.add('active');
}

async function editarRol(id) {
  const res = await fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=get_rol&id='+id });
  const r = await res.json();
  document.getElementById('modalRolTitle').textContent = 'Editar Rol';
  document.getElementById('rol_action').value = 'editar_rol';
  document.getElementById('rol_id').value = r.id;
  document.getElementById('rol_nombre').value = r.nombre;
  document.getElementById('rol_desc').value = r.descripcion ?? '';
  const permisos = r.permisos ?? {};
  ['dashboard','empleados','roles','nomina','pagos','historial','reportes'].forEach(p => {
    const el = document.getElementById('p_' + p);
    if (el) el.checked = !!permisos[p];
  });
  document.getElementById('rolMsg').style.display = 'none';
  document.getElementById('modalRol').classList.add('active');
}

async function guardarRol(e) {
  e.preventDefault();
  const res = await fetch('', { method:'POST', body: new URLSearchParams(new FormData(document.getElementById('formRol'))) });
  const data = await res.json();
  const m = document.getElementById('rolMsg');
  m.style.display='block'; m.className = data.ok ? 'alert-item green' : 'alert-error'; m.textContent = data.msg;
  if (data.ok) setTimeout(() => location.reload(), 1200);
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
