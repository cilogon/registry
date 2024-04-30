<?php
  if (!isset($params['escape']) || $params['escape'] !== false) {
      $message = h($message);
  }
?>

<?php if(!empty($message)): ?>
  <?php /* CFM-221: while a prefix such as "Error: " or "Success: " can be sent with the Alert, 
    we avoid prefixes to better support LTR languages. Prefixes, if desired, should be included 
    directly in the language strings instead. */ ?>
  <?= $this->element('notify/alert', [
    'message' => $message,
    'type' => 'success',
    'dismissible' => true
  ]) ?>
<?php endif; ?>