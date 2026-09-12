<?php
/**
 * Carlos POS Web — front controller
 * ------------------------------------------------------------
 * Every screen is reached through  index.php?page=...
 */

require_once __DIR__ . '/app/bootstrap.php';

/**
 * page => [controller file, action, requires login]
 */
$routes = [
    'login'      => ['auth',      'login',      false],
    'logout'     => ['auth',      'logout',     false],
    'pos'        => ['pos',       'pos',        true],
    'products'   => ['products',  'products',   true],
    'categories' => ['products',  'categories', true],
    'sales'      => ['sales',     'sales',      true],
    'sale'       => ['sales',     'sale_view',  true],
    'reports'    => ['reports',   'reports',    true],
    'settings'   => ['settings',  'settings',   true],
    'users'      => ['settings',  'users',      true],
];

$page = get('page', 'pos');

if (!isset($routes[$page])) {
    http_response_code(404);
    $page = '404';
    [$controllerFile, $action] = ['errors', 'not_found'];
} else {
    [$controllerFile, $action] = $routes[$page];
}

if ($routes[$page][2] ?? true) {
    require_login();
}
csrf_guard();

require_once __DIR__ . '/app/controllers/' . $controllerFile . '.php';

$handler = $action . '_controller';
if (!function_exists($handler)) {
    http_response_code(500);
    exit('Controller handler "' . e($handler) . '" is missing.');
}

$data = $handler();

/* ---------- render ---------- */
$layout   = ($action === 'login') ? 'blank' : 'app';
$viewFile = __DIR__ . '/app/views/' . $action . '.php';

require __DIR__ . '/app/views/layout/header.php';

if (file_exists($viewFile)) {
    require $viewFile;
} else {
    echo '<div class="card"><p>View "' . e($action) . '" is missing.</p></div>';
}

require __DIR__ . '/app/views/layout/footer.php';
