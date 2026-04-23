<?php
/**
 * COmanage Rule Trait
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

use Cake\ORM\TableRegistry;
use \App\Lib\Util\StringUtilities;

trait RuleTrait {
  /**
   * Case insensitive uniqueness rule.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Entity  $entity    Entity to be validated
   * @param  array   $options   Application rule options
   * @return bool|string        true if the Rule check passes, false otherwise
   */

  public function ruleIsCaseInsensitiveUnique($entity, array $options): bool|string {
    // Build a where clause of lowercased values of the current $entity
    $whereClause = [];
    
    if($entity->id) {
      // Exclude the current record from the uniqueness check
      $whereClause['id <>'] = $entity->id;
    }

    foreach($options['fields'] as $f) {
      // We might have non-string fields (eg: co_id, used to constrain the search)
      if(is_string($entity->$f)) {
        $whereClause['LOWER('.$f.')'] = strtolower($entity->$f);
      } else {
        $whereClause[$f] = $entity->$f;
      }
    }

    $count = $this->find()
                  ->where($whereClause)
                  ->count();
    
    if($count > 0) {
      // XXX note this error lookup won't work for Plugins
      return __d('error', 'exists', [__d('controller', StringUtilities::entityToClassName($entity), [1])]);
    }

    return true;
  }
}
