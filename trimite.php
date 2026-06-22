<?php
ini_set('display_errors', '0'); // nu expune detalii de eroare vizitatorilor
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/administrare/throttle.php';

function fail($msg, $code = 422) {
  http_response_code($code);
  echo json_encode(['errors' => [['message' => $msg]]]);
  exit;
}

// Curăță CRLF din valorile ce ajung în header-e de email (anti header injection).
function hdr($s) { return trim(preg_replace('/[\r\n]+/', ' ', (string)$s)); }

// Plafonează lungimea inputurilor (anti bloat / abuz).
function cap($s, $n) { return mb_substr((string)$s, 0, $n); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Metodă invalidă.', 405);
if (!empty($_POST['_gotcha'])) { echo json_encode(['ok' => true]); exit; }

// Rate limiting pe IP: max 5 trimiteri / oră. Oprește spamul de lead-uri și
// folosirea serverului ca releu de email înainte de orice procesare costisitoare.
if (rate_blocked('form_' . client_ip(), 5, 3600)) {
  fail('Ai trimis prea multe mesaje. Te rugăm să încerci mai târziu sau scrie-ne pe WhatsApp.', 429);
}
rate_register('form_' . client_ip(), 3600);

$nume    = cap(trim($_POST['nume'] ?? ''), 150);
$email   = cap(trim($_POST['email'] ?? ''), 254);
$telefon = cap(trim($_POST['telefon'] ?? ''), 40);
$firma   = cap(trim($_POST['firma'] ?? ''), 150);
$mesaj   = cap(trim($_POST['mesaj'] ?? ''), 5000);
$consimt = $_POST['consimtamant'] ?? '';

if ($nume === '')                     fail('Te rugăm să completezi numele.');
if ($telefon === '' && $email === '') fail('Lasă-ne un telefon sau un email.');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Adresa de email nu pare validă.');
if ($email !== '') {
  $dom = substr(strrchr($email, '@'), 1);
  if ($dom === '' || $dom === false || !checkdnsrr($dom, 'MX')) {
    fail('Domeniul emailului nu pare să existe. Verifică adresa.');
  }
}
if ($consimt !== 'da') fail('Te rugăm să accepți prelucrarea datelor.');

$cfg    = require __DIR__ . '/administrare/private/config.php';
$token  = bin2hex(random_bytes(32));
$expira = date('Y-m-d H:i:s', time() + 7 * 86400);

try {
  require __DIR__ . '/administrare/db.php';
  $st = db()->prepare('INSERT INTO clienti (nume, firma, email, telefon, sursa, status, data_lead, observatii, token_confirmare, token_expira) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
  $st->execute([
    $nume,
    ($firma   !== '' ? $firma   : null),
    ($email   !== '' ? $email   : null),
    ($telefon !== '' ? $telefon : null),
    'formular', 'lead_nou', date('Y-m-d'),
    ($mesaj !== '' ? $mesaj : null),
    ($email !== '' ? $token  : null),
    ($email !== '' ? $expira : null),
  ]);
  $cid = (int) db()->lastInsertId();
  if ($mesaj !== '') {
    db()->prepare('INSERT INTO evenimente (client_id, tip, text) VALUES (?, ?, ?)')->execute([$cid, 'mesaj_formular', $mesaj]);
  }
} catch (Throwable $ex) {
  fail('A apărut o eroare la salvare. Încearcă din nou sau scrie-ne pe WhatsApp.', 500);
}

$host = preg_replace('/[^a-z0-9.\-]/i', '', ($_SERVER['HTTP_HOST'] ?? 'smart-web.ro'));
$from = 'From: noreply@' . $host . "\r\n";

// Notificare internă (către agenție)
$to = $cfg['notify_email'] ?? 'contact@smart-web.ro';
$corp = "Lead nou de pe site:\n\nNume: $nume\nFirmă: $firma\nEmail: $email\nTelefon: $telefon\n\nMesaj:\n$mesaj";
$hInt = $from;
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) $hInt .= 'Reply-To: ' . $email . "\r\n";
@mail($to, '[Smart-Web] Lead nou: ' . hdr($nume), $corp, $hInt);

// Email de confirmare către client (un singur email: click -> pagina cu brief)
if ($email !== '') {
  $base = rtrim($cfg['base_url'] ?? ('https://' . $host), '/');
  $link = $base . '/confirma.php?token=' . $token;
  $subiect = 'Confirmă-ți adresa - Smart-Web';
  $body = "Salut, $nume!\n\n"
        . "Am primit cererea ta. Confirmă-ți adresa de email accesând link-ul de mai jos, ca să continuăm:\n\n"
        . "$link\n\n"
        . "Link-ul e valabil 7 zile. Dacă nu tu ai trimis această cerere, ignoră acest mesaj.\n\n"
        . "— Echipa Smart-Web";
  @mail($email, $subiect, $body, $from);
}

echo json_encode(['ok' => true]);
