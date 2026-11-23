<?php partial('shared/head', ['t' => 'Tu sesión con ' . $s['coach_nombre'], 'sub' => e(fecha($s['created_at'])) . ' · Es anónimo: el coach solo ve un promedio mensual.', 'back' => url('inicio')]); ?>
<form method="post" class="space-y-4">
  <?= csrf_field() ?>
  <?php foreach (['tecnica' => '¿Te corrigió la técnica?', 'atencion' => '¿Estuvo pendiente de ti?', 'rutina' => '¿La rutina fue adecuada para ti?'] as $k => $p): ?>
    <div class="card p-5">
      <div class="font-medium"><?= e($p) ?></div>
      <div class="mt-3 grid grid-cols-5 gap-2 rate">
        <?php for ($i = 1; $i <= 5; $i++): ?><label><input type="radio" name="<?= $k ?>" value="<?= $i ?>" required><span><?= $i ?></span></label><?php endfor; ?>
      </div>
      <div class="mt-1.5 flex justify-between text-xs text-dark/50"><span>Nada</span><span>Totalmente</span></div>
    </div>
  <?php endforeach; ?>
  <div class="card p-5"><label class="label">Comentario (opcional)</label><textarea name="comentario" rows="3" maxlength="500" class="input" placeholder="Algo que quieras destacar o mejorar"></textarea></div>
  <button class="btn-primary w-full">Enviar</button>
</form>
