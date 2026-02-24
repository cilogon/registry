<?php
/**
 * COmanage Registry Search Utilities
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

namespace App\Lib\Util;

use Cake\ORM\TableRegistry;

class SearchUtilities {
  // Currently, only clonable models support CRN and UUID searching.
  // To add a new clonable model, see https://spaces.at.internet2.edu/x/DIBuFQ
  // Because this list is used by CloneCommand, it should be sorted in dependency order.
  static protected $clonableModels = [
    'ApiUsers',
    'Apis',
    'Cous',
    'Groups',
    'IdentifierAssignments',
    'Pipelines',
    'ExternalIdentitySources',
    'ProvisioningTargets',
    'Servers',
    'Types'
  ];

  // To add a new backend to search:
  //  (1) Implement $model->search($id, $q, $limit)
  //  (2) Add the model to $models here, and define which roles can query it
  //  (3) Update documentation at https://spaces.at.internet2.edu/pages/viewpage.action?pageId=243078053

  static protected $globalSearchModels = [
    'Addresses' => [
      'parent'        => ['People' => 'person_id', 'PersonRoles' => 'person_role_id'],
      'roles'         => ['platformAdmin', 'coAdmin'],
      'displayField'  => 'street',
      'searchLimited' => false
    ],
    'EmailAddresses' => [
      'parent'        => ['People' => 'person_id'],
      'roles'         => ['platformAdmin', 'coAdmin'],
      'displayField'  => 'mail',
      'searchLimited' => true
    ],
    'Groups' => [
      'parent'        => ['Cos' => 'co_id'],
      'roles'         => ['platformAdmin', 'coAdmin'],
      'displayField'  => 'name',
      'searchLimited' => false
    ],
    'Identifiers' => [
      'parent'        => ['Groups' => 'group_id', 'People' => 'person_id'],
      'roles'         => ['platformAdmin', 'coAdmin'],
      'displayField'  => 'identifier',
      'searchLimited' => true
    ],
    'Names' => [
      'parent'        => ['People' => 'person_id'],
      'roles'         => ['platformAdmin', 'coAdmin'],
      'displayField'  => 'full_name',
      'searchLimited' => true
    ],
    'PersonRoles' => [
      'parent'        => ['People' => 'person_id'],
      'roles'         => ['platformAdmin', 'coAdmin'],
      'displayField'  => 'title',
      'searchLimited' => false
    ],
    'TelephoneNumbers' => [
      'parent'        => ['People' => 'person_id', 'PersonRoles' => 'person_role_id'],
      'roles'         => ['platformAdmin', 'coAdmin'],
      'displayField'  => 'number',
      'searchLimited' => false
    ],
    'Urls' => [
      'parent'        => ['People' => 'person_id'],
      'roles'         => ['platformAdmin', 'coAdmin'],
      'displayField'  => 'url',
      'searchLimited' => false
    ]
  ];

  /**
   * Perform a search across all objects that support Change Request Identifiers.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $coId CO ID
   * @param  string $cri  Change Request Identifier
   * @return array        Array of arrays of matching Entities, keyed on Entity class
   *                      (an empty array will be returned if no matches are found)
   */

  public static function criSearch(int $coId, string $cri): array {
    $ret = [];

    // Unlike uuidSearch, criSearch can find multiple records

    foreach(self::$clonableModels as $m) {
      $Table = TableRegistry::getTableLocator()->get($m);

      // Clonable model must FK directly to CO
      $entities = $Table->find()->where(['co_id' => $coId, 'cri' => $cri])->all();

      if($entities) {
        $ret[$m] = $entities;
      }
    }

    return $ret;
  }

  /**
   * Obtain the set of clonable models, which support CRI and UUID searching.
   * 
   * @since  COmanage Registry v5.2.0
   * @return array    Array of clonable models
   */

  public static function getClonableModels(): array {
    return self::$clonableModels;
  }

  /**
   * Obtain the set of supported models for Global Search, and their associated
   * metadata.
   * 
   * @since  COmanage Registry v5.2.0
   * @return array    Array of Global Search models and metadata
   */

  public static function getGlobalSearchModels(): array {
    // XXX inject plugins here CFM-109

    return self::$globalSearchModels;
  }

  /**
   * Perform a "Global" Search, ie: a search from the main search bar.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $coId CO ID
   * @param  string $q    Query string
   * @return array        Array of search results, sorted by searchable model
   */

  public static function globalSearch(int $coId, string $q): array {
    // $results tracks the per-model backend results
    $results = [
      'Cos'           => [],
      'Groups'        => [],
      'People'        => [],
      // If we matched on a UUID
      'uuid'          => null,
      // If we matched on a CRN
      'crn'           => [],
      // If we reached our search limit
      'limitReached'  => false
    ];

    // Pull our search configuration
    $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');

    $settings = $CoSettings->find()->where(['co_id' => $coId])->firstOrFail();

    $searchLimit = $settings->search_global_limit;

    // We call the function rather than use the array directly for when we add
    // plugin support (CFM-109)
    $models = self::getGlobalSearchModels();

    // Search the Global Search models
    foreach(array_keys($models) as $m) {
      // If we're in limited search mode, we don't search all models
      if($settings->search_global_limited_models
          && !$models[$m]['searchLimited']) {
        continue;
      }

      $authorized = true;

      $Table = TableRegistry::getTableLocator()->get($m);

      $searchResults = $Table->search(coId: $coId, q: $q, limit: $searchLimit);

      // For models with a parent other than Co, we aggregate the results to the parent
      // model, but track what the matching model was. We key on the foreign key to the parent
      // to also unique-ify the results while we're here.

      foreach($searchResults as $r) {
        // Some tables support multiple parent models (eg: Identifiers), so we walk through
        // the possibilities to see which one matched
        foreach($models[$m]['parent'] as $pmodel => $pkey) {
          if(!empty($r->$pkey)) {
            if($m == 'Groups') {
              // We special case Groups since (unlike People) they can match on both the
              // primary model (Groups::name) or associated models (Identifiers::identifier).
              // We force any Groups matches into the parent key format.
              $results['Groups'][$r->id]['Groups'] = $r;
            } elseif($pmodel == 'Cos') {
              // This will look something like $results['Cos']['Departments'][] = $entity
              $results[$pmodel][$m][] = $r;
            } elseif($pmodel == 'PersonRoles') {
              // Although we matched on a PersonRole we're really interested in the Person
              $results['People'][$r->person_role->person_id][$m] = $r->person_role;
            } else {
              // Note we're also keying on the matched model, so this will look something like
              // $results['People'][123]['Names'] = $entity
              $results[$pmodel][$r->$pkey][$m] = $r;
            }
          }
        }
      }

      if(count($results['Cos']) + count($results['Groups']) + count($results['People']) >= $searchLimit) {
        $results['limitReached'] = true;
        break;
      }
    }

    // Additionally, search the clonable models. We do this regardless of whether
    // the search limit was reached, since they are exempt from the search limit.

    // If $q looks like a UUID, search on UUIDs
    if(\Cake\Validation\Validation::uuid($q)) {
      $results['uuid'] = self::uuidSearch($coId, $q);
    }

    // Search on CRI
    $results['cri'] = self::criSearch($coId, $q);

    return $results;
  }

  /**
   * Perform a search across all objects that support UUIDs.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $coId CO ID
   * @param  string $uuid UUID
   * @return ?array       Array of 'class' and 'entity' for the matching record, if found
   */

  public static function uuidSearch(int $coId, string $uuid): ?array {
    $ret = null;

    // In theory there should only be one entity in a CO with a given UUID
    // (though the same entity could exist in multiple COs), so we simply
    // return the first match we find. We could slightly optimize this by
    // searching models with larger numbers of records first (ie: search
    // People before Authenticators), but at the end of the day this is going
    // to be a somewhat expensive call to make.

    foreach(self::$clonableModels as $m) {
      $Table = TableRegistry::getTableLocator()->get($m);

      // Clonable model must FK directly to CO
      $entity = $Table->find()->where(['co_id' => $coId, 'uuid' => $uuid])->first();

      if($entity) {
        return [
          'class' => $m,
          'entity' => $entity
        ];
      }
    }

    return $ret;
  }
}