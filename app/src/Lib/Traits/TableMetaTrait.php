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

use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use App\Lib\Enum\TableTypeEnum;
use App\Lib\Util\StringUtilities;
use App\Lib\Util\TableUtilities;

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

  public function filterMetadataForCopy(
    \Cake\ORM\Table $table,
    \Cake\Datasource\EntityInterface $entity,
    array $related=[]
  ): array {
// XXX There is overlap with Petitions::duplicateFilterEntityData and
// TableMetaTrait::filterMetadataFields (used mostly for UI stuff),
// should maybe refactor these. filterMetadata() is more based on the Petitions one
// See also CFM-442 and CFM-480

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
          if(!empty($entity->$m[0])) {
            // hasMany Relation with at least one entity populated. Use the entity to get
            // the appropriate table to make sure we handle plugins correctly.
            $t = TableRegistry::getTableLocator()->get($entity->$m[0]->getSource());
          } else {
            $t = TableRegistry::getTableLocator()->get($v);
          }
        }

        if(is_array($entity->$m)) {
          // HasMany

          foreach($entity->$m as $s) {
            $ret[$m][] = $this->filterMetadataForCopy($t, $s);
          }
        } elseif(!empty($entity->$m1)) {
          // HasOne

          $ret[$m1] = $this->filterMetadataForCopy($t, $entity->$m1);
        }
      } elseif(is_array($v)) {
        // $k is the model name (EnrollmentFlowSteps) and $v is an array of related models
        // $m is the lowercased model name (enrollment_flow_steps)
        $m = Inflector::tableize($k);
        // $m1 is the singular version (enrollment_flow_step)
        $m1 = Inflector::singularize($m);
        // $t is the Table for $k
        if(!empty($entity->plugin) && StringUtilities::pluginModel($entity->plugin) == $k) {
          // For pluggable models, get the plugin table from the entity configuration
          $t = TableRegistry::getTableLocator()->get($entity->plugin);
        } else {
          $t = TableRegistry::getTableLocator()->get($k);
        }

        if(is_array($entity->$m)) {
          // HasMany

          foreach($entity->$m as $s) {
            $ret[$m][] = $this->filterMetadataForCopy($t, $s, $v);
          }
        } elseif(!empty($entity->$m1)) {
          // HasOne

          $ret[$m1] = $this->filterMetadataForCopy($t, $entity->$m1, $v);
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
      'adopted_person_id',
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
   * Update the foreign keys in $clone to point to the correct entities in the target CO.
   * This function is here and not in ClonableTrait in order to be available for
   * related models that are not themselves directly Clonable.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  EntityInterface  $original   Original entity
   * @param  EntityInterface  $clone      Clone (not yet saved)
   * @param  int              $targetCoId CO ID for target
   * @param  string           $dataSource Target DataSource connection name
   * @return EntityInterface              Clone, updated as necessary
   */

  public function fixCloneForeignKeys(
    EntityInterface $original,
    EntityInterface $clone,
    int $targetCoId,
    string $dataSource
  ): EntityInterface {
    // To figure out the set of foreign keys for a table we start by getting its
    // belongsTo assocations. (We're only interested in assocations where the foreign
    // key is defined in this table.)

    // The Tables for $original and $clone should already be in the TableRegistry,
    // so we don't need to specially query for them (via TableUtilities)
    $OriginalTable = TableRegistry::getTableLocator()->get($original->getSource());
    
    foreach($OriginalTable->associations()->getByType('BelongsTo') as $assn) {
      if(in_array($assn->getForeignKey(), $OriginalTable->getPrimaryLinks())) {
        // Skip the primary key for this table
        continue; 
      }

      $aForeignKey = $assn->getForeignKey();

      if(!empty($original->$aForeignKey)) {
        // We have a non-empty value for this foreign key. We need to map the _source_
        // FK to its UUID, then find the same UUID on the target, then replace the FK.

        $clone->$aForeignKey = $OriginalTable->mapForeignKey(
          $assn, 
          $original->$aForeignKey, 
          $targetCoId,
          $dataSource
        );
      }
    }

    // Now handle any related models that might be riding along. Not all related models
    // are necessarily populated in $original, so we'll need to check for that.

    foreach($OriginalTable->associations()->getByType(['hasOne', 'hasMany']) as $rassn) {
      // We use the property name to find the sub-entity
      $property = $rassn->getProperty();
      $tproperty = $property;

      if($dataSource != 'default') {
        // The target property is prefixed with the datasource name

        $tproperty = $dataSource . "_" . $property;
      }

      if(!empty($original->$property)) {
        // We have a non-empty related entity, eg $server->match_sever

        if(is_array($original->$property)) {
          // hasMany - This is annoying because we can't directly correlate each original
          // entity to each cloned entity. This is a similar problem to Pipeline processing,
          // so we use the same solution, which is isProbablyThisArray().

          $fixed = [];

          foreach($original->$property as $rorig) {
            // Walk the clones until we find a match
            foreach($clone->$tproperty as $rclone) {
              // $rclone might be an array or it might be an entity. When Cake marshals
              // an array into an entity, it sometimes leaves subrelations as arrays
              // apparently at least in some cases those provided by plugins since it
              // can't resolve the entity to a table. We actually need both formats here
              // since isProbablyThisArray() expects an array, while fixCloneForeignKeys
              // expects an entity.

              if(is_array($rclone)) {
                // Use the original table to find the source name, but use the target
                // datasource to get the table handle.
                $TargetTable = TableUtilities::getTableWithDataSource(
                  tableName: $rorig->getSource(),
                  connectionName: $dataSource
                );

                $rarray = $rclone;
                $rentity = $TargetTable->newEntity($rclone);
              } else {
                $rarray = $rclone->toArray();
                $rentity = $rclone;
              }

              // The reason this will work is because we haven't fixed the foreign keys yet.
              // If we did, this mismatch would cause isProbablyThisArray to always return
              // false.
              if($rorig->isProbablyThisArray($rclone)) {
                $fixed[] = $this->fixCloneForeignKeys(
                  $rorig,
                  $rentity,
                  $targetCoId,
                  $dataSource
                );
              }
            }
          }

          $clone->$tproperty = $fixed;
        } else {
          // hasOne

          $clone->$tproperty = $this->fixCloneForeignKeys(
            $original->$property,
            $clone->$tproperty,
            $targetCoId,
            $dataSource
          );
        }
      }
    }

    return $clone;
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
   * Map a foreign key based on UUID lookup.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Association  $assn             Association being examined
   * @param  int          $originalFK       Foreign key value of original associated entity
   * @param  int          $targetCoId       CO ID for target
   * @param  string       $targetDataSource Data source to use for target lookup
   * @return int                            ID of corresponding target entity
   */

  public function mapForeignKey(
    \Cake\ORM\Association $assn,
    int $originalFK,
    int $targetCoId,
    string $targetDataSource
  ): int {
    $SourceTable = TableUtilities::getTableWithDataSource(
      tableName: $assn->getClassName(),
      connectionName: 'default'
    );

    $originalForeignEntity = $SourceTable->get($originalFK);

    // Query the Target Table using the UUID we just found.
    // This will throw an Exception if the UUID is not found, which is fine
    // because it means we can't resolve the link and so we can't clone.
    $TargetTable = TableUtilities::getTableWithDataSource(
      tableName: $assn->getClassName(),
      connectionName: $targetDataSource
    );

    $cloneForeignEntity = $TargetTable->getByUuid($originalForeignEntity->uuid, $targetCoId);
        
    return $cloneForeignEntity->id;
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
    // There is a similar function in EntityMetaTrait because sometimes we have a Table
    // context and sometimes we have an Entity context.

    return "source_" . Inflector::underscore(StringUtilities::tableToEntityName($this)) . "_id";
  }
}
