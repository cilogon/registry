<?php
/**
 * COmanage Registry Common Helper
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

namespace App\View\Helper;

use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\View\Helper;

class CommonHelper extends Helper
{
  /**
   * Select count(*)
   *
   * @param   string  $modelName       Model name in `group_members` format
   * @param   array   $whereClause     where clause array
   *
   * @return int
   */
  public function getModelTotalCount(string $modelName, array $whereClause): int
  {
    $modelsName = Inflector::camelize($modelName);
    $ModelTable = TableRegistry::getTableLocator()->get($modelsName);
    $count = $ModelTable->find()
                        ->where($whereClause)
                        ->count();

    return $count;
  }
}