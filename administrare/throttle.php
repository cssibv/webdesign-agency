<?php
// Rate limiting pe IP, bazat pe fișiere (fereastră fixă).
// Datele stau în private/throttle (blocat web prin .htaccess).
// Fail-open dacă FS-ul nu e scriibil, ca un disc plin să nu blocheze permanent
// utilizatorii legitimi.

function _throttle_file($key) {
  $dir = __DIR__ . '/private/throttle';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  return $dir . '/' . substr(preg_replace('/[^a-z0-9_]/i', '_', $key), 0, 80) . '.json';
}

// IP-ul clientului. NU folosim X-Forwarded-For (poate fi falsificat de client).
function client_ip() {
  return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// true dacă $key a atins deja $max lovituri în fereastra curentă.
function rate_blocked($key, $max, $window) {
  $f = _throttle_file($key);
  if (!is_file($f)) return false;
  $d = json_decode(@file_get_contents($f), true);
  if (!is_array($d) || time() > ($d['reset'] ?? 0)) return false;
  return ($d['count'] ?? 0) >= $max;
}

// Înregistrează o lovitură pentru $key; pornește o fereastră nouă dacă a expirat.
function rate_register($key, $window) {
  $f = _throttle_file($key);
  $fp = @fopen($f, 'c+');
  if (!$fp) return;
  if (flock($fp, LOCK_EX)) {
    $d   = json_decode(stream_get_contents($fp), true);
    $now = time();
    if (!is_array($d) || $now > ($d['reset'] ?? 0)) $d = ['count' => 0, 'reset' => $now + $window];
    $d['count']++;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($d));
    flock($fp, LOCK_UN);
  }
  fclose($fp);
}

// Șterge contorul pentru $key (ex. după un login reușit).
function rate_clear($key) {
  $f = _throttle_file($key);
  if (is_file($f)) @unlink($f);
}
