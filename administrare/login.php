<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

if (!empty($_SESSION['user_id'])) redirect('index.php');

$err = '';
$now = time();
$_SESSION['login_lock_until'] = $_SESSION['login_lock_until'] ?? 0;
$_SESSION['login_attempts']   = $_SESSION['login_attempts'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  if ($now < $_SESSION['login_lock_until']) {
    $err = 'Prea multe încercări. Reîncearcă peste câteva minute.';
  } else {
    $email  = trim($_POST['email'] ?? '');
    $parola = $_POST['parola'] ?? '';
    $st = db()->prepare('SELECT * FROM utilizatori WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $u = $st->fetch();
    if ($u && password_verify($parola, $u['parola_hash'])) {
      session_regenerate_id(true);
      $_SESSION['user_id']        = $u['id'];
      $_SESSION['user_nume']      = $u['nume'];
      $_SESSION['login_attempts'] = 0;
      redirect('index.php');
    } else {
      $_SESSION['login_attempts']++;
      if ($_SESSION['login_attempts'] >= 5) {
        $_SESSION['login_lock_until'] = $now + 300;
        $_SESSION['login_attempts']   = 0;
      }
      $err = 'Email sau parolă greșite.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Autentificare - SmartWeb Admin</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <div class="login-box panel">
    <h1>SmartWeb Admin</h1>
    <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label>Email</label>
      <input type="email" name="email" required autofocus>
      <label>Parolă</label>
      <input type="password" name="parola" required>
      <p><button class="btn btn--primary" type="submit">Intră</button></p>
    </form>
  </div>
</body>
</html>
