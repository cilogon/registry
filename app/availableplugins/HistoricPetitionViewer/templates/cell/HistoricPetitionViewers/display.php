<?php
declare(strict_types=1);

// Inputs (backward-compatible)
$metaRows = $vv_historic_petition_metadata_records ?? [];
$attrRows = $vv_historic_petition_attributes ?? [];
$entityId = (isset($vv_obj) && is_object($vv_obj) && isset($vv_obj->id)) ? (string)$vv_obj->id : null;

if ($entityId === null) {
  echo __d('error', 'notfound', 'Historic Petition');
  return;
}

// Helpers
$toArr = static function ($row): array {
  if (is_array($row)) return $row;
  if (is_object($row)) return method_exists($row, 'toArray') ? (array)$row->toArray() : (array)$row;
  return [];
};

$prettify = static function (string $label): string {
  // petition_meta_hist_rec_id -> Petition Meta Hist Rec Id
  $label = str_replace(['_', '-'], ' ', strtolower($label));
  $label = preg_replace('/\s+/', ' ', trim($label));
  return ucwords($label);
};

$renderLi = static function (string $label, $value) {
  if ($value === null || $value === '') return;
  $out = is_scalar($value) ? (string)$value : json_encode($value);
  ?>
    <li class="petition-key-value">
      <h4 class="petition-attr-label">
        <?= h($label) ?>
      </h4>
      <div class="petition-attr-value">
        <?= h($out) ?>
      </div>
    </li>
  <?php
};

// Exclusions and preferred ordering (customize if needed)
$excludeMetaKeys = [
  'id', 'petition_id',
  'historic_petition_metadata_id', 'historic_petition_attribute_id',
  'revision', 'deleted', 'actor_identifier', 'created', 'modified'
];
$preferredMetaOrder = [
  'approver_comment',
  'return_url',
  'token', 'petitioner_token', 'enrollee_token',
  'enrollee_external_identity_id', 'archived_external_identity_id', 'enrollee_person_role_id',
  'sponsor_person_id', 'approver_person_id',
  'co_invite_id', 'vetting_request_id',
  'enrollment_flow_id', 'historic_petition_viewer_id',
];

// Unique IDs for tabs (in case multiple cells on the page)
$tabIdBase = 'hpv-' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $entityId ?? uniqid());
$metaTabId = $tabIdBase . '-meta';
$attrTabId = $tabIdBase . '-attrs';
?>

<div class="historic-petition-id"><?= __d('historic_petition_viewer','historic.petition',$entityId) ?></div>

<!-- Metadata -->
<h3><?= __d('historic_petition_viewer','metadata') ?></h3>
<?php if (!empty($metaRows)): ?>
  <?php foreach ($metaRows as $meta): ?>
    <?php
    $arr = $toArr($meta);
    $keys = array_values(array_diff(array_keys($arr), $excludeMetaKeys));
    $ordered = array_values(array_unique(array_merge($preferredMetaOrder, $keys)));
    ?>
    <ul class="petition-metadata-list">
      <?php foreach ($ordered as $k): ?>
        <?php if (in_array($k, $excludeMetaKeys, true)) continue; ?>
        <?php $renderLi($prettify($k), $arr[$k] ?? null); ?>
      <?php endforeach; ?>
    </ul>
  <?php endforeach; ?>
<?php else: ?>
  <p class="empty-hint"><?= __d('historic_petition_viewer','metadata.none') ?></p>
<?php endif; ?>

<!-- Attributes -->
<h3><?= __d('historic_petition_viewer','attributes') ?></h3>
<?php if (!empty($attrRows)): ?>
  <ul class="petition-attributes-list">
    <?php foreach ($attrRows as $row): ?>
      <?php
      $a = $toArr($row);
      $label = $a['attribute'] ?? null;
      $value = $a['value'] ?? null;
      if ($label === null) continue;
      $renderLi($prettify((string)$label), $value);
      ?>
    <?php endforeach; ?>
  </ul>
<?php else: ?>
  <p class="empty-hint"><?= __d('historic_petition_viewer','attributes.none') ?></p>
<?php endif; ?>

