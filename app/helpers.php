<?php
/**
 * Shared helpers: escaping, URL, CSRF, auth, money, settings.
 */

/* ------------------------------------------------------------------ */
/* Output & URLs                                                       */
/* ------------------------------------------------------------------ */

/** HTML-escape a value for safe output. */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Base URL of the application (auto-detected, no trailing slash). */
function base_url($path = '')
{
    static $base = null;

    if ($base === null) {
        if (BASE_URL !== '') {
            $base = rtrim(BASE_URL, '/');
        } else {
            $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
            $dir    = str_replace('\\', '/', dirname($script));
            $base   = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
        }
    }

    return $base . '/' . ltrim((string) $path, '/');
}

/** URL to a file inside /assets. */
function asset($path)
{
    return base_url('assets/' . ltrim($path, '/'));
}

/** Redirect and stop execution. */
function redirect($url)
{
    if (strpos($url, 'http') !== 0 && strpos($url, '/') !== 0) {
        $url = base_url($url);
    }
    if (!headers_sent()) {
        header('Location: ' . $url);
    }
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    exit;
}

/** Current request URI (query string included) — used by login redirect. */
function current_url()
{
    return $_SERVER['REQUEST_URI'] ?? base_url('');
}

/* ------------------------------------------------------------------ */
/* Request                                                             */
/* ------------------------------------------------------------------ */

function is_post()
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post($key, $default = '')
{
    return isset($_POST[$key]) ? (is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key]) : $default;
}

function get($key, $default = '')
{
    return isset($_GET[$key]) ? (is_string($_GET[$key]) ? trim($_GET[$key]) : $_GET[$key]) : $default;
}

function post_num($key, $default = 0)
{
    $v = post($key, null);
    return ($v === null || $v === '') ? $default : (float) str_replace(',', '', $v);
}

/** Read a JSON request body (AJAX) as an array. */
function json_body()
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_out($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/* ------------------------------------------------------------------ */
/* CSRF                                                                */
/* ------------------------------------------------------------------ */

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_ok()
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? post('csrf_token', '');
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $sent);
}

/** Abort with 400 unless the CSRF token matches (POST requests only). */
function csrf_guard()
{
    if (is_post() && !csrf_ok()) {
        http_response_code(400);
        exit('Invalid or expired security token. Please reload the page and try again.');
    }
}

/* ------------------------------------------------------------------ */
/* Flash messages                                                      */
/* ------------------------------------------------------------------ */

function flash($message, $type = 'success')
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function take_flash()
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ------------------------------------------------------------------ */
/* Auth                                                                */
/* ------------------------------------------------------------------ */

function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

/** Cached user record for the logged-in user (or null). */
function current_user()
{
    static $user = false;

    if ($user === false) {
        $user = null;
        if (is_logged_in()) {
            $user = db_one('SELECT id, name, username, role, active FROM users WHERE id = ? AND active = 1',
                           [(int) $_SESSION['user_id']]);
            if (!$user) {
                // Account deleted / deactivated while logged in
                session_destroy();
                redirect('index.php?page=login');
            }
        }
    }

    return $user;
}

function require_login()
{
    if (!is_logged_in()) {
        flash('Please sign in to continue.', 'warning');
        redirect('index.php?page=login&redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    current_user();
}

function require_admin()
{
    require_login();
    $u = current_user();
    if (!$u || $u['role'] !== 'admin') {
        http_response_code(403);
        flash('Administrator access is required for that page.', 'danger');
        redirect('index.php?page=pos');
    }
}

function is_admin()
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

/* ------------------------------------------------------------------ */
/* Money & formatting                                                  */
/* ------------------------------------------------------------------ */

/** Format a number as money using the configured currency symbol. */
function money($amount, $withSymbol = true)
{
    $dp      = (int) setting('decimal_places', '2');
    $symbol  = setting('currency_symbol', '₱');
    $number  = number_format((float) $amount, $dp, '.', ',');
    if (!$withSymbol) {
        return $number;
    }
    return setting('currency_position', 'before') === 'before'
        ? $symbol . $number
        : $number . ' ' . $symbol;
}

/** Plain number with no symbol (for input values / JSON). */
function money_plain($amount)
{
    return number_format((float) $amount, (int) setting('decimal_places', '2'), '.', '');
}

function fmt_date($datetime, $withTime = true)
{
    if (empty($datetime)) {
        return '—';
    }
    $ts = is_numeric($datetime) ? (int) $datetime : strtotime($datetime);
    return date($withTime ? 'M d, Y h:i A' : 'M d, Y', $ts);
}

/** First letter of a name (works with or without the mbstring extension). */
function initial($name)
{
    $name = trim((string) $name);
    if ($name === '') {
        return '?';
    }
    return function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($name, 0, 1))
        : strtoupper(substr($name, 0, 1));
}

function fmt_qty($qty)
{
    return rtrim(rtrim(number_format((float) $qty, 3, '.', ''), '0'), '.');
}

/* ------------------------------------------------------------------ */
/* Settings                                                            */
/* ------------------------------------------------------------------ */

function load_settings()
{
    try {
        $rows = db_all('SELECT setting_key, setting_value FROM settings');
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $out[$row['setting_key']] = $row['setting_value'];
    }
    return $out;
}

function setting($key, $default = '')
{
    global $APP_SETTINGS;
    if (!isset($APP_SETTINGS[$key]) || $APP_SETTINGS[$key] === null || $APP_SETTINGS[$key] === '') {
        return $default;
    }
    return $APP_SETTINGS[$key];
}

/** Persist one or more settings (admin only). */
function save_settings(array $pairs)
{
    $st = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($pairs as $key => $value) {
        $st->execute([$key, (string) $value]);
        $GLOBALS['APP_SETTINGS'][$key] = (string) $value;
    }
}

/* ------------------------------------------------------------------ */
/* Misc                                                                */
/* ------------------------------------------------------------------ */

/** Generate the next sequential sale number, e.g. INV-000123 */
function next_sale_no()
{
    $prefix = setting('sale_prefix', 'INV');
    $prefix = preg_replace('/[^A-Za-z0-9\-]/', '', $prefix);
    if ($prefix === '') {
        $prefix = 'INV';
    }

    $last = db_val(
        "SELECT sale_no FROM sales WHERE sale_no LIKE ? ORDER BY id DESC LIMIT 1",
        [$prefix . '-%']
    );

    $next = 1;
    if ($last && preg_match('/(\d+)$/', $last, $m)) {
        $next = (int) $m[1] + 1;
    }

    return $prefix . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
}

/** Build a simple LIMIT/OFFSET pagination helper. */
function paginate($total, $perPage, $currentPage, $baseQuery = [])
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page  = min(max(1, (int) $currentPage), $pages);
    return [
        'total'   => (int) $total,
        'perPage' => (int) $perPage,
        'pages'   => $pages,
        'page'    => $page,
        'offset'  => ($page - 1) * $perPage,
        'from'    => $total ? ($page - 1) * $perPage + 1 : 0,
        'to'      => min($total, $page * $perPage),
    ];
}

/** Stream an array of rows as a CSV download. */
function csv_download($filename, array $header, array $rows)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF"); // BOM so Excel opens UTF-8 correctly
    fputcsv($out, $header);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

/** Record a stock movement (sales, voids, manual adjustments). */
function stock_move($productId, $change, $reason, $balanceAfter = null)
{
    $productId = (int) $productId;
    if ($productId <= 0) {
        return;
    }
    if ($balanceAfter === null) {
        $balanceAfter = (float) db_val('SELECT stock_qty FROM products WHERE id = ?', [$productId]);
    }
    $user = current_user();
    db_insert('stock_movements', [
        'product_id'    => $productId,
        'qty_change'    => $change,
        'balance_after' => $balanceAfter,
        'reason'        => $reason,
        'user_id'       => $user ? $user['id'] : null,
        'user_name'     => $user ? $user['name'] : 'System',
    ]);
}

/** Log an action to the PHP error log (handy while developing). */
function app_log($message)
{
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log('[Carlos-pos] ' . $message);
    }
}
