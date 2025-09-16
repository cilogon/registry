<?php
/**
 * COmanage Registry Breadcrumbs Trait
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

trait BreadcrumbsTrait {
  /**
   * Build the common person-centric breadcrumb parents.
   *
   * Returns only the parents to prepend (does not overwrite existing `vv_bc_parents`).
   * Safely de-duplicates by checking currently set parents.
   *
   * @param int $personId The person ID to build crumbs for.
   * @param bool $includePeopleIndex Whether to include the People index crumb.
   * @return array<string,array{label:string,target:array}>
   */
  protected function buildPersonBreadcrumbs(int $personId, bool $includePeopleIndex = true): array
  {
    if ($personId <= 0) {
      return [];
    }

    $People = $this->fetchTable('People');
    $vv_bc_parents = (array)$this->viewBuilder()->getVar('vv_bc_parents');
    $parents = [];

    // 1) People index
    if ($includePeopleIndex && empty($vv_bc_parents['cos:' . $this->getCOID()])) {
      $parents['cos:' . $this->getCOID()] = [
        'label'  => __d('controllers', 'People', [99]),
        'target' => [
          'plugin'     => null,
          'controller' => 'people',
          'action'     => 'index',
          '?'          => [ 'co_id' => $this->getCOID() ],
        ],
      ];
    }

    // 2) Person crumb
    $person = $People->get($personId, contain: ['PrimaryName']);
    $personKey = strtolower($People->getAlias()) . ':' . $personId; // eg: people:123

    if (empty($vv_bc_parents[$personKey])) {
      $parents[$personKey] = [
        'label'  => $People->generateDisplayField($person),
        'target' => [
          'plugin'     => null,
          'controller' => 'people',
          'action'     => 'edit',
          $personId,
        ],
      ];
    }

    return $parents;
  }
}