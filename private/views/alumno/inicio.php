<?php
$estadoChip = ['vigente' => 'chip-ok', 'vencida' => 'chip-danger', 'pausada' => 'chip-warn', 'futura' => 'chip-neutral'];
?>
<?php if ($bienvenida): ?>
  <div class="mb-6 rounded-xl bg-primary text-light p-6 relative overflow-hidden" data-bienvenida>
    <img src="<?= asset('assets/img/brand/isotipo-claro.svg') ?>" alt="" class="absolute -right-6 -bottom-6 w-36 opacity-15">
    <div class="text-2xl font-semibold leading-snug"><?= e(setting('brand_bienvenida')) ?></div>
    <p class="mt-2 text-light/80">Check-in registrado a las <?= date('H:i') ?>.<?= $racha > 1 ? ' Llevas ' . $racha . ' semanas seguidas.' : '' ?></p>
    <?php if ($hoy): ?><a href="<?= e(url('sesion', ['id' => $hoy['id']])) ?>" class="mt-4 inline-flex btn bg-light text-primary hover:bg-white">Ver mi rutina de hoy</a><?php endif; ?>
  </div>
<?php endif; ?>

<div id="geo-welcome" class="hidden mb-6 rounded-xl bg-primary text-light p-6">
  <div class="text-xl font-semibold" data-geo-msg><?= e(setting('brand_bienvenida')) ?></div>
  <p class="mt-1 text-light/80 text-sm" data-geo-sub>Estás en el gimnasio.</p>
  <button type="button" class="mt-4 btn bg-light text-primary hover:bg-white" data-geo-confirm>Hacer check-in</button>
</div>

<div class="flex items-end justify-between gap-3">
  <div>
    <p class="text-dark/60 text-sm"><?= e(dia_nombre((int) date('N'))) ?> <?= date('j') ?></p>
    <h1 class="text-2xl font-semibold">Hola, <?= e($u['nombre']) ?></h1>
  </div>
  <?php if ($memb): ?>
    <span class="chip <?= $estadoChip[$memb['estado']] ?? 'chip-neutral' ?>">Plan <?= e($memb['plan_nombre']) ?> · <?= e($memb['estado']) ?></span>
  <?php endif; ?>
</div>

<?php if (!$acceso): ?>
  <div class="mt-5 rounded-lg border border-warning/30 bg-warning/5 px-4 py-3 text-sm text-warning flex gap-2"><?= icon('alert', 'w-5 h-5 shrink-0') ?><span><?= e($motivo) ?></span></div>
<?php elseif ($memb && $memb['estado'] === 'vigente' && $memb['dias_restantes'] <= (int) setting('alerta_vence_dias', '7')): ?>
  <div class="mt-5 rounded-lg border border-dark/10 bg-white px-4 py-3 text-sm flex gap-2"><?= icon('calendar', 'w-5 h-5 shrink-0 text-dark/50') ?><span>Tu plan vence el <?= e(fecha($memb['fecha_fin'])) ?>. Renueva en recepción para no perder tu racha.</span></div>
<?php endif; ?>

<?php if (!$ficha || empty($ficha['consentimiento_salud_at'])): ?>
  <a href="<?= e(url('ficha')) ?>" class="mt-4 flex items-center gap-3 rounded-lg border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-primary">
    <?= icon('file', 'w-5 h-5 shrink-0') ?><span class="flex-1">Completa tu ficha de ingreso (objetivo y restricciones) para que tu rutina sea segura.</span><?= icon('right', 'w-4 h-4') ?>
  </a>
<?php endif; ?>

<?php if ($pendCalif): ?>
  <a href="<?= e(url('calificar', ['sesion' => $pendCalif['id']])) ?>" class="mt-4 flex items-center gap-3 rounded-lg border border-dark/10 bg-white px-4 py-3 text-sm">
    <?= icon('star', 'w-5 h-5 shrink-0 text-accent') ?><span class="flex-1">¿Cómo estuvo tu sesión con <?= e($pendCalif['coach']) ?>? Son 3 preguntas.</span><?= icon('right', 'w-4 h-4 text-dark/40') ?>
  </a>
<?php endif; ?>

<section class="mt-7">
  <h2 class="section-title">Hoy</h2>
  <?php if ($hoy): ?>
    <a href="<?= e(url('sesion', ['id' => $hoy['id']])) ?>" class="mt-2 block card p-5 hover:border-primary/40 transition">
      <div class="flex items-start justify-between gap-3">
        <div>
          <div class="font-semibold text-lg"><?= e($hoy['titulo'] ?: 'Rutina de hoy') ?></div>
          <div class="text-sm text-dark/60 mt-0.5">
            <?= $hoy['coach_nombre'] ? 'Con ' . e($hoy['coach_nombre']) . ' · ' : '' ?><?= count($items) ?> ejercicios · ~<?= estimacion_min($items) ?> min
          </div>
        </div>
        <span class="chip <?= $hoy['estado'] === 'en_curso' ? 'chip-ok' : 'chip-neutral' ?>"><?= $hoy['estado'] === 'en_curso' ? 'En curso' : 'Lista' ?></span>
      </div>
      <ol class="mt-4 space-y-1.5 text-[15px]">
        <?php foreach (array_slice($items, 0, 6) as $i => $it): ?>
          <li class="flex items-center gap-3">
            <span class="w-5 text-right text-dark/40 text-sm"><?= $i + 1 ?></span>
            <span class="flex-1 truncate <?= $it['estado'] === 'hecho' ? 'line-through text-dark/40' : '' ?>"><?= e($it['nombre']) ?></span>
            <span class="text-sm text-dark/50"><?= (int) $it['series'] ?>×<?= (int) $it['reps_min'] ?><?= $it['reps_max'] != $it['reps_min'] ? '–' . (int) $it['reps_max'] : '' ?></span>
          </li>
        <?php endforeach; ?>
        <?php if (count($items) > 6): ?><li class="pl-8 text-sm text-dark/50">y <?= count($items) - 6 ?> más</li><?php endif; ?>
      </ol>
      <div class="mt-5 btn-primary w-full"><?= $hoy['estado'] === 'en_curso' ? 'Continuar' : 'Empezar' ?></div>
    </a>
  <?php elseif ($modo === 'libre'): ?>
    <div class="mt-2 card p-5">
      <p class="text-dark/70">Tu plan es sin coach: elige uno de los entrenos del gimnasio y registra tus series y pesos.</p>
      <a href="<?= e(url('rutina-libre')) ?>" class="mt-4 btn-primary w-full">Elegir entreno</a>
      <p class="mt-3 text-xs text-dark/50">¿Quieres que un coach te arme la rutina? Pregunta en recepción por los planes semipersonalizados.</p>
    </div>
  <?php else: ?>
    <div class="mt-2 card p-5">
      <?php if ($checkin): ?>
        <div class="flex items-center gap-2 text-primary font-medium"><?= icon('check', 'w-5 h-5') ?>Check-in a las <?= date('H:i', strtotime($checkin['created_at'])) ?></div>
        <p class="mt-1 text-dark/65">Tu coach te va a armar la rutina en un momento. Aparecerá aquí.</p>
      <?php else: ?>
        <p class="text-dark/70">Cuando llegues, acerca el celular al tag de la entrada o escanea el QR. Tu coach te arma la rutina y aparece aquí.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<section class="mt-8 grid grid-cols-2 gap-3">
  <a href="<?= e(url('progreso')) ?>" class="card p-4">
    <div class="text-sm text-dark/60">Racha</div>
    <div class="mt-1 text-2xl font-semibold"><?= $racha ?> <span class="text-base font-normal text-dark/60"><?= $racha === 1 ? 'semana' : 'semanas' ?></span></div>
  </a>
  <a href="<?= e(url('reporte')) ?>" class="card p-4">
    <div class="text-sm text-dark/60">Resumen mensual</div>
    <div class="mt-1 font-medium flex items-center gap-1"><?= e(mes_nombre((int) date('n', strtotime('first day of last month')))) ?> <?= icon('right', 'w-4 h-4') ?></div>
  </a>
</section>

<?php if ($ultimas): ?>
<section class="mt-8">
  <h2 class="section-title">Últimas sesiones</h2>
  <div class="mt-2 card divide-y divide-dark/5">
    <?php foreach ($ultimas as $s): ?>
      <a href="<?= e(url('sesion', ['id' => $s['id']])) ?>" class="flex items-center justify-between px-4 py-3">
        <div><div class="font-medium"><?= e($s['titulo'] ?: 'Sesión') ?></div><div class="text-sm text-dark/55"><?= e(fecha($s['created_at'])) ?><?= $s['coach_nombre'] ? ' · ' . e($s['coach_nombre']) : '' ?></div></div>
        <?= icon('right', 'w-4 h-4 text-dark/40') ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<div class="hidden" data-geo="<?= $u['geo_opt_in'] && !$checkin && $acceso ? '1' : '0' ?>"></div>
