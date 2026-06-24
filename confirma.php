<?php
ini_set('display_errors', '0'); // nu expune detalii de eroare vizitatorilor
require __DIR__ . '/administrare/db.php';
require __DIR__ . '/administrare/mailer.php';
$cfg = require __DIR__ . '/administrare/private/config.php';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
// Curăță CRLF din valorile ce ajung în header-e de email (anti header injection).
function hdr($s) { return trim(preg_replace('/[\r\n]+/', ' ', (string)$s)); }

function pagina($titlu, $continut) {
  echo '<!DOCTYPE html><html lang="ro"><head><meta charset="UTF-8">';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
  echo '<meta name="robots" content="noindex, nofollow">';
  echo '<title>' . e($titlu) . ' - Smart-Web</title>';
  echo '<link rel="stylesheet" href="css/style.css">';
  echo '<style>.cf{max-width:720px;margin:0 auto}.cf label{display:block;font-weight:600;margin:1.1rem 0 .3rem}'
     . '.cf input,.cf textarea{width:100%;padding:.6rem .7rem;border:1px solid #cdd6e0;border-radius:8px;font:inherit;background:#fff;color:#1c2733}'
     . '.cf textarea{min-height:80px;resize:vertical}.cf .hp{position:absolute;left:-9999px}'
     . '.cf fieldset{border:0;padding:0;margin:0}.cf legend{display:block;font-weight:600;margin:1.1rem 0 .45rem;padding:0}'
     . '.cf .opts{display:flex;flex-direction:column;gap:.45rem}'
     . '.cf .opt{display:flex;align-items:center;gap:.55rem;font-weight:400;margin:0;cursor:pointer}'
     . '.cf .opt input{width:auto;margin:0;flex:0 0 auto}'
     . '.cf input[type=date]{max-width:240px}.cf input:disabled{background:#eef2f6;color:#90a0b0}'
     . '.cf .req{color:#c0392b}.cf .hint{font-weight:400;color:#65758a;font-size:.9rem}'
     . '.cf .err{background:#fdecea;border:1px solid #f5b7b1;color:#922b21;padding:.75rem .9rem;border-radius:8px;margin-bottom:1rem}'
     . '.cf fieldset.err-fs{outline:2px solid #e74c3c;outline-offset:8px;border-radius:8px}</style>';
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

// Opțiunile pentru întrebările cu bife / radio
$OPT = [
  'public_tinta' => ['Persoane fizice (B2C)', 'Firme (B2B)', 'Clienți locali (Brașov / județ)', 'Clienți din toată țara'],
  'brand'        => ['Am logo', 'Am culori de brand', 'Le creați voi'],
  'continut'     => ['Am textele', 'Am pozele', 'Am nevoie de ajutor la texte', 'Am nevoie de poze'],
  'scop'         => ['Prezență online / credibilitate', 'Clienți noi / cereri de ofertă', 'Programări / rezervări', 'Vânzări online (magazin)', 'Promovare servicii / produse'],
  'pagini'       => ['Acasă', 'Servicii', 'Despre noi', 'Contact', 'Portofoliu / Galerie', 'Blog', 'Prețuri', 'Întrebări frecvente'],
];
$PLANURI   = ['Start', 'Business', 'Pro', 'Nu sunt sigur — recomandați voi'];
$fields    = ['domeniu_activitate','servicii','public_tinta','referinte','brand','continut','scop','pagini','domeniu_dorit','date_afisare','termen','plan_vizat','alte_detalii'];
$arrFields = array_keys($OPT);
$required  = ['domeniu_activitate','servicii','public_tinta','brand','continut','scop','pagini','domeniu_dorit','date_afisare','termen','plan_vizat'];

function colecteaza_brief(array $fields, array $arrFields) {
  $nz = function ($v) { $v = trim((string)$v); return $v === '' ? null : $v; };
  $out = [];
  foreach ($fields as $f) {
    if ($f === 'termen') {
      $out[$f] = !empty($_POST['termen_flexibil']) ? 'Flexibil' : $nz($_POST['termen'] ?? '');
    } elseif (in_array($f, $arrFields, true)) {
      $a = $_POST[$f] ?? [];
      if (!is_array($a)) $a = [$a];
      $a = array_values(array_filter(array_map('trim', $a), function ($x) { return $x !== ''; }));
      $out[$f] = $a ? implode(', ', $a) : null;
    } else {
      $out[$f] = $nz($_POST[$f] ?? '');
    }
  }
  return $out;
}

function cb_group($name, $label, array $optiuni, $valoare) {
  $sel = array_filter(array_map('trim', explode(',', (string)$valoare)), function ($x) { return $x !== ''; });
  $h = '<fieldset data-req="1"><legend>' . e($label) . ' <span class="req">*</span></legend><div class="opts">';
  foreach ($optiuni as $o) {
    $ck = in_array($o, $sel, true) ? ' checked' : '';
    $h .= '<label class="opt"><input type="checkbox" name="' . e($name) . '[]" value="' . e($o) . '"' . $ck . '> ' . e($o) . '</label>';
  }
  return $h . '</div></fieldset>';
}

function radio_group($name, $label, array $optiuni, $valoare) {
  $h = '<fieldset><legend>' . e($label) . ' <span class="req">*</span></legend><div class="opts">';
  foreach ($optiuni as $o) {
    $ck = ((string)$valoare === $o) ? ' checked' : '';
    $h .= '<label class="opt"><input type="radio" name="' . e($name) . '" value="' . e($o) . '"' . $ck . ' required> ' . e($o) . '</label>';
  }
  return $h . '</div></fieldset>';
}

$nume = e($client['firma'] ?: $client['nume']);
$vals = array_fill_keys($fields, null);
$briefError = '';

// Salvarea brief-ului
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['brief'])) {
  if (!empty($_POST['_gotcha'])) {
    pagina('Mulțumim', '<h2>Mulțumim! 🎉</h2><p>Am primit toate detaliile. Te contactăm în cel mai scurt timp ca să pornim.</p><p><a class="btn btn--primary" href="/">Înapoi pe site</a></p>');
  }
  $vals = colecteaza_brief($fields, $arrFields);
  $lipsa = [];
  foreach ($required as $f) { if (($vals[$f] ?? null) === null) $lipsa[] = $f; }
  if ($lipsa) {
    $briefError = 'Te rugăm completează toate câmpurile obligatorii (cele marcate cu *).';
  } else {
    $row = [$cid];
    foreach ($fields as $f) $row[] = $vals[$f];
    $sql = 'INSERT INTO brief (client_id, ' . implode(', ', $fields) . ') VALUES (?' . str_repeat(', ?', count($fields)) . ')';
    db()->prepare($sql)->execute($row);
    db()->prepare('INSERT INTO evenimente (client_id, tip, text) VALUES (?, ?, ?)')->execute([$cid, 'brief', 'A completat chestionarul de brief']);
    $to = $cfg['notify_email'] ?? 'contact@smart-web.ro';
    $base = rtrim($cfg['base_url'] ?? 'https://smart-web.ro', '/');
    $briefInner = email_h('Brief completat')
      . email_p('Clientul <strong>' . email_esc($client['nume']) . '</strong> a completat chestionarul.')
      . email_button('Vezi în panou &rarr;', $base . '/administrare/index.php');
    smtp_send($cfg, $to, '[SmartWeb] Brief completat: ' . hdr($client['nume']), 'Clientul ' . $client['nume'] . ' a completat chestionarul. Vezi detaliile în panou.', '', email_layout($cfg, $briefInner));
    pagina('Mulțumim', '<h2>Mulțumim! 🎉</h2><p>Am primit toate detaliile. Te contactăm în cel mai scurt timp ca să pornim.</p><p><a class="btn btn--primary" href="/">Înapoi pe site</a></p>');
  }
}

// Dacă a completat deja brief-ul, nu-l mai arătăm
$stB = db()->prepare('SELECT COUNT(*) FROM brief WHERE client_id = ?');
$stB->execute([$cid]);
$areBrief = (int) $stB->fetchColumn();

if ($areBrief > 0) {
  pagina('Confirmat', '<h2>Adresă confirmată ✓</h2><p>Mulțumim, ' . $nume . '! Am primit deja detaliile tale. Te contactăm în curând.</p><p><a class="btn btn--primary" href="/">Înapoi pe site</a></p>');
}

$tok = e($token);
$today = date('Y-m-d');
$flexChecked = ($vals['termen'] === 'Flexibil') ? ' checked' : '';
$termenDate  = ($vals['termen'] && $vals['termen'] !== 'Flexibil') ? e($vals['termen']) : '';
$errHtml = $briefError !== '' ? '<div class="err">' . e($briefError) . '</div>' : '';
$v = function ($k) use ($vals) { return e((string)($vals[$k] ?? '')); };

$form = $errHtml
  . '<h2>Adresă confirmată ✓</h2>'
  . '<p>Mulțumim, ' . $nume . '! Ca să-ți pregătim site-ul mai repede, completează detaliile de mai jos — durează ~2 minute. Câmpurile cu <span class="req">*</span> sunt obligatorii.</p>'
  . '<form method="post" class="cf">'
  . '<input type="hidden" name="brief" value="1">'
  . '<input type="hidden" name="token" value="' . $tok . '">'
  . '<div class="hp"><label>Nu completa</label><input name="_gotcha" tabindex="-1" autocomplete="off"></div>'
  . '<label>Cu ce se ocupă firma ta? <span class="req">*</span></label><input name="domeniu_activitate" value="' . $v('domeniu_activitate') . '" required>'
  . '<label>Ce servicii/produse oferi? (cele mai importante de promovat) <span class="req">*</span></label><textarea name="servicii" required>' . $v('servicii') . '</textarea>'
  . cb_group('public_tinta', 'Cine e clientul tău ideal?', $OPT['public_tinta'], $vals['public_tinta'])
  . '<label>Site-uri care îți plac / concurenți, linkuri <span class="hint">(opțional)</span></label><textarea name="referinte">' . $v('referinte') . '</textarea>'
  . cb_group('brand', 'Ai logo și culori de brand?', $OPT['brand'], $vals['brand'])
  . cb_group('continut', 'Ai texte și poze pentru site?', $OPT['continut'], $vals['continut'])
  . cb_group('scop', 'Ce vrei să obții cu site-ul?', $OPT['scop'], $vals['scop'])
  . cb_group('pagini', 'Ce pagini vrei pe site?', $OPT['pagini'], $vals['pagini'])
  . '<label>Ce nume de domeniu ți-ar plăcea? (ex: firma-mea.ro) <span class="req">*</span></label><input name="domeniu_dorit" value="' . $v('domeniu_dorit') . '" required>'
  . '<label>Date de afișat pe site (telefon, adresă, program, social media) <span class="req">*</span></label><textarea name="date_afisare" required>' . $v('date_afisare') . '</textarea>'
  . '<fieldset><legend>Până când ai vrea să fie gata? <span class="req">*</span></legend>'
    . '<input type="date" name="termen" min="' . $today . '" value="' . $termenDate . '">'
    . '<label class="opt" style="margin-top:.5rem"><input type="checkbox" name="termen_flexibil" value="1"' . $flexChecked . '> Nu mă grăbesc / sunt flexibil</label>'
  . '</fieldset>'
  . radio_group('plan_vizat', 'Ce plan ai în vedere?', $PLANURI, $vals['plan_vizat'])
  . '<label>Alte detalii <span class="hint">(opțional)</span></label><textarea name="alte_detalii">' . $v('alte_detalii') . '</textarea>'
  . '<p style="margin-top:1.3rem"><button type="submit" class="btn btn--primary btn--lg">Trimite detaliile</button></p>'
  . '</form>'
  . '<script>(function(){var f=document.querySelector("form.cf");if(!f)return;var flex=f.querySelector("input[name=termen_flexibil]"),dt=f.querySelector("input[name=termen]");function sync(){if(flex&&dt){dt.disabled=flex.checked;if(flex.checked)dt.value="";}}if(flex){flex.addEventListener("change",sync);sync();}f.addEventListener("submit",function(ev){var fss=f.querySelectorAll("fieldset[data-req]");for(var i=0;i<fss.length;i++){var bx=fss[i].querySelectorAll("input[type=checkbox]"),ok=false;for(var j=0;j<bx.length;j++){if(bx[j].checked)ok=true;}if(bx.length&&!ok){ev.preventDefault();fss[i].classList.add("err-fs");fss[i].scrollIntoView({block:"center"});return;}}if(dt&&!dt.disabled&&!dt.value){ev.preventDefault();var fs=dt.closest("fieldset");fs.classList.add("err-fs");fs.scrollIntoView({block:"center"});return;}});})();</script>';

pagina('Confirmă datele', $form);
