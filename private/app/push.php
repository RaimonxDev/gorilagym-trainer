<?php
/**
 * Web Push con VAPID (sin payload: el service worker consulta el mensaje al servidor).
 * Sin dependencias externas: solo OpenSSL.
 */
declare(strict_types=1);

function b64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}
function b64url_dec(string $s): string
{
    return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
}

/** Genera par de claves VAPID (P-256). */
function vapid_generate(): array
{
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$key) throw new RuntimeException('OpenSSL no pudo generar la clave EC.');
    openssl_pkey_export($key, $pem);
    $d = openssl_pkey_get_details($key);
    $pub = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    return ['public' => b64url($pub), 'private_pem' => $pem];
}

function der_to_raw(string $der): string
{
    $pos = 2;
    if (ord($der[1]) & 0x80) $pos += ord($der[1]) & 0x7f;
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $pos++; // 0x02
        $len = ord($der[$pos++]);
        $int = substr($der, $pos, $len);
        $pos += $len;
        $int = ltrim($int, "\0");
        $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
    }
    return $out;
}

function vapid_jwt(string $endpoint): string
{
    $p = parse_url($endpoint);
    $aud = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    $header = b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $claims = b64url(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => (string) config('vapid_subject', 'mailto:admin@example.com')]));
    $data = $header . '.' . $claims;
    $ok = openssl_sign($data, $sig, (string) config('vapid_private_pem'), OPENSSL_ALGO_SHA256);
    if (!$ok) throw new RuntimeException('No se pudo firmar el JWT VAPID.');
    return $data . '.' . b64url(der_to_raw($sig));
}

/** Envía un push vacío. Devuelve código HTTP (201 ok; 404/410 = suscripción muerta). */
function push_send(string $endpoint, string $urgency = 'high'): int
{
    if (!config('vapid_public') || !config('vapid_private_pem')) return 0;
    $headers = [
        'Authorization: vapid t=' . vapid_jwt($endpoint) . ', k=' . config('vapid_public'),
        'TTL: 120',
        'Urgency: ' . $urgency,
        'Content-Length: 0',
    ];
    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => '',
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    }
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => '', 'timeout' => 10, 'ignore_errors' => true]]);
    @file_get_contents($endpoint, false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $h) if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) $code = (int) $m[1];
    return $code;
}

/** Despacha notificaciones pendientes cuya hora ya llegó. */
function push_dispatch(): int
{
    $now = time();
    $pend = qa('SELECT DISTINCT user_id FROM notificaciones WHERE enviada_at IS NULL AND cancelada = 0 AND enviar_at <= ? AND enviar_at > ?', [$now, $now - 3600]);
    $sent = 0;
    foreach ($pend as $p) {
        $uid = (int) $p['user_id'];
        $subs = qa('SELECT * FROM push_subs WHERE user_id = ?', [$uid]);
        q('UPDATE notificaciones SET enviada_at = ? WHERE user_id = ? AND enviada_at IS NULL AND cancelada = 0 AND enviar_at <= ?', [$now, $uid, $now]);
        foreach ($subs as $s) {
            $code = push_send($s['endpoint']);
            if ($code === 404 || $code === 410) q('DELETE FROM push_subs WHERE id = ?', [$s['id']]);
            if ($code >= 200 && $code < 300) $sent++;
        }
    }
    // Limpieza de notificaciones viejas
    q('DELETE FROM notificaciones WHERE enviar_at < ?', [$now - 14 * 86400]);
    return $sent;
}
