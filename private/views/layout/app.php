<?php
$u = current_user();
$brand = setting('brand_nombre', 'Gorila Gym');
$title = isset($title) ? $title . ' · ' . $brand : $brand;
$nav = $nav ?? '';
$isStaff = $u && $u['rol'] !== 'alumno';
$logo = setting('brand_logo') ? media_url(setting('brand_logo')) : asset('assets/img/brand/logo-horizontal.svg');
$avisos = $u ? qa("SELECT * FROM avisos WHERE desde <= datetime('now','localtime') AND (hasta IS NULL OR hasta >= datetime('now','localtime')) ORDER BY id DESC LIMIT 2") : [];
$bare = $bare ?? false;
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="theme-color" content="<?= e(setting('brand_primary', '#283614')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= e($brand) ?>">
<link rel="manifest" href="<?= e(url('manifest')) ?>">
<link rel="icon" type="image/png" href="<?= asset('assets/img/icons/favicon.png') ?>">
<link rel="apple-touch-icon" href="<?= asset('assets/img/icons/apple-touch-icon.png') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<style>:root{--c-primary:<?= hex_rgb(setting('brand_primary', '#283614')) ?>;--c-hover:<?= hex_rgb(setting('brand_hover', '#606C38')) ?>;--c-dark:<?= hex_rgb(setting('brand_dark', '#111D13')) ?>;--c-light:<?= hex_rgb(setting('brand_light', '#F5F9E5')) ?>;--c-accent:<?= hex_rgb(setting('brand_accent', '#606C38')) ?>;--c-danger:<?= hex_rgb(setting('brand_danger', '#B3261E')) ?>;--c-warning:<?= hex_rgb(setting('brand_warning', '#A86B00')) ?>}</style>
</head>
<body class="bg-light text-dark antialiased min-h-screen <?= $isStaff ? 'lg:pl-60' : '' ?>"
      data-base="<?= e(base_path()) ?>"
      data-csrf="<?= e(csrf_token()) ?>"
      data-user="<?= (int) ($u['id'] ?? 0) ?>"
      data-rol="<?= e($u['rol'] ?? '') ?>"
      data-pwa-hint="<?= $u && !$u['pwa_hint_visto'] ? '1' : '0' ?>">

<?php if (!$bare && $u): ?>
  <?php if ($isStaff): ?>
  <aside class="hidden lg:flex fixed inset-y-0 left-0 w-60 flex-col border-r border-dark/10 bg-white/60 backdrop-blur">
    <div class="px-5 pt-6 pb-4"><img src="<?= e($logo) ?>" alt="<?= e($brand) ?>" class="h-9"></div>
    <nav class="flex-1 overflow-y-auto px-3 pb-6 text-[15px]">
      <?php partial('layout/nav_staff', ['nav' => $nav, 'variant' => 'side']); ?>
    </nav>
    <div class="border-t border-dark/10 px-5 py-4 text-sm">
      <div class="font-medium truncate"><?= e(nombre_completo($u)) ?></div>
      <div class="text-dark/60"><?= e(rol_label($u['rol'])) ?></div>
      <form method="post" action="<?= e(url('logout')) ?>" class="mt-2"><?= csrf_field() ?><button class="text-primary hover:underline">Cerrar sesión</button></form>
    </div>
  </aside>
  <?php endif; ?>

  <header class="sticky top-0 z-30 bg-light/90 backdrop-blur border-b border-dark/10 pt-[env(safe-area-inset-top)] <?= $isStaff ? 'lg:hidden' : '' ?>">
    <div class="mx-auto max-w-3xl px-4 h-14 flex items-center justify-between">
      <a href="<?= e(url()) ?>"><img src="<?= e($logo) ?>" alt="<?= e($brand) ?>" class="h-7"></a>
      <div class="flex items-center gap-1">
        <?php if ($isStaff): ?>
          <a href="<?= e(url('notificaciones')) ?>" class="p-2 rounded-md hover:bg-dark/5" aria-label="Notificaciones"><?= icon('bell') ?></a>
          <button type="button" class="p-2 rounded-md hover:bg-dark/5" data-toggle="#staff-menu" aria-label="Menú"><?= icon('menu') ?></button>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($isStaff): ?>
    <div id="staff-menu" class="hidden border-t border-dark/10 bg-light">
      <nav class="mx-auto max-w-3xl px-3 py-3 text-[15px]"><?php partial('layout/nav_staff', ['nav' => $nav, 'variant' => 'side']); ?>
        <form method="post" action="<?= e(url('logout')) ?>" class="px-3 pt-2"><?= csrf_field() ?><button class="text-primary">Cerrar sesión</button></form>
      </nav>
    </div>
    <?php endif; ?>
  </header>
<?php endif; ?>

<?php foreach ($avisos as $a): ?>
  <div class="bg-primary text-light">
    <div class="mx-auto max-w-5xl px-4 py-2.5 text-sm flex gap-2 items-start"><?= icon('megaphone', 'w-4 h-4 mt-0.5 shrink-0') ?>
      <div><span class="font-medium"><?= e($a['titulo']) ?></span><?php if ($a['cuerpo']): ?> · <span class="text-light/85"><?= e($a['cuerpo']) ?></span><?php endif; ?></div>
    </div>
  </div>
<?php endforeach; ?>

<main class="<?= $isStaff ? 'mx-auto max-w-6xl' : 'mx-auto max-w-3xl' ?> px-4 pt-5 <?= $u && !$bare ? 'pb-28 lg:pb-12' : 'pb-12' ?>">
  <?php foreach (flashes() as $f): ?>
    <div class="mb-4 rounded-lg px-4 py-3 text-sm border <?= $f['type'] === 'error' ? 'bg-danger/5 border-danger/30 text-danger' : ($f['type'] === 'warning' ? 'bg-warning/5 border-warning/30 text-warning' : 'bg-primary/5 border-primary/20 text-primary') ?>" role="status"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>

<?php if (!$bare && $u): ?>
  <nav class="fixed bottom-0 inset-x-0 z-30 border-t border-dark/10 bg-white/95 backdrop-blur pb-[env(safe-area-inset-bottom)] <?= $isStaff ? 'lg:hidden' : '' ?>">
    <div class="mx-auto max-w-3xl grid <?= $isStaff ? 'grid-cols-4' : (setting('muro_activo', '1') === '1' ? 'grid-cols-5' : 'grid-cols-4') ?> text-[11px]">
      <?php
      if ($isStaff) {
          $items = [['coach', 'coach', 'home', 'Inicio'], ['coach/alumnos', 'alumnos', 'users', 'Alumnos'], ['sala', 'sala', 'grid', 'Sala']];
          $items[] = can_admin() ? ['admin', 'admin', 'settings', 'Gestión'] : ['muro', 'muro', 'fire', 'Jungla'];
      } else {
          $items = [['inicio', 'inicio', 'home', 'Inicio'], ['progreso', 'progreso', 'chart', 'Progreso']];
          if (setting('muro_activo', '1') === '1') $items[] = ['muro', 'muro', 'fire', 'Jungla', true];
          $items[] = ['notificaciones', 'avisos', 'bell', 'Avisos'];
          $items[] = ['perfil', 'perfil', 'user', 'Perfil'];
      }
      foreach ($items as $it): [$route, $key, $ic, $label] = $it; $on = $nav === $key; ?>
        <?php if (!empty($it[4])): ?>
          <a href="<?= e(url($route)) ?>" class="flex flex-col items-center gap-0.5 pb-2.5 <?= $on ? 'text-primary font-medium' : 'text-dark/55' ?>" aria-label="<?= e($label) ?>">
            <span class="-mt-5 w-14 h-14 rounded-full bg-primary text-light flex items-center justify-center shadow-md ring-4 ring-white <?= $on ? 'bg-hover' : '' ?>"><?= icon($ic, 'w-7 h-7') ?></span><span><?= e($label) ?></span>
          </a>
        <?php else: ?>
          <a href="<?= e(url($route)) ?>" class="flex flex-col items-center gap-0.5 py-2.5 <?= $on ? 'text-primary font-medium' : 'text-dark/55' ?>">
            <?= icon($ic, 'w-6 h-6') ?><span><?= e($label) ?></span>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </nav>
<?php endif; ?>

<div id="toast" class="fixed left-1/2 -translate-x-1/2 bottom-24 z-50 hidden max-w-[90vw] rounded-lg bg-dark text-light text-sm px-4 py-3 shadow-lg"></div>
<div id="offline-badge" class="fixed top-[calc(env(safe-area-inset-top)+64px)] right-3 z-40 hidden items-center gap-1.5 rounded-md bg-warning text-white text-xs px-2.5 py-1.5"><?= icon('wifiOff', 'w-4 h-4') ?><span>Sin conexión · se guardará al volver</span></div>

<?php if ($u && !$bare) partial('shared/pwa_hint'); ?>

<script src="<?= asset('assets/js/app.js') ?>"></script>
<?php foreach (($scripts ?? []) as $s): ?><script src="<?= asset($s) ?>"></script><?php endforeach; ?>
</body>
</html>
