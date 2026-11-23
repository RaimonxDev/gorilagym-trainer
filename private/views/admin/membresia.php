<?php partial('shared/head', ['t' => 'Plan de ' . nombre_completo($a), 'sub' => e(doc_display($a)), 'back' => url('coach/alumno', ['id' => $a['id']])]);
$m = $editar; ?>
<div class="grid lg:grid-cols-2 gap-6">
  <div class="space-y-6">
    <section>
      <h2 class="section-title">Membresías</h2>
      <div class="mt-2 card divide-y divide-dark/5">
        <?php if (!$rows): ?><p class="p-4 text-sm text-dark/55">Sin membresías.</p><?php endif; ?>
        <?php foreach ($rows as $r):
          $regalo = $r['beneficio'] && in_array($r['modalidad'], explode(',', (string) $r['beneficio_modalidades']), true); ?>
          <div class="px-4 py-3">
            <div class="flex items-center justify-between gap-2">
              <div><span class="font-medium"><?= e($r['plan']) ?></span> · <?= e($r['modalidad']) ?><?= $r['pausada'] ? ' <span class="chip chip-warn">pausada</span>' : '' ?></div>
              <?php if (can('planes.gestionar')): ?><a class="text-sm text-primary" href="<?= e(url('admin/membresia', ['alumno' => $a['id'], 'editar' => $r['id']])) ?>">Editar</a><?php endif; ?>
            </div>
            <div class="text-sm text-dark/60"><?= e(fecha($r['fecha_inicio'])) ?> → <?= e(fecha($r['fecha_fin'])) ?> · <?= e($r['origen']) ?></div>
            <?php if ($r['notas']): ?><div class="text-sm text-dark/60"><?= e($r['notas']) ?></div><?php endif; ?>
            <?php if ($regalo && can('planes.gestionar')): ?>
              <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="accion" value="regalo"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" <?= $r['regalo_entregado_at'] ? 'checked' : '' ?> data-autosubmit> <?= e($r['beneficio']) ?> entregado<?= $r['regalo_entregado_at'] ? ' (' . e(fecha($r['regalo_entregado_at'])) . ')' : '' ?></label></form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if (can('planes.gestionar')): ?>
    <section>
      <h2 class="section-title"><?= $m ? 'Editar membresía' : 'Nueva membresía' ?></h2>
      <form method="post" class="mt-2 card p-5 grid sm:grid-cols-2 gap-3">
        <?= csrf_field() ?><input type="hidden" name="accion" value="<?= $m ? 'editar' : 'crear' ?>"><?php if ($m): ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><?php endif; ?>
        <div><label class="label">Plan</label><select name="plan_id" class="input"><?php foreach ($planes as $p): ?><option value="<?= (int) $p['id'] ?>" <?= ($m['plan_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option><?php endforeach; ?></select></div>
        <div><label class="label">Modalidad</label><select name="modalidad" class="input"><?php foreach (['mensual', 'trimestral', 'semestral', 'anual'] as $mo): ?><option value="<?= $mo ?>" <?= ($m['modalidad'] ?? '') === $mo ? 'selected' : '' ?>><?= ucfirst($mo) ?></option><?php endforeach; ?></select></div>
        <div><label class="label">Inicio</label><input type="date" name="fecha_inicio" class="input" required value="<?= e($m['fecha_inicio'] ?? date('Y-m-d')) ?>"></div>
        <div><label class="label">Término</label><input type="date" name="fecha_fin" class="input" value="<?= e($m['fecha_fin'] ?? '') ?>"><p class="text-xs text-dark/50 mt-1">Vacío = se calcula según modalidad.</p></div>
        <div class="sm:col-span-2"><label class="label">Notas</label><input name="notas" class="input" value="<?= e($m['notas'] ?? '') ?>"></div>
        <div class="sm:col-span-2 flex gap-2"><button class="btn-primary">Guardar</button><?php if ($m): ?><a href="<?= e(url('admin/membresia', ['alumno' => $a['id']])) ?>" class="btn-ghost">Cancelar</a><?php endif; ?></div>
      </form>
      <?php if ($m): ?>
        <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="accion" value="borrar"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn-ghost btn-sm text-danger" data-confirm="¿Eliminar esta membresía?">Eliminar membresía</button></form>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </div>

  <div class="space-y-6">
    <?php if (can('planes.pausas')): ?>
    <section>
      <h2 class="section-title">Pausa por lesión</h2>
      <p class="text-sm text-dark/60 mt-1">Solo procede por una lesión ocurrida dentro del recinto que impida seguir entrenando. No aplica por viajes, enfermedades ni motivos personales.</p>
      <?php if ($actual): ?>
      <form method="post" class="mt-2 card p-5 grid sm:grid-cols-2 gap-3">
        <?= csrf_field() ?><input type="hidden" name="accion" value="pausa">
        <div><label class="label">Fecha del incidente</label><input type="date" name="fecha_incidente" class="input" max="<?= date('Y-m-d') ?>" required></div>
        <div><label class="label">Pausa desde</label><input type="date" name="desde" class="input" value="<?= date('Y-m-d') ?>"></div>
        <div><label class="label">Hasta (si se sabe)</label><input type="date" name="hasta" class="input"></div>
        <div class="sm:col-span-2"><label class="label">Qué pasó</label><textarea name="descripcion" class="input" rows="2" required placeholder="Máquina, ejercicio, quién atendió, indicación médica"></textarea></div>
        <label class="sm:col-span-2 toggle"><input type="checkbox" name="en_recinto" value="1" required><span class="text-sm">Confirmo que la lesión ocurrió dentro del gimnasio.</span></label>
        <div class="sm:col-span-2"><button class="btn-secondary">Registrar pausa</button></div>
      </form>
      <?php else: ?><p class="mt-2 text-sm text-dark/55">El alumno no tiene membresía para pausar.</p><?php endif; ?>
      <?php if ($pausas): ?><div class="mt-3 card divide-y divide-dark/5 text-sm"><?php foreach ($pausas as $p): ?><div class="px-4 py-2.5"><span class="chip chip-neutral"><?= e($p['estado']) ?></span> <?= e(fecha($p['desde'])) ?><?= $p['hasta'] ? ' → ' . e(fecha($p['hasta'])) : '' ?><?= $p['dias'] ? ' · ' . (int) $p['dias'] . ' días' : '' ?><div class="text-dark/60 mt-1"><?= e($p['descripcion']) ?></div></div><?php endforeach; ?></div><?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (can('planes.acreditar')): ?>
    <section>
      <h2 class="section-title">Acreditación de estudiante</h2>
      <form method="post" class="mt-2 card p-5 grid sm:grid-cols-2 gap-3">
        <?= csrf_field() ?><input type="hidden" name="accion" value="acreditar">
        <div class="sm:col-span-2"><label class="label">Documento revisado</label><input name="documento" class="input" placeholder="Ej: certificado de alumno regular U. de Los Lagos 2026-2" required></div>
        <div><label class="label">Válido hasta</label><input type="date" name="vence" class="input" required value="<?= date('Y-m-d', strtotime('+6 months')) ?>"></div>
        <div class="flex items-end"><button class="btn-secondary w-full">Acreditar</button></div>
      </form>
      <?php if ($acred): ?><div class="mt-3 card divide-y divide-dark/5 text-sm"><?php foreach ($acred as $ac): ?><div class="px-4 py-2.5 flex justify-between"><span><?= e($ac['documento']) ?></span><span class="<?= $ac['vence'] >= date('Y-m-d') ? 'text-primary' : 'text-dark/50' ?>">hasta <?= e(fecha($ac['vence'])) ?> · <?= e($ac['validador']) ?></span></div><?php endforeach; ?></div><?php endif; ?>
    </section>
    <?php endif; ?>
  </div>
</div>
