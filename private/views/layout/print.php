<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Imprimir') . ' · ' . setting('brand_nombre')) ?></title>
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<style>:root{--c-primary:<?= hex_rgb(setting('brand_primary', '#283614')) ?>;--c-hover:<?= hex_rgb(setting('brand_hover', '#606C38')) ?>;--c-dark:<?= hex_rgb(setting('brand_dark', '#111D13')) ?>;--c-light:<?= hex_rgb(setting('brand_light', '#F5F9E5')) ?>;--c-accent:<?= hex_rgb(setting('brand_accent', '#606C38')) ?>;--c-danger:<?= hex_rgb(setting('brand_danger', '#B3261E')) ?>;--c-warning:<?= hex_rgb(setting('brand_warning', '#A86B00')) ?>}</style>
</head>
<body class="bg-white text-dark" data-base="<?= e(base_path()) ?>" data-csrf="<?= e(csrf_token()) ?>">
<?= $content ?>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<?php foreach (($scripts ?? []) as $s): ?><script src="<?= asset($s) ?>"></script><?php endforeach; ?>
</body>
</html>
