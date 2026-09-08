<?php
/**
 * Bootstrap — session, config, database, settings.
 * Included by index.php and api.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'path'     => '/',
    ]);
    session_name('aroniumpos');
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

date_default_timezone_set(APP_TIMEZONE);

/* Settings cache. Empty until the database is installed. */
$GLOBALS['APP_SETTINGS'] = [];

if (!db_ready()) {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script !== 'install.php') {
        $installed = file_exists(__DIR__ . '/../storage/installed.lock');
        redirect('install.php' . ($installed ? '?error=db' : ''));
    }
    return; // install.php continues with its own logic
}

$GLOBALS['APP_SETTINGS'] = load_settings();

/* Keep idle sessions from living forever (optional) */
if (SESSION_LIFETIME > 0 && is_logged_in()) {
    $last = $_SESSION['last_activity'] ?? 0;
    if ($last && (time() - $last) > SESSION_LIFETIME) {
        session_destroy();
        redirect('index.php?page=login&timeout=1');
    }
    $_SESSION['last_activity'] = time();
}
