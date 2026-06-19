<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
require_login();

$today    = date('Y-m-d');
$cutoff24 = date('Y-m-d H:i:s', time() - 86400);
$in5      = date('Y-m-d', time() + 5 * 86400);

function q($sql, $params = []) {
  $st = db()->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}

$a_leads  = q("SELECT id,nume,firma FROM clienti WHERE status='lead_nou' AND creat_la < ? ORDER BY creat_la", [$cutoff24]);
$a_gratis = q("SELECT id,nume,firma FROM clienti WHERE status='livrat_luna_gratis' AND data_expira_gratis IS NOT NULL AND data_expira_gratis <= ? ORDER BY data_expira_gratis", [$in5]);
$a_minim6 = q("SELECT id,nume,firma FROM clienti WHERE status='contract_semnat' AND data_expira_minim6 IS NOT NULL AND data_expira_minim6 <= ?", [$today]);
$a_plata  = q("SELECT id,nume,firma FROM clienti WHERE status='contract_semnat' AND status_plata='restant'");

$rows = db()->query("SELECT id,nume,firma,status,plan FROM clienti ORDER BY actualizat_la DESC")->fetchAll();
$byStatus = [];
foreach ($STATUSES as $k => $v) $byStatus[$k] = [];
foreach ($rows as $r) $byStatus[$r['status']][] = $r;

function alertBox($titlu, $list) {
  if (!$list) return;
  echo '<div class="alert"><strong>' . e($titlu) . ' (' . count($list) . ')</strong>';
  foreach ($list as $r) {
    $nume = $r['firma'] ?: $r['nume'];
    echo '<a href="client.php?id=' . (int)$r['id'] . '">' . e($nume) . '</a>';
  }
  echo '</div>';
}

head('Panou');
?>
<div class="page-head">
  <h1>Clienți</h1>
  <a class="btn btn--primary" href="client.php?id=0">+ Client nou</a>
</div>

<div class="alerts">
  <?php
    alertBox('⏰ Luna gratis expiră curând', $a_gratis);
    alertBox('💳 Plată restantă', $a_plata);
    alertBox('🔔 Minim 6 luni atins', $a_minim6);
    alertBox('⚠️ Lead-uri necontactate (>24h)', $a_leads);
  ?>
</div>

<div class="board">
  <?php foreach ($STATUSES as $key => $label): ?>
    <div class="col col--<?= e($key) ?>">
      <div class="col__head"><span><?= e($label) ?></span><span class="count"><?= count($byStatus[$key]) ?></span></div>
      <?php foreach ($byStatus[$key] as $r): $nume = $r['firma'] ?: $r['nume']; ?>
        <a class="card" href="client.php?id=<?= (int)$r['id'] ?>">
          <span class="firma"><?= e($nume) ?></span>
          <?php if ($r['plan']): ?><span class="meta"><?= e($PLANS[$r['plan']] ?? $r['plan']) ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <?php if (!$byStatus[$key]): ?><div class="meta" style="color:#9aa7b4">—</div><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php foot(); ?>
