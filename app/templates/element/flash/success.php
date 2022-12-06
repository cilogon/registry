<?php
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>

<?php if(!empty($message)): ?>
  <?= $this->Alert->alert($message, 'success', true, __d('information','flash.success')) ?>
<?php endif; ?>