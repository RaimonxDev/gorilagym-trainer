<?php partial('shared/head', ['t' => 'Planes', 'sub' => 'Precios, horarios y tipo de plan. Sin reservas: el alumno entra dentro del horario de su plan.', 'actions' => '<a href="' . e(url('admin/plan')) . '" class="btn-primary btn-sm">' . icon('add', 'w-4 h-4') . 'Nuevo plan</a>']); ?>
<div class="card overflow-x-auto">
  <table class="table">
    <thead><tr><th>Plan</th><th>Tipo</th><th class="text-right">Mensual</th><th class="text-right">Trimestral</th><th class="text-right">Semestral</th><th class="text-right">Anual</th><th>Horario</th><th class="text-right">Vigentes</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $p): ?>
        <tr class="<?= $p['activo'] ? '' : 'opacity-50' ?>">
          <td><div class="font-medium"><?= e($p['nombre']) ?></div><?php if ($p['requiere_estudiante']): ?><div class="text-xs text-dark/55">Requiere acreditar estudiante</div><?php endif; ?><?php if ($p['beneficio']): ?><div class="text-xs text-dark/55"><?= e($p['beneficio']) ?> (<?= e($p['beneficio_modalidades']) ?>)</div><?php endif; ?></td>
          <td><span class="chip <?= $p['tipo'] === 'coach' ? 'chip-ok' : 'chip-neutral' ?>"><?= $p['tipo'] === 'coach' ? 'Con coach' : 'Libre' ?></span></td>
          <td class="text-right tabular-nums"><?= money($p['precio_mensual']) ?></td><td class="text-right tabular-nums"><?= money($p['precio_trimestral']) ?></td>
          <td class="text-right tabular-nums"><?= money($p['precio_semestral']) ?></td><td class="text-right tabular-nums"><?= money($p['precio_anual']) ?></td>
          <td class="text-sm text-dark/70"><?= e($p['horario']) ?></td>
          <td class="text-right"><?= (int) $p['vigentes'] ?></td>
          <td class="text-right"><a class="text-primary" href="<?= e(url('admin/plan', ['id' => $p['id']])) ?>">Editar</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<form method="post" action="<?= e(url('admin/plan')) ?>" class="mt-6 card p-4 flex flex-wrap items-end gap-3 max-w-md">
  <?= csrf_field() ?><input type="hidden" name="accion" value="pase">
  <div class="flex-1"><label class="label">Precio del Pase Diario</label><input name="pase_diario_precio" class="input" inputmode="numeric" value="<?= e($pase) ?>" placeholder="Por definir" data-money></div>
  <button class="btn-secondary">Guardar</button>
</form>
