<?php partial('shared/head', ['t' => 'Alumnos', 'sub' => $total . ' resultados', 'actions' => (can('alumnos.editar') ? '<a href="' . e(url('admin/usuario', ['rol' => 'alumno'])) . '" class="btn-secondary btn-sm">' . icon('add', 'w-4 h-4') . 'Nuevo</a>' : '') . (can_any(['planes.gestionar', 'reportes.gimnasio']) ? '<a href="' . e(url('admin/exportar')) . '" class="btn-ghost btn-sm">' . icon('download', 'w-4 h-4') . 'CSV</a>' : '')]); ?>
<form class="flex flex-wrap gap-2 mb-4">
  <input type="hidden" name="r" value="coach/alumnos">
  <input name="q" value="<?= e($q) ?>" class="input flex-1 min-w-[200px]" placeholder="Nombre, RUT o correo">
  <select name="f" class="input w-auto"><?php foreach (['' => 'Todos', 'vigentes' => 'Plan vigente', 'vencidos' => 'Sin plan vigente', 'sin_correo' => 'Sin correo', 'inactivos' => 'Inactivos'] as $k => $l): ?><option value="<?= $k ?>" <?= $f === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  <button class="btn-secondary">Filtrar</button>
</form>
<div class="card divide-y divide-dark/5">
  <?php if (!$rows): ?><p class="p-4 text-sm text-dark/55">Sin resultados.</p><?php endif; ?>
  <?php foreach ($rows as $r): $m = $r['memb']; ?>
    <a href="<?= e(url('coach/alumno', ['id' => $r['id']])) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-dark/[0.02]">
      <?= avatar($r) ?>
      <div class="flex-1 min-w-0">
        <div class="font-medium truncate"><?= e(nombre_completo($r)) ?><?= !$r['activo'] ? ' <span class="chip chip-neutral ml-1">inactivo</span>' : '' ?></div>
        <div class="text-sm text-dark/55 truncate"><?= e(doc_display($r)) ?><?= $r['email'] ? '' : ' · <span class="text-warning">sin correo</span>' ?></div>
      </div>
      <?php if ($m): ?><span class="chip <?= $m['estado'] === 'vigente' ? 'chip-ok' : ($m['estado'] === 'pausada' ? 'chip-warn' : 'chip-danger') ?>"><?= e($m['plan_nombre']) ?> · <?= e($m['estado']) ?></span><?php else: ?><span class="chip chip-neutral">Sin plan</span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
<?php if ($total > 40): ?>
<div class="mt-4 flex justify-between text-sm">
  <?php if ($pag > 1): ?><a class="text-primary" href="<?= e(url('coach/alumnos', ['q' => $q, 'f' => $f, 'p' => $pag - 1])) ?>">← Anterior</a><?php else: ?><span></span><?php endif; ?>
  <?php if ($pag * 40 < $total): ?><a class="text-primary" href="<?= e(url('coach/alumnos', ['q' => $q, 'f' => $f, 'p' => $pag + 1])) ?>">Siguiente →</a><?php endif; ?>
</div>
<?php endif; ?>
