<?php
require __DIR__ . '/auth.php';
// Deconectare doar prin POST cu token CSRF, ca un site rău-voitor să nu poată
// forța logout (ex. <img src="logout.php">). GET-ul direct doar te trimite înapoi.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
csrf_check();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: login.php');
