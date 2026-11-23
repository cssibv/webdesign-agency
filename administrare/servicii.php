<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/factura_lib.php';
require_login();
factura_init();

$err = '';
$ok = isset($_GET['ok']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $rows = $_POST['s'] ?? [];
  $upd = db()->prepare('UPDATE factura_servicii SET categorie=?, denumire=?, descriere=?, um=?, pret=?, ordine=?, activ=? WHERE id=?');
  $ins = db()->prepare('INSERT INTO factura_servicii (categorie, denumire, descriere, um, pret, ordine, activ) VALUES (?, ?, ?, ?, ?, ?, ?)');
  $del = db()->prepare('DELETE FROM factura_servicii WHERE id=?');

  db()->beginTransaction();
  foreach ($rows as $key => $r) {
    $den  = trim($r['denumire'] ?? '');
    $cat  = trim($r['categorie'] ?? '') ?: 'Altele';
    $desc = trim($r['descriere'] ?? '');
    $um   = trim($r['um'] ?? '') ?: 'buc';
    $pret = round((float) str_replace(',', '.', $r['pret'] ?? '0'), 2);
    $ord  = (int) ($r['ordine'] ?? 0);
    $act  = !empty($r['activ']) ? 1 : 0;
    $vals = [mb_substr($cat, 0, 80), mb_substr($den, 0, 160), $desc === '' ? null : mb_substr($desc, 0, 255), mb_substr($um, 0, 20), max(0, $pret), $ord, $act];

    if (ctype_digit((string) $key)) {
      if (!empty($r['sterge'])) { $del->execute([(int) $key]); continue; }
      if ($den === '') continue;
      $upd->execute(array_merge($vals, [(int) $key]));
    } elseif ($den !== '') {
      $ins->execute($vals);
    }
  }
  db()->commit();
  redirect('servicii.php?ok=1');
}

$grupe = factura_catalog(false);
$categorii = array_keys($grupe);
$maxOrd = 0;
foreach ($grupe as $items) foreach ($items as $s) $maxOrd = max($maxOrd, (int) $s['ordine']);

head('Servicii și prețuri');
?>
<style>
  .sv-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
  .sv-table th { text-align: left; font-size: .75rem; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); padding: .4rem .35rem; border-bottom: 1px solid var(--line); }
  .sv-table td { padding: .3rem .35rem; vertical-align: middle; }
  .sv-table input[type=text], .sv-table input[type=number] { margin: 0; padding: .4rem .5rem; font-size: .88rem; }
  .sv-table .cat-row td { padding-top: 1.1rem; font-weight: 700; color: #c24e0a; font-size: .8rem; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--line); }
  .sv-table .w-pret { width: 105px; } .sv-table .w-um { width: 70px; } .sv-table .w-ord { width: 70px; } .sv-table .w-chk { width: 54px; text-align: center; }
  .sv-table tr.inactiv td input[type=text] { color: #94a3b8; }
  .sv-new td { background: #f8fafc; }
  .sv-help { color: var(--muted); font-size: .85rem; margin: 0 0 1rem; }
  @media (max-width: 900px) { .sv-table .hide-sm { display: none; } }
</style>

<div class="page-head">
  <h1>Servicii și prețuri</h1>
  <a class="btn btn--primary" href="factura.php">Factură nouă</a>
</div>
<?php if ($ok): ?><div class="ok">Salvat. Prețurile noi apar imediat în formularul de factură.</div><?php endif; ?>

<div class="panel">
  <p class="sv-help">Modifici direct în tabel și apeși „Salvează”. Prețurile sunt fără TVA. Debifezi „Activ” ca să ascunzi un serviciu din formular fără să-l ștergi. „Ordine” stabilește poziția în listă.</p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <datalist id="cats"><?php foreach ($categorii as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
    <table class="sv-table">
      <thead>
        <tr><th>Denumire</th><th class="hide-sm">Descriere (pe factură)</th><th>Categorie</th><th class="w-um">U.M.</th><th class="w-pret">Preț (lei)</th><th class="w-ord hide-sm">Ordine</th><th class="w-chk">Activ</th><th class="w-chk">Șterge</th></tr>
      </thead>
      <tbody>
      <?php foreach ($grupe as $cat => $items): ?>
        <tr class="cat-row"><td colspan="8"><?= e($cat) ?></td></tr>
        <?php foreach ($items as $s): $k = (int) $s['id']; ?>
          <tr class="<?= $s['activ'] ? '' : 'inactiv' ?>">
            <td><input type="text" name="s[<?= $k ?>][denumire]" value="<?= e($s['denumire']) ?>" required></td>
            <td class="hide-sm"><input type="text" name="s[<?= $k ?>][descriere]" value="<?= e($s['descriere']) ?>"></td>
            <td><input type="text" name="s[<?= $k ?>][categorie]" value="<?= e($s['categorie']) ?>" list="cats"></td>
            <td class="w-um"><input type="text" name="s[<?= $k ?>][um]" value="<?= e($s['um']) ?>"></td>
            <td class="w-pret"><input type="number" step="0.01" min="0" name="s[<?= $k ?>][pret]" value="<?= e($s['pret']) ?>"></td>
            <td class="w-ord hide-sm"><input type="number" step="1" name="s[<?= $k ?>][ordine]" value="<?= (int) $s['ordine'] ?>"></td>
            <td class="w-chk"><input type="checkbox" name="s[<?= $k ?>][activ]" value="1" <?= $s['activ'] ? 'checked' : '' ?>></td>
            <td class="w-chk"><input type="checkbox" name="s[<?= $k ?>][sterge]" value="1"></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
        <tr class="cat-row"><td colspan="8">Servicii noi</td></tr>
        <?php for ($i = 1; $i <= 3; $i++): $k = 'n' . $i; ?>
          <tr class="sv-new">
            <td><input type="text" name="s[<?= $k ?>][denumire]" placeholder="Denumire serviciu nou"></td>
            <td class="hide-sm"><input type="text" name="s[<?= $k ?>][descriere]"></td>
            <td><input type="text" name="s[<?= $k ?>][categorie]" list="cats" placeholder="Categorie"></td>
            <td class="w-um"><input type="text" name="s[<?= $k ?>][um]" value="buc"></td>
            <td class="w-pret"><input type="number" step="0.01" min="0" name="s[<?= $k ?>][pret]" value="0"></td>
            <td class="w-ord hide-sm"><input type="number" step="1" name="s[<?= $k ?>][ordine]" value="<?= $maxOrd + $i * 10 ?>"></td>
            <td class="w-chk"><input type="checkbox" name="s[<?= $k ?>][activ]" value="1" checked></td>
            <td class="w-chk"></td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <div class="form-actions">
      <button class="btn btn--primary" type="submit">Salvează</button>
      <span class="sv-help" style="margin:0">Rândurile noi lăsate fără denumire sunt ignorate.</span>
    </div>
  </form>
</div>
<?php foot(); ?>
