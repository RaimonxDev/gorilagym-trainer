<?php
declare(strict_types=1);

require_once __DIR__ . '/api.php';
require_once PRIVATE_DIR . '/app/musica.php';

/** Buscador de canciones (Spotify o catálogo de respaldo). */
function api_musica(): void
{
    $u = require_login();
    if ($u['rol'] === 'alumno' && !can('alumno.muro')) deny();
    if (!rate_limit('musica:' . $u['id'], 40, 60)) json_out(['ok' => false, 'error' => 'Muchas búsquedas seguidas. Espera unos segundos.'], 429);
    $res = buscar_canciones((string) input('q', ''));
    $items = array_map(fn($it) => $it + ['ref' => $it['fuente'] . ':' . $it['id']], $res['items']);
    json_out(['ok' => empty($res['error']), 'items' => $items, 'fuente' => $res['fuente'], 'error' => $res['error'] ?? null]);
}

/** Canción enviada desde el formulario: elegida del buscador (ref) o link pegado. */
function musica_desde_form(): array
{
    $m = musica_elegida((string) input('musica_ref', ''));
    $link = null;
    $raw = trim((string) input('spotify', ''));
    if (!$m && $raw !== '') {
        $link = spotify_parse($raw);
        if (!$link) {
            flash('error', 'Ese link no parece de Spotify. Mejor busca la canción por nombre.');
            return ['error' => true];
        }
    }
    return musica_columnas($m, $link);
}

function muro_estados(): array
{
    return array_values(array_filter(array_map(fn($l) => mb_substr(trim($l), 0, 60), explode("\n", (string) setting('muro_estados', ''))), fn($l) => $l !== ''));
}

/** Publicaciones manuales del día (incluye las borradas, para que borrar no sirva para publicar de nuevo). */
function muro_publicadas_hoy(int $uid): int
{
    return (int) qv("SELECT COUNT(*) FROM muro_posts WHERE user_id = ? AND tipo = 'estado' AND auto = 0 AND date(created_at) = date('now','localtime')", [$uid]);
}

function muro_limite_dia(): int
{
    return max(1, (int) setting('muro_posts_dia', '1'));
}

function fecha_post(string $d): string
{
    $t = strtotime($d);
    $m = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $hoy = date('Y-m-d');
    $dia = date('Y-m-d', $t);
    $pre = $dia === $hoy ? 'Hoy, ' : ($dia === date('Y-m-d', strtotime('-1 day')) ? 'Ayer, ' : '');
    return $pre . date('j', $t) . ' ' . $m[(int) date('n', $t) - 1] . ' · ' . date('H:i', $t);
}

function muro_feed(): void
{
    $u = require_login();
    if (setting('muro_activo', '1') !== '1') not_found();
    if ($u['rol'] === 'alumno' && !can('alumno.muro')) deny();
    if (is_post()) {
        if ($u['rol'] === 'alumno' && !$u['muro_opt_in']) {
            if (input('activar')) {
                update('users', ['muro_opt_in' => 1], 'id = ?', [$u['id']]);
                flash('ok', 'Listo, ya apareces en el muro. Puedes salir cuando quieras desde tu perfil.');
            }
            redirect(url('muro'));
        }
        // Solo estados predeterminados (sin texto libre)
        $txt = trim((string) input('estado', ''));
        if ($txt !== '' && !in_array($txt, muro_estados(), true)) {
            flash('error', 'Elige uno de los estados de la lista.');
            redirect(url('muro'));
        }
        $mus = musica_desde_form();
        if (!empty($mus['error'])) redirect(url('muro'));
        if ($txt === '' && !$mus) {
            flash('error', 'Elige un estado o una canción.');
            redirect(url('muro'));
        }
        $lim = muro_limite_dia();
        if (muro_publicadas_hoy((int) $u['id']) >= $lim) {
            flash('error', $lim === 1 ? 'Ya publicaste hoy. Vuelve mañana 🦍' : "Ya hiciste tus $lim publicaciones de hoy. Vuelve mañana 🦍");
            redirect(url('muro'));
        }
        tx(function () use ($u, $txt, $mus) {
            // El estado manual reemplaza al automático de la rutina
            q("UPDATE muro_posts SET eliminado_at = ? WHERE user_id = ? AND tipo = 'estado' AND auto = 1 AND eliminado_at IS NULL AND date(created_at) = date('now','localtime')", [date('Y-m-d H:i:s'), $u['id']]);
            insert('muro_posts', ['user_id' => $u['id'], 'tipo' => 'estado', 'texto' => $txt !== '' ? $txt : null, 'auto' => 0] + $mus);
        });
        redirect(url('muro'));
    }
    $posts = qa("SELECT p.*, u.nombre, u.apellido, u.foto FROM muro_posts p JOIN users u ON u.id = p.user_id
                 WHERE p.oculto = 0 AND p.eliminado_at IS NULL AND (u.muro_opt_in = 1 OR u.rol != 'alumno') AND p.created_at >= datetime('now','localtime','-3 days')
                 ORDER BY p.created_at DESC LIMIT 60");
    $ids = array_column($posts, 'id');
    $reac = [];
    $mias = [];
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        foreach (qa("SELECT post_id, emoji, COUNT(*) AS n FROM muro_reacciones WHERE post_id IN ($in) GROUP BY post_id, emoji") as $r) $reac[$r['post_id']][$r['emoji']] = (int) $r['n'];
        foreach (qa("SELECT post_id, emoji FROM muro_reacciones WHERE post_id IN ($in) AND user_id = ?", [$u['id']]) as $r) $mias[$r['post_id']][$r['emoji']] = true;
    }
    $lim = muro_limite_dia();
    $hechas = muro_publicadas_hoy((int) $u['id']);
    $mio = q1("SELECT * FROM muro_posts WHERE user_id = ? AND tipo = 'estado' AND eliminado_at IS NULL AND date(created_at) = date('now','localtime') ORDER BY auto ASC, id DESC LIMIT 1", [$u['id']]);
    view('muro/feed', ['title' => 'La jungla', 'nav' => 'muro', 'u' => $u, 'posts' => $posts, 'reac' => $reac, 'mias' => $mias, 'mio' => $mio, 'sala' => sala_datos(),
        'estados' => muro_estados(), 'puedePublicar' => $hechas < $lim, 'restantes' => max(0, $lim - $hechas), 'limite' => $lim,
        'scripts' => ['assets/js/musica.js', 'assets/js/muro.js', 'assets/js/historia.js']]);
}
