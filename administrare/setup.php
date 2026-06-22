<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

$count = (int) db()->query('SELECT COUNT(*) FROM utilizatori')->fetchColumn();
if ($count > 0) {
  exit('Setup deja efectuat. Șterge fișierul setup.php de pe server.');
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $nume   = trim($_POST['nume'] ?? '');
  $email  = trim($_POST['email'] ?? '');
  $parola = $_POST['parola'] ?? '';
  if ($nume && $email && strlen($parola) >= 8) {
    $st = db()->prepare('INSERT INTO utilizatori (nume, email, parola_hash) VALUES (?, ?, ?)');
    $st->execute([$nume, $email, password_hash($parola, PASSWORD_DEFAULT)]);
    exit('Cont creat. ȘTERGE ACUM setup.php de pe server, apoi mergi la login.php');
  }
  $msg = 'Completează toate câmpurile; parola minim 8 caractere.';
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Setup - Smart-Web Admin</title>
  <link rel="icon" type="image/svg+xml" href="../assets/img/favicon.svg">
  <link rel="stylesheet" href="../assets/fonts/fonts.css">
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <div class="login-box panel">
    <h1>Creează primul cont</h1>
    <?php if ($msg): ?><div class="err"><?= e($msg) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label>Nume</label><input name="nume" required>
      <label>Email</label><input type="email" name="email" required>
      <label>Parolă (min. 8)</label><input type="password" name="parola" required>
      <p><button class="btn btn--primary" type="submit">Creează cont</button></p>
    </form>
  </div>
</body>
</html>
