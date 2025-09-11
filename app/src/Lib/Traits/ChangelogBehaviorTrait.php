<?php
/**
 * COmanage Registry ChangelogBehavior Utility Trait
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

trait ChangelogBehaviorTrait {
  /**
   * Dispatch the local afterSave if it should be invoked.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface  $event   Event
   * @param  EntityInterface $entity  Entity (ie: Co)
   * @param  ArrayObject     $options Save options
   * @return void
   */
    
  public function afterSave(
    \Cake\Event\EventInterface $event,
    \Cake\Datasource\EntityInterface $entity,
    \ArrayObject $options
  ): void
  {
    // We don't want to trigger any callbacks when we're saving the archive copy.
    // In Cake 2, we could do this by disabling callbacks, but this feature was
    // removed in Cake 3 because it "was a common source of bugs in applications"
    // (https://github.com/cakephp/cakephp/issues/7249) and the proposed
    // workarounds don't really work with the ChangelogBehavior approach.
    // So basically we use this trait to intercept all afterSave calls, and if
    // we're not flagged as in changelog, we invoke the table's afterSave.
    
    if((!isset($options['archive']) || $options['archive'])
       // We _do_ want to trigger callbacks on delete since there's only a
       // single database write for saves (UPDATE foo SET deleted=true)
       || (isset($entity->deleted) && $entity->deleted)) {
      $table = $event->getSubject();
      
      if(method_exists($table, "localAfterSave")) {
        $result = $table->localAfterSave($event, $entity, $options);
        $event->setResult($result);
        return;
      }
    }
    
    $event->setResult(true);
  }
}
