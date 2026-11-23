<?php partial('shared/head', ['t' => 'Marca y ajustes']);
$esSuper = current_user()['rol'] === 'superadmin';
$tabs = ['marca' => 'Marca', 'sede' => 'Ubicación', 'general' => 'General', 'muro' => 'Muro', 'musica' => 'Música', 'salud' => 'Salud', 'entrada' => 'Entrada'] + ($esSuper ? ['pruebas' => 'Pruebas'] : []) + ['sistema' => 'Sistema'];
if (!isset($tabs[$tab])) $tab = 'marca';
$colores = ['brand_primary' => 'Principal', 'brand_hover' => 'Hover del principal', 'brand_dark' => 'Oscuro (en vez de negro)', 'brand_light' => 'Claro (en vez de blanco)', 'brand_accent' => 'Acento (logros, récords)', 'brand_danger' => 'Error', 'brand_warning' => 'Advertencia'];
$cronOk = $cron && strtotime($cron) > time() - 600;
?>
<nav class="tabs" aria-label="Secciones de ajustes">
  <?php foreach ($tabs as $k => $l): ?><a href="<?= e(url('admin/marca', ['tab' => $k])) ?>" class="tab <?= $tab === $k ? 'is-on' : '' ?>"><?= e($l) ?></a><?php endforeach; ?>
</nav>

<div class="max-w-3xl">
<?php if ($tab === 'marca'): ?>
  <form method="post" enctype="multipart/form-data" id="marca" class="card p-5 space-y-4">
    <?= csrf_field() ?><input type="hidden" name="accion" value="marca">
    <h2 class="font-semibold">Marca</h2>
    <div class="grid sm:grid-cols-2 gap-4">
      <div><label class="label">Nombre del gimnasio</label><input name="brand_nombre" class="input" value="<?= e(setting('brand_nombre')) ?>"></div>
      <div><label class="label">Mensaje de bienvenida (check-in)</label><input name="brand_bienvenida" class="input" value="<?= e(setting('brand_bienvenida')) ?>"></div>
      <div><label class="label">Instagram del gimnasio</label><input name="brand_instagram" class="input" value="<?= e(setting('brand_instagram') ? '@' . setting('brand_instagram') : '') ?>" placeholder="@gorilagym"><p class="text-xs text-dark/50 mt-1">Aparece en la foto para historias.</p></div>
      <div><label class="label">Bajada</label><input name="brand_tagline" class="input" value="<?= e(setting('brand_tagline')) ?>"></div>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <?php foreach ($colores as $k => $l): ?>
        <label class="block"><span class="label"><?= e($l) ?></span><span class="flex items-center gap-2 rounded-lg border border-dark/15 bg-white px-2 py-1.5"><input type="color" name="<?= $k ?>" value="<?= e(setting($k)) ?>" class="w-8 h-8 rounded border-0 bg-transparent p-0" data-color-sync><code class="text-xs" data-color-label><?= e(setting($k)) ?></code></span></label>
      <?php endforeach; ?>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
      <?php foreach (['brand_logo' => 'Logo (fondo claro)', 'brand_logo_claro' => 'Logo (fondo oscuro)'] as $k => $l): ?>
        <div><span class="label"><?= e($l) ?></span>
          <div class="rounded-lg border border-dark/10 p-3 <?= $k === 'brand_logo_claro' ? 'bg-primary' : 'bg-white' ?>"><img src="<?= e(setting($k) ? media_url(setting($k)) : asset('assets/img/brand/' . ($k === 'brand_logo' ? 'logo-horizontal.svg' : 'logo-horizontal-claro.svg'))) ?>" class="h-10" alt=""></div>
          <input type="file" name="<?= $k ?>" accept="image/png,image/webp,image/jpeg" class="file mt-2"><?php if (setting($k)): ?><label class="mt-1 flex items-center gap-2 text-xs"><input type="checkbox" name="quitar_<?= $k ?>" value="1" class="checkbox">Volver al logo original</label><?php endif; ?></div>
      <?php endforeach; ?>
    </div>
    <p class="text-xs text-dark/50">Los logos SVG originales vienen incluidos. Para reemplazarlos sube PNG o WebP (por seguridad no se aceptan SVG subidos).</p>
    <button class="btn-primary">Guardar marca</button>
  </form>
<?php endif; ?>

<?php if ($tab === 'sede'): ?>
  <form method="post" id="sede" class="card p-5 space-y-4" data-sede>
    <?= csrf_field() ?><input type="hidden" name="accion" value="sede">
    <h2 class="font-semibold">Ubicación del gimnasio</h2>
    <p class="text-sm text-dark/60">Se usa para el mensaje de bienvenida cuando el alumno abre la app cerca del gimnasio.</p>
    <div class="grid sm:grid-cols-2 gap-4">
      <div><label class="label">Nombre de la sede</label><input name="nombre" class="input" value="<?= e($sede['nombre'] ?? '') ?>"></div>
      <div><label class="label">Dirección</label><input name="direccion" class="input" value="<?= e($sede['direccion'] ?? '') ?>"></div>
      <div><label class="label">Latitud</label><input name="lat" class="input" value="<?= e($sede['lat'] ?? '') ?>" data-lat></div>
      <div><label class="label">Longitud</label><input name="lng" class="input" value="<?= e($sede['lng'] ?? '') ?>" data-lng></div>
      <div><label class="label">Radio (metros)</label><input type="number" name="radio_m" min="30" max="1000" class="input" value="<?= (int) ($sede['radio_m'] ?? 100) ?>"></div>
      <div class="flex items-end"><button type="button" class="btn-secondary w-full" data-mi-ubicacion><?= icon('location', 'w-4 h-4') ?>Usar mi ubicación actual</button></div>
    </div>
    <p class="text-xs text-dark/50">Tip: presiona "Usar mi ubicación" estando dentro del gimnasio. O copia las coordenadas desde Google Maps (clic derecho sobre el punto).</p>
    <button class="btn-primary">Guardar ubicación</button>
  </form>
<?php endif; ?>

<?php if ($tab === 'general'): ?>
  <form method="post" id="ajustes" class="card p-5 space-y-4">
    <?= csrf_field() ?><input type="hidden" name="accion" value="ajustes">
    <h2 class="font-semibold">Ajustes</h2>
    <div class="grid sm:grid-cols-3 gap-4">
      <?php foreach (['sesion_max_min' => 'Máx. sesión con coach (min)', 'plantilla_objetivo_min' => 'Objetivo plantillas (min)', 'transicion_seg' => 'Transición entre ejercicios (s)', 'calif_minimo' => 'Mín. evaluaciones para promedio',
          'alerta_inactivo_dias' => 'Alerta sin venir (días)', 'alerta_estancado_sesiones' => 'Estancado tras (sesiones)', 'alerta_vence_dias' => 'Aviso de vencimiento (días)', 'video_max_mb' => 'Máx. video (MB)', 'foto_max_mb' => 'Máx. foto (MB)',
          'otp_ttl_seg' => 'Vigencia del código (s)', 'remember_days' => 'Sesión recordada (días)'] as $k => $l): ?>
        <div><label class="label"><?= e($l) ?></label><input type="number" name="<?= $k ?>" class="input" value="<?= e(setting($k)) ?>"></div>
      <?php endforeach; ?>
    </div>
    <div>
      <label class="label" for="app_plan_libre">Alumnos con plan libre (sin coach)</label>
      <select id="app_plan_libre" name="app_plan_libre" class="input">
        <option value="limitado" <?= setting('app_plan_libre', 'limitado') === 'limitado' ? 'selected' : '' ?>>Entrenos del gimnasio y muro, sin coach</option>
        <option value="bloqueado" <?= setting('app_plan_libre', 'limitado') === 'bloqueado' ? 'selected' : '' ?>>No usan la app</option>
      </select>
      <p class="text-xs text-dark/55 mt-1">El tipo de cada plan se define en Planes. Los semipersonalizados tienen la app completa.</p>
    </div>
    <label class="toggle"><input type="checkbox" name="sesion_unica_alumnos" value="1" <?= setting('sesion_unica_alumnos', '1') === '1' ? 'checked' : '' ?>><span><b class="font-medium">Alumnos: una sola sesión activa</b><br><span class="text-sm text-dark/60">Al entrar desde otro dispositivo se cierra la sesión anterior. Evita que se compartan cuentas.</span></span></label>
    <label class="toggle"><input type="checkbox" name="sesion_unica_staff" value="1" <?= setting('sesion_unica_staff', '0') === '1' ? 'checked' : '' ?>><span><b class="font-medium">Staff: una sola sesión activa</b><br><span class="text-sm text-dark/60">Desactivado por defecto: los coaches suelen usar celular y computador.</span></span></label>
    <label class="toggle"><input type="checkbox" name="muro_activo" value="1" <?= setting('muro_activo') === '1' ? 'checked' : '' ?>><span>Muro social activo</span></label>
    <label class="toggle"><input type="checkbox" name="reporte_mensual_activo" value="1" <?= setting('reporte_mensual_activo') === '1' ? 'checked' : '' ?>><span>Enviar resumen mensual por correo a los alumnos</span></label>
    <button class="btn-primary">Guardar ajustes</button>
  </form>
<?php endif; ?>

<?php if ($tab === 'muro'): ?>
  <form method="post" class="card p-5 space-y-3">
    <?= csrf_field() ?><input type="hidden" name="accion" value="muro">
    <h2 class="font-semibold">Estados del muro</h2>
    <p class="text-sm text-dark/60">Los alumnos no escriben texto libre: eligen uno de estos estados. Uno por línea (máx. 60 caracteres, hasta 40 estados). El estado automático de la rutina ("Hoy: pierna, glúteos") se sigue generando solo.</p>
    <textarea name="muro_estados" rows="12" class="input text-sm"><?= e(setting('muro_estados')) ?></textarea>
    <div class="pt-2 max-w-xs">
      <label class="label" for="muro_posts_dia">Publicaciones por alumno al día</label>
      <input id="muro_posts_dia" type="number" name="muro_posts_dia" min="1" max="10" class="input" value="<?= (int) setting('muro_posts_dia', '1') ?>">
      <p class="text-xs text-dark/55 mt-1">Cuenta también las que el alumno borra. El estado automático de la rutina no cuenta.</p>
    </div>
    <button class="btn-primary">Guardar</button>
  </form>
<?php endif; ?>

<?php if ($tab === 'musica'): ?>
  <form method="post" id="spotify" class="card p-5 space-y-4">
    <?= csrf_field() ?><input type="hidden" name="accion" value="spotify">
    <div class="flex items-center justify-between gap-3"><h2 class="font-semibold">Buscador de canciones (Spotify)</h2>
      <span class="chip <?= $spotifyOk ? 'chip-ok' : 'chip-neutral' ?>"><?= $spotifyOk ? 'Spotify activo' : 'Usando catálogo de respaldo' ?></span></div>
    <p class="text-sm text-dark/60">El buscador de canciones de la Jungla funciona siempre. Con credenciales de Spotify los resultados vienen de Spotify; sin ellas (o si Spotify falla) usa el catálogo público de Apple y el botón abre la canción en Spotify.</p>
    <details class="text-sm text-dark/70"><summary class="cursor-pointer text-primary">Cómo obtener las credenciales</summary>
      <ol class="mt-2 space-y-1.5 list-decimal pl-5">
        <li>Entra a developer.spotify.com/dashboard con una cuenta <b>Premium</b> (Spotify lo exige para apps en modo desarrollo).</li>
        <li>Crea una app: en "Which API/SDKs" marca <b>Web API</b>. Como Redirect URI pon la URL de GorilaGym (no se usa, pero es obligatoria).</li>
        <li>Copia el <b>Client ID</b> y el <b>Client secret</b> aquí.</li>
      </ol>
      <p class="mt-2 text-dark/55">Solo se usa la búsqueda (máx. 10 resultados). Los alumnos no inician sesión en Spotify.</p>
    </details>
    <div class="grid sm:grid-cols-2 gap-4">
      <div><label class="label">Client ID</label><input name="spotify_client_id" class="input font-mono text-sm" value="<?= e(setting('spotify_client_id')) ?>" autocomplete="off"></div>
      <div><label class="label">Client secret</label><input type="password" name="spotify_client_secret" class="input font-mono text-sm" placeholder="<?= setting('spotify_client_secret') ? '•••••••• (guardado)' : '' ?>" autocomplete="new-password"></div>
    </div>
    <div class="flex flex-wrap gap-2">
      <button class="btn-primary">Guardar y probar</button>
      <?php if (setting('spotify_client_id')): ?><button class="btn-ghost" name="quitar" value="1" data-confirm="¿Quitar credenciales? Se usará el catálogo de respaldo.">Quitar credenciales</button><?php endif; ?>
    </div>
  </form>
<?php endif; ?>

<?php if ($tab === 'salud'): ?>
  <form method="post" id="cuestionario" class="card p-5 space-y-3">
    <?= csrf_field() ?><input type="hidden" name="accion" value="cuestionario">
    <h2 class="font-semibold">Cuestionario de salud</h2>
    <p class="text-sm text-dark/60">Una pregunta por línea (respuesta Sí/No). Es un borrador base: conviene validarlo con un profesional y con asesoría legal.</p>
    <textarea name="cuestionario_salud" rows="7" class="input text-sm"><?= e($preg) ?></textarea>
    <button class="btn-secondary">Guardar cuestionario</button>
  </form>
<?php endif; ?>

<?php if ($tab === 'entrada'): ?>
  <section id="entrada_token" class="card p-5">
    <h2 class="font-semibold">Tag de la entrada</h2>
    <p class="text-sm text-dark/60 mt-1">Al escanearlo el alumno hace check-in y ve "<?= e(setting('brand_bienvenida')) ?>".</p>
    <?php partial('shared/qr_nfc', ['qrUrl' => abs_url('', ['entrada' => setting('entrada_token')]), 'nombre' => 'Entrada']); ?>
    <form method="post" class="mt-4"><?= csrf_field() ?><input type="hidden" name="accion" value="entrada_token"><button class="btn-ghost btn-sm" data-confirm="El QR y el tag actuales dejarán de funcionar. ¿Continuar?">Regenerar código de entrada</button></form>
  </section>
<?php endif; ?>

<?php if ($tab === 'pruebas' && $esSuper): ?>
  <section id="demo" class="card p-5 space-y-3">
    <h2 class="font-semibold">Datos de prueba</h2>
    <p class="text-sm text-dark/60">Crea un alumno y un coach que entran con RUT y contraseña (sin correo), para probar en vivo. Si el catálogo está vacío, agrega además 5 máquinas, 7 ejercicios con alternativas y 2 plantillas. Todo se elimina con un clic.</p>
    <?php if ($demoClaves): ?>
      <div class="rounded-lg border border-primary/30 bg-primary/5 p-4 text-[15px] space-y-2">
        <div class="font-medium text-primary">Anota estas contraseñas, no se vuelven a mostrar</div>
        <div><span class="text-dark/60">Alumno:</span> RUT <b class="font-mono"><?= e($demoClaves['alumno']['rut']) ?></b> · contraseña <b class="font-mono select-all"><?= e($demoClaves['alumno']['clave']) ?></b></div>
        <div><span class="text-dark/60">Coach:</span> RUT <b class="font-mono"><?= e($demoClaves['coach']['rut']) ?></b> · contraseña <b class="font-mono select-all"><?= e($demoClaves['coach']['clave']) ?></b></div>
        <div class="text-sm text-dark/60">En el login: escribe el RUT y la contraseña.</div>
      </div>
    <?php endif; ?>
    <?php if ($demo): ?>
      <p class="text-sm"><span class="chip chip-ok">Activos</span> Alumno 11.111.111-1 · Coach 22.222.222-2<?= !empty($demo['ejercicios']) ? ' · catálogo de ejemplo' : '' ?></p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="demo_eliminar"><button class="btn-ghost btn-sm text-danger" data-confirm="¿Eliminar los usuarios de prueba, su historial y el catálogo de ejemplo?">Eliminar datos de prueba</button></form>
    <?php else: ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="demo_crear"><button class="btn-secondary">Crear alumno y coach de prueba</button></form>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($tab === 'sistema'): ?>
  <section id="sistema" class="card p-5 space-y-3 text-sm">
    <h2 class="font-semibold text-[15px]">Sistema</h2>
    <div class="flex items-center gap-2"><span class="chip <?= $cronOk ? 'chip-ok' : 'chip-danger' ?>"><?= $cronOk ? 'Cron funcionando' : 'Cron sin ejecutarse' ?></span><span class="text-dark/60">Última ejecución: <?= e($cron ?: 'nunca') ?></span></div>
    <div><div class="label">Línea para el cron del hosting (cada minuto)</div><input class="input font-mono text-xs" readonly value="* * * * * php <?= e(PRIVATE_DIR) ?>/cron/cron.php >/dev/null 2>&1"></div>
    <div><div class="label">Si el hosting no permite cron por consola, usa esta URL en un "cron por URL" cada minuto</div><input class="input font-mono text-xs" readonly value="<?= e(abs_url('api/cron', ['token' => config('cron_token')])) ?>"></div>
    <p class="text-dark/55">Sin cron, el aviso de fin de descanso solo funciona con la app abierta y no hay respaldos automáticos.</p>
    <form method="post" class="pt-2"><?= csrf_field() ?><input type="hidden" name="accion" value="probar_correo"><button class="btn-secondary btn-sm"><?= icon('mail', 'w-4 h-4') ?>Enviar correo de prueba</button></form>
    <p class="text-dark/55">Si el correo llega a spam: configura SPF, DKIM y DMARC del dominio en el hosting.</p>
    <div class="text-dark/50 pt-2">Versión <?= APP_VERSION ?> · PHP <?= PHP_VERSION ?> · SQLite <?= e(db()->query('select sqlite_version()')->fetchColumn()) ?></div>
  </section>
<?php endif; ?>

</div>
