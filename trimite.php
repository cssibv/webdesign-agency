<?php
header('Content-Type: application/json; charset=utf-8');

function fail($msg, $code = 422) {
  http_response_code($code);
  echo json_encode(['errors' => [['message' => $msg]]]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Metodă invalidă.', 405);

if (!empty($_POST['_gotcha'])) { echo json_encode(['ok' => true]); exit; }

$nume    = trim($_POST['nume'] ?? '');
$email   = trim($_POST['email'] ?? '');
$telefon = trim($_POST['telefon'] ?? '');
$firma   = trim($_POST['firma'] ?? '');
$mesaj   = trim($_POST['mesaj'] ?? '');
$consimt = $_POST['consimtamant'] ?? '';

if ($nume === '')                     fail('Te rugăm să completezi numele.');
if ($telefon === '' && $email === '') fail('Lasă-ne un telefon sau un email.');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Adresa de email nu pare validă.');
if ($consimt !== 'da')                fail('Te rugăm să accepți prelucrarea datelor.');

try {
  require __DIR__ . '/administrare/db.php';
  $st = db()->prepare('INSERT INTO clienti (nume, firma, email, telefon, sursa, status, data_lead, observatii) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
  $st->execute([
    $nume,
    ($firma   !== '' ? $firma   : null),
    ($email   !== '' ? $email   : null),
    ($telefon !== '' ? $telefon : null),
    'formular',
    'lead_nou',
    date('Y-m-d'),
    ($mesaj   !== '' ? $mesaj   : null),
  ]);
  $cid = (int) db()->lastInsertId();
  if ($mesaj !== '') {
    db()->prepare('INSERT INTO evenimente (client_id, tip, text) VALUES (?, ?, ?)')->execute([$cid, 'mesaj_formular', $mesaj]);
  }
} catch (Throwable $ex) {
  fail('A apărut o eroare la salvare. Încearcă din nou sau scrie-ne pe WhatsApp.', 500);
}

$cfg = require __DIR__ . '/administrare/private/config.php';
$to  = $cfg['notify_email'] ?? 'contact@smart-web.ro';
$corp = "Lead nou de pe site:\n\nNume: $nume\nFirmă: $firma\nEmail: $email\nTelefon: $telefon\n\nMesaj:\n$mesaj";
$host = preg_replace('/[^a-z0-9.\-]/i', '', ($_SERVER['HTTP_HOST'] ?? 'smart-web.ro'));
$headers = 'From: site@' . $host . "\r\n";
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) $headers .= 'Reply-To: ' . $email . "\r\n";
@mail($to, '[SmartWeb] Lead nou: ' . $nume, $corp, $headers);

echo json_encode(['ok' => true]);
