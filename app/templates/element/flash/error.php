<?php
  if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
  }
?>
<?php /*
<div class="message error" onclick="this.classList.add('hidden');"><?= $message ?></div>
*/ ?>


<?php
  if(!empty($message)) {
    // Strip tags then escape quotes before handing Flash message to noty.js
    $filteredMessage = filter_var(filter_var($message,FILTER_SANITIZE_STRING,FILTER_FLAG_NO_ENCODE_QUOTES),FILTER_SANITIZE_MAGIC_QUOTES);
    // Replace all newlines with html breaks
    $filteredMessage = str_replace(array("\r", "\n"), '<br/>', $filteredMessage);
    print "<script>generateFlash('" . $filteredMessage . "', 'error');</script>";
  }
?>

