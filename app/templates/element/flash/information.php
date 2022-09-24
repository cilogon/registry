<?php
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>

<?php if(!empty($message)): ?>
  <?= $this->Alert->alert(h($message), 'information', true, __d('information','flash.information')) ?>
<?php endif; ?>