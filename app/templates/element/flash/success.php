<?php
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<?php /*
<div class="message success" onclick="this.classList.add('hidden')"><?= $message ?></div>
*/ ?>

<?php if(!empty($message)): ?>
  <div class="toast success" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
    <div class="toast-header">
      <?= $this->Html->image("COmanage-Gears-SM.png", array('alt' => __('registry.meta.logo'))); ?>
      <span class="me-auto"><?= __('product.comanage'); ?></span>
      <small><?= __d('information','flash.success'); ?></small>
      <button type="button" class="btn-close nospin" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body">
      <?= h($message); ?>
    </div>
  </div>
  
  <script>
    var toastElList = [].slice.call(document.querySelectorAll('.toast'))
    var toastList = toastElList.map(function(toastEl) {
      return new bootstrap.Toast(toastEl);
    });
    toastList.forEach(toast => toast.show());
  </script>
<?php endif; ?>