<?php
/**
 * COmanage Registry MVEA Card
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

// Get parameters
$vv_obj = $vv_obj;          // the object with the MVEAs in it  
$mveaType = $mveaType;      // the type of MVEA we will fetch
$entityType = $entityType;  // the type of entity continaing the MVEA (person, person_role, external_identity)
  
// Get the camel-case controller name and generate the title  
$mveaController = Cake\Utility\Inflector::camelize($mveaType);
$title = __d('controller', $mveaController, [99]);

// Create an MVEA card for a canvas page
// XXX NOTE: this view is deprecated - mveaJs is instead used with VueJS components. This remains for reference.  
?>

<div class="col cm-mvea-col">
  <div class="card cm-card h-100">
    <div class="card-body">
      <h2 class="card-title">
        <?= $this->Html->link(
          __d('controller', $mveaController, [99]),
          [ 'controller' => $mveaType,
            'action' => 'index',
            '?' => ['person_id' => $vv_obj['id']]
          ]
        ); ?>
      </h2>
      <div class="card-text">
        <ul id="person-canvas-<?= $mveaType ?>>" class="fields data-list">
          <?php foreach($vv_obj[$mveaType] as $mvea): ?>
            <li class="field-data-container linked-row">
              <div class="field-data force-wrap ">
                <?php 
                  // Get the link label based on MVEA type
                  $linkTitle = __d('information','global.title.none');
                  switch($mveaType) {
                    case 'names': 
                      /* XXX THIS IS TEMPORARY. This approach for generating the name label
                         is stolen directly from Model/Entity/Name.php::_getFullName(). Better is to have
                         this pre-populated in the data when it arrives or pass to a function. */
                      $cn = "";
                      if (!empty($mvea['display_name'])) {
                        $cn = $mvea['display_name'];
                      } else {
                        if (empty($mvea['language'])
                          || !in_array($mvea['language'], ['hu', 'ja', 'ko', 'za-Hans', 'za-Hant'])) {
                          // Western order. (Including honorific in this view if set.)
                          if (!empty($mvea['honorific'])) {
                            $cn .= ($cn != "" ? ' ' : '') . $mvea['honorific'];
                          }
  
                          if (!empty($mvea['given'])) {
                            $cn .= ($cn != "" ? ' ' : '') . $mvea['given'];
                          }
  
                          if (!empty($mvea['middle'])) {
                            $cn .= ($cn != "" ? ' ' : '') . $mvea['middle'];
                          }
  
                          if (!empty($mvea['family'])) {
                            $cn .= ($cn != "" ? ' ' : '') . $mvea['family'];
                          }
  
                          if (!empty($mvea['suffix'])) {
                            $cn .= ($cn != "" ? ' ' : '') . $mvea['suffix'];
                          }
                        } else {
                          // Switch to Eastern order.
                          if (!empty($mvea['family'])) {
                            $cn .= ($cn != "" ? ' ' : '') . $mvea['family'];
                          }
  
                          if (!empty($mvea['given'])) {
                            $cn .= ($cn != "" ? ' ' : '') . $mvea['given'];
                          }
                        }
                      }
                      // This should never happen with names, but have a fallback anyway:
                      if (empty($cn)) {
                        $cn = __d('information', 'global.title.none');
                      }
                      $linkTitle = $cn;
                      break;
                    case 'email_addresses':
                      $linkTitle = $mvea['mail'];
                      break;
                    case 'identifiers':
                      $linkTitle = $mvea['identifier'];
                      break;
                    case 'ad_hoc_attributes':
                      $linkTitle = !empty($mvea['value']) ? $mvea['value'] : __d('information','global.value.none');
                      break;
                    case 'addresses':
                      $linkTitle = trim($mvea['room'] . ' ' . $mvea['street']);
                      break;
                    case 'telephone_numbers':
                      $linkTitle = $mvea['country_code'] . ' ' . $mvea['area_code'] . ' ' . $mvea['number'];
                      break;
                    case 'urls':
                      $linkTitle = !empty($mvea['description']) ? $mvea['description'] : $mvea['url'];
                      break;
                    case 'pronouns':
                      break;
                  }
                ?>
                <?php if($mveaType == 'addresses'): ?>
                  <address>
                <?php endif; ?>
                <?= $this->Html->link(
                  $linkTitle,
                  [ 
                    'controller' => $mveaType,
                    'action' => 'edit',
                    $mvea['id']
                  ],
                  [
                    'class' => 'row-link'
                  ]
                ); ?>
                <?php if($mveaType == 'addresses'): ?>
                    <?php // XXX if we have other places to output an address just for display, make a function. ?>
                    <?php if(!empty($mvea['locality']) || !empty($mvea['state'])): ?>
                      <br><?= $mvea['locality'] ?>
                      <?= (!empty($mvea['locality']) && !empty($mvea['state'])) ? ', ' : '' ?> 
                      <?= $mvea['state']; ?>
                    <?php endif; ?>
                    <?php if(!empty($mvea['postal_code']) || !empty($mvea['country'])): ?>
                      <br><?= $mvea['postal_code'] . ' ' . $mvea['country']; ?>
                    <?php endif; ?>
                  </address>
                <?php endif; ?>
              </div>
              <?php if(!empty(
                $mvea['type_id']) 
                || $mveaType == 'names' && $mvea['primary_name']
                || $mveaType == 'identifiers' && $mvea['login']
                || !empty($mvea['language']) 
                || !($mvea['verified']) 
                || !(empty($mvea['tag']))
              ): ?>
                <div class="field-data data-label">
                  <?php if($mveaType == 'names' && $mvea['primary_name']): ?>
                    <span class="mr-1 badge bg-outline-secondary primary"><?= __d('field','primary') ?></span>
                  <?php endif; ?>
                  <?php if($mveaType == 'identifiers' && $mvea['login']): ?>
                    <span class="mr-1 badge bg-outline-secondary login"><?= __d('field','login') ?></span>
                  <?php endif; ?>
                  <?php if($mveaType == 'email_addresses' && !($mvea['verified'])): ?>
                    <span class="mr-1 badge bg-warning unverified"><?= __d('field','unverified') ?></span>
                  <?php endif; ?>
                  <?php if(!empty($mvea['type'])): ?>
                    <span class="mr-1 badge bg-light type">
                      <?= !empty($mvea['type']['display_name']) ? $mvea['type']['display_name'] : $mvea['type']['value'] ?>
                    </span>
                  <?php endif; ?>
                  <?php if(!empty($mvea['language'])): ?>
                    <span class="mr-1 badge bg-light lang">
                      <?= __d('enumeration','LanguageEnum.' . $mvea['language']) ?>
                    </span>
                  <?php endif; ?>
                  <?php if($mveaType == 'ad_hoc_attributes' && !empty($mvea['tag'])): ?>
                    <span class="mr-1 badge bg-light ad-hoc"><?= $mvea['tag'] ?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
