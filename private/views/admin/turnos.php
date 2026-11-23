<?php partial('shared/head', ['t' => 'Turnos del staff', 'sub' => 'Para reportes por turno y para saber quién está en sala.']); ?>
<form method="post" class="card p-5 grid sm:grid-cols-4 gap-3 items-end">
  <?= csrf_field() ?>
  <div class="sm:col-span-2"><label class="label">Persona</label><select name="user_id" class="input"><?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e(trim($s['nombre'] . ' ' . $s['apellido'])) ?> · <?= e(rol_label($s['rol'])) ?></option><?php endforeach; ?></select></div>
  <div><label class="label">Desde</label><input type="time" name="desde" class="input" value="07:00"></div>
  <div><label class="label">Hasta</label><input type="time" name="hasta" class="input" value="15:00"></div>
  <div class="sm:col-span-4 flex flex-wrap gap-2"><?php for ($d = 1; $d <= 7; $d++): ?><label class="pill"><input type="checkbox" name="dias[]" value="<?= $d ?>" <?= $d <= 5 ? 'checked' : '' ?>><span><?= dia_nombre($d) ?></span></label><?php endfor; ?></div>
  <div class="sm:col-span-4"><button class="btn-primary">Agregar turno</button></div>
</form>
<div class="mt-6 card overflow-x-auto">
  <table class="table"><thead><tr><th>Día</th><th>Horario</th><th>Persona</th><th></th></tr></thead>
    <tbody><?php if (!$rows): ?><tr><td colspan="4" class="text-dark/55">Sin turnos cargados.</td></tr><?php endif; ?>
      <?php foreach ($rows as $t): ?><tr><td><?= dia_nombre((int) $t['dia']) ?></td><td><?= e($t['desde']) ?>–<?= e($t['hasta']) ?></td><td><?= e(trim($t['nombre'] . ' ' . $t['apellido'])) ?></td>
        <td class="text-right"><form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="borrar"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="text-dark/40 hover:text-danger"><?= icon('delete', 'w-4 h-4') ?></button></form></td></tr><?php endforeach; ?></tbody>
  </table>
</div>
