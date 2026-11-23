<?php
// Invoice generator: storage for the service catalog and invoice settings.

const FACTURA_SERVICII_IMPLICIT = [
  ['Abonamente', 'Abonament Start', 'lună', 299, 'Site, găzduire, SSL, mentenanță, SEO de bază'],
  ['Abonamente', 'Abonament Business', 'lună', 549, 'Găzduire, mentenanță, SEO local, raport lunar'],
  ['Abonamente', 'Abonament Pro', 'lună', 999, 'Găzduire, mentenanță, SEO lunar, intervenții custom'],
  ['Site-uri și aplicații', 'Site de prezentare (5-8 pagini)', 'buc', 3500, 'Design, dezvoltare, optimizare mobil și SEO de bază'],
  ['Site-uri și aplicații', 'Landing page', 'buc', 1500, 'Pagină de prezentare cu formular de contact'],
  ['Site-uri și aplicații', 'Magazin online WooCommerce', 'buc', 6000, 'Până la 100 de produse, plăți și livrare'],
  ['Site-uri și aplicații', 'Aplicație web la comandă', 'buc', 15000, 'Conform ofertei acceptate'],
  ['Site-uri și aplicații', 'Ore de dezvoltare / modificări', 'oră', 200, 'Lucrări în afara abonamentului'],
  ['Mentenanță și găzduire', 'Găzduire + domeniu + SSL', 'an', 500, 'Găzduire, domeniu .ro, certificat SSL'],
  ['Mentenanță și găzduire', 'Mentenanță site existent', 'lună', 250, 'Actualizări, backup, securitate, monitorizare'],
  ['Mentenanță și găzduire', 'Mentenanță magazin WooCommerce', 'lună', 500, 'Actualizări, backup, securitate, monitorizare'],
  ['SEO și Google', 'Configurare măsurare', 'buc', 900, 'Google Analytics 4, Search Console, Tag Manager, conversii'],
  ['SEO și Google', 'Optimizare pentru AI (AEO/GEO)', 'buc', 1200, 'Date structurate, întrebări frecvente, profiluri, verificare în AI'],
  ['SEO și Google', 'SEO local', 'lună', 900, 'Optimizare lunară, urmărirea pozițiilor, raport'],
  ['SEO și Google', 'Articol de blog SEO', 'buc', 300, 'Cercetare cuvinte cheie, redactare, publicare'],
  ['SEO și Google', 'Optimizare profil Google Business', 'buc', 800, 'Configurare completă, categorii, servicii, poze'],
  ['SEO și Google', 'Audit SEO site', 'buc', 1200, 'Analiză tehnică și de conținut, cu plan de acțiuni'],
  ['Social media', 'Social media foto (8 postări)', 'lună', 500, 'Editare din materialul clientului, text, publicare'],
  ['Social media', 'Social media video (4 reels)', 'lună', 700, 'Montaj din materialul clientului, subtitrări, publicare'],
  ['Social media', 'Social media foto + video (8 + 4)', 'lună', 1000, 'Editare și publicare din materialul clientului'],
  ['Social media', 'Postare foto', 'buc', 75, 'Editare, text, publicare'],
  ['Social media', 'Reel / video scurt', 'buc', 200, 'Format vertical 9:16, subtitrări, muzică'],
  ['Reclame', 'Configurare campanie Google / Meta', 'buc', 1200, 'Structură cont, public, anunțuri, conversii'],
  ['Reclame', 'Administrare campanie Google Ads', 'lună', 600, 'Buget plătit de client direct la Google'],
  ['Reclame', 'Administrare campanie Meta Ads', 'lună', 600, 'Buget plătit de client direct la Meta'],
];

function factura_init() {
  db()->exec("CREATE TABLE IF NOT EXISTS factura_servicii (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categorie VARCHAR(80) NOT NULL,
    denumire VARCHAR(160) NOT NULL,
    descriere VARCHAR(255) NULL,
    um VARCHAR(20) NOT NULL DEFAULT 'buc',
    pret DECIMAL(10,2) NOT NULL DEFAULT 0,
    ordine INT NOT NULL DEFAULT 0,
    activ TINYINT(1) NOT NULL DEFAULT 1
  ) DEFAULT CHARSET=utf8mb4");
  db()->exec("CREATE TABLE IF NOT EXISTS factura_setari (
    cheie VARCHAR(40) PRIMARY KEY,
    valoare TEXT NULL
  ) DEFAULT CHARSET=utf8mb4");

  if ((int) db()->query('SELECT COUNT(*) FROM factura_servicii')->fetchColumn() === 0) {
    $st = db()->prepare('INSERT INTO factura_servicii (categorie, denumire, um, pret, descriere, ordine) VALUES (?, ?, ?, ?, ?, ?)');
    foreach (FACTURA_SERVICII_IMPLICIT as $i => $s) {
      $st->execute([$s[0], $s[1], $s[2], $s[3], $s[4], ($i + 1) * 10]);
    }
  }
}

function factura_setare($cheie, $implicit = null) {
  $st = db()->prepare('SELECT valoare FROM factura_setari WHERE cheie = ?');
  $st->execute([$cheie]);
  $v = $st->fetchColumn();
  return $v === false ? $implicit : $v;
}

function factura_salveaza_setare($cheie, $valoare) {
  db()->prepare('INSERT INTO factura_setari (cheie, valoare) VALUES (?, ?) ON DUPLICATE KEY UPDATE valoare = VALUES(valoare)')
    ->execute([$cheie, $valoare]);
}

// Catalog grouped by category, categories in the order of their first service.
function factura_catalog($doarActive = true) {
  $sql = 'SELECT id, categorie, denumire, descriere, um, pret, ordine, activ FROM factura_servicii'
       . ($doarActive ? ' WHERE activ = 1' : '') . ' ORDER BY ordine, id';
  $grupe = [];
  foreach (db()->query($sql)->fetchAll() as $r) {
    $r['pret'] = (float) $r['pret'];
    $grupe[$r['categorie']][] = $r;
  }
  return $grupe;
}
