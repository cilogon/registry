<?php
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>

<?php if(!empty($message)): ?>
  <?php /* Note: unlike Notice, Error, and Success messages, Information messages require 
           no prefix. That is, we don't include "Information: " in front of the message. */ ?> 
  <?= $this->Alert->alert($message, 'information', true) ?>
<?php endif; ?>