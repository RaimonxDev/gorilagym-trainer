<?php
$esAlumno = ($u['rol'] ?? $rolDef) === 'alumno';
partial('shared/head', ['t' => $title, 'back' => $esAlumno ? ($u ? url('coach/alumno', ['id' => $u['id']]) : url('coach/alumnos')) : url('admin/usuarios')]);
$tipo = $u['doc_tipo'] ?? 'rut';
?>
<form method="post" class="card p-5 grid sm:grid-cols-2 gap-4 max-w-2xl">
  <?= csrf_field() ?>
  <?php if (!$u): ?><input type="hidden" name="rol_ctx" value="<?= e($rolDef) ?>"><?php endif; ?>
  <div><label class="label">Nombre *</label><input name="nombre" class="input" required value="<?= e($u['nombre'] ?? '') ?>"></div>
  <div><label class="label">Apellido</label><input name="apellido" class="input" value="<?= e($u['apellido'] ?? '') ?>"></div>
  <div><label class="label">Tipo de documento</label>
    <select name="doc_tipo" class="input" data-doc-select><?php foreach (['rut' => 'RUT', 'pasaporte' => 'Pasaporte', 'dni' => 'DNI', 'otro' => 'Otro'] as $k => $l): ?><option value="<?= $k ?>" <?= $tipo === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div><label class="label">Documento *</label><input name="doc" class="input" required value="<?= e($u ? ($tipo === 'rut' ? rut_format($u['doc_num']) : $u['doc_num']) : '') ?>" <?= $tipo === 'rut' ? 'data-rut' : '' ?>><p class="hidden mt-1.5 text-sm text-danger" data-rut-error>RUT inválido</p></div>
  <div><label class="label">Correo <?= $esAlumno ? '' : '*' ?></label><input type="email" name="email" class="input" value="<?= e($u['email'] ?? '') ?>" <?= $esAlumno ? '' : 'required' ?>></div>
  <div><label class="label">Teléfono</label><input name="telefono" class="input" value="<?= e($u['telefono'] ?? '') ?>" inputmode="tel"></div>
  <div><label class="label">Rol</label>
    <select name="rol" class="input" <?= count($roles) === 1 ? 'disabled' : '' ?>><?php foreach ($roles as $r): ?><option value="<?= $r ?>" <?= ($u['rol'] ?? $rolDef) === $r ? 'selected' : '' ?>><?= e(rol_label($r)) ?></option><?php endforeach; ?></select>
    <?php if (count($roles) === 1): ?><input type="hidden" name="rol" value="<?= e($roles[0]) ?>"><?php endif; ?></div>
  <div class="flex items-end"><label class="toggle"><input type="checkbox" name="activo" value="1" <?= ($u['activo'] ?? 1) ? 'checked' : '' ?>><span>Activo</span></label></div>
  <div class="sm:col-span-2 flex flex-wrap gap-2"><button class="btn-primary">Guardar</button></div>
</form>
<?php if ($u && $u['password_hash']): ?>
<form method="post" class="mt-4"><?= csrf_field() ?><input type="hidden" name="accion" value="quitar_clave"><button class="btn-ghost btn-sm" data-confirm="¿Quitar la contraseña y cerrar todas sus sesiones? La próxima vez deberá crear una nueva con un código a su correo.">Quitar contraseña y cerrar sesiones</button></form>
<?php endif; ?>
<?php if ($u && $esAlumno && can_any(['planes.gestionar', 'planes.pausas', 'planes.acreditar'])): ?>
  <a class="mt-4 inline-block text-primary text-sm" href="<?= e(url('admin/membresia', ['alumno' => $u['id']])) ?>">Gestionar plan y membresías →</a>
<?php endif; ?>
