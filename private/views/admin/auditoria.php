<?php partial('shared/head', ['t' => 'Auditoría']); ?>
<form class="flex gap-2 mb-4 max-w-md"><input type="hidden" name="r" value="admin/auditoria"><input name="q" value="<?= e($q) ?>" class="input" placeholder="Filtrar por acción, persona o detalle"><button class="btn-secondary">Buscar</button></form>
<div class="card overflow-x-auto">
  <table class="table text-sm">
    <thead><tr><th>Fecha</th><th>Quién</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr><td class="whitespace-nowrap"><?= e(fecha($r['created_at'], 'd/m/Y H:i')) ?></td><td class="whitespace-nowrap"><?= $r['nombre'] ? e(trim($r['nombre'] . ' ' . $r['apellido'])) : '—' ?></td>
          <td class="whitespace-nowrap"><?= e(str_replace('_', ' ', $r['accion'])) ?><?= $r['entidad'] ? ' <span class="text-dark/45">' . e($r['entidad']) . ' #' . (int) $r['entidad_id'] . '</span>' : '' ?></td>
          <td class="max-w-md truncate text-dark/65" title="<?= e($r['detalle']) ?>"><?= e(mb_substr((string) $r['detalle'], 0, 140)) ?></td><td class="text-dark/45"><?= e($r['ip']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<div class="mt-4 flex justify-between text-sm">
  <?php if ($pag > 1): ?><a class="text-primary" href="<?= e(url('admin/auditoria', ['q' => $q, 'p' => $pag - 1])) ?>">← Anterior</a><?php else: ?><span></span><?php endif; ?>
  <?php if (count($rows) === 50): ?><a class="text-primary" href="<?= e(url('admin/auditoria', ['q' => $q, 'p' => $pag + 1])) ?>">Siguiente →</a><?php endif; ?>
</div>
