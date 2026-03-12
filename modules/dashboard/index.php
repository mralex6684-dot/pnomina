<?php
require_once __DIR__ . '/../../includes/auth.php';
requiereLogin();
header('Location: ' . APP_URL . '/index.php');
exit;
