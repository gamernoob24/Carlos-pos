<?php
/**
 * Settings (business / tax / receipt) and user administration.
 */

/* ------------------------------------------------------------------ */
/* Application settings                                                */
/* ------------------------------------------------------------------ */

function settings_controller()
{
    if (is_post() && get('action') === 'save') {
        require_admin();
        save_settings([
            'business_name'       => post('business_name', 'Aronium POS Web'),
            'business_address'    => post('business_address'),
            'business_phone'      => post('business_phone'),
            'business_tin'        => post('business_tin'),
            'currency_symbol'     => post('currency_symbol', '₱'),
            'currency_position'   => in_array(post('currency_position'), ['before', 'after'], true) ? post('currency_position') : 'before',
            'tax_name'            => post('tax_name', 'Tax'),
            'tax_rate'            => max(0, post_num('tax_rate')),
            'tax_mode'            => in_array(post('tax_mode'), ['exclusive', 'inclusive'], true) ? post('tax_mode') : 'exclusive',
            'receipt_footer'      => post('receipt_footer'),
            'receipt_width'       => in_array(post('receipt_width'), ['58', '80'], true) ? post('receipt_width') : '80',
            'low_stock_threshold' => max(0, post_num('low_stock_threshold')),
            'allow_negative_stock'=> isset($_POST['allow_negative_stock']) ? '1' : '0',
            'sale_prefix'         => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', post('sale_prefix', 'INV'))) ?: 'INV',
            'decimal_places'      => in_array(post('decimal_places'), ['0', '2', '3'], true) ? post('decimal_places') : '2',
        ]);
        flash('Settings saved.', 'success');
        redirect('index.php?page=settings');
    }

    if (is_post() && get('action') === 'password') {
        settings_change_password();
    }

    return [
        'title'    => 'Settings',
        'dbTables' => db_all("SHOW TABLES"),
    ];
}

function settings_change_password()
{
    $user     = current_user();
    $current  = (string) post('current_password');
    $new      = (string) post('new_password');
    $confirm  = (string) post('confirm_password');

    $hash = (string) db_val('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);

    if (!password_verify($current, $hash)) {
        flash('Current password is not correct.', 'danger');
    } elseif (strlen($new) < 6) {
        flash('New password must be at least 6 characters.', 'danger');
    } elseif ($new !== $confirm) {
        flash('New passwords do not match.', 'danger');
    } else {
        db_update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
        flash('Password updated.', 'success');
    }

    redirect('index.php?page=settings');
}

/* ------------------------------------------------------------------ */
/* Users                                                               */
/* ------------------------------------------------------------------ */

function users_controller()
{
    require_admin();

    $action = get('action', 'list');

    if (is_post()) {
        if ($action === 'save')    { users_save(); }
        if ($action === 'delete')  { users_delete(); }
        if ($action === 'password'){ users_reset_password(); }
    }

    $rows = db_all(
        'SELECT u.*, (SELECT COUNT(*) FROM sales s WHERE s.user_id = u.id) AS sale_count
           FROM users u ORDER BY u.role, u.name'
    );

    return [
        'title' => 'Users',
        'rows'  => $rows,
        'edit'  => ((int) get('id', 0)) ? db_one('SELECT * FROM users WHERE id = ?', [(int) get('id')]) : null,
    ];
}

function users_save()
{
    require_admin();

    $id       = (int) post('id', 0);
    $name     = (string) post('name');
    $username = (string) post('username');
    $role     = post('role') === 'admin' ? 'admin' : 'cashier';
    $password = (string) post('password');
    $active   = isset($_POST['active']) ? 1 : 0;

    if ($name === '' || $username === '') {
        flash('Name and username are required.', 'danger');
        redirect('index.php?page=users');
    }

    $clash = db_val('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $id]);
    if ($clash) {
        flash('That username is already taken.', 'danger');
        redirect('index.php?page=users');
    }

    if ($id > 0) {
        $data = ['name' => $name, 'username' => $username, 'role' => $role, 'active' => $active];
        if ($password !== '') {
            if (strlen($password) < 6) {
                flash('Password must be at least 6 characters.', 'danger');
                redirect('index.php?page=users');
            }
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        // Never let the system lose its last administrator
        if ((int) current_user()['id'] === $id && ($role !== 'admin' || !$active)) {
            flash('You cannot remove your own administrator access.', 'danger');
            redirect('index.php?page=users');
        }
        db_update('users', $data, 'id = ?', [$id]);
        flash('User "' . e($name) . '" updated.', 'success');
    } else {
        if (strlen($password) < 6) {
            flash('New users need a password of at least 6 characters.', 'danger');
            redirect('index.php?page=users');
        }
        db_insert('users', [
            'name'          => $name,
            'username'      => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => $role,
            'active'        => $active,
        ]);
        flash('User "' . e($name) . '" created.', 'success');
    }

    redirect('index.php?page=users');
}

function users_reset_password()
{
    require_admin();

    $id       = (int) post('id', 0);
    $password = (string) post('password');

    if ($id <= 0 || strlen($password) < 6) {
        flash('Enter a new password of at least 6 characters.', 'danger');
        redirect('index.php?page=users');
    }

    db_update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$id]);
    flash('Password reset.', 'success');
    redirect('index.php?page=users');
}

function users_delete()
{
    require_admin();

    $id = (int) post('id', 0);
    $me = (int) current_user()['id'];

    if ($id === $me) {
        flash('You cannot delete your own account.', 'danger');
        redirect('index.php?page=users');
    }

    $user = db_one('SELECT * FROM users WHERE id = ?', [$id]);
    if ($user) {
        if ($user['role'] === 'admin'
            && (int) db_val("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1") <= 1) {
            flash('At least one active administrator must remain.', 'danger');
            redirect('index.php?page=users');
        }
        db_delete('users', 'id = ?', [$id]); // sales keep the user_name snapshot
        flash('User deleted.', 'success');
    }

    redirect('index.php?page=users');
}
