<?php partial('shared/head', ['t' => 'Pausas por lesión', 'sub' => 'Solo lesiones ocurridas dentro del recinto. Al cerrar la pausa, el vencimiento se extiende por los días pausados.']); ?>
<div class="card divide-y divide-dark/5">
  <?php if (!$rows): ?><p class="p-4 text-sm text-dark/55">No hay pausas registradas.</p><?php endif; ?>
  <?php foreach ($rows as $p): ?>
    <div class="px-4 py-4">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <a href="<?= e(url('coach/alumno', ['id' => $p['alumno_id']])) ?>" class="font-medium"><?= e(trim($p['nombre'] . ' ' . $p['apellido'])) ?></a>
        <span class="chip <?= ['pendiente' => 'chip-warn', 'activa' => 'chip-ok'][$p['estado']] ?? 'chip-neutral' ?>"><?= e($p['estado']) ?></span>
      </div>
      <div class="text-sm text-dark/60 mt-0.5">Plan <?= e($p['plan']) ?> · incidente <?= e(fecha($p['fecha_incidente'])) ?> · pausa desde <?= e(fecha($p['desde'])) ?><?= $p['hasta'] ? ' hasta ' . e(fecha($p['hasta'])) : '' ?><?= $p['dias'] ? ' · ' . (int) $p['dias'] . ' días' : '' ?></div>
      <div class="text-[15px] mt-2"><?= e($p['descripcion']) ?></div>
      <div class="text-xs text-dark/50 mt-1">Registró <?= e($p['registrado']) ?><?= $p['aprobado'] ? ' · resolvió ' . e($p['aprobado']) : '' ?></div>
      <?php if (in_array($p['estado'], ['pendiente', 'activa'], true)): ?>
        <form method="post" class="mt-3 flex gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <?php if ($p['estado'] === 'pendiente'): ?>
            <button name="accion" value="aprobar" class="btn-primary btn-sm">Aprobar y congelar plan</button>
            <button name="accion" value="rechazar" class="btn-ghost btn-sm">Rechazar</button>
          <?php else: ?>
            <button name="accion" value="cerrar" class="btn-secondary btn-sm" data-confirm="¿Cerrar la pausa hoy y extender el vencimiento?">Cerrar pausa (vuelve a entrenar)</button>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
