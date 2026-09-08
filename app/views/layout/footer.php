<?php
/**
 * Layout: footer / scripts
 */
$extraScripts = $data['scripts'] ?? [];
if ($page === 'pos') {
    $extraScripts[] = 'js/pos.js';
}
?>
<?php if ($layout === 'app'): ?>
    </main>
  </div>
</div>
<div class="sidebar-backdrop" data-toggle-sidebar></div>
<?php else: ?>
  </div><!-- /.auth-wrap -->
<?php endif; ?>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php foreach ($extraScripts as $script): ?>
<script src="<?= e(asset($script)) ?>" defer></script>
<?php endforeach; ?>
<?php if (!empty($data['inlineJs'])): ?>
<script><?= $data['inlineJs'] ?></script>
<?php endif; ?>
</body>
</html>
