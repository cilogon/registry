<?php
/**
 * COmanage Registry Authenticator Trait
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Lib\Traits;

use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;

trait AuthenticatorTrait {
  // The set of plugin entry point models used in configurations for this model
  protected $_pluginModels = [];

  /**
   * Handle changelog archive during (before) save of object.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Event           $event   The beforeSave event
   * @param  EntityInterface $entity  Entity
   * @param  ArrayObject     $options Options
   */

  public function beforeSave(Event $event, EntityInterface $entity, \ArrayObject $options) {
    // Note that because we are changelog enabled we don't need a separate beforeDelete()

    $subject = $event->getSubject();
    // table = (eg) passwords
    $table = $subject->getTable();
    // alias = (eg) Passwords
    $alias = $subject->getAlias();
    // entityName = (eg) Password
    $entityName = Inflector::singularize($alias);

    // For reset operation, we allow a locked authenticator to be reset, in which case
    // we just skip this check.
    if(isset($options['reset']) && $options['reset']) {
      return;
    }

    // We need to check if the Authenticator is locked for the subject Person, and if so
    // reject the request. For this, we need the Authenticator ID, which we need to look up.
    // For that, we need to map the Authenticator (eg "Password") to its parent model
    // (eg "PasswordAuthenticator").

    // $pluginModel = (eg) PasswordAuthenticator.PasswordAuthenticators
    $pluginModel = $entityName . "Authenticator." . $entityName . "Authenticators";
    // $pluginFK = (eg) password_authenticator_id
    $pluginFK = Inflector::singularize($table) . "_authenticator_id";

    $pluginTable = TableRegistry::getTableLocator()->get($pluginModel);
    $Authenticators = TableRegistry::getTableLocator()->get('Authenticators');

    $pluginCfg = $pluginTable->get($entity->$pluginFK);

    $status = $Authenticators->AuthenticatorStatuses->find()
                             ->where([
                               'authenticator_id' => $pluginCfg->authenticator_id,
                               'person_id'        => $entity->person_id
                             ])
                             ->first();

    if($status && $status->isLocked()) {
      throw new \RuntimeException(__d('error', 'Authenticators.manage.locked'));
    }
    
    return;
  }
}
