<?php
$STATUSES = [
  'lead_nou'             => 'Lead nou',
  'in_discutie'          => 'În discuție',
  'acceptat'             => 'Acceptat',
  'in_constructie'       => 'În construcție',
  'livrat_luna_gratis'   => 'Livrat (luna gratis)',
  'decizie_dupa_gratis'  => 'Decizie după luna gratis',
  'contract_semnat'      => 'Contract semnat',
  'pierdut_arhivat'      => 'Pierdut',
];
$PLANS = ['start' => 'Start', 'business' => 'Business', 'pro' => 'Pro'];
$PLATA = ['neinceput' => 'Neînceput', 'la_zi' => 'La zi', 'restant' => 'Restant'];

function val($v, $bold = true) {
  $v = trim((string)$v);
  if ($v === '') return '<span class="unset">nestabilit</span>';
  return $bold ? '<strong>' . e($v) . '</strong>' : e($v);
}

function head($title) {
  echo '<!DOCTYPE html><html lang="ro"><head><meta charset="UTF-8">';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
  echo '<meta name="robots" content="noindex, nofollow">';
  echo '<title>' . e($title) . ' · Smart Web Admin</title>';
  echo '<link rel="icon" type="image/svg+xml" href="../assets/img/favicon.svg">';
  echo '<link rel="stylesheet" href="../assets/fonts/fonts.css">';
  echo '<link rel="stylesheet" href="admin.css?v=' . filemtime(__DIR__ . '/admin.css') . '"></head><body>';
  echo '<header class="topbar"><a class="topbar__logo" href="index.php">Smart <span>Web</span> <small>Admin</small></a>';
  echo '<div class="topbar__right"><span class="topbar__user">' . e(current_user()) . '</span>';
  echo '<a class="btn btn--ghost btn--sm" href="logout.php">Ieșire</a></div></header>';
  echo '<main class="wrap">';
}

function foot() { echo '</main></body></html>'; }
