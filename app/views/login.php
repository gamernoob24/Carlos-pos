<?php /** @var array $data */ ?>
<div class="auth-card">
  <div class="auth-brand">
    <div class="brand-mark lg">A</div>
    <div>
      <h2>Carlos POS</h2>
      <p>Web Edition — sign in to start selling</p>
    </div>
  </div>

  <?php if (!empty($data['error'])): ?>
    <div class="alert alert-danger"><span><?= e($data['error']) ?></span></div>
  <?php endif; ?>
  <?php if (get('timeout')): ?>
    <div class="alert alert-warning"><span>Your session timed out. Please sign in again.</span></div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('index.php?page=login')) ?>" class="form" autocomplete="off">
    <?= csrf_field() ?>
    <label class="field">
      <span>Username</span>
      <input type="text" name="username" value="<?= e($data['username']) ?>" required autofocus
             placeholder="admin">
    </label>
    <label class="field">
      <span>Password</span>
      <input type="password" name="password" required placeholder="••••••••">
    </label>
    <button class="btn btn-primary btn-block btn-lg" type="submit">Sign in</button>
  </form>

  <div class="auth-hint">
    <strong>Default accounts</strong>
    <code>admin</code> / <code>admin123</code> &nbsp;·&nbsp; <code>cashier</code> / <code>cashier123</code>
    <small>Change these passwords under <em>Settings → Users</em> before going live.</small>
  </div>
</div>
