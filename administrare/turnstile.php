<?php

function turnstile_ok($secret, $token, $ip, $hosts = [], $action = '') {
  if ($secret === '') return true;
  if ($token === '') return false;
  $post = http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $ip]);
  $url  = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
  $raw  = false;
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_TIMEOUT => 8]);
    $raw = curl_exec($ch);
    curl_close($ch);
  }
  if ($raw === false) {
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $post, 'timeout' => 8, 'ignore_errors' => true]]);
    $raw = @file_get_contents($url, false, $ctx);
  }
  if ($raw === false) return true;
  $res = json_decode($raw, true);
  if (!is_array($res) || empty($res['success'])) return false;
  if ($hosts && !in_array(strtolower($res['hostname'] ?? ''), $hosts, true)) return false;
  if ($action !== '' && ($res['action'] ?? '') !== $action) return false;
  return true;
}
