<?php
  if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
  }
?>
<?php /*
<div class="message error" onclick="this.classList.add('hidden');"><?= $message ?></div>
*/ ?>

<?php if(!empty($message)): ?>
  <?= $this->Alert->alert(h($message), 'danger', true, __d('information','flash.error')) ?>
<?php endif; ?>
