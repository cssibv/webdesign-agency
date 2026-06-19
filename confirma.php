<?php
require __DIR__ . '/administrare/db.php';
$cfg = require __DIR__ . '/administrare/private/config.php';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function pagina($titlu, $continut) {
  echo '<!DOCTYPE html><html lang="ro"><head><meta charset="UTF-8">';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
  echo '<meta name="robots" content="noindex, nofollow">';
  echo '<title>' . e($titlu) . ' - SmartWeb</title>';
  echo '<link rel="stylesheet" href="css/style.css">';
  echo '<style>.cf{max-width:720px;margin:0 auto}.cf label{display:block;font-weight:600;margin:1.1rem 0 .3rem}'
     . '.cf input,.cf textarea{width:100%;padding:.6rem .7rem;border:1px solid #cdd6e0;border-radius:8px;font:inherit;background:#fff;color:#1c2733}'
     . '.cf textarea{min-height:80px;resize:vertical}.cf .hp{position:absolute;left:-9999px}</style>';
  echo '</head><body><main class="section"><div class="container cf">' . $continut . '</div></main></body></html>';
  exit;
}

$token = trim($_POST['token'] ?? $_GET['token'] ?? '');

if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
  pagina('Link invalid', '<h2>Link invalid</h2><p>Link-ul de confirmare nu este valid. Scrie-ne pe WhatsApp dacă ai nevoie de ajutor.</p><p><a class="btn btn--primary" href="/">Înapoi pe site</a></p>');
}

$st = db()->prepare('SELECT * FROM clienti WHERE token_confirmare = ? LIMIT 1');
$st->execute([$token]);
$client = $st->fetch();

if (!$client) {
  pagina('Link invalid', '<h2>Link invalid sau deja folosit</h2><p>Cererea nu a fost găsită. Scrie-ne pe WhatsApp pentru ajutor.</p><p><a class="btn btn--primary" href="/">Înapoi pe site</a></p>');
}
if ($client['token_expira'] !== null && strtotime($client['token_expira']) < time()) {
  pagina('Link expirat', '<h2>Link expirat</h2><p>Link-ul a expirat. Trimite din nou formularul de pe site sau scrie-ne pe WhatsApp.</p><p><a class="btn btn--primary" href="/#contact">Înapoi la formular</a></p>');
}

$cid = (int) $client['id'];

// Marchează emailul confirmat (o singură dată)
if ($client['email_confirmat'] !== 'da') {
  db()->prepare('UPDATE clienti SET email_confirmat = ?, confirmat_la = NOW() WHERE id = ?')->execute(['da', $cid]);
  db()->prepare('INSERT INTO evenimente (client_id, tip, text) VALUES (?, ?, ?)')->execute([$cid, 'email_confirmat', 'Adresa de email confirmată']);
}

// Salvarea brief-ului
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['brief'])) {
  if (empty($_POST['_gotcha'])) {
    $nz = function ($v) { $v = trim((string)$v); return $v === '' ? null : $v; };
    $fields = ['domeniu_activitate','servicii','public_tinta','referinte','brand','continut','scop','pagini','domeniu_dorit','date_afisare','termen','plan_vizat','alte_detalii'];
    $vals = [$cid];
    foreach ($fields as $f) $vals[] = $nz($_POST[$f] ?? '');
    $sql = 'INSERT INTO brief (client_id, ' . implode(', ', $fields) . ') VALUES (?' . str_repeat(', ?', count($fields)) . ')';
    db()->prepare($sql)->execute($vals);
    db()->prepare('INSERT INTO evenimente (client_id, tip, text) VALUES (?, ?, ?)')->execute([$cid, 'brief', 'A completat chestionarul de brief']);
    $to = $cfg['notify_email'] ?? 'contact@smart-web.ro';
    @mail($to, '[SmartWeb] Brief completat: ' . $client['nume'], 'Clientul ' . $client['nume'] . ' a completat chestionarul. Vezi detaliile în panou.', 'From: noreply@smart-web.ro' . "\r\n");
  }
  pagina('Mulțumim', '<h2>Mulțumim! 🎉</h2><p>Am primit toate detaliile. Te contactăm în cel mai scurt timp ca să pornim.</p><p><a class="btn btn--primary" href="/">Înapoi pe site</a></p>');
}

// Dacă a completat deja brief-ul, nu-l mai arătăm
$areBrief = (int) db()->query('SELECT COUNT(*) FROM brief WHERE client_id = ' . $cid)->fetchColumn();
$nume = e($client['firma'] ?: $client['nume']);

if ($areBrief > 0) {
  pagina('Confirmat', '<h2>Adresă confirmată ✓</h2><p>Mulțumim, ' . $nume . '! Am primit deja detaliile tale. Te contactăm în curând.</p><p><a class="btn btn--primary" href="/">Înapoi pe site</a></p>');
}

$tok = e($token);
$form = <<<HTML
<h2>Adresă confirmată ✓</h2>
<p>Mulțumim, $nume! Ca să-ți pregătim site-ul mai repede, completează câteva detalii despre afacerea ta. Toate sunt opționale, dar ne ajută mult.</p>
<form method="post" class="cf">
  <input type="hidden" name="brief" value="1">
  <input type="hidden" name="token" value="$tok">
  <div class="hp"><label>Nu completa</label><input name="_gotcha" tabindex="-1" autocomplete="off"></div>
  <label>Cu ce se ocupă firma ta?</label><textarea name="domeniu_activitate"></textarea>
  <label>Ce servicii/produse oferi? (cele mai importante de promovat)</label><textarea name="servicii"></textarea>
  <label>Cine e clientul tău ideal?</label><textarea name="public_tinta"></textarea>
  <label>Site-uri care îți plac / concurenți (linkuri)</label><textarea name="referinte"></textarea>
  <label>Ai logo și culori de brand? (sau le facem noi)</label><textarea name="brand"></textarea>
  <label>Ai texte și poze? (sau ai nevoie de ajutor)</label><textarea name="continut"></textarea>
  <label>Ce vrei să obții cu site-ul? (prezență, clienți, rezervări...)</label><textarea name="scop"></textarea>
  <label>Ce pagini vrei? (Acasă, Servicii, Despre, Contact, Blog...)</label><textarea name="pagini"></textarea>
  <label>Ai deja un domeniu? Ce nume ți-ar plăcea?</label><input name="domeniu_dorit">
  <label>Date de afișat pe site (telefon, adresă, program, social media)</label><textarea name="date_afisare"></textarea>
  <label>Ai un termen/o urgență?</label><input name="termen">
  <label>Plan vizat (Start / Business / Pro)?</label><input name="plan_vizat">
  <label>Alte detalii</label><textarea name="alte_detalii"></textarea>
  <p style="margin-top:1.3rem"><button type="submit" class="btn btn--primary btn--lg">Trimite detaliile</button></p>
</form>
HTML;

pagina('Confirmă datele', $form);
