<?php
  // XXX are these classes set anywhere? Are they in use?
  $class = 'message';
  if (!empty($params['class'])) {
    $class .= ' ' . $params['class'];
  }
  if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
  }
?>

<?php if(!empty($message)): ?>
  <?= $this->Alert->alert($message, 'warning', true, __d('information','flash.default')) ?>
<?php endif; ?>

