<?php
$traspaso = null;
foreach ($notas as $n) if ($n['tipo'] === 'traspaso') {
    $traspaso = $n;
    break;
}
echo '<div class="mb-2">' . avatar($a, 'w-14 h-14 text-lg') . '</div>';
partial('shared/head', ['t' => nombre_completo($a), 'sub' => e(doc_display($a)) . ($a['email'] ? ' · ' . e($a['email']) : ' · <span class="text-warning">sin correo</span>') . ($a['telefono'] ? ' · ' . e($a['telefono']) : ''), 'back' => url('coach/alumnos'),
    'actions' => can('alumnos.editar') ? '<a class="btn-ghost btn-sm" href="' . e(url('admin/usuario', ['id' => $a['id']])) . '">' . icon('edit', 'w-4 h-4') . 'Editar</a>' : '']);
?>

<div class="grid lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-6">

    <!-- Acción principal -->
    <section class="card p-5">
      <?php if ($hoy): ?>
        <div class="flex items-center justify-between gap-3">
          <div><div class="font-semibold">Rutina de hoy</div><div class="text-sm text-dark/60"><?= $hoy['estado'] === 'en_curso' ? 'En curso' : 'Lista para empezar' ?></div></div>
          <div class="flex gap-2"><a href="<?= e(url('coach/rutina', ['sesion' => $hoy['id']])) ?>" class="btn-secondary btn-sm"><?= icon('edit', 'w-4 h-4') ?>Editar</a><a href="<?= e(url('sesion', ['id' => $hoy['id']])) ?>" class="btn-primary btn-sm">Ver sesión</a></div>
        </div>
      <?php elseif ($acceso && can('rutinas.armar')): ?>
        <form method="post" action="<?= e(url('coach/nueva')) ?>" class="space-y-3">
          <?= csrf_field() ?><input type="hidden" name="alumno_id" value="<?= (int) $a['id'] ?>">
          <div class="font-semibold">Armar rutina de hoy</div>
          <?php if ($sugerido): ?><p class="text-sm text-dark/65">Según sus últimas sesiones, le toca: <b class="font-medium text-primary"><?= e($sugerido) ?></b></p><?php endif; ?>
          <div class="flex flex-col sm:flex-row gap-2">
            <select name="plantilla_id" class="input sm:flex-1"><option value="">Desde cero</option><?php foreach ($plantillas as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option><?php endforeach; ?></select>
            <button class="btn-primary">Armar rutina</button>
          </div>
        </form>
      <?php else: ?>
        <div class="flex gap-2 text-warning text-sm"><?= icon('alert', 'w-5 h-5 shrink-0') ?><span><?= e($motivo) ?></span></div>
      <?php endif; ?>
    </section>

    <?php if ($traspaso): ?>
      <section class="rounded-lg border border-accent/30 bg-accent/5 p-4">
        <div class="text-xs uppercase tracking-wide text-accent font-medium">Nota de traspaso · <?= e($traspaso['autor']) ?> · <?= e(hace($traspaso['created_at'])) ?></div>
        <div class="mt-1 text-[15px]"><?= nl2br(e($traspaso['texto'])) ?></div>
      </section>
    <?php endif; ?>

    <?php if ($ficha !== null || can('alumnos.salud')): ?>
    <section>
      <div class="flex items-center justify-between"><h2 class="section-title">Ficha</h2><a class="text-sm text-primary" href="<?= e(url('ficha', ['alumno' => $a['id']])) ?>">Editar</a></div>
      <div class="mt-2 card p-4 text-[15px] space-y-1.5">
        <?php if (!$ficha): ?><p class="text-dark/55 text-sm">El alumno aún no completa su ficha de ingreso.</p>
        <?php else: $tags = tags_list($ficha['restricciones']); $si = array_filter(json_decode((string) $ficha['cuestionario'], true) ?: [], fn($r) => $r['r'] === 'si'); ?>
          <div><span class="text-dark/55">Objetivo:</span> <?= e($ficha['objetivo'] ?: '—') ?> · <span class="text-dark/55">Nivel:</span> <?= e($ficha['nivel']) ?> · <span class="text-dark/55">Días:</span> <?= (int) $ficha['dias_semana'] ?>/sem</div>
          <div><span class="text-dark/55">Restricciones:</span> <?php if ($tags): foreach ($tags as $t): ?><span class="chip chip-warn mr-1"><?= e($t) ?></span><?php endforeach; else: ?>ninguna<?php endif; ?></div>
          <?php if ($ficha['restricciones_detalle']): ?><div class="text-sm text-dark/70"><?= e($ficha['restricciones_detalle']) ?></div><?php endif; ?>
          <?php if ($si): ?><div class="mt-2 rounded-md bg-warning/5 border border-warning/20 px-3 py-2 text-sm text-warning"><b class="font-medium">Respondió "sí":</b> <?= e(implode(' · ', array_column($si, 'p'))) ?></div><?php endif; ?>
          <?php if (!$ficha['consentimiento_salud_at']): ?><div class="text-xs text-dark/50">Sin consentimiento de datos de salud registrado.</div><?php endif; ?>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <section>
      <div class="flex items-center justify-between"><h2 class="section-title">Historial</h2><a class="text-sm text-primary" href="<?= e(url('progreso', ['alumno' => $a['id']])) ?>">Ver progreso</a></div>
      <div class="mt-2 card divide-y divide-dark/5">
        <?php if (!$sesiones): ?><p class="p-4 text-sm text-dark/55">Sin sesiones aún.</p><?php endif; ?>
        <?php foreach ($sesiones as $s): ?>
          <a href="<?= e(url('sesion', ['id' => $s['id']])) ?>" class="flex items-center justify-between px-4 py-3">
            <div class="min-w-0"><div class="font-medium truncate"><?= e($s['grupos'] ?: ($s['titulo'] ?: 'Sesión')) ?></div><div class="text-sm text-dark/55"><?= e(fecha($s['created_at'])) ?><?= $s['coach_nombre'] ? ' · ' . e($s['coach_nombre']) : ' · libre' ?><?= $s['feedback'] ? ' · se sintió ' . (int) $s['feedback'] . '/5' : '' ?></div></div>
            <span class="text-sm text-dark/50"><?= e($s['estado']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if ($estancados): ?>
    <section>
      <h2 class="section-title">Ejercicios estancados</h2>
      <div class="mt-2 card divide-y divide-dark/5 text-sm">
        <?php foreach ($estancados as $e): ?><div class="flex justify-between px-4 py-2.5"><span><?= e($e['ejercicio']) ?></span><span class="text-dark/55"><?= kg($e['peso']) ?> · <?= (int) $e['sesiones'] ?> sesiones sin subir</span></div><?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if (can('alumnos.notas')): ?>
    <section>
      <h2 class="section-title">Notas del staff</h2>
      <form method="post" action="<?= e(url('coach/nota')) ?>" class="mt-2 card p-4 space-y-3">
        <?= csrf_field() ?><input type="hidden" name="alumno_id" value="<?= (int) $a['id'] ?>">
        <textarea name="texto" rows="2" class="input" placeholder="Ej: ojo rodilla izquierda, le toca subir peso en sentadilla" required></textarea>
        <div class="flex items-center justify-between gap-3">
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="tipo" value="traspaso" class="checkbox"> Nota de traspaso (la ve el próximo coach)</label>
          <button class="btn-secondary btn-sm">Guardar</button>
        </div>
      </form>
      <div class="mt-3 space-y-2">
        <?php foreach ($notas as $n): ?>
          <div class="card px-4 py-3 text-[15px]">
            <div class="flex justify-between text-xs text-dark/50"><span><?= $n['tipo'] === 'traspaso' ? 'Traspaso · ' : '' ?><?= e($n['autor']) ?> · <?= e(fecha($n['created_at'], 'd/m H:i')) ?></span>
              <?php if ((int) $n['autor_id'] === uid()): ?><form method="post" action="<?= e(url('coach/nota')) ?>"><?= csrf_field() ?><input type="hidden" name="alumno_id" value="<?= (int) $a['id'] ?>"><button name="borrar" value="<?= (int) $n['id'] ?>" class="hover:text-danger" data-confirm="¿Borrar nota?">Borrar</button></form><?php endif; ?></div>
            <div class="mt-1"><?= nl2br(e($n['texto'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>

  <!-- Lateral -->
  <aside class="space-y-6">
    <section class="card p-4 text-[15px]">
      <div class="flex items-center justify-between"><h2 class="font-semibold">Plan</h2>
        <?php if (can_any(['planes.gestionar', 'planes.pausas', 'planes.acreditar'])): ?><a class="text-sm text-primary" href="<?= e(url('admin/membresia', ['alumno' => $a['id']])) ?>">Gestionar</a><?php endif; ?></div>
      <?php if ($memb): ?>
        <div class="mt-2"><span class="chip <?= $memb['estado'] === 'vigente' ? 'chip-ok' : ($memb['estado'] === 'pausada' ? 'chip-warn' : 'chip-danger') ?>"><?= e($memb['plan_nombre']) ?> · <?= e($memb['estado']) ?></span></div>
        <div class="mt-2 text-sm text-dark/65"><?= e(ucfirst($memb['modalidad'])) ?> · vence <?= e(fecha($memb['fecha_fin'])) ?></div>
        <div class="text-sm text-dark/65"><?= e(plan_horario_texto((int) $memb['plan_id'])) ?></div>
        <?php if ($memb['requiere_estudiante']): ?><div class="text-sm mt-1 <?= $acred ? 'text-primary' : 'text-warning' ?>"><?= $acred ? 'Estudiante acreditado hasta ' . e(fecha($acred['vence'])) : 'Falta acreditar estudiante' ?></div><?php endif; ?>
      <?php else: ?><p class="mt-2 text-sm text-dark/55">Sin plan registrado.</p><?php endif; ?>
      <div class="mt-3 pt-3 border-t border-dark/5 text-sm text-dark/60">Último check-in: <?= $ultCheckin ? e(hace($ultCheckin)) : 'nunca' ?></div>
    </section>

    <section class="card p-4 text-sm space-y-1.5">
      <h2 class="font-semibold text-[15px]">Indicadores</h2>
      <div class="flex justify-between"><span class="text-dark/60">Adherencia (60 días)</span><span class="font-medium"><?= $adherencia['pct'] !== null ? $adherencia['pct'] . '%' : '—' ?></span></div>
      <div class="flex justify-between"><span class="text-dark/60">Cómo se siente (prom.)</span><span class="font-medium"><?= $feedback ? e($feedback) . '/5' : '—' ?></span></div>
      <div class="flex justify-between"><span class="text-dark/60">Racha</span><span class="font-medium"><?= semanas_seguidas((int) $a['id']) ?> semanas</span></div>
    </section>

    <?php if (can('alumnos.activar')): ?>
    <section class="card p-4">
      <h2 class="font-semibold text-[15px]"><?= $a['email'] ? 'Cambiar correo' : 'Activar cuenta' ?></h2>
      <p class="text-sm text-dark/60 mt-1">Verifica la identidad en persona antes de registrar el correo.</p>
      <form method="post" action="<?= e(url('coach/activar')) ?>" class="mt-3 space-y-2">
        <?= csrf_field() ?><input type="hidden" name="alumno_id" value="<?= (int) $a['id'] ?>">
        <input type="email" name="email" class="input" placeholder="correo@ejemplo.cl" value="<?= e($a['email']) ?>" required>
        <button class="btn-secondary w-full" data-confirm="¿Verificaste la identidad del alumno en persona?">Guardar correo</button>
      </form>
    </section>
    <?php endif; ?>

    <?php if ($pausas): ?>
    <section class="card p-4 text-sm"><h2 class="font-semibold text-[15px]">Pausas</h2>
      <?php foreach ($pausas as $p): ?><div class="mt-2"><span class="chip chip-neutral"><?= e($p['estado']) ?></span> <?= e(fecha($p['desde'])) ?><?= $p['hasta'] ? ' → ' . e(fecha($p['hasta'])) : '' ?></div><?php endforeach; ?>
    </section>
    <?php endif; ?>
  </aside>
</div>
