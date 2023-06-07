<?php
/**
* COmanage Registry Subnavigation Tabs Element
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
*
* The subnavigation is structured in first, second, and third level blocks.
* Only External Identity Roles have a third-level of navigation.
* 
*/
use Cake\View\Helper;

$linkFilter = [];
$flashArgs = [];
$curId = NULL;
$curController = $this->request->getParam('controller');
$curAction = $this->request->getParam('action');

if(!empty($vv_primary_link) && !empty($this->request->getQuery($vv_primary_link))) {
  // This will work for most top-level index views
  $curId = $this->request->getQuery($vv_primary_link);
  $linkFilter = [$vv_primary_link => $curId];
  // For top-level nav
  if(!empty($vv_person_id)) {
    $curId = $vv_person_id;
    $linkFilter = ['person_id' => $vv_person_id];
  }
} elseif (!empty($vv_obj)) {
  // This will work for most top-level edit views
  // XXX we might produce the $vv_primary_link for edit views so the approach below could be deprecated
  $curId = $vv_obj->id;
  if(!empty($vv_person_id)) {
    $curId = $vv_person_id;
  } elseif(!empty($vv_primary_link_id)) {
    $curId = $vv_primary_link_id;
  }
  // For top-level nav while in edit pages.
  if ($name == 'person') {
    $linkFilter = ['person_id' => $curId];
  } elseif ($name == 'group') {
    $linkFilter = ['group_id' => $curId];
  }
}

if(!empty($vv_obj)) {
  // Set the badge style for Person Status
  $statusBadgeClass = 'bg-warning';
  if($vv_obj['status'] == 'A') {
    $statusBadgeClass = 'bg-outline-secondary primary';
  } elseif (in_array($vv_obj['status'], ['D','N','S','X','XP'])) {
    $statusBadgeClass = 'bg-danger';
  }  
}

$supertitle = __d('information','global.title.none');
if(!empty($vv_supertitle)) {
  $supertitle = $vv_supertitle;
} elseif(!empty($vv_obj)) {
  $supertitle = $vv_obj->$vv_display_field;
} elseif(!empty($vv_person_name)) {
  $supertitle = $vv_person_name->full_name;
} elseif(!empty($vv_bc_parent_obj)) {
  $supertitle = $vv_bc_parent_obj->$vv_bc_parent_displayfield;
}
?>

<div id="subnavigation">
  <div class="supertitle-container">
    <div class="supertitle">
      <h1><?= $supertitle ?></h1>
      <?php if(!empty($vv_obj['status']) && $active == 'person'): ?>
        <span class="person-status-badge mr-1 badge <?= $statusBadgeClass ?>">
          <?= __d('enumeration', 'StatusEnum.' . $vv_obj['status']); ?>
        </span>
      <?php endif; ?>
    </div>
    
    <?php if($name == 'person'): ?>
      <!-- Specialty person dropdown menus - ADD and global actions -->
      <nav id="person-actions">
        <?php
          // Build the Add menu
          $action_args = array();
          $action_args['vv_attr_id'] =  $curId;
          $action_args['vv_actions_type'] = 'person-actions-add-menu';
          $action_args['vv_actions_title'] = __d('operation','add');
          $action_args['vv_actions_icon'] = 'add_circle';
          $action_args['vv_actions_class'] = 'person-actions-add-menu';
          $actionOrderDefault = $this->Menu->getMenuOrder('Default');
          $personAddMenuActions = [
            [
              'controller' => 'names',
              'action' => 'add',
              'icon' => 'account_box',
              'iconClass' => 'material-icons-outlined'
            ],
            [
              'controller' => 'email_addresses',
              'action' => 'add',
              'icon' => 'email',
              'iconClass' => 'material-icons-outlined'
            ],
            [
              'controller' => 'identifiers',
              'action' => 'add',
              'icon' => 'fingerprint'
            ],
            [
              'controller' => 'person_roles',
              'action' => 'add',
              'icon' => 'emoji_people'
            ],
            [
              'controller' => 'ad_hoc_attributes',
              'action' => 'add',
              'icon' => 'check_box',
              'iconClass' => 'material-icons-outlined'
            ],
            [
              'controller' => 'addresses',
              'action' => 'add',
              'icon' => 'contact_mail',
              'iconClass' => 'material-icons-outlined'
            ],
            [
              'controller' => 'history_records',
              'action' => 'add',
              'icon' => 'history'
            ],
            [
              'controller' => 'telephone_numbers',
              'action' => 'add',
              'icon' => 'phone'
            ],
            [
              'controller' => 'urls',
              'action' => 'add',
              'icon' => 'link'
            ]
          ];
          foreach(($personAddMenuActions ?? []) as $a) {
            $actionOrder = !empty($a['order']) ? $a['order'] : $actionOrderDefault++;
            $actionIcon = !empty($a['icon']) ? $a['icon'] : $this->Menu->getMenuIcon('Default');
            $actionIconClass = !empty($a['iconClass']) ? $a['iconClass'] : '';
            $actionClass = !empty($a['class']) ? $a['class'] : '';
            $actionUrl = $this->Url->build(
              [
                'controller' => $a['controller'],
                'action' => $a['action'],
                '?' => [
                  'person_id' => $curId
                ]
              ]
            );
            $actionLabel = __d('controller', Cake\Utility\Inflector::camelize($a['controller']), [1]);
            $action_args['vv_actions'][] = [
              'order' => $actionOrder,
              'icon' => $actionIcon,
              'iconClass' => $actionIconClass,
              'url' => $actionUrl,
              'class' => $actionClass,
              'label' => $actionLabel
            ];
          }
        ?>
        <div class="field-actions person-actions-add-menu-container">
          <?= $this->element('menuAction', $action_args) ?>
        </div>
            
        <?php
          // Build the global Person Actions menu
          $action_args = array();
          $action_args['vv_attr_id'] =  $curId;
          $action_args['vv_actions_type'] = 'person-actions-menu';
          $action_args['vv_actions_title'] = __d('field','actions',[99]);
          $action_args['vv_actions_icon'] = 'settings';
          $action_args['vv_actions_class'] = 'person-actions-menu';
          // history records
          $actionUrl = $this->Url->build(
            [
              'controller' => 'history_records',
              'action' => 'index',
              '?' => [
                'person_id' => $curId
              ]
            ]
          );
          $action_args['vv_actions'][] = array(
            'order' => $this->Menu->getMenuOrder('Default'),
            'icon' => 'history',
            'url' => $actionUrl,
            'label' => __d('controller', 'HistoryRecords', [99])
          );
          // provisioning actions
          $actionUrl = $this->Url->build(
            [
              'controller' => 'provisioning_targets',
              'action' => 'status',
              '?' => [
                'person_id' => $curId
              ]
            ]
          );
          $action_args['vv_actions'][] = array(
            'order' => $this->Menu->getMenuOrder('Default'),
            'icon' => 'cloud_sync',
            'url' => $actionUrl,
            'label' => __d('operation', 'provisioning.status')
          );
          // delete
          $actionPostBtnArray = ['action' => 'delete', $curId];
          $actionUrl = $this->Url->build(['action' => 'delete', $curId]);
          $action_args['vv_actions'][] = array(
            'order' => $this->Menu->getMenuOrder('Delete'),
            'icon' =>  $this->Menu->getMenuIcon('Delete'),
            'url' => 'javascript:void(0);',
            'label' => __d('operation', 'delete'),
            'class' => 'deletebutton nospin',
            'onclick' => array(
              'dg_bd_txt' => __d(
                'operation', 
                'delete.confirm', 
                [$supertitle . ', ' . __d('information','entity.id',[$curId])]
              ),
              'dg_post_btn_array' => $actionPostBtnArray,
              'dg_url' => $actionUrl,
              'dg_conf_btn' => __d('operation', 'remove'),
              'dg_cancel_btn' => __d('operation', 'cancel'),
              'dg_title' => __d('operation', 'remove'),
              'dg_bd_txt_repl_str' => ''
            )
          );
        ?>
        <div class="field-actions person-actions-menu-container">
          <?= $this->element('menuAction', $action_args) ?>
        </div>
      </nav>
    <?php endif; // person-actions ?>
  </div>

  <?php /* Flash Messages are placed below supertitle when subnavigation exists. */ ?>
  <?= $this->element('flash', $flashArgs); ?>
  
  <!-- Top-Level Subnavigation Tabs -->  
  <nav id="cm-<?= $name ?>-subnav-tabs" class="cm-subnav-tabs">
    <ul class="nav nav-tabs">
  
      <?php if($name == 'person'): ?>      
        <!-- Person Subnavigation -->
        <?php
          // Simplify our tests for roles and external identifiers
          $isPersonRole = (
            $vv_primary_link == 'person_role_id'
            || $vv_primary_link == 'person_role_id'
            || ($curController == 'PersonRoles' && ($curAction == 'edit' || $curAction == 'view'))
          ) ? true : false;
  
          $isExternalIdRole = (
            $vv_primary_link == 'external_identity_role_id'
            || ($curController == 'ExternalIdentityRoles' && ($curAction == 'edit' || $curAction == 'view'))
          ) ? true : false;
  
          $isExternalId = (
            $vv_primary_link == 'external_identity_id'
            || $vv_primary_link == 'external_identity_role_id'
            || ($curController == 'ExternalIdentities' && ($curAction == 'edit' || $curAction == 'view'))
            || ($curController == 'ExternalIdentityRoles' && ($curAction == 'edit' || $curAction == 'view'))
          ) ? true : false;
          
          // Determine active tab. This logic is necessitated by MVEAs (that are displayed in multiple contexts).
          $isCanvasTab = ($active == 'canvas') ? true : false;
          
          $isPersonTab = (
            $active == 'person' 
            && !$isPersonRole
            && !$isExternalId
          ) ? true : false;
          
          $isRolesTab = (
            $active == 'person_roles'
            || $isPersonRole
            && !$isExternalId
          ) ? true : false;
          
          $isExternalIdentitiesTab = (
            $active == 'external_identities' 
            || $isExternalId
          ) ? true : false;
  
          $isGroupsTab = ($active == 'groups') ? true : false;
        ?>
        
        <li class="nav-item">
          <?php
            $linkClass = $isPersonTab ? 'nav-link active' : 'nav-link';
            print $this->Html->link(
              __d('controller', 'People', [1]),
              [ 'controller' => 'people',
                'action' => 'edit',
                $curId
              ],
              ['class' => $linkClass]
            );
          ?>
        </li>
        <li class="nav-item">
          <?php
            $linkClass = $isRolesTab ? 'nav-link active' : 'nav-link';
            print $this->Html->link(
              __d('controller', 'PersonRoles', [99]),
              [ 'controller' => 'person_roles',
                'action' => 'index',
                '?' => $linkFilter
              ],
              ['class' => $linkClass]
            );
          ?>
        </li>
        <li class="nav-item">
          <?php
            $linkClass = $isExternalIdentitiesTab ? 'nav-link active' : 'nav-link';
            print $this->Html->link(
              __d('controller', 'ExternalIdentities', [99]),
              [ 'controller' => 'external-identities',
                'action' => 'index',
                '?' => $linkFilter
              ],
              ['class' => $linkClass]
            );
          ?>
        </li>
        <?php 
        /* XXX Groups tab placeholder - Keep for now. A group listing may only need to 
           exist on the Overview tab.
        <li class="nav-item">
          <?php
            $linkClass = $isGroupsTab ? 'nav-link active' : 'nav-link';
            print $this->Html->link(
              __d('controller', 'Groups', [99]),
              [ 'controller' => 'groups',
                'action' => 'index',
                '?' => $linkFilter
              ],
              ['class' => $linkClass]
            );
          ?>
        </li>
        */ ?>
      <?php endif; // person ?>
      
      <?php if ($name == 'group'): ?>
      <!-- Group Subnavigation -->
        <li class="nav-item">
          <?php
            $linkClass = ($active == 'properties') ? 'nav-link active' : 'nav-link';
            print $this->Html->link(
              __d('controller', 'Properties', [99]),
              [ 'controller' => 'groups',
                'action' => 'edit',
                $curId
              ],
              ['class' => $linkClass]
            );
          ?>
        </li>
        <li class="nav-item">
          <?php
            $linkClass = ($active == 'members') ? 'nav-link active' : 'nav-link';
            print $this->Html->link(
              __d('controller', 'Members', [99]),
              [ 'controller' => 'group_members',
                'action' => 'index',
                '?' => $linkFilter
              ],
              ['class' => $linkClass]
            ); 
          ?>
        </li>
        <li class="nav-item">
          <?php
            $linkClass = ($active == 'owners') ? 'nav-link active' : 'nav-link';
            print $this->Html->link(
              __d('controller', 'Owners', [99]),
              [ 'controller' => 'group_owners',
                'action' => 'index',
                '?' => $linkFilter
              ],
              ['class' => $linkClass]
            );
          ?>
        </li>
        <li class="nav-item">
          <?php
            $linkClass = ($active == 'nestings') ? 'nav-link active' : 'nav-link';
            print $this->Html->link(
              __d('controller', 'Nestings', [99]),
              [ 'controller' => 'group_nestings',
                'action' => 'index',
                '?' => $linkFilter
              ],
              ['class' => $linkClass]
            );
          ?>
        </li>
      <?php endif; // group ?>
    </ul>
  </nav>
    
  <?php if(!empty($subActive) && !($curController == 'PersonRoles' && $curAction == 'add')): ?>
  <!-- Second Level Subnavigation Links -->
    <?php
      $parentId = $curId;
      $curId = $this->request->getQuery($vv_primary_link);
      if($isPersonRole || $isExternalId) {
        if($isExternalId && !empty($vv_ei_id)) {
          $curId = $vv_ei_id;
        }
        // We display the role or external identity name above the subnav to show the hierarchy.
        // We also use this structure to set the second-level $linkFilter
        print '<h2>';
        if(!empty($vv_ei_name)) {
          // We have an external identity name
          print $vv_ei_name->full_name;
          $linkFilter = ['external_identity_id' => $curId];
        } elseif(!empty($vv_person_role)) {
          // We have a person role
          print $vv_person_role;
          $linkFilter = ['person_role_id' => $curId];
        } elseif(!empty($vv_obj)) {
          // We are editing/viewing an object
          if(!empty($vv_obj->title)) {
            print $vv_obj->title;
          } elseif (!empty($vv_subtitle)) {
            print $vv_subtitle;
          } else {
            print print __d('information','global.title.none');
          }
          // Set up $linkFilter for edit/view on Roles and External Identities
          $curId = $vv_obj->id;
          if($curController == 'PersonRoles') {
            $linkFilter = ['person_role_id' => $curId];
          }
          if($curController == 'ExternalIdentities') {
            $linkFilter = ['external_identity_id' => $curId];
          }
        } else {
          // We shouldn't get here, but have a deafult in case.
          print __d('information','global.title.none');
        }
        print '</h2>'; 
      }
    ?>
    <nav id="cm-<?= $name ?>-subnav-links" class="cm-subnav-links">
      <ul class="list-inline">
        <?php if($name == 'person'): ?>
          <li class="list-inline-item">
            <?php
              // Properties
              $cc = 'people';
              $cid = $curId;
              if($isPersonTab) {
                $cid = $parentId;
              }
              if($isPersonRole) {
                $cc = 'person-roles';
                $cid = !empty($vv_person_role_id) ? $vv_person_role_id : $curId;
              }
              if($isExternalId) {
                $cc = 'external-identities';
                if(!empty($vv_ei_id)) {
                  $cid = $vv_ei_id;  
                }
              }
              $linkClass = ($subActive == 'properties') ? 'nav-link active' : 'nav-link';
              print $this->Html->link(
                __d('controller', 'Properties', [99]),
                [ 'controller' => $cc,
                  'action' => 'edit',
                  $cid
                ],
                ['class' => $linkClass]
              );
            ?>
          </li>
          <?php if(!$isPersonRole): ?>
            <li class="list-inline-item">
              <?php
                // Names
                $linkClass = ($subActive == 'names') ? 'nav-link active' : 'nav-link';
                print $this->Html->link(
                  __d('controller', 'Names', [99]),
                  [ 'controller' => 'names',
                    'action' => 'index',
                    '?' => $linkFilter
                  ],
                  ['class' => $linkClass]
                );
              ?>
            </li>
            <li class="list-inline-item">
              <?php
                // Email Addresses
                $linkClass = ($subActive == 'email_addresses') ? 'nav-link active' : 'nav-link';
                print $this->Html->link(
                  __d('controller', 'EmailAddresses', [99]),
                  [ 'controller' => 'email_addresses',
                    'action' => 'index',
                    '?' => $linkFilter
                  ],
                  ['class' => $linkClass]
                );
              ?>  
            </li>
            <li class="list-inline-item">
              <?php
                // Identifiers
                $linkClass = ($subActive == 'identifiers') ? 'nav-link active' : 'nav-link';
                print $this->Html->link(
                  __d('controller', 'Identifiers', [99]),
                  [ 'controller' => 'identifiers',
                    'action' => 'index',
                    '?' => $linkFilter
                  ],
                  ['class' => $linkClass]
                );
              ?>
            </li>
            <?php if($isExternalId): ?>
              <li class="list-inline-item">
                <?php
                  // External Identity Roles
                  $linkClass = ($subActive == 'external_identity_roles' ||  $isExternalIdRole) ? 'nav-link active' : 'nav-link';
                  print $this->Html->link(
                    __d('controller', 'ExternalIdentityRoles', [99]),
                    [ 'controller' => 'external_identity_roles',
                      'action' => 'index',
                      '?' => $linkFilter
                    ],
                    ['class' => $linkClass]
                  );
                ?>
              </li>
            <?php endif; ?>
          <?php endif; ?>
          <li class="list-inline-item">
            <?php
              // Ad-Hoc Attributes
              $linkClass = ($subActive == 'ad_hoc_attributes' && !($isExternalIdRole)) ? 'nav-link active' : 'nav-link';
              print $this->Html->link(
                __d('controller', 'AdHocAttributes', [99]),
                [ 'controller' => 'ad_hoc_attributes',
                  'action' => 'index',
                  '?' => $linkFilter
                ],
                ['class' => $linkClass]
              );
            ?>
          </li>
          <li class="list-inline-item">
            <?php
              // Addresses
              $linkClass = ($subActive == 'addresses' && !($isExternalIdRole)) ? 'nav-link active' : 'nav-link';
              print $this->Html->link(
                __d('controller', 'Addresses', [99]),
                [ 'controller' => 'addresses',
                  'action' => 'index',
                  '?' => $linkFilter
                ],
                ['class' => $linkClass]
              );
            ?>
          </li>
          <li class="list-inline-item">
            <?php
              // Telephone Numbers
              $linkClass = ($subActive == 'telephone_numbers' && !($isExternalIdRole)) ? 'nav-link active' : 'nav-link';
              print $this->Html->link(
                __d('controller', 'TelephoneNumbers', [99]),
                [ 'controller' => 'telephone_numbers',
                  'action' => 'index',
                  '?' => $linkFilter
                ],
                ['class' => $linkClass]
              );
            ?>
          </li>
          <?php if(!$isPersonRole): ?>
            <li class="list-inline-item">
              <?php
                // URLs
                $linkClass = ($subActive == 'urls') ? 'nav-link active' : 'nav-link';
                print $this->Html->link(
                  __d('controller', 'Urls', [99]),
                  [ 'controller' => 'urls',
                    'action' => 'index',
                    '?' => $linkFilter
                  ],
                  ['class' => $linkClass]
                );
              ?>
            </li>
            <li class="list-inline-item">
              <?php
                // Pronouns
                $linkClass = ($subActive == 'pronouns') ? 'nav-link active' : 'nav-link';
                print $this->Html->link(
                  __d('controller', 'Pronouns', [99]),
                  [ 'controller' => 'pronouns',
                    'action' => 'index',
                    '?' => $linkFilter
                  ],
                  ['class' => $linkClass]
                );
              ?>
            </li>
          <?php endif; ?>
        <?php endif; // person subnav ?>
      </ul>
    </nav>
    
    <?php if($isExternalIdRole): ?>
    <!-- Third-level subnavigation links - only for External Identity Roles  -->
      <div id="external-id-role">
        <h3>
          <?php 
            // Set the link filter for External Identity Roles
            $curId = $vv_primary_link_obj->id;
            if(!empty($vv_obj) && ($curController == 'ExternalIdentityRoles' && ($curAction == 'edit' || $curAction == 'view'))) {
              $curId = $vv_obj->id;  
            }
            $linkFilter = ['external_identity_role_id' => $curId];
            
            if(!empty($vv_ei_role)) {
              print $vv_ei_role;
            } elseif(!empty($vv_obj->title)) {
              print $vv_obj->title;
            } else {
              // As above, we shouldn't get here, but have a deafult in case.
              print __d('information','global.title.none');
            }
          ?>  
        </h3>
        <nav id="external-id-role-nav" class="cm-subnav-links">
          <ul class="list-inline">
            <li class="list-inline-item">
              <?php
                // Properties
                $linkClass = (
                  $curController == 'ExternalIdentityRoles' 
                  && ($curAction == 'edit' || $curAction == 'view')
                ) ? 'nav-link active' : 'nav-link';
                print $this->Html->link(
                  __d('controller', 'Properties', [99]),
                  [ 'controller' => 'external-identity-roles',
                    'action' => 'edit',
                    $curId
                  ],
                  ['class' => $linkClass]
                );
              ?>
            </li>
            <li class="list-inline-item">
              <?php
                // Ad-Hoc Attributes
                $linkClass = ($subActive == 'ad_hoc_attributes') ? 'nav-link active' : 'nav-link';
                print $this->Html->link(
                  __d('controller', 'AdHocAttributes', [99]),
                  [ 'controller' => 'ad_hoc_attributes',
                    'action' => 'index',
                    '?' => $linkFilter
                  ],
                  ['class' => $linkClass]
                );
              ?>
            </li>
            <li class="list-inline-item">
              <?php
                // Addresses
                $linkClass = ($subActive == 'addresses') ? 'nav-link active' : 'nav-link';
                print $this->Html->link(
                  __d('controller', 'Addresses', [99]),
                  [ 'controller' => 'addresses',
                    'action' => 'index',
                    '?' => $linkFilter
                  ],
                  ['class' => $linkClass]
                );
              ?>
            </li>
            <li class="list-inline-item">
              <?php
                // Telephone Numbers
                $linkClass = ($subActive == 'telephone_numbers') ? 'nav-link active' : 'nav-link';
                print $this->Html->link(
                  __d('controller', 'TelephoneNumbers', [99]),
                  [ 'controller' => 'telephone_numbers',
                    'action' => 'index',
                    '?' => $linkFilter
                  ],
                  ['class' => $linkClass]
                );
              ?>
            </li>
          </ul>  
        </nav>
      </div>
    <?php endif; // external identity 2nd level subnav ?>
  <?php endif; // 2nd level subnav ?>
</div>
  