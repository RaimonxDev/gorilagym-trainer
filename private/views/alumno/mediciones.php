<?php partial('shared/head', ['t' => 'Mediciones', 'sub' => 'Opcional. Solo tú y el staff autorizado las ven.', 'back' => url('progreso')]); ?>
<form method="post" class="card p-5 grid grid-cols-2 sm:grid-cols-4 gap-3">
  <?= csrf_field() ?>
  <div class="col-span-2 sm:col-span-4"><label class="label">Fecha</label><input type="date" name="fecha" class="input" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></div>
  <?php foreach (['peso' => 'Peso (kg)', 'cintura' => 'Cintura (cm)', 'cadera' => 'Cadera (cm)', 'pecho' => 'Pecho (cm)', 'brazo' => 'Brazo (cm)', 'muslo' => 'Muslo (cm)'] as $k => $l): ?>
    <div><label class="label"><?= $l ?></label><input name="<?= $k ?>" class="input" inputmode="decimal" placeholder="—"></div>
  <?php endforeach; ?>
  <div class="col-span-2 sm:col-span-4"><button class="btn-primary">Guardar</button></div>
</form>

<?php if (count($rows) > 1): ?>
<section class="mt-6"><h2 class="section-title">Peso corporal</h2>
  <div class="mt-2 card p-4"><div class="h-40" data-chart="line" data-unit="kg" data-json='<?= e(json_encode(array_values(array_map(fn($r) => ['x' => fecha($r['fecha'], 'd/m'), 'y' => (float) $r['peso']], array_reverse(array_filter($rows, fn($r) => $r['peso'] !== null)))))) ?>'></div></div>
</section>
<?php endif; ?>

<section class="mt-6">
  <div class="card overflow-x-auto">
    <table class="table">
      <thead><tr><th>Fecha</th><th>Peso</th><th>Cintura</th><th>Cadera</th><th>Pecho</th><th>Brazo</th><th>Muslo</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr><td><?= e(fecha($r['fecha'])) ?></td>
          <?php foreach (['peso', 'cintura', 'cadera', 'pecho', 'brazo', 'muslo'] as $k): ?><td class="tabular-nums"><?= $r[$k] !== null ? e(rtrim(rtrim(number_format((float) $r[$k], 1, ',', ''), '0'), ',')) : '—' ?></td><?php endforeach; ?>
          <td class="text-right"><form method="post"><?= csrf_field() ?><button name="borrar" value="<?= (int) $r['id'] ?>" class="text-dark/40 hover:text-danger" data-confirm="¿Borrar esta medición?" aria-label="Borrar"><?= icon('delete', 'w-4 h-4') ?></button></form></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" class="text-dark/50">Sin mediciones.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
