<?php
// Trimite email prin SMTP autentificat. Pe shared hosting mail() nu poate trimite
// extern fără autentificare. Credențialele vin din private/config.php (smtp_*).
function smtp_send(array $cfg, $to, $subject, $body, $replyTo = '') {
  $host = $cfg['smtp_host'] ?? '';
  $user = $cfg['smtp_user'] ?? '';
  $pass = $cfg['smtp_pass'] ?? '';
  $port = (int) ($cfg['smtp_port'] ?? 465);
  $from = $cfg['smtp_from'] ?? $user;
  if ($host === '' || $user === '' || $from === '') return false;

  $ehlo = (strpos($from, '@') !== false) ? substr(strrchr($from, '@'), 1) : 'localhost';
  $transport = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
  $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
  $fp = @stream_socket_client($transport, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
  if (!$fp) return false;
  stream_set_timeout($fp, 20);

  $get = function () use ($fp) {
    $out = '';
    while (($line = fgets($fp, 600)) !== false) {
      $out .= $line;
      if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $out;
  };
  $send = function ($c) use ($fp, $get) { fwrite($fp, $c . "\r\n"); return $get(); };
  $code = function ($r) { return (int) substr($r, 0, 3); };

  $ok = ($code($get()) === 220);
  if ($ok && $code($send("EHLO $ehlo")) !== 250) $ok = false;
  if ($ok && $port === 587) {
    if ($code($send('STARTTLS')) === 220) {
      stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
      $send("EHLO $ehlo");
    } else {
      $ok = false;
    }
  }
  if ($ok && $code($send('AUTH LOGIN')) !== 334) $ok = false;
  if ($ok && $code($send(base64_encode($user))) !== 334) $ok = false;
  if ($ok && $code($send(base64_encode($pass))) !== 235) $ok = false;
  if ($ok && $code($send("MAIL FROM:<$from>")) !== 250) $ok = false;
  if ($ok && $code($send("RCPT TO:<$to>")) !== 250) $ok = false;
  if ($ok && $code($send('DATA')) !== 354) $ok = false;

  if ($ok) {
    $headers  = "From: $from\r\n";
    if ($replyTo !== '') $headers .= "Reply-To: $replyTo\r\n";
    $headers .= "To: $to\r\n";
    $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    $norm = preg_replace('/\r\n|\r|\n/', "\n", (string) $body);
    $norm = preg_replace('/^\./m', '..', $norm);
    $norm = str_replace("\n", "\r\n", $norm);
    fwrite($fp, $headers . "\r\n" . $norm . "\r\n.\r\n");
    if ($code($get()) !== 250) $ok = false;
  }
  @fwrite($fp, "QUIT\r\n");
  fclose($fp);
  return $ok;
}
