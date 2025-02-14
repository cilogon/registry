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

use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use App\Lib\Enum\TableTypeEnum;
use App\Lib\Util\StringUtilities;

trait TableMetaTrait {
  // What type of Table is this?
  private $tableType = null;

  /**
   * Filter the metadata attributes from an entity in a manner suitable for copy (duplicate).
   * 
   * @since  COmanage Registry v5.1.0
   * @param  Table            $table    Table for $entity
   * @param  EntityInterface  $entity   Entity to copy (filter)
   * @param  array            $related  Related models to process
   * @return array                      Array of filtered attributes
   */

  protected function filterMetadataForCopy(
    \Cake\ORM\Table $table,
    \Cake\Datasource\EntityInterface $entity,
    array $related=[]
  ): array {
// XXX There is overlap with Petitions::duplicateFilterEntityData and
// TableMetaTrait::filterMetadataFields (used mostly for UI stuff),
// should maybe refactor these. filterMetadata() is more based on the Petitions one
// See also CFM-442

    $ret = [];

    $metaFields = [
      // Metadata fields start with the basic Cake metadata
      'id',
      'created',
      'modified',
      // Add changelog metadata
      'actor_identifier',
      'deleted',
      'revision',
      $entity->changelogAttributeName()
    ];

    // Handling parent keys is a bit complex for duplicating models, and we're probably
    // going to need to know some context. For example, when we duplicate an Enrollment
    // Flow we want to create a new Enrollment Flow and Enrollment Flow Step, but we
    // want to Enrollment Flow Step to point to an existing Message Template. However
    // when we duplicate an entire CO we also need to duplicate the Message Template.
    // XXX For now we only support the first scenario, which we implement by removing
    // primary keys, not all foreign keys.

    // Find the primary link for this entity. We want to keep co_id (presumably the
    // top level primary link) but otherwise remove the link to allow Cake to rekey.

    $link = $table->findPrimaryLinkEntity($entity);
    $linkKey = StringUtilities::entityToForeignKey($link);

    if($linkKey != 'co_id') {
      $metaFields[] = $linkKey;
    }

    // Now that we've figured out the metadata, walk the list of visible attributes
    // and populate the ones that are defined and not metadata fields

    foreach($entity->getVisible() as $visible) {
      if(!in_array($visible, $metaFields) 
         && isset($entity->$visible)
         // Skip arrays, which are related models
         && !is_array($entity->$visible)) {
        $ret[$visible] = $entity->$visible;
      }
    }

    // Next handle related models (recursively). If the current entity is a Pluggable model
    // we need to handle the related plugin data specially.

    foreach($related as $k => $v) {
      if(is_int($k)) {
        // $v is the model name (EnrollmentFlowSteps)
        // $m is the lowercased model name (enrollment_flow_steps)
        $m = Inflector::tableize($v);
        // $m1 is the singular version (enrollment_flow_step)
        $m1 = Inflector::singularize($m);
        // $t is the Table for $v
        if(!empty($entity->plugin) && StringUtilities::pluginModel($entity->plugin) == $v) {
          // For pluggable models, get the plugin table from the entity configuration
          $t = TableRegistry::getTableLocator()->get($entity->plugin);
        } else {
          $t = TableRegistry::getTableLocator()->get($v);
        }

        if(is_array($entity->$m)) {
          // HasMany

          foreach($entity->$m as $s) {
            $ret[$m][] = $this->filterMetadataForDuplicate($t, $s);
          }
        } elseif(!empty($entity->$m1)) {
          // HasOne

          $ret[$m1] = $this->filterMetadataForDuplicate($t, $entity->$m1);
        }
      } elseif(is_array($v)) {
        // $k is the model name (EnrollmentFlowSteps) and $v is an array of related models
        // $m is the lowercased model name (enrollment_flow_steps)
        $m = Inflector::tableize($k);
        // $m1 is the singular version (enrollment_flow_step)
        $m1 = Inflector::singularize($m);
        // $t is the Table for $k
        if(!empty($entity->plugin) && StringUtilities::pluginModel($entity->plugin) == $v) {
          // For pluggable models, get the plugin table from the entity configuration
          $t = TableRegistry::getTableLocator()->get($entity->plugin);
        } else {
          $t = TableRegistry::getTableLocator()->get($k);
        }

        if(is_array($entity->$m)) {
          // HasMany

          foreach($entity->$m as $s) {
            $ret[$m][] = $this->filterMetadataForDuplicate($t, $s, $v);
          }
        } elseif(!empty($entity->$m1)) {
          // HasOne

          $ret[$m1] = $this->filterMetadataForDuplicate($t, $entity->$m1, $v);
        }
      }
    }

    return $ret;
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
      'owners_group_id',
      'enrollee_person_id',
      'petitioner_person_id',
      'authz_group_id',
      'authz_cou_id',
      'redirect_on_finalize',
      'collect_enrollee_email'
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
