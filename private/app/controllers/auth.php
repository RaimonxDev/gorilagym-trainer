<?php
declare(strict_types=1);

function otp_hash(string $code, int $uid): string
{
    return hash_hmac('sha256', $uid . ':' . $code, (string) config('app_key'));
}

function mask_email(string $e): string
{
    [$a, $d] = explode('@', $e) + ['', ''];
    return mb_substr($a, 0, 2) . str_repeat('•', max(2, mb_strlen($a) - 2)) . '@' . $d;
}

/** Crea y envía un código. $destino permite enviarlo a un correo nuevo (cambio de correo). */
function otp_enviar(array $u, string $proposito, ?string $destino = null, ?string $dato = null): bool
{
    if (!rate_limit('otp_doc:' . $u['id'], 5, 3600)) {
        flash('error', 'Pediste demasiados códigos. Espera una hora o pide ayuda en recepción.');
        return false;
    }
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    q('UPDATE otp_codes SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [time(), $u['id']]);
    insert('otp_codes', ['user_id' => $u['id'], 'code_hash' => otp_hash($code, (int) $u['id']), 'expires_at' => time() + (int) setting('otp_ttl_seg', '600'),
        'ip' => client_ip(), 'created_at' => time(), 'proposito' => $proposito, 'dato' => $dato]);
    $min = (int) round((int) setting('otp_ttl_seg', '600') / 60);
    $intro = [
        'login' => 'Tu código para crear tu contraseña en ' . e(setting('brand_nombre')) . ' es:',
        'recuperar' => 'Tu código para recuperar tu contraseña en ' . e(setting('brand_nombre')) . ' es:',
        'correo' => 'Tu código para confirmar este correo en ' . e(setting('brand_nombre')) . ' es:',
    ][$proposito] ?? 'Tu código es:';
    return send_mail($destino ?? (string) $u['email'], 'Tu código: ' . $code,
        '<p>Hola ' . e($u['nombre']) . ',</p><p>' . $intro . '</p>'
        . '<p style="font-size:30px;letter-spacing:6px;font-weight:bold;margin:18px 0">' . $code . '</p>'
        . '<p>Vence en ' . $min . ' minutos y sirve una sola vez. Si no lo pediste, ignora este correo.</p>');
}

/** Valida el último código vigente del usuario para ciertos propósitos. Devuelve la fila o un string de error. */
function otp_validar(int $uid, string $code, array $propositos)
{
    $in = implode(',', array_fill(0, count($propositos), '?'));
    $otp = q1("SELECT * FROM otp_codes WHERE user_id = ? AND used_at IS NULL AND proposito IN ($in) ORDER BY id DESC LIMIT 1", array_merge([$uid], $propositos));
    if (!$otp || $otp['expires_at'] < time()) return 'El código venció. Pide uno nuevo.';
    if ($otp['attempts'] >= 5) {
        update('otp_codes', ['used_at' => time()], 'id = ?', [$otp['id']]);
        return 'Demasiados intentos fallidos. Pide un código nuevo.';
    }
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) === 6 && hash_equals($otp['code_hash'], otp_hash($code, $uid))) {
        update('otp_codes', ['used_at' => time()], 'id = ?', [$otp['id']]);
        return $otp;
    }
    q('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?', [$otp['id']]);
    $rest = 4 - (int) $otp['attempts'];
    return 'Código incorrecto.' . ($rest > 0 ? " Te quedan $rest intentos." : '');
}

function destino_post_login(array $u): string
{
    if (!$u['password_hash']) return url('cuenta/contrasena');
    if ($u['rol'] === 'alumno' && !q1('SELECT 1 FROM fichas WHERE user_id = ? AND consentimiento_salud_at IS NOT NULL', [$u['id']])) return url('ficha', ['primera' => 1]);
    $to = $_SESSION['after_login'] ?? null;
    unset($_SESSION['after_login']);
    return $to && str_starts_with($to, base_path() . '/') ? $to : url();
}

function leer_doc(): array
{
    $tipo = in_array(input('doc_tipo'), ['rut', 'pasaporte', 'dni', 'otro'], true) ? input('doc_tipo') : 'rut';
    $raw = (string) input('doc', '');
    if ($raw === '') return [$tipo, $raw, null, 'Ingresa tu RUT.'];
    if ($tipo === 'rut' && !rut_valid($raw)) return [$tipo, $raw, null, 'El RUT no es válido. Revisa el dígito verificador.'];
    return [$tipo, $raw, doc_normalize($tipo, $raw), null];
}

function auth_login(): void
{
    if (current_user()) redirect(url());
    $state = null;
    $data = ['doc_tipo' => 'rut', 'doc' => ''];
    if (is_post()) {
        [$tipo, $raw, $doc, $err] = leer_doc();
        $data = ['doc_tipo' => $tipo, 'doc' => $raw];
        $pass = (string) ($_POST['contrasena'] ?? '');
        if (!rate_limit('login_ip:' . client_ip(), 30, 900)) {
            flash('error', 'Demasiados intentos desde esta conexión. Espera unos minutos.');
        } elseif ($err) {
            flash('error', $err);
        } else {
            $u = q1('SELECT * FROM users WHERE doc_tipo = ? AND doc_num = ?', [$tipo, $doc]);
            if ($u && !$u['activo']) {
                $state = 'inactivo';
            } elseif ($u && !$u['password_hash'] && !alumno_app_permitido($u)) {
                $state = 'sin_plan';
                $motivo = alumno_app_bloqueo($u);
            } elseif ($u && !$u['password_hash']) {
                // Primer ingreso: crear contraseña con un código al correo
                if (!valid_email($u['email'])) $state = 'sin_correo';
                elseif (otp_enviar($u, 'login')) {
                    $_SESSION['otp_uid'] = (int) $u['id'];
                    $_SESSION['otp_mail'] = mask_email($u['email']);
                    $_SESSION['otp_motivo'] = 'crear';
                    redirect(url('login/codigo'));
                }
            } elseif ($u && !rate_limit('pwd:' . $u['id'], 8, 900)) {
                flash('error', 'Demasiados intentos con esta cuenta. Espera 15 minutos o recupera tu contraseña.');
            } elseif ($u && $pass !== '' && password_verify($pass, $u['password_hash']) && !alumno_app_permitido($u)) {
                $state = 'sin_plan';
                $motivo = alumno_app_bloqueo($u);
            } elseif ($u && $pass !== '' && password_verify($pass, $u['password_hash'])) {
                if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) update('users', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
                login_user($u, true);
                redirect(destino_post_login($u));
            } elseif ($pass === '') {
                flash('error', 'Ingresa tu contraseña.');
            } else {
                flash('error', 'RUT o contraseña incorrectos.');
            }
        }
    }
    view('auth/login', ['title' => 'Entrar', 'state' => $state, 'motivo' => $motivo ?? '', 'data' => $data, 'bare' => true]);
}

function auth_recuperar(): void
{
    if (current_user()) redirect(url('cuenta/contrasena'));
    $state = null;
    $data = ['doc_tipo' => 'rut', 'doc' => (string) input('doc', '')];
    if (is_post()) {
        [$tipo, $raw, $doc, $err] = leer_doc();
        $data = ['doc_tipo' => $tipo, 'doc' => $raw];
        if (!rate_limit('rec_ip:' . client_ip(), 15, 900)) flash('error', 'Demasiados intentos. Espera unos minutos.');
        elseif ($err) flash('error', $err);
        else {
            $u = q1('SELECT * FROM users WHERE doc_tipo = ? AND doc_num = ? AND activo = 1', [$tipo, $doc]);
            if (!$u) $state = 'no_existe';
            elseif (!alumno_app_permitido($u)) {
                $state = 'sin_plan';
                $motivo = alumno_app_bloqueo($u);
            }
            elseif (!valid_email($u['email'])) $state = 'sin_correo';
            elseif (otp_enviar($u, 'recuperar')) {
                $_SESSION['otp_uid'] = (int) $u['id'];
                $_SESSION['otp_mail'] = mask_email($u['email']);
                $_SESSION['otp_motivo'] = 'recuperar';
                redirect(url('login/codigo'));
            }
        }
    }
    view('auth/recuperar', ['title' => 'Recuperar contraseña', 'state' => $state, 'motivo' => $motivo ?? '', 'data' => $data, 'bare' => true]);
}

function auth_codigo(): void
{
    $uid = (int) ($_SESSION['otp_uid'] ?? 0);
    if (!$uid) redirect(url('login'));
    if (is_post()) {
        $r = otp_validar($uid, (string) input('codigo', ''), ['login', 'recuperar']);
        if (is_string($r)) {
            flash('error', $r);
            if (!str_starts_with($r, 'Código incorrecto')) {
                unset($_SESSION['otp_uid']);
                redirect(url('login'));
            }
            redirect(url('login/codigo'));
        }
        $u = q1('SELECT * FROM users WHERE id = ? AND activo = 1', [$uid]);
        unset($_SESSION['otp_uid'], $_SESSION['otp_mail'], $_SESSION['otp_motivo']);
        if (!$u) redirect(url('login'));
        login_user($u, true);
        $_SESSION['crear_contrasena'] = 1; // con el código ya se verificó la identidad: no se pide la contraseña actual
        redirect(url('cuenta/contrasena'));
    }
    view('auth/codigo', ['title' => 'Código', 'mail' => $_SESSION['otp_mail'] ?? '', 'motivo' => $_SESSION['otp_motivo'] ?? 'crear', 'bare' => true]);
}

/** Ruta antigua (v1.0–1.3): el login ya pide la contraseña. */
function auth_clave(): void
{
    redirect(url('login'));
}

function contrasena_valida(string $p, array $u): ?string
{
    if (mb_strlen($p) < 8) return 'La contraseña debe tener al menos 8 caracteres.';
    if (mb_strlen($p) > 200) return 'La contraseña es demasiado larga.';
    if (!preg_match('/[A-Za-zÁÉÍÓÚÑáéíóúñ]/u', $p) || !preg_match('/\d/', $p)) return 'Usa letras y números.';
    $doc = preg_replace('/[^0-9kK]/', '', (string) $u['doc_num']);
    if (strlen($doc) >= 6 && stripos(preg_replace('/[^0-9kK]/', '', $p), substr($doc, 0, -1)) !== false) return 'La contraseña no puede contener tu RUT.';
    if (in_array(mb_strtolower($p), ['12345678', 'password1', 'gorila123', 'gorilagym1', 'qwerty123'], true)) return 'Esa contraseña es muy fácil de adivinar.';
    return null;
}

function cuenta_contrasena(): void
{
    $u = require_login();
    $crear = !$u['password_hash'] || !empty($_SESSION['crear_contrasena']);
    if (is_post()) {
        $nueva = (string) ($_POST['nueva'] ?? '');
        $rep = (string) ($_POST['repetir'] ?? '');
        $err = null;
        if (!$crear) {
            if (!rate_limit('pwd_cambio:' . $u['id'], 6, 900)) $err = 'Demasiados intentos. Espera 15 minutos.';
            elseif (!password_verify((string) ($_POST['actual'] ?? ''), (string) $u['password_hash'])) $err = 'Tu contraseña actual no es correcta.';
        }
        $err = $err ?? contrasena_valida($nueva, $u);
        if (!$err && $nueva !== $rep) $err = 'Las contraseñas no coinciden.';
        if ($err) {
            flash('error', $err);
            redirect(url('cuenta/contrasena'));
        }
        update('users', ['password_hash' => password_hash($nueva, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
        // Cierra los "recordarme" de otros dispositivos (deja este)
        $sel = explode(':', (string) ($_COOKIE['gg_rem'] ?? ''))[0];
        q('DELETE FROM remember_tokens WHERE user_id = ? AND selector != ?', [$u['id'], $sel]);
        unset($_SESSION['crear_contrasena']);
        audit($crear ? 'contrasena_creada' : 'contrasena_cambiada', 'users', (int) $u['id']);
        if (valid_email($u['email']) && !$crear) send_mail($u['email'], 'Cambiaste tu contraseña', '<p>Hola ' . e($u['nombre']) . ',</p><p>Tu contraseña de ' . e(setting('brand_nombre')) . ' se cambió el ' . date('d/m/Y \a \l\a\s H:i') . '.</p><p>Si no fuiste tú, recupérala de inmediato desde la pantalla de ingreso y avisa en recepción.</p>');
        flash('ok', $crear ? 'Contraseña creada. Desde ahora entras con tu RUT y tu contraseña.' : 'Contraseña actualizada.');
        $u['password_hash'] = 'x';
        redirect($crear ? destino_post_login($u) : url('perfil', ['tab' => 'seguridad']));
    }
    view('auth/contrasena', ['title' => $crear ? 'Crea tu contraseña' : 'Cambiar contraseña', 'u' => $u, 'crear' => $crear, 'nav' => 'perfil', 'bare' => $crear]);
}

function cuenta_correo(): void
{
    $u = require_login();
    $paso = isset($_SESSION['correo_nuevo']) ? 'codigo' : 'correo';
    if (input('cancelar')) {
        unset($_SESSION['correo_nuevo']);
        redirect(url('perfil', ['tab' => 'seguridad']));
    }
    if (is_post()) {
        if ($paso === 'correo') {
            $nuevo = mb_strtolower(trim((string) input('email', '')));
            if (!valid_email($nuevo)) flash('error', 'Ese correo no es válido.');
            elseif ($nuevo === mb_strtolower((string) $u['email'])) flash('error', 'Es el mismo correo que ya tienes.');
            elseif ($u['password_hash'] && !password_verify((string) ($_POST['contrasena'] ?? ''), $u['password_hash'])) flash('error', 'Tu contraseña no es correcta.');
            elseif (otp_enviar($u, 'correo', $nuevo, $nuevo)) {
                $_SESSION['correo_nuevo'] = $nuevo;
                flash('ok', 'Te enviamos un código a ' . $nuevo . '.');
            }
            redirect(url('cuenta/correo'));
        }
        $nuevo = (string) $_SESSION['correo_nuevo'];
        $r = otp_validar((int) $u['id'], (string) input('codigo', ''), ['correo']);
        if (is_string($r)) {
            flash('error', $r);
            if (!str_starts_with($r, 'Código incorrecto')) unset($_SESSION['correo_nuevo']);
            redirect(url('cuenta/correo'));
        }
        if ($r['dato'] !== $nuevo) {
            unset($_SESSION['correo_nuevo']);
            flash('error', 'El código no corresponde a ese correo. Inténtalo de nuevo.');
            redirect(url('cuenta/correo'));
        }
        $anterior = $u['email'];
        update('users', ['email' => $nuevo], 'id = ?', [$u['id']]);
        unset($_SESSION['correo_nuevo']);
        audit('correo_cambiado', 'users', (int) $u['id'], ['anterior' => $anterior, 'nuevo' => $nuevo, 'verificacion' => 'codigo']);
        if (valid_email($anterior)) send_mail($anterior, 'Tu correo fue cambiado', '<p>Hola ' . e($u['nombre']) . ',</p><p>El correo de tu cuenta de ' . e(setting('brand_nombre')) . ' se cambió a <b>' . e(mask_email($nuevo)) . '</b>.</p><p>Si no fuiste tú, avisa en recepción de inmediato.</p>');
        flash('ok', 'Correo actualizado.');
        redirect(url('perfil', ['tab' => 'seguridad']));
    }
    view('auth/correo', ['title' => 'Cambiar correo', 'u' => $u, 'paso' => $paso, 'nuevo' => $_SESSION['correo_nuevo'] ?? '', 'nav' => 'perfil']);
}

function auth_logout(): void
{
    if (is_post()) logout_user(input('todos') === '1');
    redirect(url('login'));
}

function pwa_manifest(): void
{
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    $bp = base_path();
    $n = setting('brand_nombre', 'Gorila Gym');
    echo json_encode([
        'name' => $n,
        'short_name' => mb_substr($n, 0, 12),
        'description' => setting('brand_tagline', ''),
        'lang' => 'es-CL',
        'start_url' => $bp . '/?src=pwa',
        'scope' => $bp . '/',
        'id' => $bp . '/',
        'display' => 'standalone',
        'orientation' => 'portrait',
        'background_color' => setting('brand_light', '#F5F9E5'),
        'theme_color' => setting('brand_primary', '#283614'),
        'icons' => [
            ['src' => $bp . '/assets/img/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $bp . '/assets/img/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $bp . '/assets/img/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
