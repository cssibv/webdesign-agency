<?php
ini_set('display_errors', '0'); // nu expune detalii de eroare vizitatorilor
header('Referrer-Policy: no-referrer'); // tokenul din URL să nu se scurgă prin Referer
require __DIR__ . '/administrare/db.php';
require __DIR__ . '/administrare/mailer.php';
require __DIR__ . '/administrare/throttle.php';
$cfg = require __DIR__ . '/administrare/private/config.php';

// Anti-sondare / anti-abuz pe IP (brute-force pe token e oricum imposibil — 256 biți).
if (rate_blocked('confirma_' . client_ip(), 60, 3600)) {
  http_response_code(429);
  exit('Prea multe cereri. Te rugăm încearcă mai târziu.');
}
rate_register('confirma_' . client_ip(), 3600);

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
// Curăță CRLF din valorile ce ajung în header-e de email (anti header injection).
function hdr($s) { return trim(preg_replace('/[\r\n]+/', ' ', (string)$s)); }

function pagina($titlu, $continut) {
  echo '<!DOCTYPE html><html lang="ro"><head><meta charset="UTF-8">';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
  echo '<meta name="robots" content="noindex, nofollow">';
  echo '<meta name="referrer" content="no-referrer">';
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
     . '.color-list{display:flex;flex-direction:column;gap:.5rem;margin:.2rem 0 .7rem}'
     . '.color-slot{display:flex;align-items:center;gap:.7rem}'
     . '.color-slot input[type=color]{width:54px;height:40px;border:1px solid var(--c-border);border-radius:10px;background:none;cursor:pointer;padding:3px}'
     . '.color-slot .hex{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.95rem;color:var(--c-text-soft);letter-spacing:.03em}'
     . '.color-del{margin-left:auto;width:32px;height:32px;border:1px solid var(--c-border);background:var(--c-surface);color:var(--c-text-soft);border-radius:8px;font-size:1.15rem;line-height:1;cursor:pointer}'
     . '.color-del:hover{border-color:#e8a39a;color:#c0392b}'
     . '.color-edit{border:1px solid var(--c-border);background:var(--c-surface);color:var(--c-teal-dark);font-weight:600;font-size:.85rem;padding:.42rem .85rem;border-radius:8px;cursor:pointer}'
     . '.color-edit:hover{border-color:var(--c-teal);color:var(--c-teal)}'
     . '.color-add{display:inline-flex;align-items:center;gap:.3rem;background:none;border:1px dashed var(--c-border);color:var(--c-teal-dark);font-weight:600;font-size:.92rem;padding:.5rem .95rem;border-radius:999px;cursor:pointer}'
     . '.color-add:hover{border-color:var(--c-teal);color:var(--c-teal)}'
     . '.color-add:disabled{opacity:.4;cursor:not-allowed;border-color:var(--c-border);color:var(--c-text-soft)}'
     . '.char-counter{font-size:.82rem;color:var(--c-text-soft);text-align:right;margin:.3rem 0 0}'
     . '.char-counter.is-max{color:#c0392b;font-weight:600}'
     . '.social-inputs{display:flex;flex-direction:column;gap:.6rem;margin-top:.85rem}'
     . '.social-in{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem}'
     . '.social-in[hidden]{display:none}'
     . '.social-in .field__label{min-width:120px;margin:0}'
     . '.social-in input{flex:1;min-width:200px;padding:.55rem .8rem;border:1px solid var(--c-border);border-radius:var(--radius-sm);font:inherit;background:var(--c-bg-alt);color:var(--c-text)}'
     . '.field.err-field .color-list,.field.err-field .social-inputs,.field.err-field .choices{outline:2px dashed #e8a39a;outline-offset:6px;border-radius:12px}'
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
function f_text($name, $label, $req, $textarea = false, $ph = '', $maxlen = null) {
  $mark = $req ? ' <span class="req">*</span>' : ' <span class="hint">(opțional)</span>';
  $r = $req ? ' required' : '';
  $p = $ph !== '' ? ' placeholder="' . e($ph) . '"' : '';
  $m = $maxlen ? ' maxlength="' . (int)$maxlen . '"' : '';
  $h = '<div class="field"><label class="field__label" for="f_' . e($name) . '">' . e($label) . $mark . '</label>';
  if ($textarea) {
    $h .= '<textarea id="f_' . e($name) . '" name="' . e($name) . '"' . $r . $p . $m . '>' . old_v($name) . '</textarea>';
  } else {
    $h .= '<input type="text" id="f_' . e($name) . '" name="' . e($name) . '" value="' . old_v($name) . '"' . $r . $p . $m . '>';
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

// Slot de culoare: pătrat cu pipetă + hex + buton de ștergere
function color_slot($hex) {
  $hex = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$hex) ? strtoupper($hex) : '#1A4D8F';
  return '<div class="color-slot">'
    . '<input type="color" name="culoare[]" value="' . e($hex) . '">'
    . '<span class="hex">' . e($hex) . '</span>'
    . '<button type="button" class="color-edit">Schimbă</button>'
    . '<button type="button" class="color-del" aria-label="Șterge culoarea">&times;</button>'
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
      $labels = ['Principală', 'Secundară'];
      $i = 0;
      foreach ((array)($_POST['culoare'] ?? []) as $hex) {
        if ($i >= 5) break;
        $hex = (string)$hex;
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
          $pal[] = ($labels[$i] ?? ('Culoarea ' . ($i + 1))) . ' ' . strtoupper($hex);
          $i++;
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
  $ct = $nz($_POST['contact_telefon'] ?? '');
  $ca = $nz($_POST['contact_adresa'] ?? '');
  $cprog = $nz($_POST['contact_program'] ?? '');
  $cl = [];
  if ($ct !== null)    $cl[] = 'Telefon: ' . $ct;
  if ($ca !== null)    $cl[] = 'Adresă: ' . $ca;
  if ($cprog !== null) $cl[] = 'Program: ' . $cprog;
  $contact = $cl ? implode("\n", $cl) : null;
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
    'termen'             => 'Aproximativ 3-5 zile lucrătoare',
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
  foreach (['domeniu_activitate','servicii','domeniu_dorit','contact_telefon','contact_adresa','contact_program','plan_vizat','brand_logo','brand_culori','cont_texte','cont_poze'] as $k) {
    if ($nz($_POST[$k] ?? '') === null) return false;
  }
  foreach (['public_tinta','scop','pagini'] as $k) {
    if (!$hasMulti($k)) return false;
  }

  // Telefon: format valid (aceeași regulă ca pe site)
  $tel = preg_replace('/\s+/', '', (string)($_POST['contact_telefon'] ?? ''));
  if (!preg_match('/^(0\d{9}|(\+|00)\d{8,15})$/', $tel)) return false;

  // Logo: dacă îl creăm noi, descrierea e obligatorie
  if (($_POST['brand_logo'] ?? '') === 'Nu, îl creați voi' && $nz($_POST['logo_descriere'] ?? '') === null) return false;
  // Culori: dacă „le am", minim 2 culori valide (ca în UI)
  if (($_POST['brand_culori'] ?? '') === 'Da, le am') {
    $n = 0;
    foreach ((array)($_POST['culoare'] ?? []) as $hex) {
      if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$hex)) $n++;
    }
    if ($n < 2) return false;
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
    try {
      db()->beginTransaction();
      db()->prepare($sql)->execute($row);
      db()->prepare('INSERT INTO evenimente (client_id, tip, text) VALUES (?, ?, ?)')->execute([$cid, 'brief', 'A completat chestionarul de brief']);
      db()->prepare('UPDATE clienti SET token_confirmare = NULL, token_expira = NULL WHERE id = ?')->execute([$cid]); // tokenul nu mai e folosibil după ce brief-ul e completat
      db()->commit();
    } catch (Throwable $ex) {
      if (db()->inTransaction()) db()->rollBack();
      mail_log("Salvare brief EȘUATĂ pentru lead #$cid (" . $client['email'] . "): " . $ex->getMessage());
      $briefError = 'A apărut o eroare la salvare. Datele tale nu s-au pierdut — încearcă din nou peste un minut sau scrie-ne pe WhatsApp.';
    }
  }
  if ($briefError === '') {
    $to = $cfg['notify_email'] ?? 'contact@smart-web.ro';
    $base = rtrim($cfg['base_url'] ?? 'https://smart-web.ro', '/');
    $briefInner = email_h('Brief completat')
      . email_p('Clientul <strong>' . email_esc($client['nume']) . '</strong> a completat chestionarul.')
      . email_button('Vezi în panou &rarr;', $base . '/administrare/index.php');
    if (!smtp_send($cfg, $to, '[SmartWeb] Brief completat: ' . hdr($client['nume']), 'Clientul ' . $client['nume'] . ' a completat chestionarul. Vezi detaliile în panou.', '', email_layout($cfg, $briefInner))) {
      mail_log("Notificare brief EȘUATĂ pentru lead #$cid. Brief salvat — verifică panoul.");
    }
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
$errHtml = $briefError !== '' ? '<div class="err">' . e($briefError) . '</div>' : '';

$termenField = '<div class="field"><span class="field__label">Cât durează realizarea?</span>'
  . '<p class="field__hint">După ce avem toate detaliile despre cum ți-ai dori să arate site-ul (texte, imagini și clipuri video), îl realizăm în aproximativ <strong>3-5 zile lucrătoare</strong>. Îți confirmăm termenul exact când pornim.</p>'
  . '</div>';

// Sloturi de culoare (din POST la reîncărcare după eroare, altfel două implicite). Min 2, max 5.
$initColors = [];
if (isset($_POST['brand_culori'])) {
  foreach ((array)($_POST['culoare'] ?? []) as $hx) {
    if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$hx)) $initColors[] = strtoupper($hx);
  }
}
$initColors = array_slice($initColors, 0, 5);
while (count($initColors) < 2) $initColors[] = count($initColors) ? '#FF7A45' : '#1A4D8F';
$colorSlots = '';
foreach ($initColors as $hx) $colorSlots .= color_slot($hx);

// Pastile + câmpuri pentru social media
$SOCIAL = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'google' => 'Google Business'];
$selSocial = (array)($_POST['social'] ?? []);
$socialPills = '';
$socialInputs = '';
foreach ($SOCIAL as $k => $lbl) {
  $on = in_array($k, $selSocial, true);
  $socialPills .= '<label class="choice"><input type="checkbox" class="social-pill" name="social[]" value="' . e($k) . '"' . ($on ? ' checked' : '') . '><span class="choice__b">' . e($lbl) . '</span></label>';
  $socialInputs .= '<div class="social-in" id="socin_' . e($k) . '"' . ($on ? '' : ' hidden') . '>'
    . '<label class="field__label" for="soch_' . e($k) . '">' . e($lbl) . '</label>'
    . '<input type="text" id="soch_' . e($k) . '" name="social_h[' . e($k) . ']" value="' . e($_POST['social_h'][$k] ?? '') . '" maxlength="120" placeholder="@nume sau link"' . ($on ? '' : ' disabled') . '>'
    . '</div>';
}

$form = $errHtml
  . '<h2>Adresă confirmată ✓</h2>'
  . '<p>Mulțumim, ' . $nume . '! Ca să-ți pregătim site-ul mai repede, completează detaliile de mai jos. Câmpurile cu <span class="req">*</span> sunt obligatorii.</p>'
  . '<form method="post">'
  . '<input type="hidden" name="brief" value="1">'
  . '<input type="hidden" name="token" value="' . $tok . '">'
  . '<div class="hp"><label>Nu completa</label><input name="_gotcha" tabindex="-1" autocomplete="off"></div>'

  . '<section class="grp"><h3 class="grp__title">Despre afacerea ta</h3>'
  . f_text('domeniu_activitate', 'Cu ce se ocupă firma ta?', true, false, 'ex: cabinet stomatologic, firmă de instalații...', 120)
  . f_text('servicii', 'Ce servicii sau produse oferi? (cele mai importante de promovat)', true, true, '', 300)
  . f_pills('public_tinta', 'Cine e clientul tău ideal?', $PUBLIC, true, 'Poți alege mai multe.')
  . f_text('referinte', 'Site-uri care îți plac sau concurenți (linkuri)', false, true, '', 300)
  . '</section>'

  . '<section class="grp"><h3 class="grp__title">Conținut și identitate vizuală</h3>'
  . f_pills('brand_logo', 'Ai un logo?', ['Da, îl am', 'Nu, îl creați voi'], false)
  . '<div class="field cond" id="logo_have"' . ((($_POST['brand_logo'] ?? '') === 'Da, îl am') ? '' : ' hidden') . '>'
    . '<label class="field__label" for="f_logo_link">Trimite-ne logo-ul</label>'
    . '<span class="field__hint">Pune un link (Google Drive, site etc.) sau scrie „îl trimit pe email la contact@smart-web.ro".</span>'
    . '<input type="text" id="f_logo_link" name="logo_link" value="' . old_v('logo_link') . '" maxlength="200" placeholder="link către logo sau «îl trimit pe email»">'
  . '</div>'
  . '<div class="field cond" id="logo_make"' . ((($_POST['brand_logo'] ?? '') === 'Nu, îl creați voi') ? '' : ' hidden') . '>'
    . '<label class="field__label" for="f_logo_descriere">Cum ți-ai dori să arate logo-ul? <span class="req">*</span></label>'
    . '<span class="field__hint">Descrie pe scurt: stil (modern, minimalist, clasic), simboluri sau elemente, ce mesaj să transmită, eventual culori preferate.</span>'
    . '<textarea id="f_logo_descriere" name="logo_descriere" maxlength="400">' . old_v('logo_descriere') . '</textarea>'
  . '</div>'
  . f_pills('brand_culori', 'Ai culori sau identitate de brand?', ['Da, le am', 'Nu, alegeți voi'], false)
  . '<div class="field cond" id="culori_box"' . ((($_POST['brand_culori'] ?? '') === 'Da, le am') ? '' : ' hidden') . '>'
    . '<span class="field__label">Culorile tale de brand <span class="hint">(2–5 culori)</span></span>'
    . '<span class="field__hint">Apasă „Schimbă" ca să alegi fiecare culoare. Poți adăuga până la 5 și șterge cu ×.</span>'
    . '<div class="color-list" id="color_list">' . $colorSlots . '</div>'
    . '<button type="button" class="color-add" id="color_add">+ adaugă o culoare</button>'
    . '<input type="text" name="culori_note" value="' . old_v('culori_note') . '" maxlength="200" placeholder="Note (opțional): unde le folosești, nume de culori..." style="margin-top:.8rem;width:100%">'
  . '</div>'
  . f_pills('cont_texte', 'Ai textele pentru site?', ['Da, le am', 'Nu, mă ajutați voi'], false)
  . f_pills('cont_poze', 'Ai pozele sau imaginile?', ['Da, le am', 'Nu, mă ajutați voi'], false)
  . '</section>'

  . '<section class="grp"><h3 class="grp__title">Site-ul dorit</h3>'
  . f_pills('scop', 'Ce vrei să obții cu site-ul?', $SCOP, true, 'Poți alege mai multe.')
  . f_pills('pagini', 'Ce pagini vrei pe site?', $PAGINI, true, 'Poți alege mai multe.')
  . f_text('domeniu_dorit', 'Ce nume de domeniu ți-ar plăcea?', true, false, 'ex: firma-mea.ro', 80)
  . '<div class="field"><label class="field__label" for="f_contact_telefon">Telefon <span class="req">*</span></label>'
    . '<input type="tel" id="f_contact_telefon" name="contact_telefon" value="' . old_v('contact_telefon') . '" maxlength="20" inputmode="tel" required pattern="0\d{9}|(\+|00)\d{8,15}" title="ex: 0712345678 sau +40712345678" placeholder="ex: 0712345678 sau +40712345678">'
  . '</div>'
  . f_text('contact_adresa', 'Adresă', true, false, 'ex: Str. Lungă nr. 1, Brașov', 120)
  . f_text('contact_program', 'Program de lucru', true, false, 'ex: Luni–Vineri 9–17, Sâmbătă 9–13', 120)
  . '<div class="field"><span class="field__label">Conturi de social media</span>'
    . '<span class="field__hint">Apasă rețelele pe care le ai și scrie link-ul. Dacă nu ai încă, apasă „Nu am conturi încă".</span>'
    . '<div class="choices">' . $socialPills
      . '<label class="choice"><input type="checkbox" id="social_none" name="social_none" value="1"' . (!empty($_POST['social_none']) ? ' checked' : '') . '><span class="choice__b">Nu am conturi încă</span></label>'
    . '</div>'
    . '<div class="social-inputs">' . $socialInputs . '</div>'
  . '</div>'
  . '</section>'

  . '<section class="grp"><h3 class="grp__title">Livrare și plan</h3>'
  . $termenField
  . f_pills('plan_vizat', 'Ce plan ai în vedere?', $PLANURI, false)
  . f_text('alte_detalii', 'Alte detalii', false, true, '', 400)
  . '</section>'

  . '<div class="field" style="margin-top:2.2rem"><button type="submit" class="btn btn--primary btn--lg">Trimite detaliile</button></div>'
  . '</form>'
  . '<script>(function(){var f=document.querySelector(".cf form");if(!f)return;'
    . 'var lh=document.getElementById("logo_have"),lm=document.getElementById("logo_make");'
    . 'function syncLogo(){var c=f.querySelector("input[name=brand_logo]:checked"),v=c?c.value:"";if(lh)lh.hidden=(v!=="Da, îl am");if(lm)lm.hidden=(v!=="Nu, îl creați voi");}'
    . 'Array.prototype.forEach.call(f.querySelectorAll("input[name=brand_logo]"),function(r){r.addEventListener("change",syncLogo);});syncLogo();'
    . 'var cb=document.getElementById("culori_box");'
    . 'function syncCul(){var c=f.querySelector("input[name=brand_culori]:checked"),v=c?c.value:"";if(cb)cb.hidden=(v!=="Da, le am");}'
    . 'Array.prototype.forEach.call(f.querySelectorAll("input[name=brand_culori]"),function(r){r.addEventListener("change",syncCul);});syncCul();'
    . 'var clist=document.getElementById("color_list"),cadd=document.getElementById("color_add");'
    . 'function colorState(){if(!clist)return;var n=clist.querySelectorAll(".color-slot").length;if(cadd)cadd.disabled=(n>=5);Array.prototype.forEach.call(clist.querySelectorAll(".color-del"),function(b){b.style.display=(n<=2?"none":"");});}'
    . 'if(clist){clist.addEventListener("input",function(e){if(e.target.type==="color"){var h=e.target.parentNode.querySelector(".hex");if(h)h.textContent=e.target.value.toUpperCase();}});'
    . 'clist.addEventListener("click",function(e){var ed=e.target.closest?e.target.closest(".color-edit"):null;if(ed){var ci=ed.parentNode.querySelector("input[type=color]");if(ci)ci.click();return;}var b=e.target.closest?e.target.closest(".color-del"):null;if(b&&clist.querySelectorAll(".color-slot").length>2){b.parentNode.remove();colorState();}});}'
    . 'if(cadd&&clist){cadd.addEventListener("click",function(){if(clist.querySelectorAll(".color-slot").length>=5)return;var d=document.createElement("div");d.className="color-slot";d.innerHTML="<input type=\\"color\\" name=\\"culoare[]\\" value=\\"#1AA9A0\\"><span class=\\"hex\\">#1AA9A0</span><button type=\\"button\\" class=\\"color-edit\\">Schimbă</button><button type=\\"button\\" class=\\"color-del\\" aria-label=\\"Sterge\\">&times;</button>";clist.appendChild(d);colorState();});}'
    . 'colorState();'
    . 'var sn=document.getElementById("social_none");'
    . 'var pills=f.querySelectorAll(".social-pill");'
    . 'function showInput(key,on){var row=document.getElementById("socin_"+key);if(!row)return;var inp=row.querySelector("input");row.hidden=!on;if(inp)inp.disabled=!on;}'
    . 'function socialNone(on){Array.prototype.forEach.call(pills,function(ck){if(on){ck.checked=false;showInput(ck.value,false);}});}'
    . 'Array.prototype.forEach.call(pills,function(ck){ck.addEventListener("change",function(){if(ck.checked&&sn&&sn.checked)sn.checked=false;showInput(ck.value,ck.checked);if(ck.checked){var row=document.getElementById("socin_"+ck.value);var inp=row&&row.querySelector("input");if(inp)inp.focus();}});});'
    . 'if(sn)sn.addEventListener("change",function(){socialNone(sn.checked);});'
    . 'Array.prototype.forEach.call(pills,function(ck){showInput(ck.value,ck.checked);});if(sn&&sn.checked)socialNone(true);'
    . 'Array.prototype.forEach.call(f.querySelectorAll("textarea[maxlength]"),function(t){var max=t.getAttribute("maxlength");var c=document.createElement("p");c.className="char-counter";function upd(){c.textContent=t.value.length+"/"+max;c.className="char-counter"+(t.value.length>=(max-0)?" is-max":"");}t.parentNode.appendChild(c);t.addEventListener("input",upd);upd();});'
    . 'f.addEventListener("change",function(e){var fl=e.target.closest(".field");if(fl)fl.classList.remove("err-field");});'
    . 'f.addEventListener("submit",function(ev){var bad=null;function mark(el){if(!el)return;var fl=el.closest?el.closest(".field"):el;if(fl){fl.classList.add("err-field");if(!bad)bad=fl;}}'
    . 'var g=f.querySelectorAll(".choices[data-req-group]");for(var i=0;i<g.length;i++){if(!g[i].querySelector("input:checked"))mark(g[i]);}'
    . 'if(lm&&!lm.hidden){var ld=lm.querySelector("textarea");if(ld&&!ld.value.trim())mark(lm);}'
    . 'if(cb&&!cb.hidden){if(!clist||!clist.querySelector(".color-slot"))mark(cb);}'
    . 'if(!(sn&&sn.checked)){var any=false,sbad=false;Array.prototype.forEach.call(pills,function(ck){if(ck.checked){any=true;var row=document.getElementById("socin_"+ck.value);var inp=row&&row.querySelector("input");if(!inp||!inp.value.trim())sbad=true;}});if(!any||sbad)mark(sn);}'
    . 'if(bad){ev.preventDefault();bad.scrollIntoView({block:"center",behavior:"smooth"});}});'
    . '})();</script>';

pagina('Confirmă datele', $form);
