<?php
/**
 * Authentication controller: login / logout.
 */

function login_controller()
{
    // Already signed in?
    if (is_logged_in()) {
        redirect('index.php?page=pos');
    }

    $error = '';
    $username = '';

    if (is_post()) {
        $username = (string) post('username');
        $password = (string) post('password');

        // Simple throttling: max 8 attempts per 5 minutes per session
        $_SESSION['login_attempts'] = array_values(array_filter(
            $_SESSION['login_attempts'] ?? [],
            function ($t) { return $t > time() - 300; }
        ));

        if (count($_SESSION['login_attempts']) >= 8) {
            $error = 'Too many failed attempts. Please wait a few minutes and try again.';
        } elseif ($username === '' || $password === '') {
            $error = 'Enter both username and password.';
        } else {
            $user = db_one('SELECT * FROM users WHERE username = ? AND active = 1', [$username]);

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']       = (int) $user['id'];
                $_SESSION['last_activity'] = time();
                unset($_SESSION['login_attempts']);

                db_update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

                $target = (string) get('redirect', '');
                $target = ($target !== '' && strpos($target, 'index.php') === 0) ? $target : 'index.php?page=pos';
                redirect($target);
            }

            $_SESSION['login_attempts'][] = time();
            $error = 'Incorrect username or password.';
        }
    }

    return [
        'title'    => 'Sign in',
        'error'    => $error,
        'username' => $username,
    ];
}

function logout_controller()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    redirect('index.php?page=login');
}
