<?php partial('shared/head', $soloPlantillas
    ? ['t' => 'Entrenos del gimnasio', 'sub' => 'Elige el de hoy y regístralo serie a serie. Si una máquina está ocupada, puedes cambiar por una alternativa o dejarla para el final.', 'back' => url('inicio')]
    : ['t' => 'Arma tu rutina', 'sub' => 'Parte desde una plantilla del gimnasio o desde cero. Después puedes cambiar ejercicios, series y orden.', 'back' => url('inicio')]); ?>
<form method="post" class="space-y-3">
  <?= csrf_field() ?>
  <?php foreach ($plantillas as $p): ?>
    <button name="plantilla_id" value="<?= (int) $p['id'] ?>" class="w-full text-left card p-4 hover:border-primary/40">
      <div class="font-medium"><?= e($p['nombre']) ?></div>
      <div class="text-sm text-dark/60"><?= (int) $p['n'] ?> ejercicios<?= $p['nivel'] !== 'todos' ? ' · ' . e($p['nivel']) : '' ?><?= $p['descripcion'] ? ' · ' . e($p['descripcion']) : '' ?></div>
    </button>
  <?php endforeach; ?>
  <?php if (!$plantillas): ?><p class="text-sm text-dark/60 text-center py-6">Todavía no hay entrenos cargados. Pregunta en recepción.</p><?php endif; ?>
  <?php if (!$soloPlantillas): ?><button name="plantilla_id" value="0" class="w-full btn-secondary">Empezar desde cero</button><?php endif; ?>
</form>
