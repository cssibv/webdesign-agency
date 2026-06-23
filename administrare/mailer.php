<?php
function smtp_send(array $cfg, $to, $subject, $text, $replyTo = '', $html = '') {
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

    if ($html !== '') {
      $b = bin2hex(random_bytes(16));
      $headers .= "Content-Type: multipart/alternative; boundary=\"$b\"\r\n";
      $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            . $text . "\r\n--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            . $html . "\r\n--$b--";
    } else {
      $headers .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
      $body = $text;
    }

    $norm = preg_replace('/\r\n|\r|\n/', "\n", $body);
    $norm = preg_replace('/^\./m', '..', $norm);
    $norm = str_replace("\n", "\r\n", $norm);
    fwrite($fp, $headers . "\r\n" . $norm . "\r\n.\r\n");
    if ($code($get()) !== 250) $ok = false;
  }
  @fwrite($fp, "QUIT\r\n");
  fclose($fp);
  return $ok;
}

function email_esc($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

define('EMAIL_FONT', "system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif");

function email_h($text) {
  return '<h1 style="margin:0 0 16px;font-family:' . EMAIL_FONT . ';font-size:22px;font-weight:800;color:#0d3b66;">' . $text . '</h1>';
}

function email_p($html) {
  return '<p style="margin:0 0 18px;font-family:' . EMAIL_FONT . ';font-size:16px;line-height:1.6;color:#1c2b36;">' . $html . '</p>';
}

function email_small($html) {
  return '<p style="margin:0;font-family:' . EMAIL_FONT . ';font-size:13px;line-height:1.6;color:#54677a;">' . $html . '</p>';
}

function email_button($text, $url) {
  return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;"><tr>'
    . '<td align="center" bgcolor="#ff7a45" style="border-radius:10px;">'
    . '<a href="' . email_esc($url) . '" style="display:inline-block;padding:14px 32px;font-family:' . EMAIL_FONT . ';font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px;">' . $text . '</a>'
    . '</td></tr></table>';
}

function email_layout(array $cfg, $inner) {
  $url = rtrim($cfg['base_url'] ?? 'https://smart-web.ro', '/');
  return '<!DOCTYPE html><html lang="ro"><head><meta charset="UTF-8">'
    . '<meta name="viewport" content="width=device-width,initial-scale=1.0"></head>'
    . '<body style="margin:0;background:#f4f8fb;">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f8fb;"><tr><td align="center" style="padding:24px 12px;">'
    . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(13,59,102,.10);">'
    . '<tr><td style="background:#0d3b66;padding:28px 36px;">'
    . '<span style="font-family:' . EMAIL_FONT . ';font-size:24px;font-weight:800;letter-spacing:-.3px;color:#ffffff;">Smart <span style="color:#ff7a45;">Web</span></span>'
    . '</td></tr>'
    . '<tr><td style="padding:36px 36px 28px 36px;">' . $inner . '</td></tr>'
    . '<tr><td style="background:#f4f8fb;border-top:1px solid #e2e9f0;padding:28px 36px;">'
    . '<div style="font-family:' . EMAIL_FONT . ';font-size:18px;font-weight:800;letter-spacing:-.3px;color:#0d3b66;margin-bottom:6px;">Smart <span style="color:#ff7a45;">Web</span></div>'
    . '<p style="margin:0 0 14px;font-family:' . EMAIL_FONT . ';font-size:13px;line-height:1.5;color:#54677a;">Site-uri care îți aduc clienți în Brașov.</p>'
    . '<p style="margin:0;font-family:' . EMAIL_FONT . ';font-size:12px;line-height:1.6;color:#8395a7;">'
    . '<a href="' . $url . '" style="color:#1aa9a0;text-decoration:none;">smart-web.ro</a><br>'
    . '<a href="mailto:contact@smart-web.ro" style="color:#1aa9a0;text-decoration:none;">contact@smart-web.ro</a>'
    . '</p></td></tr>'
    . '</table></td></tr></table></body></html>';
}
