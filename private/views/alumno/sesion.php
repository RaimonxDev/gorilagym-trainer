<div id="sesion-app" data-id="<?= (int) $s['id'] ?>" data-focus="<?= (int) ($focus ?? 0) ?>" data-auto="<?= $autostart ? '1' : '0' ?>">
  <div class="mb-4">
    <a href="<?= e(url()) ?>" class="inline-flex items-center gap-1 text-sm text-dark/60 hover:text-primary"><?= icon('left', 'w-4 h-4') ?>Inicio</a>
    <div class="mt-2 flex items-start justify-between gap-3">
      <div class="min-w-0">
        <h1 class="text-[22px] font-semibold leading-tight truncate" data-titulo><?= e($s['titulo'] ?: 'Rutina de hoy') ?></h1>
        <p class="text-sm text-dark/60 mt-0.5" data-sub><?= e(nombre_completo($alumno)) ?></p>
      </div>
      <a href="<?= e(url('coach/rutina', ['sesion' => $s['id']])) ?>" class="hidden btn-secondary btn-sm" data-editar><?= icon('edit', 'w-4 h-4') ?>Editar</a>
    </div>
  </div>

  <!-- Barra de tiempo -->
  <div class="card px-4 py-3 mb-5" data-tiempo>
    <div class="flex items-center justify-between text-sm">
      <span class="text-dark/60" data-tiempo-label>Tiempo estimado</span>
      <span class="font-medium tabular-nums" data-tiempo-valor>—</span>
    </div>
    <div class="mt-2 h-1.5 rounded-full bg-dark/10 overflow-hidden"><div class="h-full bg-primary transition-all" style="width:0%" data-tiempo-bar></div></div>
  </div>

  <div data-aviso-coach class="hidden mb-5 rounded-lg border border-dark/10 bg-white px-4 py-3 text-sm"></div>

  <div data-items class="space-y-3">
    <div class="card p-6 text-center text-dark/50">Cargando rutina…</div>
  </div>

  <div class="mt-6 space-y-3" data-acciones-finales></div>
</div>

<!-- Overlay de descanso -->
<div id="rest" class="hidden fixed inset-0 z-40 bg-dark/95 text-light flex-col items-center justify-center px-6 pb-[env(safe-area-inset-bottom)]">
  <div class="text-sm uppercase tracking-wide text-light/60">Descanso</div>
  <div class="mt-2 text-7xl font-semibold tabular-nums" data-rest-time>1:30</div>
  <div class="mt-3 text-light/70 text-center" data-rest-next></div>
  <div class="mt-8 flex gap-3">
    <button type="button" class="btn bg-light/10 text-light hover:bg-light/20" data-rest-add>+15 s</button>
    <button type="button" class="btn bg-light text-dark hover:bg-white" data-rest-skip>Saltar</button>
  </div>
  <label class="mt-10 flex items-center gap-2 text-sm text-light/60"><input type="checkbox" data-wakelock class="accent-current"> Mantener pantalla encendida</label>
</div>

<!-- Modal genérico -->
<div id="modal" class="hidden fixed inset-0 z-50 bg-dark/40 items-end sm:items-center justify-center">
  <div class="w-full sm:max-w-md bg-white rounded-t-xl sm:rounded-xl max-h-[85vh] overflow-y-auto pb-[env(safe-area-inset-bottom)]" data-modal-body></div>
</div>
