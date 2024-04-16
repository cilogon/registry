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
use App\Lib\Enum\TableTypeEnum;
use App\Lib\Util\StringUtilities;

trait TableMetaTrait {
  // What type of Table is this?
  private $tableType = null;

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
      'api_key',
      // XXX maybe replace this with a regex, source_*_id?
      'source_ad_hoc_attribute_id',
      'source_address_id',
      'source_email_address_id',
      'source_external_identity_id',
      'source_identifier_id',
      'source_name_id',
      'source_pronoun_id',
      'source_telephone_number_id',
      'source_url_id',
      'owners_group_id'
    ];

    $newa = array();
    foreach($coltype as $clmn => $type) {
      // XXX We need to check if the type is an enum or plain string. The enum is a string
      //     but during filtering we do not use like but eq
      //     If required we can treat enum types as string types

      $fType = $type;
      // XXX Cakephp Inflector's camel-case function returns a Pascal case string while the variable function
      //     returns a camel-case string
      $viewVarsKey = Inflector::variable(Inflector::pluralize($clmn));
      if(isset($this->getAutoViewVars()[$viewVarsKey]['type'])) {
        $fType = $this->getAutoViewVars()[$viewVarsKey]['type'];
      }

      if(\in_array($clmn, $meta_fields, true)) {
        // Move the value to metadata
        $newa['meta'][$clmn] = $fType;
      } else {
        // Just copy the value
        $newa[$clmn] = $fType;
      }
    }

    return $newa ?? [];
  }
  
  /**
   * Determine if this Table represents Registry artifacts.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool True if this Table represents artifact data, false otherwise
   */
  
  public function isArtifactTable() {
    return $this->tableType === TableTypeEnum::Artifact;
  }
  
  /**
   * Determine if this Table represents Registry configuration.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool True if this Table represents Configuration data, false otherwise
   */
  
  public function isConfigurationTable() {
    return $this->tableType === TableTypeEnum::Configuration;
  }
  
  /**
   * Set the type of this Table.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  TableTypeEnum $tableType Table Type
   */

  public function setTableType(string $tableType) {
    $this->tableType = $tableType;
  }

  /**
   * Determine the source foreign key attribute for this table, for tables that
   * have Pipelined attributes from External Identities to People.
   * 
   * @since  COmanage Registry v5.0.0
   * @return string     Source name field (eg: source_name_id)
   */

  public function sourceForeignKey(): string {
    return "source_" . Inflector::underscore(StringUtilities::tableToEntityName($this)) . "_id";
  }
}
