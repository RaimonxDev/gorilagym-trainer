<?php
$esAlumno = $u['rol'] === 'alumno';
$tabs = $esAlumno ? ['cuenta' => 'Mi cuenta', 'privacidad' => 'Privacidad', 'notificaciones' => 'Notificaciones', 'seguridad' => 'Seguridad']
    : ['cuenta' => 'Mi cuenta', 'notificaciones' => 'Notificaciones', 'seguridad' => 'Seguridad'];
if (!isset($tabs[$tab])) $tab = 'cuenta';
?>
<div class="flex items-center gap-4 mb-5">
  <?= avatar($u, 'w-16 h-16 text-xl') ?>
  <div class="min-w-0">
    <h1 class="text-[22px] leading-tight font-semibold truncate"><?= e(nombre_completo($u)) ?></h1>
    <p class="text-dark/60 text-[15px]"><?= e(doc_display($u)) ?> · <?= e(rol_label($u['rol'])) ?></p>
  </div>
</div>

<nav class="tabs" aria-label="Secciones del perfil">
  <?php foreach ($tabs as $k => $l): ?><a href="<?= e(url('perfil', ['tab' => $k])) ?>" class="tab <?= $tab === $k ? 'is-on' : '' ?>"><?= e($l) ?></a><?php endforeach; ?>
</nav>

<?php if ($tab === 'cuenta'): ?>
  <section class="card p-5">
    <h2 class="font-semibold">Foto de perfil</h2>
    <form method="post" enctype="multipart/form-data" class="mt-3 flex items-center gap-4" data-avatar-form>
      <?= csrf_field() ?><input type="hidden" name="accion" value="foto">
      <span data-avatar-preview><?= avatar($u, 'w-20 h-20 text-2xl') ?></span>
      <div class="flex flex-col gap-2">
        <label class="btn-secondary btn-sm cursor-pointer"><?= icon('image', 'w-4 h-4') ?>Elegir foto<input type="file" accept="image/*" class="sr-only" data-avatar-input></label>
        <input type="file" name="foto" class="hidden" data-avatar-final>
        <?php if (!empty($u['foto'])): ?><button class="text-sm text-dark/55 text-left" name="accion" value="quitar_foto" formnovalidate data-confirm="¿Quitar tu foto?">Quitar foto</button><?php endif; ?>
      </div>
    </form>
    <p class="mt-2 text-xs text-dark/50">Se recorta cuadrada y se reduce antes de subir. Se le quitan los datos de ubicación de la foto.</p>
  </section>

  <?php if ($esAlumno): ?>
  <section class="card p-5 mt-4">
    <h2 class="font-semibold">Mi plan</h2>
    <?php if ($memb): ?>
      <div class="mt-2 text-[15px]"><b class="font-medium">Plan <?= e($memb['plan_nombre']) ?></b> · <?= e($memb['modalidad']) ?></div>
      <div class="text-sm text-dark/65 mt-0.5"><?= $memb['estado'] === 'vigente' ? 'Vigente hasta el ' . e(fecha($memb['fecha_fin'])) . ' (' . $memb['dias_restantes'] . ' días)' : 'Estado: ' . e($memb['estado']) . ' · vence ' . e(fecha($memb['fecha_fin'])) ?></div>
      <div class="text-sm text-dark/65 mt-1">Horario: <?= e(plan_horario_texto((int) $memb['plan_id'])) ?></div>
      <?php if ($memb['requiere_estudiante']): ?><div class="text-sm mt-1 <?= $acred ? 'text-primary' : 'text-warning' ?>"><?= $acred ? 'Estudiante acreditado hasta ' . e(fecha($acred['vence'])) : 'Falta acreditar condición de estudiante' ?></div><?php endif; ?>
    <?php else: ?><p class="mt-2 text-sm text-dark/60">No tienes un plan registrado.</p><?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="card p-5 mt-4">
    <h2 class="font-semibold">Datos de contacto</h2>
    <div class="mt-3 text-sm"><span class="text-dark/60">Correo:</span> <?= e($u['email'] ?: '—') ?></div>
    <a href="<?= e(url('cuenta/correo')) ?>" class="text-sm text-primary mt-1 inline-block"><?= $u['email'] ? 'Cambiar correo' : 'Agregar correo' ?></a>
    <form method="post" class="mt-4 flex gap-2 items-end">
      <?= csrf_field() ?><input type="hidden" name="accion" value="datos">
      <div class="flex-1"><label class="label">Teléfono</label><input name="telefono" class="input" value="<?= e($u['telefono']) ?>" inputmode="tel" placeholder="+56 9 1234 5678"></div>
      <button class="btn-secondary">Guardar</button>
    </form>
  </section>

  <?php if ($esAlumno): ?>
  <section class="card p-5 mt-4">
    <h2 class="font-semibold">Accesos rápidos</h2>
    <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
      <a href="<?= e(url('ficha')) ?>" class="btn-secondary"><?= icon('file', 'w-4 h-4') ?>Ficha de ingreso</a>
      <a href="<?= e(url('mediciones')) ?>" class="btn-secondary"><?= icon('chart', 'w-4 h-4') ?>Mediciones</a>
      <a href="<?= e(url('instalar')) ?>" class="btn-secondary"><?= icon('phone', 'w-4 h-4') ?>Instalar app</a>
      <a href="<?= e(url('reporte')) ?>" class="btn-secondary"><?= icon('calendar', 'w-4 h-4') ?>Resumen mensual</a>
    </div>
  </section>
  <?php endif; ?>

<?php elseif ($tab === 'privacidad'): ?>
  <section class="card p-5">
    <h2 class="font-semibold">Comunidad y ubicación</h2>
    <form method="post" class="mt-4 space-y-4">
      <?= csrf_field() ?><input type="hidden" name="accion" value="preferencias">
      <label class="toggle"><input type="checkbox" name="muro_opt_in" value="1" <?= $u['muro_opt_in'] ? 'checked' : '' ?>><span><b class="font-medium">Aparecer en el muro</b><br><span class="text-dark/60 text-sm">Tu estado del día y tu canción. Solo lo ven alumnos y staff del gimnasio.</span></span></label>
      <label class="toggle"><input type="checkbox" name="muro_logros" value="1" <?= $u['muro_logros'] ? 'checked' : '' ?>><span><b class="font-medium">Publicar mis logros</b><br><span class="text-dark/60 text-sm">Récords y rachas, sin mostrar pesos.</span></span></label>
      <label class="toggle"><input type="checkbox" name="muro_mostrar_pesos" value="1" <?= $u['muro_mostrar_pesos'] ? 'checked' : '' ?>><span><b class="font-medium">Mostrar el peso en mis récords</b><br><span class="text-dark/60 text-sm">Desactivado por defecto.</span></span></label>
      <label class="toggle"><input type="checkbox" name="geo_opt_in" value="1" <?= $u['geo_opt_in'] ? 'checked' : '' ?>><span><b class="font-medium">Usar mi ubicación al abrir la app</b><br><span class="text-dark/60 text-sm">Solo con la app abierta, para darte la bienvenida y hacer check-in. Nunca en segundo plano.</span></span></label>
      <button class="btn-secondary">Guardar preferencias</button>
    </form>
  </section>

<?php elseif ($tab === 'notificaciones'): ?>
  <section class="card p-5">
    <h2 class="font-semibold">Notificaciones en este dispositivo</h2>
    <p class="text-sm text-dark/60 mt-1">Aviso de fin de descanso y avisos del gimnasio. En iPhone requiere tener la app en la pantalla de inicio.</p>
    <div class="mt-4 flex flex-wrap items-center gap-3"><button type="button" class="btn-secondary" data-push-toggle>Activar notificaciones</button><span class="text-sm text-dark/60" data-push-status></span></div>
  </section>
  <a href="<?= e(url('notificaciones')) ?>" class="mt-4 inline-block text-sm text-primary">Ver notificaciones recientes →</a>

<?php else: ?>
  <?php if ($esAlumno && setting('sesion_unica_alumnos', '1') === '1'): ?>
    <div class="mb-4 rounded-lg border border-dark/10 bg-white px-4 py-3 text-sm text-dark/70 flex gap-2"><?= icon('shield', 'w-5 h-5 shrink-0 text-primary') ?><span>Tu cuenta solo puede estar abierta en <b class="font-medium">un dispositivo</b> a la vez. Si entras desde otro, se cierra aquí.</span></div>
  <?php endif; ?>
  <section class="card p-5">
    <h2 class="font-semibold">Contraseña</h2>
    <p class="text-sm text-dark/60 mt-1">Al cambiarla se cierran las sesiones guardadas en otros dispositivos.</p>
    <form method="post" action="<?= e(url('cuenta/contrasena')) ?>" class="mt-4 space-y-3 max-w-md">
      <?= csrf_field() ?>
      <input type="text" name="usuario" value="<?= e($u['doc_tipo'] === 'rut' ? rut_format($u['doc_num']) : $u['doc_num']) ?>" autocomplete="username" class="sr-only" tabindex="-1" aria-hidden="true" readonly>
      <?php partial('shared/pwd', ['name' => 'actual', 'label' => 'Contraseña actual', 'ac' => 'current-password']); ?>
      <?php partial('shared/pwd', ['name' => 'nueva', 'label' => 'Nueva contraseña', 'ac' => 'new-password', 'minlength' => 8]); ?>
      <?php partial('shared/pwd', ['name' => 'repetir', 'label' => 'Repite la contraseña', 'ac' => 'new-password', 'minlength' => 8]); ?>
      <p class="text-xs text-dark/55">Mínimo 8 caracteres, con letras y números.</p>
      <button class="btn-secondary">Cambiar contraseña</button>
    </form>
  </section>

  <section class="card p-5 mt-4">
    <h2 class="font-semibold">Correo de recuperación</h2>
    <p class="text-sm text-dark/60 mt-1">Ahí te llegan los códigos si olvidas tu contraseña: <b class="font-medium"><?= e($u['email'] ? $u["email"] : 'sin correo') ?></b></p>
    <a href="<?= e(url('cuenta/correo')) ?>" class="btn-secondary mt-3 inline-flex"><?= $u['email'] ? 'Cambiar correo' : 'Agregar correo' ?></a>
  </section>

  <section class="card p-5 mt-4">
    <h2 class="font-semibold">Sesión</h2>
    <div class="mt-3 flex flex-wrap gap-2">
      <form method="post"><?= csrf_field() ?><button class="btn-secondary" name="accion" value="cerrar_todo">Cerrar sesión en todos los dispositivos</button></form>
      <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="btn-ghost">Cerrar sesión aquí</button></form>
    </div>
  </section>

  <?php if ($esAlumno): ?>
  <section class="mt-4 p-5 text-sm">
    <details>
      <summary class="cursor-pointer text-dark/60">Solicitar eliminación de mis datos</summary>
      <?php if ($solicitud): ?><p class="mt-3 text-dark/70">Tu solicitud del <?= e(fecha($solicitud['created_at'])) ?> está en revisión.</p>
      <?php else: ?>
        <form method="post" class="mt-3 space-y-2">
          <?= csrf_field() ?><input type="hidden" name="accion" value="eliminar">
          <p class="text-dark/65">El gimnasio eliminará tus datos personales y de salud. Se conservan registros anónimos de asistencia para estadísticas.</p>
          <textarea name="motivo" class="input" rows="2" placeholder="Motivo (opcional)"></textarea>
          <button class="btn-danger btn-sm" data-confirm="¿Enviar solicitud de eliminación de datos?">Enviar solicitud</button>
        </form>
      <?php endif; ?>
    </details>
  </section>
  <?php endif; ?>
<?php endif; ?>
