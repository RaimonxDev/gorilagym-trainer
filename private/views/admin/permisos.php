<?php partial('shared/head', ['t' => 'Permisos', 'sub' => 'Qué puede hacer cada rol. El super admin siempre tiene todo. Los cambios aplican de inmediato.']); ?>
<form method="post" data-permisos>
  <?= csrf_field() ?>
  <div class="card overflow-x-auto">
    <table class="table">
      <thead><tr><th class="w-1/2">Permiso</th><th class="text-center">Super admin</th><?php foreach ($roles as $r): ?><th class="text-center"><?= e(rol_label($r)) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
        <?php $g = null; foreach ($perms as $p): if ($p['grupo'] !== $g): $g = $p['grupo']; ?>
          <tr class="bg-dark/[0.03]"><td colspan="<?= 2 + count($roles) ?>" class="text-xs uppercase tracking-wide text-dark/55 font-medium"><?= e($g) ?></td></tr>
        <?php endif; ?>
          <tr>
            <td><div class="font-medium flex items-center gap-1.5"><?= e($p['nombre']) ?><?php if ($p['critico']): ?><span class="chip chip-warn">crítico</span><?php endif; ?></div><div class="text-sm text-dark/55"><?= e($p['descripcion']) ?></div></td>
            <td class="text-center"><span class="inline-flex text-primary" title="Siempre activo"><?= icon('lock', 'w-4 h-4') ?></span></td>
            <?php foreach ($roles as $r):
              $bloq = ($r === 'alumno') !== ($p['ambito'] === 'alumno'); ?>
              <td class="text-center">
                <?php if ($bloq): ?><span class="text-dark/20">—</span>
                <?php else: ?><label class="switch"><input type="checkbox" name="p[<?= $r ?>][<?= e($p['key']) ?>]" value="1" <?= isset($act[$r][$p['key']]) ? 'checked' : '' ?> <?= $p['critico'] ? 'data-critico' : '' ?>><span></span></label><?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="mt-4 card p-4 hidden" data-critico-box>
    <label class="toggle"><input type="checkbox" name="confirmo_criticos" value="1"><span class="text-sm text-warning">Entiendo que estoy delegando permisos críticos (sistema, datos sensibles). Quedará registrado en auditoría.</span></label>
  </div>
  <div class="mt-4 flex flex-wrap gap-2">
    <button class="btn-primary">Guardar permisos</button>
    <button class="btn-ghost" name="accion" value="restaurar" data-confirm="¿Restaurar todos los permisos a los valores por defecto?">Restaurar valores por defecto</button>
  </div>
</form>
