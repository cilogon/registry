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

  use Cake\Utility\Hash;

  $options = [
    'type' => 'post',
    'url' => [
      'plugin'      => null,
      'controller'  => 'dashboards',
      'action'      => 'search'
    ],
    'id' => 'search'
  ];

  $resultsCount = 0;
  // Count only People and Groups for now. Other models can come later.
  foreach(['People', 'Groups', 'Cos'] as $pm) {
    $resultsCount += count($vv_results[$pm]);
  }

  if($vv_results['uuid']) {
    // It's unlikely we'll get here with a uuid in the search results since uuids will
    // generally result in an exact match, which will cause a redirect to the result

    $resultsCount++;
  }

  if(!empty($vv_results['cri'])) {
    foreach($vv_results['cri'] as $m => $rs) {
      $resultsCount += $rs->count();
    }
  }
?>

<div class="page-title-container">
  <div class="page-title">
    <h1><?= $vv_title; ?></h1>
  </div>
  <?php if($resultsCount): ?>
    <ul id="search-results-meta">
      <li class="search-results-found"><?= __d('result','search.result.found', [$resultsCount]); ?></li>
    </ul>
  <?php endif; ?>
</div>

<?= $this->element('flash') // Flash messages ?>
  
<div id="search-results">
  <?php if($resultsCount): ?>
    <nav id="cm-searchresults-subnav-tabs" class="cm-subnav-tabs">
      <ul class="nav nav-tabs" role="tablist">
        <?php $isFirstTab = true; ?>
        <?php foreach(['People', 'Groups'] as $i=>$pm): ?>
          <?php if(!empty($vv_results[$pm])): ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link search-result-tab<?= $isFirstTab ? ' active' : '' ?>" 
                      id="search-results-<?= strtolower($pm) ?>-tab" 
                      data-bs-toggle="tab" 
                      data-bs-target="#search-results-<?= strtolower($pm) ?>" 
                      type="button" role="tab" 
                      aria-controls="search-results-<?= strtolower($pm) ?>" 
                      aria-selected="true">
                <span class="tab-title">
                  <?= __d('controller', $pm, 2) ?>
                </span>
                <span class="badge rounded-pill bg-outline-primary">
                  <?= count($vv_results[$pm]) ?>
                </span>
              </button>
            </li>
            <?php $isFirstTab = false; ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div  id="search-results-tab-content ?>" class="search-results-group-container tab-content">
    <?php $isFirstTab = true; ?>
    <?php foreach(['People', 'Groups', 'Cos'] as $i=>$pm): ?>
      <?php if(!empty($vv_results[$pm])): ?>
        <div id="search-results-<?= strtolower($pm) ?>" 
             class="tab-pane fade<?= $isFirstTab ? ' show active' : '' ?>" 
             role="tabpanel" 
             aria-labelledby="search-results-<?= strtolower($pm) ?>-tab">
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
        <?php $isFirstTab = false; ?>
      <?php endif; ?> 
    <?php endforeach; ?>
    <?php if(!empty($vv_results['cri'])): ?>
      <div id="search-results-cri" 
             class="tab-pane fade<?= $isFirstTab ? ' show active' : '' ?>" 
             role="tabpanel" 
             aria-labelledby="search-results-cri-tab">
        <ul class="search-results-group">
          <?php foreach(array_keys($vv_results['cri']) as $model): ?>
            <?php foreach($vv_results['cri'][$model] as $match): ?>
              <?php
                $url = [
                  'controller'  => \Cake\Utility\Inflector::dasherize($model),
                  'action'      => 'edit',
                  $match->id
                ];

// XXX meh...
                $displayString = $model . " " . $match->id;
                $matchInfo = $match->modified;
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
            <?php endforeach; // $match ?>
          <?php endforeach; // $model ?>
        </ul>
      </div>
      <?php $isFirstTab = false; ?>
    <?php endif; // cri ?>
    </div>
  <?php else: ?>
    <p>
      <?= __d('result','search.retry'); ?>
    </p>
  <?php endif; ?>
</div>
