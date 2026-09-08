<?php
/**
 * Layout: header / sidebar / topbar
 * Variables available from index.php: $data, $page, $layout, $action
 */
$user     = $layout === 'app' ? current_user() : null;
$flashes  = take_flash();
$title    = $data['title'] ?? ucfirst($page ?? 'POS');
$lowCount = 0;
if ($layout === 'app') {
    $lowCount = (int) db_val('SELECT COUNT(*) FROM products WHERE active = 1 AND stock_qty <= reorder_level');
}
$navItems = [
    'pos'        => ['Point of Sale', 'cart'],
    'products'   => ['Products',      'box'],
    'categories' => ['Categories',    'tag'],
    'sales'      => ['Sales History', 'receipt'],
    'reports'    => ['Reports',       'chart'],
    'settings'   => ['Settings',      'gear'],
    'users'      => ['Users',         'users'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#111827">
<title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="7" fill="#2563eb"/><path d="M9 22V10h3.2l3.8 7 3.8-7H23v12h-3v-6.6l-3.1 5.6h-1.8L12 15.4V22z" fill="#fff"/></svg>') ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="layout-<?= e($layout) ?><?= $page === 'pos' ? ' page-pos' : '' ?>">
<?php if ($layout === 'app'): ?>
<div class="app-shell">

  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-mark">A</div>
      <div class="brand-text">
        <strong>Aronium POS</strong>
        <span>Web Edition</span>
      </div>
    </div>

    <nav class="nav">
      <?php foreach ($navItems as $key => [$label, $icon]): ?>
        <?php if ($key === 'users' && !is_admin()) { continue; } ?>
        <a class="nav-link<?= $page === $key ? ' active' : '' ?>" href="<?= e(base_url('index.php?page=' . $key)) ?>">
          <span class="ico ico-<?= e($icon) ?>" aria-hidden="true"></span>
          <span class="nav-label"><?= e($label) ?></span>
          <?php if ($key === 'products' && $lowCount > 0): ?>
            <span class="nav-badge" title="Low stock items"><?= e($lowCount) ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
      <div class="who">
        <div class="avatar"><?= e(initial($user['name'])) ?></div>
        <div>
          <strong><?= e($user['name']) ?></strong>
          <span class="role"><?= e(ucfirst($user['role'])) ?></span>
        </div>
      </div>
      <a class="btn btn-ghost btn-sm" href="<?= e(base_url('index.php?page=logout')) ?>">Sign out</a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-toggle" type="button" aria-label="Menu" data-toggle-sidebar>
        <span></span><span></span><span></span>
      </button>
      <div class="topbar-title">
        <h1><?= e($title) ?></h1>
      </div>
      <div class="topbar-meta">
        <span class="clock" id="clock"><?= e(date('D, M d · h:i A')) ?></span>
        <a class="btn btn-primary btn-sm" href="<?= e(base_url('index.php?page=pos')) ?>">New Sale</a>
      </div>
    </header>

    <main class="content">
      <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="alert">
          <span><?= $f['message'] ?></span>
          <button type="button" class="alert-close" aria-label="Dismiss">&times;</button>
        </div>
      <?php endforeach; ?>
<?php else: ?>
  <div class="auth-wrap">
    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>" role="alert"><span><?= $f['message'] ?></span></div>
    <?php endforeach; ?>
<?php endif; ?>
