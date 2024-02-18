<?php
/**
 * COmanage Registry PrimaryLink Trait
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

declare(strict_types = 1);

namespace App\Lib\Traits;

use \Cake\Datasource\EntityInterface;
use \Cake\ORM\TableRegistry;

trait PrimaryLinkTrait {
  // Primary Link field (eg: model:co_id). Note some models, in particular
  // MVEAs, can have multiple Primary Links. $primaryLinks will be key/value
  // pairs, where the key is the field (eg: co_id) and the value is the table
  // (eg: Cos).
  private $primaryLinks = [];

  // Allow empty primary link?
  private $allowEmptyActions = [];
  
  // Actions that can have an unkeyed (ie: self asserted) primary link ID
  private $unkeyedActions = ['add', 'index'];
  
  // Actions where the primary link can be obtained by looking up the record ID
  private $lookupActions = ['delete', 'edit', 'canvas', 'view'];
  
  // Where to redirect on add or edit, can be 'self', 'index', 'pluggableLink', or 'primaryLink'.
  // We use null to mean "index unless we're in a plugin context, in which case pluggableLink".
  // This array is keyed on the action (or "*" for default).
  private $redirectGoal = ['*' => null];
  
  // Accept the current CO ID?
  private $acceptCoId = false;
  protected $curCoId = null;
  
  /**
   * Determine if this table accepts the CO ID via AppController.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool true if this table accepts the CO ID, false otherwise
   */
  
  public function acceptsCoId(): bool {
    return $this->acceptCoId;
  }
  
  /**
   * Check to see whether the specified actions permits the primary link to be empty.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string   $action Action
   * @return boolean          true if permitted, false otherwise
   */
  
  public function allowEmptyPrimaryLink(string $action) {
    return in_array($action , $this->allowEmptyActions);
  }
  
  /**
   * Check to see whether the specified action allows a record ID to be passed
   * in the URL, which can be used to lookup a primary link.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $action Action
   * @return boolean true if permitted, false otherwise
   */
  
  public function allowLookupPrimaryLink(string $action) {
    return in_array($action, $this->lookupActions, true);
  }
  
  /**
   * Check to see whether the specified action is allowed to assert a primary link ID
   * directly (ie: not via lookup of an associated record).
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $action Action
   * @return boolean true if permitted, false otherwise
   */
  
  public function allowUnkeyedPrimaryLink(string $action) {
    return in_array($action, $this->unkeyedActions, true);
  }
  
  /**
   * Determine the CO for an entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EntityInterface $entity   Entity
   * @param  bool            $original If true, calculate based on the original value (for a dirty entity)
   * @return int|null                CO ID or null if not found
   */
  
  public function calculateCoForRecord(EntityInterface $entity, bool $original=false): ?int {
    if(isset($this->primaryLinks['co_id'])) {
      if(!empty($entity->co_id)) {
        return ($original ? $entity->getOriginal('co_id') : $entity->get('co_id'));
      }
    } else {
      foreach($this->primaryLinks as $linkField => $linkTable) {
        $lf = $linkField;

        if(strstr($linkField, '.')) {
          // Modified plugin notation ("CoreAssigners.format_assigner_id"),
          // we just need the fieldname, not the full string.

          $bits = explode(".", $linkField, 2);
          $lf = $bits[1];
        }

        if(!empty($entity->$lf)) {
          // Use this field. Recursively ask the primaryLink until we get an answer.
          $LinkTable = TableRegistry::getTableLocator()->get($linkTable);

          $linkValue = ($original ? $entity->getOriginal($lf) : $entity->get($lf));

          return $LinkTable->findCoForRecord($linkValue);
        }
      }
    }
    
    return null;
  }
  
  /**
   * Determine the CO for a record based on its ID.
   *
   * #since  COmanage Registry v5.0.0
   * @param  int      $id Record ID
   * @return int|null     CO ID or null if not found
   * @throws Cake\Datasource\Exception\RecordNotFoundException
   */
  
  public function findCoForRecord(int $id): ?int {
    // Pull the object to examine the primary links. We might be asked to find the
    // CO for a deleted object (eg: to add a history record, or to show an older
    // value for an entity), so accept archived records.
    return $this->calculateCoForRecord($this->get($id, ['archived' => true]));
  }
  
  /**
   * Generate an ORM Query for the Primary Link.
   *
   * @since  COmanage Registry v5.0.0
   * @param  CakeORMQuery $query   Cake ORM Query
   * @param  array        $options Cake ORM Query options
   * @return CakeORMQuery          Cake ORM Query
   */
  
  public function findFilterPrimaryLink(\Cake\ORM\Query $query, array $options) {
    return $query->where([$this->getPrimaryLink() => $options[$this->primaryLink]]);
  }
  
  /**
   * Find the Primary Link associated with the requested object ID.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $id       Object ID
   * @param  bool   $archived Whether to retrieve archived (deleted) records
   * @return object           Primary Link information (as an object)
   * @throws \InvalidArgumentException
   */
  
  public function findPrimaryLink(int $id, bool $archived=false): object {
    $obj = $this->get($id, ['archived' => $archived]); //->firstOrFail();
    
    // We might have multiple primary link keys (eg for MVEAs), but only one
    // should be set. Return the first one we find.
    foreach(array_keys($this->primaryLinks) as $plKey) {
      if(!empty($obj->$plKey)) {
        // If this Primary Link points to a plugin, add a hint for the callter
        $plugin = null;

        if(strstr($this->primaryLinks[$plKey], '.')) {
          $plugin = \App\Lib\Util\StringUtilities::pluginPlugin($this->primaryLinks[$plKey]);
        }

        return (object)[
          'plugin'  => $plugin,
          'attr'    => $plKey,
          'value'   => $obj->$plKey,
          'co_id'   => $this->calculateCoForRecord($obj)
        ];
      }
    }
    
    throw new \InvalidArgumentException(__d('error', 'primary_link'));
  }
  
  /**
   * Find the Primary Link for an entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity $entity Entity
   * @return Entity         Primary Link (as an Entity)
   * @throws \InvalidArgumentException
   */
  
  public function findPrimaryLinkEntity($entity) {
    foreach(array_keys($this->primaryLinks) as $plKey) {
      if(!empty($entity->$plKey)) {
        $LinkTable = TableRegistry::getTableLocator()->get($this->primaryLinks[$plKey]);
        
        return $LinkTable->findById($entity->$plKey)->firstOrFail();
      }
    }
    
    throw new \InvalidArgumentException(__d('error', 'primary_link'));
  }
  
  /**
   * Obtain the primary link fields.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Primary link attributes
   */
  
  public function getPrimaryLinks(): array {
    return array_keys($this->primaryLinks);
  }
  
  /**
   * Obtain the primary link's table name.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $primaryLink Primary Link field
   * @return string              Primary link table name
   */
  
  public function getPrimaryLinkTableName(string $primaryLink): string {
    return $this->primaryLinks[$primaryLink] ?? 'Cos';
  }
  
  /**
   * Obtain this table's redirect goal.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $action  Action
   * @return string          Redirect goal
   */
  
  public function getRedirectGoal(string $action): ?string {
    return $this->redirectGoal[$action] ?? $this->redirectGoal['*'];
  }
  
  /**
   * Determine the External Identity ID associated with an entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity $entity Entity
   * @return ?int           Person ID
   */
  
  public function lookupExternalIdentityId($entity): ?int {
    // We need to see if 'external_identity_id' exists on the $entity, but it's
    // not easy. We can't use property_exists() because Cake is dynamically
    // getting. The Cake Entity API documentation says we should be able to call
    // $entity->__isset(), but that doesn't actually implement the documented
    // behavior. (Github issue: https://github.com/cakephp/cakephp/issues/16408)
    // So we have to extract the key and then use array_key_exists() (but NOT isset()).

    $a = $entity->extract(['external_identity_id']);
    
    if($entity->getSource() == 'ExternalIdentities') {
      return $entity->id;
    } elseif(array_key_exists('external_identity_id', $a)) {
      // We want to return here whether or not the key is set since if it's NULL
      // we're not directly pointing to an External Identity. We can't use
      // property_exists because Cake is dynamically getting.
      
      return $entity->external_identity_id;
    } else {
      $linkEntity = $this->findPrimaryLinkEntity($entity);
      
      if(!empty($linkEntity->external_identity_id)) {
        return $linkEntity->external_identity_id;
      }
    }
    
    return null;
  }
  
  /**
   * Determine the External Identity Role ID associated with an entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity $entity Entity
   * @return int            Person Role ID
   */
  
  public function lookupExternalIdentityRoleId($entity): ?int {
    $a = $entity->extract(['external_identity_role_id']);
    
    if($entity->getSource() == 'ExternalIdentityRoles') {
      return $entity->id;
    } elseif(array_key_exists('external_identity_role_id', $a)) {
      return $entity->external_identity_role_id;
    }
    
    return null;
  }
  
  /**
   * Determine the Group ID associated with an entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity $entity Entity
   * @return int            Group ID
   */
  
  public function lookupGroupId($entity): ?int {
    $a = $entity->extract(['group_id']);
    
    if($entity->getSource() == 'Groups') {
      return $entity->id;
    } elseif(array_key_exists('group_id', $a)) {
      return $entity->group_id;
    }
    
    return null;
  }
  
  /**
   * Determine the Person ID associated with an entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity $entity Entity
   * @return ?int           Person ID
   */
  
  public function lookupPersonId($entity): ?int {
    $a = $entity->extract(['person_id']);
    
    if($entity->getSource() == 'People') {
      return $entity->id;
    } elseif(array_key_exists('person_id', $a)
             // MVEAs can have multiple parent keys, but not all of them may be set
             && !empty($entity->person_id)) {
      return $entity->person_id;
    } else {
      $linkEntity = $this->findPrimaryLinkEntity($entity);

      if(!empty($linkEntity->person_id)) {
        return $linkEntity->person_id;
      } else {
        // Our parent link does not directly point to Person, so try recursing
        // on our parent table, though we might also not have a parent that points
        // to a Person (eg Group -> Co).
        
        $LinkTable = TableRegistry::getTableLocator()->get($linkEntity->getSource());

        if(method_exists($LinkTable, "lookupPersonId")) {
          return $LinkTable->lookupPersonId($linkEntity);
        }
      }
    }
    
    return null;
  }
  
  /**
   * Determine the Person Role ID associated with an entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity $entity Entity
   * @return int            Person Role ID
   */
  
  public function lookupPersonRoleId($entity): ?int {
    $a = $entity->extract(['person_role_id']);
    
    if($entity->getSource() == 'PersonRoles') {
      return $entity->id;
    } elseif(array_key_exists('person_role_id', $a)) {
      return $entity->person_role_id;
    }
    
    return null;
  }
  
  /**
   * Set whether this table accepts a CO ID, set by AppController. In general,
   * tables should NOT use this unless there is no other way to get the CO ID.
   * In general, it is preferable to accept the CO ID as a function argument,
   * or by calling findCoForRecord or calculateCoForRecord. This functionality
   * is for contexts like setting validation rules, where passing in the CO ID
   * normally is not possible.
   *
   * @since  COmanage Registry v5.0.0
   * @param  bool $accepts true if this table accepts the CO ID, false otherwise
   */
  
  public function setAcceptsCoId(bool $accepts) {
    $this->acceptCoId = $accepts;
  }
  
  /**
   * Set whether the primary link is permitted to be empty.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  array   $actions   Actions where the primary link is allowed to be empty
   */
  
  public function setAllowEmptyPrimaryLink(array $actions) {
    $this->allowEmptyActions = array_merge($this->allowEmptyActions, $actions);
  }
  
  /**
   * Set whether the primary link can be resolved via the object ID in the URL.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  array   $actions   Actions where the primary link can be obtained by looking up the record ID
   */
  
  public function setAllowLookupPrimaryLink(array $actions) {
    $this->lookupActions = array_merge($this->lookupActions, $actions);
  }
  
  /**
   * Set which actions permit a primary link to be passed as a request parameter.
   * Defaults to [add, index].
   * 
   * @since  COmanage Registry v5.0.0
   * @param  array $actions Array of actions that permit unkeyed primary links.
   */
  
  public function setAllowUnkeyedPrimaryLink(array $actions) {
    $this->unkeyedActions = array_merge($this->unkeyedActions, $actions);
  }
  
  /**
   * Set the current CO ID. Intended for use with AppController.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int $coId  CO ID
   */
  
  public function setCurCoId(int $coId) {
    $this->curCoId = $coId;
  }
  
  /**
   * Set the primary link attribute.
   *
   * @since  COmanage Registry v5.0.0
   * @param  mixed $field Primary link attribute, or an array of primary links
   */ 
  
  public function setPrimaryLink($fields) {
    if(is_string($fields)) {
      $fields = [$fields];
    }
    
    foreach($fields as $field) {
      $t = null;
      
      // Calculate the table name for future reference. This could just be
      // a simple reference ("person_id" => "People") or it could be in
      // plugin notation ("CoreAssigner.format_assigner_id" => "CoreAssigner.FormatAssigners").
      // Note the plugin notation isn't exactly standard (Plugin.field doesn't make sense
      // except that we inflect it to something that does).

      if(preg_match('/^(.*)\.(.*?)_id$/', $field, $f)) {
        // Modified plugin notation match
        $t = $f[1] . "." . \Cake\Utility\Inflector::camelize(\Cake\Utility\Inflector::pluralize($f[2]));
        // We need the key to be the field name, not Plugin.field
        $this->primaryLinks[$f[2]."_id"] = $t;
      } elseif(preg_match('/^(.*?)_id$/', $field, $f)) {
        // Standard foreign key match
        $t = \Cake\Utility\Inflector::camelize(\Cake\Utility\Inflector::pluralize($f[1]));
        $this->primaryLinks[$field] = $t;
      } else {
        $this->primaryLinks[$field] = null;
      }
    }
  }
  
  /**
   * Set the redirect goal for this table. 
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $goal   Redirect goal ('index', 'pluggableLink', 'primaryLink', 'self', 'special')
   * @param  string $action Action to set goal for ('*' for default)
   * @throws InvalidArgumentException
   */
  
  public function setRedirectGoal(string $goal, string $action='*') {
    if(!in_array($goal, ['index', 'pluggableLink', 'primaryLink', 'self', 'special'])) {
      throw new \InvalidArgumentException(__d('error', 'invalid', [$goal]));
    }
    
    $this->redirectGoal[$action] = $goal;
  }
}
