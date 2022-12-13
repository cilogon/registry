<?php
/**
 * COmanage Registry Dashboards Dashboard View
 *
 * Portions licensed to the University Corporation for Advanced Internet
 * Development, Inc. ("UCAID") under one or more contributor license agreements.
 * See the NOTICE file distributed with this work for additional information
 * regarding copyright ownership.
 *
 * UCAID licenses this file to you under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @link          https://www.internet2.edu/comanage COmanage Project
 * @package       registry
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

  $options = [
    'type' => 'post',
    'url' => [
      'plugin'      => null,
      'controller'  => 'dashboards',
      'action'      => 'search'
    ],
    'id' => 'search'
  ];

  $noResults = true;
?>

<div class="pageTitleContainer">
  <div class="pageTitle">
    <h1><?= $vv_title; ?></h1>
  </div>
</div>

<!-- Flash Messages and defined Info Banners -->
<div class="alert-container" id="flash-messages">
  <?= $this->Flash->render() ?>

  <?php if(!empty($indexBanners)): ?>
    <?php foreach($indexBanners as $b): ?>
      <?=  $this->Alert->alert($b, 'warning') ?>
    <?php endforeach; // $indexBanners ?>
  <?php endif; // $indexBanners ?>

  <?php if(!empty($banners)): ?>
    <?php foreach($banners as $b): ?>
      <?=  $this->Alert->alert($b, 'warning') ?>
    <?php endforeach; // $banners ?>
  <?php endif; // $banners ?>
</div>

<?php
  $peopleResultsCount = count($vv_results['People']);
  $groupsResultsCount = count($vv_results['Groups']);
?>  
<?php if($peopleResultsCount || $groupsResultsCount): ?>
  <?php $noResults = false; ?>
  <ul id="search-results-meta">
    <li class="search-results-found"><?= __d('result', 'search.result.found') ?></li>
    <?php if($peopleResultsCount): ?>
     <li>
       <a href="#search-results-people" class="nospin">
        <?= __d('result','search.result.found.modelCount', [$peopleResultsCount, __d('controller', "People", [$peopleResultsCount])]); ?>
       </a>
     </li>
    <?php endif; ?>
    <?php if($groupsResultsCount): ?>
      <li>
        <a href="#search-results-groups" class="nospin">
          <?= __d('result','search.result.found.modelCount', [$groupsResultsCount, __d('controller', "Groups", [$groupsResultsCount])]); ?>
        </a>
      </li>
    <?php endif; ?>
  </ul>
<?php endif; ?>
  
<div id="search-results" class="accordion">
<!-- Start with the Primary Registry objects -->
<?php foreach(['People', 'Groups'] as $pm): ?>
  <?php if(!empty($vv_results[$pm])): ?>
    <div  id="search-results-<?= strtolower($pm) ?>" class="search-results-group-container accordion-item">
      <div id="search-results-<?= strtolower($pm) ?>-header" class="search-results-group-title accordion-header">
        <button class="accordion-button" 
                data-bs-toggle="collapse" 
                data-bs-target="#search-results-<?= strtolower($pm) ?>-body" 
                aria-expanded="true" 
                aria-controls="search-results-<?= strtolower($pm) ?>-body">
          <?= __d('controller', $pm, 2); ?>
        </button>
      </div>
      <div id="search-results-<?= strtolower($pm) ?>-body" class="accordion-collapse collapse show">
        <div class="accordion-body">
          <ul class="search-results-group">
            <?php foreach($vv_results[$pm] as $pkey => $matches): ?>
              <?php  
                $url = [
                  'controller'  => \Cake\Utility\Inflector::dasherize($pm),
                  'action'      => 'edit',
                  $pkey
                ];
                // The same entity can match on more than one searchable model
              ?>
                
              <?php foreach($matches as $m => $entity): ?>
                <?php 
                  $displayField = $vv_supported_models[$m]['displayField'];
                  $displayLabel = __d('field', $displayField);
                  $displayString = $entity->$displayField;
          
                  // If we match on a related model (for example PersonRoles for People)
                  // indicate what actually matched
                  $matchInfo = __d('result', 'search.result.id', $pkey);
          
                  // Do we have a more informative string to render?
                  if(!empty($entity->person->primary_name->full_name)) {
                    $displayString = $entity->person->primary_name->full_name;
          
                    $matchInfo = __d('result', 'search.result.related', $displayLabel, $entity->$displayField, $pkey);
                  } elseif(!empty($entity->group->name)) {
                    $displayString = $entity->group->name;
          
                    $matchInfo = __d('result', 'search.result.related', $displayLabel, $displayString, $pkey);
                  }
                ?>
                <li class="search-result">
                  <a href="<?= $this->Url->build($url) ?>">
                    <div class="search-result-name">
                      <?= $displayString ?>
                    </div>
                    <div class="search-result-match-info">
                      <?= filter_var($matchInfo, FILTER_SANITIZE_SPECIAL_CHARS) ?>
                    </div>
                  </a>
                </li>
              <?php endforeach; ?>  
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  <?php endif; ?> 
<?php endforeach; ?>

<?php if($noResults): ?>
  <p>
    <?= __d('result','search.retry'); ?>
  </p>
<?php endif; ?>
  
</div>
