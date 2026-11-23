<?php partial('shared/head', ['t' => 'Usuarios del staff', 'actions' => '<a href="' . e(url('admin/usuario')) . '" class="btn-primary btn-sm">' . icon('add', 'w-4 h-4') . 'Nuevo</a>']); ?>
<div class="card overflow-x-auto">
  <table class="table">
    <thead><tr><th>Nombre</th><th>Rol</th><th>Documento</th><th>Correo</th><th>Último acceso</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="<?= $r['activo'] ? '' : 'opacity-50' ?>">
          <td class="font-medium"><?= e(nombre_completo($r)) ?></td>
          <td><span class="chip <?= $r['rol'] === 'superadmin' ? 'chip-ok' : 'chip-neutral' ?>"><?= e(rol_label($r['rol'])) ?></span></td>
          <td><?= e(doc_display($r)) ?></td><td><?= e($r['email']) ?></td>
          <td class="text-dark/60"><?= $r['last_login'] ? e(hace($r['last_login'])) : 'nunca' ?></td>
          <td class="text-right"><a class="text-primary" href="<?= e(url('admin/usuario', ['id' => $r['id']])) ?>">Editar</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
