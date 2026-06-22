<?php
ini_set('display_errors', '0'); // nu expune stack-trace / structură DB în producție
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'httponly' => true,
  'secure'   => $secure,
  'samesite' => 'Strict',
]);
session_start();

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function csrf_token() {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}

function csrf_check() {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400);
    exit('Cerere invalidă.');
  }
}

function require_login() {
  if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
}

function current_user() { return $_SESSION['user_nume'] ?? ''; }

function redirect($url) { header("Location: $url"); exit; }
