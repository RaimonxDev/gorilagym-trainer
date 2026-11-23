<?php partial('shared/head', ['t' => 'Sala en vivo', 'sub' => '<span data-hora></span> · se actualiza sola cada 15 s']); ?>
<div id="sala" class="space-y-6">
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    <div class="card p-4"><div class="text-sm text-dark/60">En la jungla</div><div class="mt-1 text-2xl font-semibold" data-presentes>—</div></div>
    <div class="card p-4"><div class="text-sm text-dark/60">Sesiones en curso</div><div class="mt-1 text-2xl font-semibold" data-nses>—</div></div>
    <div class="card p-4 col-span-2"><div class="text-sm text-dark/60">Grupos de hoy</div><div class="mt-1 flex flex-wrap gap-1.5" data-grupos></div></div>
  </div>
  <section><h2 class="section-title">Entrenando ahora</h2><div class="mt-2 grid md:grid-cols-2 gap-3" data-sesiones></div></section>
  <div class="grid md:grid-cols-2 gap-6">
    <section><h2 class="section-title">Máquinas ocupadas</h2><div class="mt-2 card divide-y divide-dark/5 text-sm" data-ocupacion></div></section>
    <section><h2 class="section-title">En mantención</h2><div class="mt-2 card divide-y divide-dark/5 text-sm" data-mantencion></div></section>
  </div>
</div>
