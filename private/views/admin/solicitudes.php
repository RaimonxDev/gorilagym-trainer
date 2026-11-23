<?php partial('shared/head', ['t' => 'Solicitudes de eliminación de datos', 'sub' => 'Al ejecutar se borran datos personales y de salud. Se conservan registros anónimos de asistencia y entrenamiento.']); ?>
<div class="card divide-y divide-dark/5">
  <?php if (!$rows): ?><p class="p-4 text-sm text-dark/55">Sin solicitudes.</p><?php endif; ?>
  <?php foreach ($rows as $s): ?>
    <div class="px-4 py-4">
      <div class="flex justify-between"><b class="font-medium"><?= e(trim($s['nombre'] . ' ' . $s['apellido'])) ?></b><span class="chip <?= $s['estado'] === 'pendiente' ? 'chip-warn' : 'chip-neutral' ?>"><?= e($s['estado']) ?></span></div>
      <div class="text-sm text-dark/60"><?= e(fecha($s['created_at'])) ?> · <?= e($s['email'] ?? '') ?></div>
      <?php if ($s['motivo']): ?><div class="text-sm mt-1">"<?= e($s['motivo']) ?>"</div><?php endif; ?>
      <?php if ($s['estado'] === 'pendiente'): ?>
        <form method="post" class="mt-3 flex flex-wrap items-center gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
          <input name="confirmar" class="input w-40 py-1.5 text-sm" placeholder="Escribe ELIMINAR">
          <button name="accion" value="ejecutar" class="btn-danger btn-sm">Eliminar datos</button>
          <button name="accion" value="rechazar" class="btn-ghost btn-sm">Rechazar</button></form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
