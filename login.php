<?php
require_once __DIR__ . '/includes/auth.php';
iniciarSesion();

// Si ya está logueado, redirigir
if (estaAutenticado()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $error = 'Por favor ingresa tu correo y contraseña.';
    } elseif (!login($email, $password)) {
        $error = 'Correo o contraseña incorrectos.';
    } else {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar Sesión — NominaRest</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
</head>
<body>
<div class="login-page">
  <div class="login-bg"></div>
  <div class="login-card">
    <div class="login-logo">Nomina<span>Rest</span></div>
    <div class="login-subtitle">Sistema de gestión de nómina para restaurantes</div>

    <?php if ($error): ?>
      <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="form-group">
        <label class="form-label">Correo electrónico</label>
        <input type="email" name="email" class="form-control"
               placeholder="usuario@empresa.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label">Contraseña</label>
        <input type="password" name="password" class="form-control"
               placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary w-100" style="margin-top:8px;justify-content:center;padding:12px;">
        Ingresar al sistema
      </button>
    </form>

    <div class="login-footer">
      <strong>Demo:</strong> admin@pnomina.com / password
    </div>
  </div>
</div>
</body>
</html>
