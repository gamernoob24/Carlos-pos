<?php /** @var array $data */ ?>
<div class="grid-2">
  <div class="card">
    <div class="card-head"><h3><?= $data['edit'] ? 'Edit user' : 'Add user' ?></h3></div>
    <form method="post" action="<?= e(base_url('index.php?page=users&action=save')) ?>" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e($data['edit']['id'] ?? 0) ?>">
      <label class="field"><span>Full name</span>
        <input class="input" type="text" name="name" required value="<?= e($data['edit']['name'] ?? '') ?>">
      </label>
      <label class="field"><span>Username</span>
        <input class="input" type="text" name="username" required value="<?= e($data['edit']['username'] ?? '') ?>">
      </label>
      <label class="field"><span>Role</span>
        <select class="input" name="role">
          <option value="cashier" <?= ($data['edit']['role'] ?? 'cashier') === 'cashier' ? 'selected' : '' ?>>Cashier — sell, view products &amp; sales</option>
          <option value="admin" <?= ($data['edit']['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator — full access</option>
        </select>
      </label>
      <label class="field"><span><?= $data['edit'] ? 'New password (leave blank to keep)' : 'Password' ?></span>
        <input class="input" type="password" name="password" minlength="6" <?= $data['edit'] ? '' : 'required' ?>>
      </label>
      <label class="check">
        <input type="checkbox" name="active" value="1" <?= !isset($data['edit']['active']) || $data['edit']['active'] ? 'checked' : '' ?>>
        <span>Active (can sign in)</span>
      </label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $data['edit'] ? 'Save user' : 'Create user' ?></button>
        <?php if ($data['edit']): ?>
          <a class="btn btn-ghost" href="<?= e(base_url('index.php?page=users')) ?>">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h3>Team</h3></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>User</th><th>Role</th><th class="right">Sales</th><th class="right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($data['rows'] as $u): ?>
          <tr class="<?= $u['active'] ? '' : 'row-voided' ?>">
            <td>
              <strong><?= e($u['name']) ?></strong>
              <div class="muted small">@<?= e($u['username']) ?> · last login <?= e($u['last_login'] ? fmt_date($u['last_login']) : 'never') ?></div>
            </td>
            <td><span class="pill <?= $u['role'] === 'admin' ? 'pill-card' : 'pill-muted' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
            <td class="right"><?= e($u['sale_count']) ?></td>
            <td class="right">
              <a class="btn btn-sm btn-ghost" href="<?= e(base_url('index.php?page=users&action=edit&id=' . $u['id'])) ?>">Edit</a>
              <button class="btn btn-sm btn-ghost" type="button" data-reset-user="<?= e($u['id']) ?>" data-user-name="<?= e($u['name']) ?>">Reset&nbsp;PW</button>
              <?php if ((int) $u['id'] !== (int) current_user()['id']): ?>
                <form method="post" action="<?= e(base_url('index.php?page=users&action=delete')) ?>" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                  <button class="btn btn-sm btn-danger" type="submit" data-confirm="Delete user &quot;<?= e($u['name']) ?>&quot;?">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="muted small">Deleting a user keeps their past sales (the cashier name is stored on each sale).</p>
  </div>
</div>

<div class="modal" id="resetModal" hidden>
  <div class="modal-card">
    <h3>Reset password</h3>
    <p class="muted" id="resetWho"></p>
    <form method="post" id="resetForm" action="<?= e(base_url('index.php?page=users&action=password')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="resetId" value="">
      <label class="field"><span>New password</span>
        <input class="input" type="password" name="password" required minlength="6">
      </label>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-primary">Set password</button>
      </div>
    </form>
  </div>
</div>
