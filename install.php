<?php
/**
 * Aronium POS Web — one-click installer
 * ------------------------------------------------------------
 * Creates the database, imports the schema, creates the admin
 * account and writes your credentials into config/config.php.
 *
 * When you are done, delete this file (or leave it — it refuses
 * to run again once storage/installed.lock exists).
 */

session_name('aroniumpos');
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/helpers.php';

date_default_timezone_set(APP_TIMEZONE);

$lockFile = __DIR__ . '/storage/installed.lock';
$sqlFile  = __DIR__ . '/database/aronium_pos.sql';

$step   = 'form';                 // form | done
$errors = [];
$log    = [];
$alreadyInstalled = file_exists($lockFile);

/* ------------------------------------------------------------------ */
/* Handle the form                                                     */
/* ------------------------------------------------------------------ */
if (is_post()) {
    if (!csrf_ok()) {
        $errors[] = 'The form expired. Please reload and try again.';
    } else {
        $host = trim((string) post('db_host', 'localhost'));
        $name = trim((string) post('db_name', 'aronium_pos'));
        $user = trim((string) post('db_user', 'root'));
        $pass = (string) post('db_pass', '');

        $adminName = trim((string) post('admin_name', 'Administrator'));
        $adminUser = trim((string) post('admin_user', 'admin'));
        $adminPass = (string) post('admin_pass', '');

        if ($name === '' || preg_match('/[^A-Za-z0-9_$]/', $name)) {
            $errors[] = 'Database name may only contain letters, numbers, underscore and $.';
        }
        if ($adminUser === '' || strlen($adminPass) < 6) {
            $errors[] = 'Administrator username is required and the password must be at least 6 characters.';
        }

        if (!$errors) {
            try {
                // 1. connect to the server (no database yet) and create it
                $pdo = new PDO(
                    'mysql:host=' . $host . ';charset=' . DB_CHARSET,
                    $user,
                    $pass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $name) . '`
                            DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $log[] = 'Database <code>' . e($name) . '</code> is ready.';
                $pdo->exec('USE `' . str_replace('`', '``', $name) . '`');

                // 2. import the schema + starter data
                if (!file_exists($sqlFile)) {
                    throw new RuntimeException('database/aronium_pos.sql was not found.');
                }
                $sql = file_get_contents($sqlFile);
                $sql = preg_replace('/^\s*--.*$/m', '', $sql);
                $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

                $statements = array_filter(array_map('trim', explode(";\n", $sql)));
                $count = 0;
                foreach ($statements as $statement) {
                    if ($statement === '') continue;
                    $pdo->exec($statement);
                    $count++;
                }
                $log[] = 'Imported schema and starter data (' . $count . ' statements).';

                // 3. create / update the administrator
                $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                $pdo->prepare(
                    'INSERT INTO users (name, username, password_hash, role, active)
                          VALUES (?, ?, ?, "admin", 1)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash),
                          role = "admin", active = 1'
                )->execute([$adminName, $adminUser, $hash]);
                $log[] = 'Administrator account <strong>' . e($adminUser) . '</strong> created.';

                // keep the demo cashier around on a fresh install
                $cashierHash = '$2y$10$tq74V4u4xKuBm0PO8m.aFe70Ew3QksXcowwzu88e8d3AsJXGN7mzy';
                $pdo->prepare(
                    'INSERT IGNORE INTO users (id, name, username, password_hash, role, active)
                          VALUES (2, ?, ?, ?, "cashier", 1)'
                )->execute(['Cashier One', 'cashier', $cashierHash]);

                // 4. write credentials into config/config.php
                $cfgPath = __DIR__ . '/config/config.php';
                if (is_writable($cfgPath)) {
                    $cfg = file_get_contents($cfgPath);
                    $pairs = ['DB_HOST' => $host, 'DB_NAME' => $name, 'DB_USER' => $user, 'DB_PASS' => $pass];
                    foreach ($pairs as $const => $value) {
                        $cfg = preg_replace(
                            "/define\('" . $const . "',\s*'[^']*'\);/",
                            "define('" . $const . "', " . var_export($value, true) . ");",
                            (string) $cfg,
                            1
                        );
                    }
                    file_put_contents($cfgPath, $cfg);
                    $log[] = 'Saved database credentials to <code>config/config.php</code>.';
                } else {
                    $log[] = '<strong>config/config.php is not writable</strong> — edit it by hand with the settings below.';
                }

                // 5. lock the installer
                if (!is_dir(__DIR__ . '/storage')) {
                    @mkdir(__DIR__ . '/storage', 0775, true);
                }
                @file_put_contents($lockFile, date('c') . PHP_EOL);

                $step = 'done';
            } catch (Throwable $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

if ($alreadyInstalled && $step !== 'done' && !is_post()) {
    // Show a "nothing to do" screen unless the user explicitly asked to reinstall
    $step = 'installed';
}

$token = csrf_token();
$dbError = get('error') === 'db';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card" style="width:min(560px,100%)">
    <div class="auth-brand">
      <div class="brand-mark lg">A</div>
      <div>
        <h2><?= e(APP_NAME) ?></h2>
        <p>Database installer</p>
      </div>
    </div>

    <?php if ($dbError): ?>
      <div class="alert alert-danger"><span>The app could not reach the database. Update the settings below and re-run the installer.</span></div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
      <div class="alert alert-danger"><span><?= $error ?></span></div>
    <?php endforeach; ?>

    <?php if ($step === 'done'): ?>
      <div class="alert alert-success"><span>Installation finished.</span></div>
      <ul class="kv" style="list-style:none;padding:0;margin:0 0 1rem">
        <?php foreach ($log as $line): ?>
          <li style="padding:.25rem 0;border-bottom:1px solid var(--line)"><?= $line ?></li>
        <?php endforeach; ?>
      </ul>
      <div class="alert alert-warning" style="text-align:left">
        <span>The starter data also includes two demo logins: <code>admin</code> / <code>admin123</code> and
        <code>cashier</code> / <code>cashier123</code>. Delete them under <em>Settings → Users</em> before going live.</span>
      </div>
      <p class="muted small">For safety you can now delete <code>install.php</code> from the folder.</p>
      <a class="btn btn-primary btn-block btn-lg" href="<?= e(base_url('index.php?page=login')) ?>">Go to sign in</a>

    <?php elseif ($step === 'installed'): ?>
      <div class="alert alert-warning"><span>This copy is already installed (storage/installed.lock exists).</span></div>
      <ul class="kv" style="list-style:none;padding:0;margin:0 0 1rem">
        <li><span>Database</span><strong><?= e(DB_NAME) ?> @ <?= e(DB_HOST) ?></strong></li>
        <li><span>User</span><strong><?= e(DB_USER) ?></strong></li>
      </ul>
      <div class="modal-actions" style="justify-content:flex-start">
        <a class="btn btn-primary" href="<?= e(base_url('index.php?page=login')) ?>">Go to sign in</a>
        <a class="btn btn-ghost" href="<?= e(base_url('index.php')) ?>">Open the POS</a>
      </div>
      <details style="margin-top:1rem">
        <summary class="muted small">Re-run the installer anyway</summary>
        <p class="muted small">Delete <code>storage/installed.lock</code> and reload this page. Re-running only re-creates missing
        tables and resets the administrator password — existing products and sales are preserved.</p>
      </details>

    <?php else: ?>
      <p class="muted">Step 1: make sure MySQL is running in the XAMPP control panel, then fill this in.</p>
      <form method="post" class="form" autocomplete="off">
        <?= csrf_field() ?>

        <h4 style="margin-top:1rem">MySQL connection</h4>
        <div class="form-grid">
          <label class="field"><span>Host</span>
            <input class="input" type="text" name="db_host" value="<?= e(post('db_host', DB_HOST)) ?>" required>
          </label>
          <label class="field"><span>Database name</span>
            <input class="input" type="text" name="db_name" value="<?= e(post('db_name', DB_NAME)) ?>" required>
          </label>
          <label class="field"><span>Username</span>
            <input class="input" type="text" name="db_user" value="<?= e(post('db_user', DB_USER)) ?>" required>
          </label>
          <label class="field"><span>Password</span>
            <input class="input" type="text" name="db_pass" value="<?= e(post('db_pass', DB_PASS)) ?>" placeholder="blank for default XAMPP">
          </label>
        </div>

        <h4 style="margin-top:1rem">Administrator account</h4>
        <div class="form-grid">
          <label class="field span-2"><span>Display name</span>
            <input class="input" type="text" name="admin_name" value="<?= e(post('admin_name', 'Administrator')) ?>" required>
          </label>
          <label class="field"><span>Username</span>
            <input class="input" type="text" name="admin_user" value="<?= e(post('admin_user', 'admin')) ?>" required>
          </label>
          <label class="field"><span>Password (min. 6 chars)</span>
            <input class="input" type="text" name="admin_pass" value="<?= e(post('admin_pass', 'admin123')) ?>" required>
          </label>
        </div>

        <div class="form-actions">
          <button class="btn btn-primary btn-lg" type="submit">Install</button>
          <a class="btn btn-ghost" href="<?= e(base_url('index.php')) ?>">Skip / open app</a>
        </div>
      </form>
      <p class="muted small" style="margin-top:1rem">
        Prefer manual setup? Import <code>database/aronium_pos.sql</code> in phpMyAdmin and edit
        <code>config/config.php</code> instead. Default login then is <code>admin</code> / <code>admin123</code>.
      </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
