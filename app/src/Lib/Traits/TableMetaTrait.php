<?php
/**
 * COmanage Registry Table Meta Trait
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

use Cake\Utility\Inflector;

trait TableMetaTrait {
  // Does this Table represent Registry objects or configuration?
  private $confTable = false;
  
  /**
   * Determine if this Table represents Registry configuration (vs objects).
   *
   * @since  COmanage Registry v5.0.0
   * @return bool True if this Table represents Configuration data, false otherwise
   */
  
  public function getIsConfigurationTable() {
    return $this->confTable;
  }
  
  /**
   * Set if this Table represents Registry configuration (vs objects).
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $vars Array of auto view variables
   */
  
  public function setIsConfigurationTable(bool $confTable) {
    $this->confTable = $confTable;
  }


  /**
   * Filter metadata fields.
   *
   * @since  COmanage Registry v5.0.0
   * @return array             An array of columns distinguished in metadata and non-metadata
   */

  protected function filterMetadataFields() {
    // Get the list of columns
    $coltype = $this->getSchema()->typeMap();
    $entity = $this->getEntityClass();
    $entity_namespace = explode('\\', $entity);
    $modelName = end($entity_namespace);

    // Get the list of belongs_to associations and construct an exclude array
    $assc_keys = [];
    foreach ($this->associations() as $assc) {
      if($assc->type() === "manyToOne") {
        $assc_keys[] = Inflector::underscore(Inflector::classify($assc->getClassName())) . "_id";
      }
    }
    // Map the model (eg: Person) to the changelog key (person_id)
    $mfk = Inflector::underscore($modelName) . "_id";


    $meta_fields = [
      ...$assc_keys,
      $mfk,
      'actor_identifier',
      // 'provisioning_target_id',
      'created', // todo: I might need to revisit this. We might want to filter according to date in some occassions. Like petitions
      'deleted',
      'id',
      'modified',
      'revision',
      'lft',  // XXX For now i skip lft.rght column for tree structures
      'rght',
      // 'parent_id', // todo: We need to filter using the parent_id. This should be an enumerator and should apply for all the models that use TreeBehavior
      'api_key'
      // 'source_ad_hoc_attribute_id',
      // 'source_address_id',
      // 'source_email_address_id',
      // 'source_identifier_id',
      // 'source_name_id',
      // 'source_external_identity_id',
      // 'source_telephone_number_id',
    ];

    $newa = array();
    foreach($coltype as $clmn => $type) {
      if(in_array($clmn, $meta_fields,true)) {
        // Move the value to metadata
        $newa['meta'][$clmn] = $type;
      } else {
        // Just copy the value
        $newa[$clmn] = $type;
      }
    }

    return $newa ?? [];
  }
}
