<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
require_login();

function logEvent($cid, $tip, $text) {
  db()->prepare('INSERT INTO evenimente (client_id, tip, text) VALUES (?, ?, ?)')->execute([$cid, $tip, $text]);
}

$id  = (int)($_GET['id'] ?? 0);
$err = '';
$ok  = isset($_GET['ok']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $nz = function ($v) { $v = is_string($v) ? trim($v) : $v; return ($v === '' || $v === null) ? null : $v; };

  $nume    = trim($_POST['nume'] ?? '');
  $firma   = $nz($_POST['firma'] ?? '');
  $email   = $nz($_POST['email'] ?? '');
  $telefon = $nz($_POST['telefon'] ?? '');
  $sursa   = $nz($_POST['sursa'] ?? '');
  $status  = $_POST['status'] ?? 'lead_nou'; if (!isset($STATUSES[$status])) $status = 'lead_nou';
  $plan    = $_POST['plan'] ?? '';           $plan = isset($PLANS[$plan]) ? $plan : null;
  $domeniu = $nz($_POST['domeniu'] ?? '');
  $dlead   = $nz($_POST['data_lead'] ?? '');
  $dlivr   = $nz($_POST['data_livrare'] ?? '');
  $dstart  = $nz($_POST['data_start_abonament'] ?? '');
  $plata   = $_POST['status_plata'] ?? 'neinceput'; if (!isset($PLATA[$plata])) $plata = 'neinceput';
  $obs     = $nz($_POST['observatii'] ?? '');
  $nota    = trim($_POST['nota'] ?? '');

  $dgratis = $dlivr  ? date('Y-m-d', strtotime($dlivr . ' +30 days'))   : null;
  $dmin6   = $dstart ? date('Y-m-d', strtotime($dstart . ' +6 months')) : null;
  $ziua    = $dstart ? (int)date('j', strtotime($dstart))               : null;

  if ($nume === '') {
    $err = 'Numele e obligatoriu.';
  } else {
    $vals = [$nume, $firma, $email, $telefon, $sursa, $status, $plan, $domeniu, $dlead, $dlivr, $dgratis, $dstart, $ziua, $dmin6, $plata, $obs];
    if ($id > 0) {
      $oldStatus = db()->prepare('SELECT status FROM clienti WHERE id=?');
      $oldStatus->execute([$id]);
      $oldStatus = $oldStatus->fetchColumn();
      $sql = 'UPDATE clienti SET nume=?,firma=?,email=?,telefon=?,sursa=?,status=?,plan=?,domeniu=?,data_lead=?,data_livrare=?,data_expira_gratis=?,data_start_abonament=?,ziua_reinnoire=?,data_expira_minim6=?,status_plata=?,observatii=? WHERE id=?';
      db()->prepare($sql)->execute(array_merge($vals, [$id]));
      if ($oldStatus !== $status) logEvent($id, 'schimbare_status', ($STATUSES[$oldStatus] ?? $oldStatus) . ' -> ' . $STATUSES[$status]);
    } else {
      $sql = 'INSERT INTO clienti (nume,firma,email,telefon,sursa,status,plan,domeniu,data_lead,data_livrare,data_expira_gratis,data_start_abonament,ziua_reinnoire,data_expira_minim6,status_plata,observatii) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
      db()->prepare($sql)->execute($vals);
      $id = (int)db()->lastInsertId();
      logEvent($id, 'creat', 'Client adăugat');
    }
    if ($nota !== '') logEvent($id, 'nota', $nota);
    redirect('client.php?id=' . $id . '&ok=1');
  }
}

if ($id > 0) {
  $st = db()->prepare('SELECT * FROM clienti WHERE id=?');
  $st->execute([$id]);
  $c = $st->fetch();
  if (!$c) { http_response_code(404); exit('Client inexistent.'); }
  $ev = db()->prepare('SELECT * FROM evenimente WHERE client_id=? ORDER BY data DESC');
  $ev->execute([$id]);
  $events = $ev->fetchAll();
} else {
  $c = ['nume' => '', 'firma' => '', 'email' => '', 'telefon' => '', 'sursa' => '', 'status' => 'lead_nou',
        'plan' => '', 'domeniu' => '', 'data_lead' => date('Y-m-d'), 'data_livrare' => '', 'data_start_abonament' => '',
        'status_plata' => 'neinceput', 'observatii' => '', 'data_expira_gratis' => '', 'data_expira_minim6' => '', 'ziua_reinnoire' => ''];
  $events = [];
}

head($id ? ($c['firma'] ?: $c['nume']) : 'Client nou');
?>
<p><a href="index.php">&larr; Înapoi la panou</a></p>
<?php if ($ok): ?><div class="ok">Salvat.</div><?php endif; ?>
<?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>

<div class="panel">
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="grid2">
      <div><label>Nume *</label><input name="nume" required value="<?= e($c['nume']) ?>"></div>
      <div><label>Firmă</label><input name="firma" value="<?= e($c['firma']) ?>"></div>
      <div><label>Email</label><input type="email" name="email" value="<?= e($c['email']) ?>"></div>
      <div><label>Telefon</label><input name="telefon" value="<?= e($c['telefon']) ?>"></div>
      <div><label>Sursă</label><input name="sursa" placeholder="formular / whatsapp / recomandare" value="<?= e($c['sursa']) ?>"></div>
      <div><label>Domeniu</label><input name="domeniu" value="<?= e($c['domeniu']) ?>"></div>
      <div>
        <label>Status</label>
        <select name="status">
          <?php foreach ($STATUSES as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= $c['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Plan</label>
        <select name="plan">
          <option value="">—</option>
          <?php foreach ($PLANS as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= $c['plan'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Data lead</label><input type="date" name="data_lead" value="<?= e($c['data_lead']) ?>"></div>
      <div><label>Data livrare</label><input type="date" name="data_livrare" value="<?= e($c['data_livrare']) ?>"></div>
      <div><label>Data start abonament</label><input type="date" name="data_start_abonament" value="<?= e($c['data_start_abonament']) ?>"></div>
      <div>
        <label>Status plată</label>
        <select name="status_plata">
          <?php foreach ($PLATA as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= $c['status_plata'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <label>Observații</label>
    <textarea name="observatii"><?= e($c['observatii']) ?></textarea>

    <label>Adaugă o notă în istoric</label>
    <input name="nota" placeholder="ex. discuție telefonică, a cerut ofertă...">

    <p style="margin-top:1rem"><button class="btn btn--primary" type="submit">Salvează</button></p>
  </form>

  <?php if ($id): ?>
    <p class="readonly">
      Luna gratis expiră: <strong><?= e($c['data_expira_gratis'] ?: '—') ?></strong> &nbsp;|&nbsp;
      Minim 6 luni până la: <strong><?= e($c['data_expira_minim6'] ?: '—') ?></strong> &nbsp;|&nbsp;
      Zi reînnoire: <strong><?= e($c['ziua_reinnoire'] ?: '—') ?></strong>
    </p>
  <?php endif; ?>
</div>

<?php if ($events): ?>
  <h2>Istoric</h2>
  <ul class="timeline">
    <?php foreach ($events as $ev): ?>
      <li>
        <div class="when"><?= e($ev['data']) ?> · <?= e($ev['tip']) ?></div>
        <div><?= nl2br(e($ev['text'])) ?></div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php foot(); ?>
