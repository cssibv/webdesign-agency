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
  echo '<meta name="color-scheme" content="light dark">';
  echo '<title>' . e($titlu) . ' · Smart Web</title>';
  echo '<script>(function(){try{var s=localStorage.getItem("theme");var d=s?s==="dark":window.matchMedia("(prefers-color-scheme: dark)").matches;if(d)document.documentElement.setAttribute("data-theme","dark");}catch(e){}})();</script>';
  echo '<link rel="stylesheet" href="css/style.css">';
  echo '<style>'
     . 'body.cf-page{background:var(--c-bg-alt)}'
     . '.cf{max-width:680px;margin:2.4rem auto;background:var(--c-surface);border:1px solid var(--c-border);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:clamp(1.5rem,4vw,2.7rem)}'
     . '.cf__brand{display:flex;align-items:center;gap:.5rem;font-weight:800;font-size:1.75rem;color:var(--c-primary);margin-bottom:1.4rem}'
     . '.cf__mark{color:var(--c-teal)}.cf__accent{color:var(--c-accent)}'
     . '.cf h2{margin:.2rem 0 .5rem;font-size:1.55rem}.cf>p{color:var(--c-text-soft);margin:0 0 1rem;line-height:1.55}'
     . '.cf .err{background:#fdecea;border:1px solid #f5b7b1;color:#922b21;padding:.85rem 1rem;border-radius:var(--radius-sm);margin-bottom:1.3rem;font-weight:600}'
     . '.grp{margin-top:2.4rem;padding-top:1.7rem;border-top:1px solid var(--c-border)}'
     . '.grp:first-of-type{margin-top:1.6rem;padding-top:0;border-top:0}'
     . '.grp__title{font-size:.78rem;letter-spacing:.09em;text-transform:uppercase;color:var(--c-teal-dark);font-weight:700;margin:0 0 1.3rem}'
     . '.field{margin-bottom:1.8rem}.field:last-child{margin-bottom:0}'
     . '.field__label{display:block;font-weight:600;color:var(--c-text);margin-bottom:.6rem}'
     . '.field__hint{display:block;color:var(--c-text-soft);font-size:.88rem;margin:-.35rem 0 .65rem}'
     . '.req{color:var(--c-accent)}.hint{font-weight:400;color:var(--c-text-soft);font-size:.9rem}'
     . '.cf input[type=text],.cf textarea{width:100%;padding:.72rem .9rem;border:1px solid var(--c-border);border-radius:var(--radius-sm);font:inherit;background:var(--c-bg-alt);color:var(--c-text);transition:border-color .15s,box-shadow .15s}'
     . '.cf textarea{min-height:92px;resize:vertical}'
     . '.cf input[type=text]:focus,.cf textarea:focus{outline:0;border-color:var(--c-teal);box-shadow:0 0 0 3px rgba(26,169,160,.18)}'
     . '.cf input[type=date]{padding:.6rem .8rem;border:1px solid var(--c-border);border-radius:var(--radius-sm);font:inherit;background:var(--c-bg-alt);color:var(--c-text);max-width:260px}'
     . '.cf input[type=date]:focus{outline:0;border-color:var(--c-teal);box-shadow:0 0 0 3px rgba(26,169,160,.18)}'
     . '.cf input[type=date]:disabled{opacity:.45;cursor:not-allowed}'
     . '.choices{display:flex;flex-wrap:wrap;gap:.6rem}'
     . '.choice{position:relative;display:inline-flex}'
     . '.choice input{position:absolute;opacity:0;width:0;height:0}'
     . '.choice__b{display:inline-flex;align-items:center;padding:.6rem 1.05rem;border:1.5px solid var(--c-border);border-radius:999px;background:var(--c-surface);color:var(--c-text);font-weight:600;font-size:.95rem;cursor:pointer;transition:background .15s,border-color .15s,color .15s}'
     . '.choice__b:hover{border-color:var(--c-teal);color:var(--c-teal-dark)}'
     . '.choice input:checked+.choice__b{background:var(--c-accent);border-color:var(--c-accent);color:#fff;box-shadow:var(--shadow-sm)}'
     . '.choice input:checked+.choice__b:hover{color:#fff}'
     . '.choice input:focus-visible+.choice__b{outline:3px solid var(--c-teal);outline-offset:2px}'
     . '.choice input:disabled+.choice__b{opacity:.4;cursor:not-allowed}'
     . '.hp{position:absolute;left:-9999px}'
     . '.field.err-field .field__label{color:#c0392b}'
     . '.field.err-field .choices{outline:2px dashed #e8a39a;outline-offset:8px;border-radius:14px}'
     . '.color-row{display:flex;align-items:center;gap:.6rem;margin:.5rem 0}'
     . '.color-row input[type=color]{width:48px;height:34px;border:1px solid var(--c-border);border-radius:8px;background:none;cursor:pointer;padding:2px}'
     . '.color-row input[type=color]:disabled{opacity:.4;cursor:not-allowed}'
     . '.color-row span{font-weight:600;color:var(--c-text)}'
     . '.social-list{display:flex;flex-direction:column;gap:.55rem}'
     . '.social-row{display:flex;flex-wrap:wrap;align-items:center;gap:.6rem}'
     . '.social-row .social-check{min-width:150px;display:flex;align-items:center;gap:.45rem;font-weight:600;color:var(--c-text)}'
     . '.social-row input[type=text]{flex:1;min-width:180px;padding:.55rem .8rem;border:1px solid var(--c-border);border-radius:var(--radius-sm);font:inherit;background:var(--c-bg-alt);color:var(--c-text)}'
     . '.social-row input[type=text]:disabled{opacity:.45}'
     . '.field.err-field .social-list,.field.err-field .color-row{outline:2px dashed #e8a39a;outline-offset:6px;border-radius:12px}'
     . '</style>';
  echo '</head><body class="cf-page"><main class="section"><div class="container"><div class="cf">'
     . '<div class="cf__brand"><span class="cf__mark">&#9670;</span> Smart <span class="cf__accent">Web</span></div>'
     . $continut . '</div></div></main></body></html>';
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

// Ordinea coloanelor din tabela brief (rămâne neschimbată)
$fields = ['domeniu_activitate','servicii','public_tinta','referinte','brand','continut','scop','pagini','domeniu_dorit','date_afisare','termen','plan_vizat','alte_detalii'];

// Opțiuni multi-choice (pastile)
$PUBLIC = ['Persoane fizice (B2C)', 'Firme (B2B)', 'Clienți locali (Brașov / județ)', 'Clienți din toată țara'];
$SCOP   = ['Prezență online / credibilitate', 'Clienți noi / cereri de ofertă', 'Programări / rezervări', 'Vânzări online (magazin)', 'Promovare servicii / produse'];
$PAGINI = ['Acasă', 'Servicii', 'Despre noi', 'Contact', 'Portofoliu / Galerie', 'Blog', 'Prețuri', 'Întrebări frecvente'];
$PLANURI = ['Start', 'Business', 'Pro', 'Nu sunt sigur, recomandați voi'];

// Citirea valorilor anterioare (pentru repopularea formularului la eroare)
function old_v($k) { return e((string)($_POST[$k] ?? '')); }
function old_in($k, $v) { $a = $_POST[$k] ?? []; if (!is_array($a)) $a = [$a]; return in_array($v, $a, true); }
function old_is($k, $v) { return (string)($_POST[$k] ?? '') === $v; }

// Câmp text / textarea
function f_text($name, $label, $req, $textarea = false, $ph = '') {
  $mark = $req ? ' <span class="req">*</span>' : ' <span class="hint">(opțional)</span>';
  $r = $req ? ' required' : '';
  $p = $ph !== '' ? ' placeholder="' . e($ph) . '"' : '';
  $h = '<div class="field"><label class="field__label" for="f_' . e($name) . '">' . e($label) . $mark . '</label>';
  if ($textarea) {
    $h .= '<textarea id="f_' . e($name) . '" name="' . e($name) . '"' . $r . $p . '>' . old_v($name) . '</textarea>';
  } else {
    $h .= '<input type="text" id="f_' . e($name) . '" name="' . e($name) . '" value="' . old_v($name) . '"' . $r . $p . '>';
  }
  return $h . '</div>';
}

// Grup de pastile selectabile (multi = checkbox, single = radio)
function f_pills($name, $label, array $optiuni, $multi, $hint = '') {
  $h = '<div class="field"><span class="field__label">' . e($label) . ' <span class="req">*</span></span>';
  if ($hint !== '') $h .= '<span class="field__hint">' . e($hint) . '</span>';
  $h .= '<div class="choices" data-req-group="1">';
  foreach ($optiuni as $o) {
    if ($multi) {
      $inp = '<input type="checkbox" name="' . e($name) . '[]" value="' . e($o) . '"' . (old_in($name, $o) ? ' checked' : '') . '>';
    } else {
      $inp = '<input type="radio" name="' . e($name) . '" value="' . e($o) . '"' . (old_is($name, $o) ? ' checked' : '') . '>';
    }
    $h .= '<label class="choice">' . $inp . '<span class="choice__b">' . e($o) . '</span></label>';
  }
  return $h . '</div></div>';
}

// Slot de culoare cu pipetă (checkbox „folosesc" + input type=color)
function color_row($key, $label, $default, $onDefault) {
  $resubmit = isset($_POST['brand_culori']);
  $on  = $resubmit ? !empty($_POST['culoare_on'][$key]) : $onDefault;
  $val = $_POST['culoare'][$key] ?? $default;
  if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string)$val)) $val = $default;
  return '<label class="color-row">'
    . '<input type="checkbox" name="culoare_on[' . e($key) . ']" value="1"' . ($on ? ' checked' : '') . '>'
    . '<input type="color" name="culoare[' . e($key) . ']" value="' . e($val) . '"' . ($on ? '' : ' disabled') . '>'
    . '<span>' . e($label) . '</span></label>';
}

// Rând platformă social media (checkbox + handle/link)
function social_row($key, $label) {
  $on  = !empty($_POST['social']) && in_array($key, (array)$_POST['social'], true);
  $val = $_POST['social_h'][$key] ?? '';
  return '<div class="social-row">'
    . '<label class="social-check"><input type="checkbox" name="social[]" value="' . e($key) . '"' . ($on ? ' checked' : '') . '> ' . e($label) . '</label>'
    . '<input type="text" name="social_h[' . e($key) . ']" value="' . e($val) . '" placeholder="@nume sau link"' . ($on ? ' required' : '') . '>'
    . '</div>';
}

// Strânge valorile pentru salvare (combină sub-întrebările Da/Nu într-o singură coloană)
function colecteaza_brief() {
  $nz = function ($v) { $v = trim((string)$v); return $v === '' ? null : $v; };
  $multi = function ($k) {
    $a = $_POST[$k] ?? [];
    if (!is_array($a)) $a = [$a];
    $a = array_values(array_filter(array_map('trim', $a), function ($x) { return $x !== ''; }));
    return $a ? implode(', ', $a) : null;
  };
  $logo = $nz($_POST['brand_logo'] ?? '');
  $culori = $nz($_POST['brand_culori'] ?? '');
  $bp = [];
  if ($logo !== null) {
    $bp[] = 'Logo: ' . $logo;
    if ($logo === 'Da, îl am') {
      $ll = $nz($_POST['logo_link'] ?? '');
      if ($ll !== null) $bp[] = 'Logo (link/notă): ' . $ll;
    } elseif ($logo === 'Nu, îl creați voi') {
      $ld = $nz($_POST['logo_descriere'] ?? '');
      if ($ld !== null) $bp[] = 'Descriere logo dorit: ' . $ld;
    }
  }
  if ($culori !== null) {
    $bp[] = 'Culori: ' . $culori;
    if ($culori === 'Da, le am') {
      $pal = [];
      foreach (['principala' => 'Principală', 'secundara' => 'Secundară', 'accent' => 'Accent'] as $k => $lbl) {
        if (!empty($_POST['culoare_on'][$k])) {
          $hex = (string)($_POST['culoare'][$k] ?? '');
          if (preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) $pal[] = $lbl . ' ' . strtoupper($hex);
        }
      }
      if ($pal) $bp[] = 'Paletă: ' . implode(', ', $pal);
      $cn = $nz($_POST['culori_note'] ?? '');
      if ($cn !== null) $bp[] = 'Note culori: ' . $cn;
    }
  }
  $brand = $bp ? implode('; ', $bp) : null;
  $texte = $nz($_POST['cont_texte'] ?? '');
  $poze = $nz($_POST['cont_poze'] ?? '');
  $continut = ($texte === null && $poze === null) ? null : ('Texte: ' . ($texte ?? '-') . '; Poze/imagini: ' . ($poze ?? '-'));
  $contact = $nz($_POST['date_afisare'] ?? '');
  if (!empty($_POST['social_none'])) {
    $social = 'Social media: nu are conturi încă.';
  } else {
    $names = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'google' => 'Google Business'];
    $sl = [];
    foreach ((array)($_POST['social'] ?? []) as $k) {
      if (!isset($names[$k])) continue;
      $h = $nz($_POST['social_h'][$k] ?? '');
      if ($h !== null) $sl[] = $names[$k] . ': ' . $h;
    }
    $social = $sl ? "Social media:\n" . implode("\n", $sl) : null;
  }
  $dateAfisare = $contact;
  if ($social !== null) $dateAfisare = ($contact !== null ? $contact . "\n\n" : '') . $social;
  return [
    'domeniu_activitate' => $nz($_POST['domeniu_activitate'] ?? ''),
    'servicii'           => $nz($_POST['servicii'] ?? ''),
    'public_tinta'       => $multi('public_tinta'),
    'referinte'          => $nz($_POST['referinte'] ?? ''),
    'brand'              => $brand,
    'continut'           => $continut,
    'scop'               => $multi('scop'),
    'pagini'             => $multi('pagini'),
    'domeniu_dorit'      => $nz($_POST['domeniu_dorit'] ?? ''),
    'date_afisare'       => $dateAfisare,
    'termen'             => !empty($_POST['termen_flexibil']) ? 'Flexibil' : $nz($_POST['termen'] ?? ''),
    'plan_vizat'         => $nz($_POST['plan_vizat'] ?? ''),
    'alte_detalii'       => $nz($_POST['alte_detalii'] ?? ''),
  ];
}

// Validare server-side a câmpurilor obligatorii
function brief_valid() {
  $nz = function ($v) { return trim((string)$v) === '' ? null : trim((string)$v); };
  $hasMulti = function ($k) {
    $a = $_POST[$k] ?? [];
    return is_array($a) && count(array_filter($a, function ($x) { return trim((string)$x) !== ''; })) > 0;
  };
  foreach (['domeniu_activitate','servicii','domeniu_dorit','date_afisare','plan_vizat','brand_logo','brand_culori','cont_texte','cont_poze'] as $k) {
    if ($nz($_POST[$k] ?? '') === null) return false;
  }
  foreach (['public_tinta','scop','pagini'] as $k) {
    if (!$hasMulti($k)) return false;
  }
  if (empty($_POST['termen_flexibil']) && $nz($_POST['termen'] ?? '') === null) return false;

  // Logo: dacă îl creăm noi, descrierea e obligatorie
  if (($_POST['brand_logo'] ?? '') === 'Nu, îl creați voi' && $nz($_POST['logo_descriere'] ?? '') === null) return false;
  // Culori: dacă „le am", cel puțin un slot bifat
  if (($_POST['brand_culori'] ?? '') === 'Da, le am') {
    $on = $_POST['culoare_on'] ?? [];
    if (!is_array($on) || count(array_filter($on)) === 0) return false;
  }
  // Social: ori „nu am conturi", ori cel puțin o platformă bifată cu handle completat
  if (empty($_POST['social_none'])) {
    $sel = (array)($_POST['social'] ?? []);
    if (count($sel) === 0) return false;
    foreach ($sel as $k) {
      if ($nz($_POST['social_h'][$k] ?? '') === null) return false;
    }
  }
  return true;
}

$nume = e($client['firma'] ?: $client['nume']);
$briefError = '';

// Salvarea brief-ului
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['brief'])) {
  if (!empty($_POST['_gotcha'])) {
    pagina('Mulțumim', '<h2>Mulțumim! 🎉</h2><p>Am primit toate detaliile. Te contactăm în cel mai scurt timp ca să pornim.</p><p><a class="btn btn--primary" href="/">Înapoi pe site</a></p>');
  }
  $stDup = db()->prepare('SELECT COUNT(*) FROM brief WHERE client_id = ?');
  $stDup->execute([$cid]);
  if ((int) $stDup->fetchColumn() > 0) {
    pagina('Mulțumim', '<h2>Mulțumim! 🎉</h2><p>Am primit deja detaliile tale. Te contactăm în cel mai scurt timp ca să pornim.</p><p><a class="btn btn--primary" href="/">Înapoi pe site</a></p>');
  }
  if (!brief_valid()) {
    $briefError = 'Te rugăm completează toate câmpurile obligatorii (cele marcate cu *).';
  } else {
    $vals = colecteaza_brief();
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
$flexChecked = !empty($_POST['termen_flexibil']) ? ' checked' : '';
$termenVal = (isset($_POST['termen']) && empty($_POST['termen_flexibil'])) ? e($_POST['termen']) : '';
$errHtml = $briefError !== '' ? '<div class="err">' . e($briefError) . '</div>' : '';

$termenField = '<div class="field"><span class="field__label">Până când ai vrea să fie gata? <span class="req">*</span></span>'
  . '<input type="date" name="termen" min="' . $today . '" value="' . $termenVal . '">'
  . '<div class="choices" style="margin-top:.7rem"><label class="choice"><input type="checkbox" name="termen_flexibil" value="1"' . $flexChecked . '><span class="choice__b">Nu mă grăbesc / sunt flexibil</span></label></div>'
  . '</div>';

$form = $errHtml
  . '<h2>Adresă confirmată ✓</h2>'
  . '<p>Mulțumim, ' . $nume . '! Ca să-ți pregătim site-ul mai repede, completează detaliile de mai jos. Câmpurile cu <span class="req">*</span> sunt obligatorii.</p>'
  . '<form method="post">'
  . '<input type="hidden" name="brief" value="1">'
  . '<input type="hidden" name="token" value="' . $tok . '">'
  . '<div class="hp"><label>Nu completa</label><input name="_gotcha" tabindex="-1" autocomplete="off"></div>'

  . '<section class="grp"><h3 class="grp__title">Despre afacerea ta</h3>'
  . f_text('domeniu_activitate', 'Cu ce se ocupă firma ta?', true, false, 'ex: cabinet stomatologic, firmă de instalații...')
  . f_text('servicii', 'Ce servicii sau produse oferi? (cele mai importante de promovat)', true, true)
  . f_pills('public_tinta', 'Cine e clientul tău ideal?', $PUBLIC, true, 'Poți alege mai multe.')
  . f_text('referinte', 'Site-uri care îți plac sau concurenți (linkuri)', false, true)
  . '</section>'

  . '<section class="grp"><h3 class="grp__title">Conținut și identitate vizuală</h3>'
  . f_pills('brand_logo', 'Ai un logo?', ['Da, îl am', 'Nu, îl creați voi'], false)
  . '<div class="field cond" id="logo_have"' . ((($_POST['brand_logo'] ?? '') === 'Da, îl am') ? '' : ' hidden') . '>'
    . '<label class="field__label" for="f_logo_link">Trimite-ne logo-ul</label>'
    . '<span class="field__hint">Pune un link (Google Drive, site etc.) sau scrie „îl trimit pe email la contact@smart-web.ro".</span>'
    . '<input type="text" id="f_logo_link" name="logo_link" value="' . old_v('logo_link') . '" placeholder="link către logo sau «îl trimit pe email»">'
  . '</div>'
  . '<div class="field cond" id="logo_make"' . ((($_POST['brand_logo'] ?? '') === 'Nu, îl creați voi') ? '' : ' hidden') . '>'
    . '<label class="field__label" for="f_logo_descriere">Cum ți-ai dori să arate logo-ul? <span class="req">*</span></label>'
    . '<span class="field__hint">Descrie pe scurt: stil (modern, minimalist, clasic), simboluri sau elemente, ce mesaj să transmită, eventual culori preferate.</span>'
    . '<textarea id="f_logo_descriere" name="logo_descriere">' . old_v('logo_descriere') . '</textarea>'
  . '</div>'
  . f_pills('brand_culori', 'Ai culori sau identitate de brand?', ['Da, le am', 'Nu, alegeți voi'], false)
  . '<div class="field cond" id="culori_box"' . ((($_POST['brand_culori'] ?? '') === 'Da, le am') ? '' : ' hidden') . '>'
    . '<span class="field__label">Culorile tale de brand</span>'
    . '<span class="field__hint">Bifează și alege cu pipeta. Lasă bifate doar culorile pe care chiar le folosești.</span>'
    . color_row('principala', 'Principală', '#1A4D8F', true)
    . color_row('secundara', 'Secundară', '#FF7A45', true)
    . color_row('accent', 'Accent', '#1AA9A0', false)
    . '<input type="text" name="culori_note" value="' . old_v('culori_note') . '" placeholder="Note (opțional): unde le folosești, nume de culori..." style="margin-top:.7rem;width:100%">'
  . '</div>'
  . f_pills('cont_texte', 'Ai textele pentru site?', ['Da, le am', 'Nu, mă ajutați voi'], false)
  . f_pills('cont_poze', 'Ai pozele sau imaginile?', ['Da, le am', 'Nu, mă ajutați voi'], false)
  . '</section>'

  . '<section class="grp"><h3 class="grp__title">Site-ul dorit</h3>'
  . f_pills('scop', 'Ce vrei să obții cu site-ul?', $SCOP, true, 'Poți alege mai multe.')
  . f_pills('pagini', 'Ce pagini vrei pe site?', $PAGINI, true, 'Poți alege mai multe.')
  . f_text('domeniu_dorit', 'Ce nume de domeniu ți-ar plăcea?', true, false, 'ex: firma-mea.ro')
  . f_text('date_afisare', 'Telefon, adresă și program de lucru', true, true, 'ex: 0727 959 331 · Str. Lungă nr. 1, Brașov · Luni–Vineri 9–17')
  . '<div class="field"><span class="field__label">Conturi de social media</span>'
    . '<span class="field__hint">Bifează ce ai și pune link-ul sau numele contului. Dacă nu ai încă, bifează ultima opțiune.</span>'
    . '<div class="social-list">'
    . social_row('facebook', 'Facebook') . social_row('instagram', 'Instagram') . social_row('tiktok', 'TikTok')
    . social_row('linkedin', 'LinkedIn') . social_row('youtube', 'YouTube') . social_row('google', 'Google Business')
    . '</div>'
    . '<label class="choice" style="margin-top:.8rem"><input type="checkbox" id="social_none" name="social_none" value="1"' . (!empty($_POST['social_none']) ? ' checked' : '') . '><span class="choice__b">Nu am conturi de social media încă</span></label>'
  . '</div>'
  . '</section>'

  . '<section class="grp"><h3 class="grp__title">Livrare și plan</h3>'
  . $termenField
  . f_pills('plan_vizat', 'Ce plan ai în vedere?', $PLANURI, false)
  . f_text('alte_detalii', 'Alte detalii', false, true)
  . '</section>'

  . '<div class="field" style="margin-top:2.2rem"><button type="submit" class="btn btn--primary btn--lg">Trimite detaliile</button></div>'
  . '</form>'
  . '<script>(function(){var f=document.querySelector(".cf form");if(!f)return;'
    . 'var flex=f.querySelector("input[name=termen_flexibil]"),dt=f.querySelector("input[name=termen]");'
    . 'function sync(){if(flex&&dt){dt.disabled=flex.checked;if(flex.checked)dt.value="";}}if(flex){flex.addEventListener("change",sync);sync();}'
    . 'var lh=document.getElementById("logo_have"),lm=document.getElementById("logo_make");'
    . 'function syncLogo(){var c=f.querySelector("input[name=brand_logo]:checked"),v=c?c.value:"";if(lh)lh.hidden=(v!=="Da, îl am");if(lm)lm.hidden=(v!=="Nu, îl creați voi");}'
    . 'Array.prototype.forEach.call(f.querySelectorAll("input[name=brand_logo]"),function(r){r.addEventListener("change",syncLogo);});syncLogo();'
    . 'var cb=document.getElementById("culori_box");'
    . 'function syncCul(){var c=f.querySelector("input[name=brand_culori]:checked"),v=c?c.value:"";if(cb)cb.hidden=(v!=="Da, le am");}'
    . 'Array.prototype.forEach.call(f.querySelectorAll("input[name=brand_culori]"),function(r){r.addEventListener("change",syncCul);});syncCul();'
    . 'Array.prototype.forEach.call(f.querySelectorAll(".color-row"),function(row){var ck=row.querySelector("input[type=checkbox]"),col=row.querySelector("input[type=color]");function s(){if(col)col.disabled=!ck.checked;}ck.addEventListener("change",s);s();});'
    . 'var sn=document.getElementById("social_none");'
    . 'function syncSocial(){var off=!!(sn&&sn.checked);Array.prototype.forEach.call(f.querySelectorAll(".social-row input"),function(i){i.disabled=off;if(off&&i.type==="checkbox")i.checked=false;});}'
    . 'if(sn)sn.addEventListener("change",syncSocial);syncSocial();'
    . 'Array.prototype.forEach.call(f.querySelectorAll(".social-row"),function(row){var ck=row.querySelector("input[type=checkbox]"),tx=row.querySelector("input[type=text]");function s(){if(tx)tx.required=ck.checked;}ck.addEventListener("change",s);s();});'
    . 'f.addEventListener("change",function(e){var fl=e.target.closest(".field");if(fl)fl.classList.remove("err-field");});'
    . 'f.addEventListener("submit",function(ev){var bad=null;function mark(el){if(!el)return;var fl=el.closest?el.closest(".field"):el;if(fl){fl.classList.add("err-field");if(!bad)bad=fl;}}'
    . 'var g=f.querySelectorAll(".choices[data-req-group]");for(var i=0;i<g.length;i++){if(!g[i].querySelector("input:checked"))mark(g[i]);}'
    . 'if(dt&&!(flex&&flex.checked)&&!dt.value)mark(dt);'
    . 'if(lm&&!lm.hidden){var ld=lm.querySelector("textarea");if(ld&&!ld.value.trim())mark(lm);}'
    . 'if(cb&&!cb.hidden){if(!cb.querySelector(".color-row input[type=checkbox]:checked"))mark(cb);}'
    . 'if(!(sn&&sn.checked)){var any=false,sbad=false;Array.prototype.forEach.call(f.querySelectorAll(".social-row"),function(row){var ck=row.querySelector("input[type=checkbox]"),tx=row.querySelector("input[type=text]");if(ck.checked){any=true;if(!tx.value.trim())sbad=true;}});if(!any||sbad)mark(sn?sn.closest(".field"):null);}'
    . 'if(bad){ev.preventDefault();bad.scrollIntoView({block:"center",behavior:"smooth"});}});'
    . '})();</script>';

pagina('Confirmă datele', $form);
