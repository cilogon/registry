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
    <h1><?= __d('operation','search.global'); ?></h1>
  </div>
</div>

<div id="search-container" aria-labelledby="global-search-toggle">
  <?php
    print $this->Form->create(null, $options);
    print $this->Form->hidden('co_id', ['default' => $vv_cur_co->id]);
  ?>
  <div class="input-group">
  <?php
    print $this->Form->label(
      'q', 
      __d('operation','search'),
      [
        'class' => 'visually-hidden'
      ]
    );
    print $this->Form->input(
      'q', 
      [
        'id' => 'q',
        'class' => 'form-control',
        'placeholder' => __d('field','search.placeholder')
      ]
    );
    print $this->Form->button(
      '<span class="material-icons-outlined">close</span>',
      ['type' => 'button', 'escapeTitle' => false, 'id' => 'search-clear', 'class' => 'btn btn-link']
    );
    print $this->Form->button(
      __d('operation','search'),
      ['type' => 'submit', 'escapeTitle' => false, 'class' => 'btn btn-primary btn-sm']
    );
    ?>
  </div>
  <?php
    print $this->Form->end();
  ?>

  <?php /* keep the following temporarily:
  <div id="global-search-type">
    <div class="form-check form-check-inline">
      <input class="form-check-input" type="radio" id="gs-type-basic" name="gs-type">
      <label class="form-check-label" for="gs-type-basic">
        basic
      </label>
    </div>
    <div class="form-check form-check-inline">
      <input class="form-check-input" type="radio" id="gs-type-advanced" name="gs-type">
      <label class="form-check-label" for="gs-type-advanced">
        advanced
      </label>
    </div>
  </div> */ ?>
</div>
  
<h2><?= $vv_title; ?></h2>

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
       <?= __d('result','search.result.found.modelCount', [$peopleResultsCount, __d('controller', "People", [$peopleResultsCount])]); ?>
     </li>
    <?php endif; ?>
    <?php if($groupsResultsCount): ?>
      <li>
        <?= __d('result','search.result.found.modelCount', [$groupsResultsCount, __d('controller', "Groups", [$groupsResultsCount])]); ?>
      </li>
    <?php endif; ?>
  </ul>
<?php endif; ?>
  
<div id="search-results">
<!-- Start with the Primary Registry objects -->
<?php foreach(['People', 'Groups'] as $pm): ?>
  <?php if(!empty($vv_results[$pm])): ?>
    <div class="search-results-group-container">
      <h3><?= __d('controller', $pm, 2); ?></h3>
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
  <?php endif; ?> 
<?php endforeach; ?>

<?php if($noResults): ?>
  <p>
    <?= __d('result','search.retry'); ?>
  </p>
<?php endif; ?>
  
</div>
