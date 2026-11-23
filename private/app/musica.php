<?php
/**
 * Buscador de canciones.
 * 1) Spotify Web API (Client Credentials, solo búsqueda; el alumno no inicia sesión en Spotify).
 * 2) Respaldo automático: catálogo público de Apple (iTunes Search API, sin claves) + botón "Abrir en Spotify".
 * Los resultados se guardan en la sesión del usuario: al publicar, el servidor usa SUS datos, no los del cliente.
 */
declare(strict_types=1);

function http_json(string $url, array $headers = [], ?string $post = null): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_HTTPHEADER => $headers, CURLOPT_USERAGENT => 'GorilaGym/1.1']);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => $post !== null ? 'POST' : 'GET', 'header' => implode("\r\n", array_merge($headers, ['User-Agent: GorilaGym/1.1'])), 'content' => $post ?? '', 'timeout' => 8, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) $code = (int) $m[1];
    }
    if ($body === false || $code < 200 || $code >= 300) {
        if ($code) error_log("musica: HTTP $code en " . parse_url($url, PHP_URL_HOST));
        return null;
    }
    $j = json_decode((string) $body, true);
    return is_array($j) ? $j : null;
}

function spotify_configurado(): bool
{
    return setting('spotify_client_id', '') !== '' && setting('spotify_client_secret', '') !== '';
}

function spotify_token(): ?string
{
    if (!spotify_configurado()) return null;
    $tok = setting('spotify_token', '');
    $exp = (int) setting('spotify_token_exp', '0');
    if ($tok !== '' && $exp > time() + 60) return $tok;
    $j = http_json('https://accounts.spotify.com/api/token', [
        'Authorization: Basic ' . base64_encode(setting('spotify_client_id') . ':' . setting('spotify_client_secret')),
        'Content-Type: application/x-www-form-urlencoded',
    ], 'grant_type=client_credentials');
    if (!$j || empty($j['access_token'])) {
        setting_set('spotify_error_at', (string) time());
        return null;
    }
    setting_set('spotify_token', $j['access_token']);
    setting_set('spotify_token_exp', (string) (time() + (int) ($j['expires_in'] ?? 3600)));
    return $j['access_token'];
}

function buscar_spotify(string $q): ?array
{
    // Si Spotify falló hace poco, no insistir por 10 minutos
    if ((int) setting('spotify_error_at', '0') > time() - 600) return null;
    $tok = spotify_token();
    if (!$tok) return null;
    $j = http_json('https://api.spotify.com/v1/search?' . http_build_query(['q' => $q, 'type' => 'track', 'limit' => 10, 'market' => 'CL']), ['Authorization: Bearer ' . $tok]);
    if (!$j || !isset($j['tracks']['items'])) {
        setting_set('spotify_error_at', (string) time());
        setting_set('spotify_token', '');
        return null;
    }
    $out = [];
    foreach ($j['tracks']['items'] as $t) {
        if (empty($t['id'])) continue;
        $imgs = $t['album']['images'] ?? [];
        $img = $imgs ? end($imgs)['url'] : null; // la más chica
        foreach ($imgs as $im) if (($im['width'] ?? 0) >= 200 && ($im['width'] ?? 0) <= 320) $img = $im['url'];
        $out[] = [
            'fuente' => 'spotify', 'id' => (string) $t['id'], 'titulo' => (string) $t['name'],
            'artista' => implode(', ', array_map(fn($a) => $a['name'], $t['artists'] ?? [])),
            'portada' => $img, 'url' => 'https://open.spotify.com/track/' . $t['id'],
        ];
    }
    return $out;
}

function buscar_itunes(string $q): ?array
{
    $j = http_json('https://itunes.apple.com/search?' . http_build_query(['term' => $q, 'entity' => 'song', 'limit' => 10, 'country' => 'CL']));
    if (!$j || !isset($j['results'])) return null;
    $out = [];
    foreach ($j['results'] as $t) {
        if (empty($t['trackId'])) continue;
        $titulo = (string) ($t['trackName'] ?? '');
        $artista = (string) ($t['artistName'] ?? '');
        $out[] = [
            'fuente' => 'catalogo', 'id' => (string) $t['trackId'], 'titulo' => $titulo, 'artista' => $artista,
            'portada' => isset($t['artworkUrl100']) ? str_replace('100x100bb', '200x200bb', (string) $t['artworkUrl100']) : null,
            'url' => 'https://open.spotify.com/search/' . rawurlencode(trim($artista . ' ' . $titulo)),
        ];
    }
    return $out;
}

/** Devuelve ['items' => [...], 'fuente' => 'spotify'|'catalogo'|null] */
function buscar_canciones(string $q): array
{
    $q = trim(preg_replace('/\s+/', ' ', $q));
    if (mb_strlen($q) < 2) return ['items' => [], 'fuente' => null];
    $key = mb_strtolower($q);
    $c = q1('SELECT data, created_at FROM musica_cache WHERE q = ?', [$key]);
    if ($c && $c['created_at'] > time() - 86400) {
        $res = json_decode($c['data'], true);
    } else {
        $items = buscar_spotify($q);
        $fuente = 'spotify';
        if ($items === null) {
            $items = buscar_itunes($q);
            $fuente = 'catalogo';
        }
        if ($items === null) return ['items' => [], 'fuente' => null, 'error' => 'El buscador no responde. Intenta en un momento o pega un link de Spotify.'];
        $res = ['items' => $items, 'fuente' => $fuente];
        q('INSERT INTO musica_cache(q, data, created_at) VALUES(?,?,?) ON CONFLICT(q) DO UPDATE SET data = excluded.data, created_at = excluded.created_at', [$key, json_encode($res, JSON_UNESCAPED_UNICODE), time()]);
        if (random_int(1, 50) === 1) q('DELETE FROM musica_cache WHERE created_at < ?', [time() - 7 * 86400]);
    }
    // Guardar en sesión para validar al publicar
    $_SESSION['musica'] = $_SESSION['musica'] ?? [];
    foreach ($res['items'] as $it) $_SESSION['musica'][$it['fuente'] . ':' . $it['id']] = $it;
    $_SESSION['musica'] = array_slice($_SESSION['musica'], -60, null, true);
    return $res;
}

/** Recupera la canción elegida (solo si vino de una búsqueda de este usuario). */
function musica_elegida(?string $ref): ?array
{
    if (!$ref) return null;
    return $_SESSION['musica'][$ref] ?? null;
}

/** Columnas a guardar a partir de una canción elegida o de un link pegado. */
function musica_columnas(?array $m, ?array $link): array
{
    if ($m) {
        return [
            'spotify_tipo' => $m['fuente'] === 'spotify' ? 'track' : 'catalogo', 'spotify_id' => $m['id'],
            'musica_fuente' => $m['fuente'], 'musica_titulo' => mb_substr($m['titulo'], 0, 150), 'musica_artista' => mb_substr($m['artista'], 0, 150),
            'musica_portada' => $m['portada'] && preg_match('~^https://~', $m['portada']) ? $m['portada'] : null, 'musica_url' => $m['url'],
        ];
    }
    if ($link) {
        return ['spotify_tipo' => $link['tipo'], 'spotify_id' => $link['id'], 'musica_fuente' => 'link', 'musica_titulo' => null, 'musica_artista' => null, 'musica_portada' => null,
            'musica_url' => 'https://open.spotify.com/' . $link['tipo'] . '/' . $link['id']];
    }
    return [];
}

/** Tarjeta de canción para muro y cola. */
function musica_card(array $r, bool $compacta = false): string
{
    $fuente = $r['musica_fuente'] ?? null;
    // Links pegados (o posts antiguos): embed oficial de Spotify
    if (!$fuente || $fuente === 'link') {
        return $r['spotify_id'] ? spotify_embed((string) $r['spotify_tipo'], (string) $r['spotify_id']) : '';
    }
    $img = $r['musica_portada'] ? '<img src="' . e($r['musica_portada']) . '" alt="" class="w-14 h-14 rounded-md object-cover bg-dark/5 shrink-0" loading="lazy" referrerpolicy="no-referrer">'
        : '<span class="w-14 h-14 rounded-md bg-dark/5 shrink-0 flex items-center justify-center text-dark/40">' . icon('music') . '</span>';
    return '<div class="flex items-center gap-3 rounded-lg border border-dark/10 bg-light/40 p-2.5">' . $img
        . '<div class="flex-1 min-w-0"><div class="font-medium truncate">' . e($r['musica_titulo']) . '</div><div class="text-sm text-dark/60 truncate">' . e($r['musica_artista']) . '</div></div>'
        . '<a href="' . e($r['musica_url']) . '" target="_blank" rel="noopener" class="btn-secondary btn-sm shrink-0">' . icon('play', 'w-4 h-4') . ($compacta ? '' : 'Spotify') . '</a></div>';
}
