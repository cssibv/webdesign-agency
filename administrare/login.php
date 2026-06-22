<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';
require __DIR__ . '/throttle.php';

if (!empty($_SESSION['user_id'])) redirect('index.php');

$err = '';
// Lockout pe IP (nu pe sesiune): atacatorul nu poate ocoli contorul pur și simplu
// renunțând la cookie-ul de sesiune. Max 5 încercări eșuate / 5 minute / IP.
$lockKey = 'login_' . client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  if (rate_blocked($lockKey, 5, 300)) {
    $err = 'Prea multe încercări. Reîncearcă peste câteva minute.';
  } else {
    $email  = trim($_POST['email'] ?? '');
    $parola = $_POST['parola'] ?? '';
    $st = db()->prepare('SELECT * FROM utilizatori WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $u = $st->fetch();
    if ($u && password_verify($parola, $u['parola_hash'])) {
      session_regenerate_id(true);
      $_SESSION['user_id']   = $u['id'];
      $_SESSION['user_nume'] = $u['nume'];
      rate_clear($lockKey);
      redirect('index.php');
    } else {
      // Verificare dummy când userul nu există, ca timpul de răspuns să nu trădeze
      // dacă emailul e înregistrat (anti user-enumeration prin timing).
      if (!$u) password_verify($parola, '$2y$10$usesomesillystringforsalttocompare000000000000000000000');
      rate_register($lockKey, 300);
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
  <title>Autentificare - Smart-Web Admin</title>
  <link rel="icon" type="image/svg+xml" href="../assets/img/favicon.svg">
  <link rel="stylesheet" href="../assets/fonts/fonts.css">
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <div class="login-box panel">
    <h1>Smart-<span style="color:#ff7a45">Web</span> Admin</h1>
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
