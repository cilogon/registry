<?php 
  $env_attributes = json_decode($vv_petition_env_identities->env_source_identity->env_attributes);

  // XXX this needs to be refactored
?>

<ul>
  <li>Env Source Identity ID: <?= $vv_petition_env_identities->env_source_identity->id ?></li>
  <li>Source Key: <?= $vv_petition_env_identities->env_source_identity->source_key ?></li>
  <?php foreach($env_attributes as $k => $v): ?>
  <li><?= __d('env_source', 'field.EnvSources.'.$k) . ": " . $v ?></li>
  <?php endforeach ?>
</ul>
