<?php
  $class = 'message';
  if (!empty($params['class'])) {
    $class .= ' ' . $params['class'];
  }
  if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
  }
?>
<?php /*
<div class="<?= h($class) ?>" onclick="this.classList.add('hidden');"><?= $message ?></div>
*/ ?>

<?php
  if(!empty($message)) {
    // Strip tags then escape quotes before handing Flash message to noty.js
    $filteredMessage = filter_var(filter_var($message,FILTER_SANITIZE_STRING,FILTER_FLAG_NO_ENCODE_QUOTES),FILTER_SANITIZE_ADD_SLASHES);
    // Replace all newlines with html breaks
    $filteredMessage = str_replace(array("\r", "\n"), '<br/>', $filteredMessage);
    print "<script>generateFlash('" . $filteredMessage . "', '" . $class . "');</script>";
  }
?>

