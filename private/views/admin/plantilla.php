<?php partial('shared/head', ['t' => $p['nombre'], 'back' => url('admin/plantillas')]); ?>
<form method="post" class="card p-4 grid sm:grid-cols-[1fr_1fr_160px_auto_auto] gap-3 items-end mb-5">
  <?= csrf_field() ?>
  <div><label class="label">Nombre</label><input name="nombre" class="input" value="<?= e($p['nombre']) ?>"></div>
  <div><label class="label">Descripción</label><input name="descripcion" class="input" value="<?= e($p['descripcion']) ?>"></div>
  <div><label class="label">Nivel</label><select name="nivel" class="input"><?php foreach (['todos', 'principiante', 'intermedio', 'avanzado'] as $n): ?><option <?= $p['nivel'] === $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></div>
  <label class="toggle pb-2.5"><input type="checkbox" name="activo" value="1" <?= $p['activo'] ? 'checked' : '' ?>><span>Activa</span></label>
  <button class="btn-secondary">Guardar</button>
</form>

<div id="builder" data-ctx="plantilla" data-id="<?= (int) $p['id'] ?>" data-alumno="0" class="grid lg:grid-cols-5 gap-6">
  <div class="lg:col-span-3 space-y-4">
    <section class="card px-4 py-3" data-tiempo>
      <div class="flex items-center justify-between text-sm"><span class="text-dark/60">Tiempo estimado</span><span class="font-medium tabular-nums" data-total>—</span></div>
      <div class="mt-2 h-1.5 rounded-full bg-dark/10 overflow-hidden"><div class="h-full bg-primary transition-all" data-bar style="width:0"></div></div>
      <div class="hidden mt-3" data-recortes></div>
    </section>
    <div class="flex items-center gap-2"><button type="button" class="btn-ghost btn-sm lg:hidden" data-abrir-panel><?= icon('add', 'w-4 h-4') ?>Ejercicio</button><button type="button" class="btn-ghost btn-sm ml-auto text-dark/50" data-vaciar>Vaciar</button></div>
    <div data-lista class="space-y-3"></div>
  </div>
  <aside class="lg:col-span-2" data-panel>
    <div class="panel-sheet lg:sticky lg:top-6 card p-4">
      <div class="flex items-center justify-between mb-3"><h2 class="font-semibold">Agregar ejercicio</h2><button type="button" class="lg:hidden p-1 text-dark/50" data-cerrar-panel aria-label="Cerrar"><?= icon('close') ?></button></div>
      <input class="input" placeholder="Buscar ejercicio o máquina" data-buscar>
      <div class="mt-3 flex flex-wrap gap-1.5" data-grupos>
        <button type="button" class="pill-btn is-on" data-grupo="">Todos</button>
        <?php foreach ($grupos as $g): ?><button type="button" class="pill-btn" data-grupo="<?= (int) $g['id'] ?>"><?= e($g['nombre']) ?></button><?php endforeach; ?>
      </div>
      <div class="mt-3 max-h-[60vh] overflow-y-auto divide-y divide-dark/5 -mx-1" data-resultados></div>
    </div>
  </aside>
</div>
<form method="post" class="mt-8"><?= csrf_field() ?><input type="hidden" name="accion" value="borrar"><button class="btn-ghost btn-sm text-danger" data-confirm="¿Eliminar la plantilla?">Eliminar plantilla</button></form>
