<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/factura_lib.php';
require_login();
factura_init();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  header('Content-Type: application/json; charset=utf-8');
  $action = $_POST['action'] ?? '';
  if ($action === 'firma') {
    $firma = json_decode($_POST['date'] ?? '', true);
    if (!is_array($firma)) { http_response_code(400); echo json_encode(['ok' => false]); exit; }
    $curat = [];
    foreach ($firma as $k => $v) {
      if (preg_match('/^f_[a-z]+$/', $k) && is_string($v)) $curat[$k] = mb_substr(trim($v), 0, 200);
    }
    factura_salveaza_setare('firma', json_encode($curat, JSON_UNESCAPED_UNICODE));
    echo json_encode(['ok' => true]);
    exit;
  }
  if ($action === 'numar') {
    factura_salveaza_setare('ultima_serie', mb_substr(trim($_POST['serie'] ?? ''), 0, 20));
    factura_salveaza_setare('ultimul_numar', mb_substr(trim($_POST['numar'] ?? ''), 0, 20));
    echo json_encode(['ok' => true]);
    exit;
  }
  http_response_code(400);
  echo json_encode(['ok' => false]);
  exit;
}

// Next number: last used + 1, keeping the zero padding (0007 -> 0008).
$ultim = (string) factura_setare('ultimul_numar', '');
$urmator = '0001';
if (preg_match('/^(.*?)(\d+)$/', $ultim, $m)) {
  $urmator = $m[1] . str_pad((string) ((int) $m[2] + 1), strlen($m[2]), '0', STR_PAD_LEFT);
}

$date = [
  'catalog'  => factura_catalog(),
  'firma'    => json_decode((string) factura_setare('firma', '{}'), true) ?: new stdClass(),
  'serie'    => (string) factura_setare('ultima_serie', 'SW') ?: 'SW',
  'numar'    => $urmator,
  'ultim'    => $ultim,
  'clienti'  => db()->query("SELECT id, nume, firma, email FROM clienti WHERE status <> 'pierdut_arhivat' ORDER BY COALESCE(firma, nume)")->fetchAll(),
  'csrf'     => csrf_token(),
];

head('Factură nouă');
?>
<link rel="stylesheet" href="factura.css?v=<?= filemtime(__DIR__ . '/factura.css') ?>">
<div class="fx">

<aside class="fx-form">
  <div class="fx-head">
    <h1>Factură nouă</h1>
    <a class="fx-link" href="servicii.php">Servicii și prețuri</a>
  </div>
  <p class="fx-sub">Completezi în stânga, factura se actualizează în dreapta.</p>

  <details id="secFirma">
    <summary>1. Firma emitentă</summary>
    <div class="fx-fields">
      <label class="full">Denumire<input data-k="f_nume" placeholder="[DENUMIRE FIRMĂ] S.R.L."></label>
      <label>CUI / cod TVA<input data-k="f_cui"></label>
      <label>Reg. Com.<input data-k="f_rc"></label>
      <label>Capital social<input data-k="f_capital" placeholder="obligatoriu la SRL; gol la PFA"></label>
      <label>Telefon<input data-k="f_tel"></label>
      <label class="full">Sediu<input data-k="f_sediu"></label>
      <label class="full">E-mail<input data-k="f_email"></label>
      <label class="full">IBAN<input data-k="f_iban"></label>
      <label>Banca<input data-k="f_banca"></label>
      <label>Regim TVA
        <select data-k="f_tva">
          <option value="21">Plătitor TVA (21%)</option>
          <option value="0">Neplătitor TVA</option>
        </select>
      </label>
      <div class="full fx-row">
        <button class="fx-btn fx-btn--ghost" id="saveFirma" type="button">Salvează datele firmei</button>
        <span class="fx-msg" id="firmaMsg"></span>
      </div>
    </div>
  </details>

  <details open>
    <summary>2. Client</summary>
    <div class="fx-fields">
      <label class="full">Alege din clienți
        <select id="pickClient"><option value="">Completez de mână</option></select>
      </label>
      <label class="full">Tip client
        <select data-k="c_tip">
          <option value="pj">Persoană juridică (firmă, PFA, ONG)</option>
          <option value="pf">Persoană fizică</option>
        </select>
      </label>
      <label class="full"><span id="lblNume">Denumire</span><input data-k="c_nume"></label>
      <label class="pj-only">CUI<input data-k="c_cui"></label>
      <label class="pj-only">Reg. Com.<input data-k="c_rc"></label>
      <label class="full pf-only" hidden>CNP (opțional)<input data-k="c_cnp" inputmode="numeric" maxlength="13"></label>
      <label class="full"><span id="lblSediu">Sediu</span><input data-k="c_sediu"></label>
      <label class="full">E-mail<input data-k="c_email"></label>
      <p class="fx-hint pf-only" hidden>La persoana fizică sunt obligatorii numele și adresa. CNP-ul nu e obligatoriu.</p>
    </div>
  </details>

  <details open>
    <summary>3. Date factură</summary>
    <div class="fx-fields">
      <label>Serie<input data-k="serie"></label>
      <label>Număr<input data-k="numar"></label>
      <label>Data emiterii<input type="date" data-k="data"></label>
      <label>Termen de plată<input type="date" data-k="termen"></label>
      <label class="full">Perioada serviciilor (opțional)<input data-k="perioada" placeholder="ex. noiembrie 2026"></label>
      <label class="full">Notă pentru client (opțional)<textarea data-k="nota" rows="2"></textarea></label>
      <p class="fx-hint" id="ultimHint"></p>
    </div>
  </details>

  <details open>
    <summary>4. Servicii (bifează din listă)</summary>
    <div class="fx-catalog" id="catalog"></div>
  </details>

  <details open>
    <summary>5. Pe factură <span id="count" class="fx-count"></span></summary>
    <div class="fx-lines" id="lines"></div>
    <div class="fx-row fx-pad">
      <button class="fx-btn fx-btn--ghost" id="addCustom" type="button">+ Serviciu personalizat</button>
      <button class="fx-btn fx-btn--ghost" id="reset" type="button">Golește</button>
    </div>
  </details>

  <button class="fx-btn fx-btn--primary" id="print" type="button">Salvează PDF / Printează</button>
</aside>

<section class="fx-preview">
  <div class="inv" id="page">
    <header>
      <div class="brand">
        <img src="logo-factura.svg" alt="Smart Web">
        <span>Web design, SEO local și promovare online</span>
      </div>
      <div class="doc">
        <h2>FACTURĂ</h2>
        <div class="nr">Seria <span data-v="serie"></span> nr. <span data-v="numar"></span></div>
        <table>
          <tr><td>Data emiterii</td><td data-v="data_fmt"></td></tr>
          <tr id="rowPer"><td>Perioada serviciilor</td><td data-v="perioada"></td></tr>
          <tr><td>Termen de plată</td><td data-v="termen_fmt"></td></tr>
        </table>
      </div>
    </header>

    <section class="parties">
      <div class="party">
        <h3>Furnizor</h3>
        <div class="pname" data-v="f_nume"></div>
        <dl>
          <dt>CUI</dt><dd data-v="f_cui"></dd>
          <dt>Reg. Com.</dt><dd data-v="f_rc"></dd>
          <dt>Capital social</dt><dd data-v="f_capital"></dd>
          <dt>Sediu</dt><dd data-v="f_sediu"></dd>
          <dt>E-mail</dt><dd data-v="f_email"></dd>
          <dt>Telefon</dt><dd data-v="f_tel"></dd>
        </dl>
      </div>
      <div class="party client">
        <h3>Client</h3>
        <div class="pname" data-v="c_nume"></div>
        <dl id="clientDl"></dl>
      </div>
    </section>

    <table class="items">
      <thead>
        <tr><th>Nr.</th><th>Denumire serviciu</th><th>U.M.</th><th>Cant.</th><th>Preț unitar<br><span class="vatcol">fără TVA</span></th><th>Valoare<br><span class="vatcol">fără TVA</span></th><th class="vatcol">TVA<br><span data-v="tva_pct"></span></th></tr>
      </thead>
      <tbody id="itemsBody"></tbody>
    </table>

    <section class="bottom">
      <div class="pay">
        <h3>Plată</h3>
        <dl>
          <dt>IBAN</dt><dd data-v="f_iban"></dd>
          <dt>Banca</dt><dd data-v="f_banca"></dd>
          <dt>Detalii plată</dt><dd>Factura <span data-v="serie"></span> <span data-v="numar"></span></dd>
        </dl>
      </div>
      <table class="totals">
        <tr class="vatcol"><td>Total fără TVA</td><td data-v="t_net"></td></tr>
        <tr class="vatcol"><td>TVA <span data-v="tva_pct"></span></td><td data-v="t_tva"></td></tr>
        <tr class="grand"><td>Total de plată</td><td data-v="t_total"></td></tr>
      </table>
    </section>

    <div class="clientnote" data-v="nota"></div>
    <section class="notes" id="notes"></section>
    <footer><span>smart-web.ro</span><span>Pagina 1 din 1</span></footer>
  </div>
</section>

</div>
<script>window.FACTURA = <?= json_encode($date, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="factura.js?v=<?= filemtime(__DIR__ . '/factura.js') ?>"></script>
<?php foot(); ?>
