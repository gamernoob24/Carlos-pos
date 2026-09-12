<?php
/**
 * Carlos POS Web — configuration
 * ------------------------------------------------------------
 * Edit these five lines to match your XAMPP MySQL setup.
 * Default XAMPP:  host = localhost,  user = root,  password = (empty)
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'Carlos_pos');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/** Application name shown in the title bar & login screen */
define('APP_NAME', 'Carlos POS Web');

/** Timezone used for sales timestamps (PHP timezone name) */
define('APP_TIMEZONE', 'Asia/Manila');

/** Session lifetime in seconds (0 = until browser closes) */
define('SESSION_LIFETIME', 0);

/**
 * Base URL of the app, e.g. ''            -> http://localhost/Carlos-pos/
 *                          '/Carlos-pos' -> if you need to force it
 * Leave as '' for auto-detection (recommended).
 */
define('BASE_URL', '');

/** Set to true to hide the "Install" link and lock down installer */
define('APP_DEBUG', true);
