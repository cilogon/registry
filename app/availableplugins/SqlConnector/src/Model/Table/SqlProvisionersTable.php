<?php
/**
 * COmanage Registry SQL Provisioners Table
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

namespace SqlConnector\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;

use App\Lib\Enum\ProvisioningEligibilityEnum;
use App\Lib\Enum\ProvisioningStatusEnum;
use App\Lib\Util\SchemaManager;
use App\Lib\Util\StringUtilities;

class SqlProvisionersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\ProvisionerTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  // Primary models that may be provisioned
  protected $primaryModels = [
    'People' => [
      'table'  => 'people',
      'name'   => 'SpPeople',
      'source' => 'People',
      'source_table' => 'people',
      'related' => [
        'AdHocAttributes',
        'Addresses',
        'EmailAddresses',
        'ExternalIdentities',
        'GroupMembers',
        'Identifiers',
        'Names',
        'PersonRoles',
        'Pronouns',
        'TelephoneNumbers',
        'Urls'
      ]
    ],
    'Groups' => [
      'table'   => 'groups',
      'name'    => 'SpGroups',
      'source'  => 'Groups',
      'source_table'  => 'groups',
      'related' => [
        'GroupMembers'
      ]
    ]
  ];

  // Secondary models that are provisioned along with one or more other models
  protected $secondaryModels = [
    'AdHocAttributes' => [
      'table'   => 'ad_hoc_attributes',
      'name'    => 'SpAdHocAttributes',
      'source'  => 'AdHocAttributes',
      'source_table' => 'ad_hoc_attributes',
      'related' => []
    ],
    'Addresses' => [
      'table'   => 'addresses',
      'name'    => 'SpAddresses',
      'source'  => 'Addresses',
      'source_table' => 'addresses',
      'related' => []
    ],
    'EmailAddresses' => [
      'table'   => 'email_addresses',
      'name'    => 'SpEmailAddresses',
      'source'  => 'EmailAddresses',
      'source_table' => 'email_addresses',
      'related' => []
    ],
    'ExternalIdentities' => [
      'table'   => 'external_identities',
      'name'    => 'SpExternalIdentities',
      'source'  => 'ExternalIdentities',
      'source_table' => 'external_identities',
      'related' => [
        'AdHocAttributes',
        'Addresses',
        'EmailAddresses',
        'ExternalIdentityRoles',
        'Identifiers',
        'Names',
        'Pronouns',
        'TelephoneNumbers',
        'Urls'
      ]
    ],
    'ExternalIdentityRoles' => [
      'table'   => 'external_identity_roles',
      'name'    => 'SpExternalIdentityRoles',
      'source'  => 'ExternalIdentityRoles',
      'source_table' => 'external_identity_roles',
      'related' => [
        'AdHocAttributes',
        'Addresses',
        'TelephoneNumbers'
      ]
    ],
    'GroupMembers' => [
      'table'   => 'group_members',
      'name'    => 'SpGroupMembers',
      'source'  => 'GroupMembers',
      'source_table' => 'group_members',
      'related' => []
    ],
// XXX Not implementing GroupOwners pending resolution of CO-2508
    'Identifiers' => [
      'table'   => 'identifiers',
      'name'    => 'SpIdentifiers',
      'source'  => 'Identifiers',
      'source_table' => 'identifiers',
      'related' => []
    ],
    'Names' => [
      'table'   => 'names',
      'name'    => 'SpNames',
      'source'  => 'Names',
      'source_table' => 'names',
      'related' => []
    ],
    'PersonRoles' => [
      'table'   => 'person_roles',
      'name'    => 'SpPersonRoles',
      'source'  => 'PersonRoles',
      'source_table' => 'person_roles',
      'related' => [
        'AdHocAttributes',
        'Addresses',
        'TelephoneNumbers'
      ]
    ],
    'Pronouns' => [
      'table'   => 'pronouns',
      'name'    => 'SpPronouns',
      'source'  => 'Pronouns',
      'source_table' => 'pronouns',
      'related' => []
    ],
    'TelephoneNumbers' => [
      'table'   => 'telephone_numbers',
      'name'    => 'SpTelephoneNumbers',
      'source'  => 'TelephoneNumbers',
      'source_table' => 'telephone_numbers',
      'related' => []
    ],
    'Urls' => [
      'table'   => 'urls',
      'name'    => 'SpUrls',
      'source'  => 'Urls',
      'source_table' => 'urls',
      'related' => []
    ]
  ];

  // Models holding reference data
  protected $referenceModels = [
    'Cous' => [
      'table'  => 'cous',
      'name'   => 'SpCous',
      'source' => 'Cous',
      'source_table' => 'cous',
// XXX Note as of right now syncReferenceData doesn't look at 'related'
// - if we need it to, that'll break Groups
      'related' => []
    ],
    'Types' => [
      'table'  => 'types',
      'name'   => 'SpTypes',
      'source' => 'Types',
      'source_table' => 'types',
      'related' => []
    ]
/* XXX not yet implemented  
    [
      'table'  => 'co_terms_and_conditions',
      // Ordinarily we'd call this SpCoTermsAndConditions, but it's not worth
      // fighting cake's inflector
      'name'   => 'SpCoTermsAndCondition',
      'source' => 'CoTermsAndConditions',
      'source_table' => 'co_terms_and_conditions'
    ],
    [
      'table'  => 'org_identity_sources',
      'name'   => 'SpOrgIdentitySource',
      'source' => 'OrgIdentitySource',
      'source_table' => 'org_identity_sources'
    ]*/
  ];

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('ProvisioningTargets');
    $this->belongsTo('Servers');
    
    $this->setDisplayField('server_id');
    
    $this->setPrimaryLink(['provisioning_target_id']);
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['reapply', 'resync']);
    
    $this->setAutoViewVars([
      'servers' => [
        'type' => 'select',
        'model' => 'Servers',
        'where' => ['plugin' => 'CoreServer.SqlServers']
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'reapply' =>  ['platformAdmin', 'coAdmin'],
        'resync' =>   ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false, //['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);

    $this->setProvisionableModels(
      array_merge(
        array_keys($this->referenceModels),
        array_keys($this->primaryModels)
      )
    );
  }
  
  /**
   * Apply the Target Database Schema.
   *
   * @since  COmanage Registry v5.0.0
   * @param  integer $id          SQL Provisioner ID
   * @throws InvalidArgumentException
   * @throws RuntimeException
   */

  public function applySchema($id) {
    // In order to apply the schema, we need to find the underlying
    // SqlConnector configuration. There should only be (at most) one...

    $Plugins = TableRegistry::getTableLocator()->get('Plugins');

    $targetSchema = $Plugins->getPluginConfig(plugin: "SqlConnector", key: "target-schema");

    if(empty($targetSchema)) {
      throw new \RuntimeException("Could not find SqlProvisioner target schema definition");
    }

    // Pull our configuration

    $spcfg = $this->get($id);

    $this->Servers->SqlServers->connect($spcfg->server_id, 'targetdb');

    $SchemaManager = new SchemaManager(connection: 'targetdb');

    $SchemaManager->applySchemaObject(
      schemaObject: $targetSchema, 
      tablePrefix: $spcfg->table_prefix
    );

    return true;
  }

  /**
   * Callback after model save.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface  $event   Event
   * @param  EntityInterface $entity  Entity (ie: Co)
   * @param  ArrayObject     $options Save options
   * @return bool                     True on success
   */
    
  public function localAfterSave(\Cake\Event\EventInterface $event, \Cake\Datasource\EntityInterface $entity, \ArrayObject $options): bool {
    // We may not have a Server configuration yet on first save.

    // Also, When the SQL Provisioner is deleted, neither the
    // database schema nor reference data is touched (PAR-SqlProvisioner-4).
    if(!empty($entity->server_id) && !$entity->deleted) {
      // Apply the database schema (PAR-SqlProvisioner-1)
      $this->llog('rule', "PAR-SqlProvisioner-1 Applying database schema for SqlProvisioner " . $entity->id);
      $this->applySchema($entity->id);
      
      // Populate or update the reference data (PAR-SqlProvisioner-2)
      $this->llog('rule', "PAR-SqlProvisioner-2 Syncing reference data for SqlProvisioner " . $entity->id);
      $this->syncReferenceData($entity->id);
    }

    return true;
  }

  /**
   * Provision object data to the provisioning target.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  SqlProvisioner               $provisioningTarget SqlProvisioner configuration
   * @param  string                       $className          Class name of primary object being provisioned
   * @param  object                       $data               Provisioning data in Entity format (eg: \App\Model\Entity\Person)
   * @param  ProvisioningEligibilityEnum  $eligibility        Provisioning Eligibility Enum
   * @return array                                            Array of status, comment, and optional identifier
   */

  public function provision(
    \SqlConnector\Model\Entity\SqlProvisioner $provisioningTarget,
    string $className,
    object $data,       // $data is currently only \App\Model\Entity\Person, but that might change
    string $eligibility
  ): array {
    // Connect to the target database
    $this->Servers->SqlServers->connect($provisioningTarget->server_id, 'targetdb');

    return $this->syncEntity(
      $provisioningTarget,
      $className,
      $data,
      $eligibility
    );
  }
  
  /**
   * Sync an entity to the target database schema.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  SqlProvisioner               $SqlProvisioner     SqlProvisioner configuration
   * @param  string                       $entityName         Entity name of primary object being provisioned
   * @param  object                       $data               Provisioning data in Entity format (eg: \App\Model\Entity\Person)
   * @param  ProvisioningEligibilityEnum  $eligibility        Provisioning Eligibility Enum
   * @param  string                       $dataSource         Datasource to provision to
   * @return array                                            Array of status, comment, and optional identifier
   */
  
  protected function syncEntity(
    \SqlConnector\Model\Entity\SqlProvisioner $SqlProvisioner,
    string $entityName,
    $data,
    string $eligibility, 
    string $dataSource='targetdb'): array {
    // Find the model config, which may vary depending on the type of entity.
    // We don't check secondaryModels because those aren't directly provisioned.
    $mconfig = $this->primaryModels[$entityName] 
               ?? ($this->referenceModels[$entityName] ?? null);
    
    if(!$mconfig) {
      throw new \RuntimeException("Model configuration for $entityName not defined");
    }

    // Pull the current target record
// XXX similar code in syncReferenceData, refactor?
    $options = [
      'table'       => $SqlProvisioner->table_prefix . $mconfig['table'],
      'alias'       => $mconfig['name'],
      'connection'  => ConnectionManager::get($dataSource)
    ];

    $SpTable = TableRegistry::get(alias: $mconfig['name'], options: $options);

    try {
      $curEntity = $SpTable->get($data->id);

      if($eligibility == ProvisioningEligibilityEnum::Eligible) {
        // We have a currently provisioned record and the subject is Eligible,
        // patch it with $data and try saving.
        $patchedEntity = $SpTable->patchEntity($curEntity, $data->toArray(), ['validate' => false]);

        $SpTable->saveOrFail(
          $patchedEntity, 
          [
            'validate'    => false, 
            'checkRules'  => false
          ]
        );

        if(!empty($mconfig['related'])) {
          // Process related models
          foreach($mconfig['related'] as $rmodel) {
            $this->syncRelatedEntities(
              SqlProvisioner: $SqlProvisioner,
              parentEntityName: $entityName,
              relatedEntityName: $rmodel,
              parentData: $data,
              eligibility: $eligibility,
              dataSource: $dataSource
            );
          }
        }

        return [
          'status'      => ProvisioningStatusEnum::Provisioned,
          'comment'     => __d('sql_connector', 'result.prov.updated'),
          'identifier'  => null
        ];
      } else {
        // The subject record is deleted or otherwise Ineligible, remove the
        // current entity. Remove the related models before the entity.

        if(!empty($mconfig['related'])) {
          // Process related models
          foreach($mconfig['related'] as $rmodel) {
            $this->syncRelatedEntities(
              SqlProvisioner: $SqlProvisioner,
              parentEntityName: $entityName,
              relatedEntityName: $rmodel,
              parentData: $data,
              eligibility: $eligibility,
              dataSource: $dataSource
            );
          }
        }

        $SpTable->delete($curEntity);

        return [
          'status'      => ProvisioningStatusEnum::NotProvisioned,
          'comment'     => __d('sql_connector', 'result.prov.deleted'),
          'identifier'  => null
        ];
      }
    }
    catch(\Cake\Datasource\Exception\RecordNotFoundException $e) {
      // The record is not yet in the SP table (probably a new record)
      if($eligibility == ProvisioningEligibilityEnum::Eligible) {
        // The subject is eligible, so provision the record
        $newEntity = $SpTable->newEntity($data->toArray(), ['validate' => false]);

        $SpTable->saveOrFail(
          $newEntity, 
          [
            'validate'    => false, 
            'checkRules'  => false
          ]
        );

        if(!empty($mconfig['related'])) {
          // Process related models
          foreach($mconfig['related'] as $rmodel) {
            $this->syncRelatedEntities(
              SqlProvisioner: $SqlProvisioner,
              parentEntityName: $entityName,
              relatedEntityName: $rmodel,
              parentData: $data,
              eligibility: $eligibility,
              dataSource: $dataSource
            );
          }
        }

        return [
          'status'      => ProvisioningStatusEnum::Provisioned,
          'comment'     => __d('sql_connector', 'result.prov.added'),
          'identifier'  => null
        ];
      } else {
        // The subject record is deleted or otherwise Ineligible, nothing to do

        return [
          'status'      => ProvisioningStatusEnum::NotProvisioned,
          'comment'     => __d('sql_connector', 'result.prov.ineligible'),
          'identifier'  => null
        ];
      }
    }
    catch(\Exception $e) {
      return [
        'status'      => ProvisioningStatusEnum::Unknown,
        'comment'     => $e->getMessage(),
        'identifier'  => null
      ];
    }
  }

  /**
   * Synchronize reference data to the target database.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int      $id          SQL Provisioner ID
   * @param  string   $dataSource  DataSource label
   */
  
  public function syncReferenceData(int $id, string $dataSource='targetdb') {
    $spcfg = $this->get($id, ['contain' => ['ProvisioningTargets']]);

    $this->Servers->SqlServers->connect($spcfg->server_id, $dataSource);

    // We treat Groups as Reference Models since they may be referred to
    // by other entities. We do NOT sync Group Members here, just the Groups.

    foreach(
      // PAR-SqlProvisioner-3 When Reference Data is resynced, Groups are also resynced.
      array_merge($this->referenceModels, ['Groups' => $this->primaryModels['Groups']])
      as $mname => $m
    ) {
      // First construct the model reflecting the target database

      $options = [
        'table'       => $spcfg->table_prefix . $m['table'],
        'alias'       => $m['name'],
        'connection'  => ConnectionManager::get($dataSource)
      ];

      $SpTable = TableRegistry::get(alias: $m['name'], options: $options);

      // Next get the source table model

// XXX don't we need to use the "plugin" datasource here and elsewhere?
// (test with job shell - maybe this is an RFE for Reprovision All)
      $SrcTable = TableRegistry::get($m['source']);

      // Pull the source records and then sync them to the target table.
      // We expect reference data to be no larger than O(100) or maybe
      // O(1000) so we don't bother with PaginatedSqlIterator here.

      $srcRecords = [];
      
      foreach($SrcTable->find()
                       ->where(['co_id' => $spcfg->provisioning_target->co_id])
                       ->toArray() as $r) {
        // We shouldn't have to manually convert the entities to arrays
        // but toArray() is returning an array of objects instead of an
        // array of arrays... (and we only need this because the second
        // parameter to patchEntities expects an array since it's typically
        // used to process form data)

        // We key on record ID for use in delete, below
        $srcRecords[$r->id] = $r->toArray();
      }

      // Pull the current target records
      $curRecords = $SpTable->find()->all();

      // Patch the target with the source. Note this will handle add and
      // insert correctly, but will ignore any records from $curRecords that
      // are not in $srcRecords.
      $patchedRecords = $SpTable->patchEntities($curRecords, $srcRecords, ['validate' => false]);

      $SpTable->saveManyOrFail($patchedRecords, ['validate' => false, 'checkRules' => false]);

      // patchEntities will handle inserts and updates, but not deletes.

      $toDelete = [];

      foreach($curRecords as $c) {
        if(!isset($srcRecords[$c->id])) {
          $toDelete[] = $c;
        }
      }

      if(!empty($toDelete)) {
        $SpTable->deleteMany($toDelete);
      }
    }
  }

  /**
   * Sync related entities to the target database schema.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  SqlProvisioner               $SqlProvisioner     SqlProvisioner configuration
   * @param  string                       $parentEntityName   Entity name of primary object being provisioned
   * @param  string                       $relatedEntityName  Entity name of related object being provisioned
   * @param  object                       $parentData         Provisioning data in Entity format (eg: \App\Model\Entity\Person) for parent
   * @param  ProvisioningEligibilityEnum  $eligibility        Provisioning Eligibility Enum
   * @param  string                       $dataSource         Datasource to provision to
   */

  protected function syncRelatedEntities(
    \SqlConnector\Model\Entity\SqlProvisioner $SqlProvisioner,
    string $parentEntityName,
    string $relatedEntityName,
    $parentData,
    string $eligibility, 
    string $dataSource='targetdb') {
    // eg: person_id
    $parentFk = StringUtilities::entityToForeignKey($parentData);
    // eg: names
    $relatedTable = Inflector::tableize($relatedEntityName);

    // $parentData will have the "new" values for the related model,
    // we need to pull the current values from the SP tables

    $mconfig = $this->secondaryModels[$relatedEntityName]; 
    
    if(!$mconfig) {
      throw new \RuntimeException("Model configuration for $relatedEntityName not defined");
    }

    $options = [
      'table'       => $SqlProvisioner->table_prefix . $mconfig['table'],
      'alias'       => $mconfig['name'],
      'connection'  => ConnectionManager::get($dataSource)
    ];

    $SpTable = TableRegistry::get(alias: $mconfig['name'], options: $options);

    // We have the source values, but we need to convert them to arrays
    // for patchEntities
    $srcEntities = [];

    foreach($parentData->$relatedTable as $r) {
      $srcEntities[$r->id] = $r->toArray();
    }

    // Pull the current provisioned data

    $curEntities = $SpTable->find()
                           ->where([$parentFk => $parentData->id])
                           ->all();

    if($eligibility == ProvisioningEligibilityEnum::Eligible) {
      // Patch the target with the source. Note this will handle add and
      // insert correctly, but will ignore any records from $curEntities that
      // are not in $srcEntities.
      $patchedEntities = $SpTable->patchEntities($curEntities, $srcEntities, ['validate' => false]);

      $SpTable->saveManyOrFail($patchedEntities, ['validate' => false, 'checkRules' => false]);
    } else {
      // Delete all currently provisioned entries, which will force by
      // clearing $srcEntities

      $srcEntities = [];
    }

    // Sync any related entities. We need to do this after save for Eligible
    // records (above) and before delition of ineligible records (below).
    // We have to do this once per instance of the parent related model.
    // eg: If we're currently syncing parent model People and related model
    // PersonRoles, we need to syncRelatedEntities on PersonRoles once for
    // _each_ roles attached to the Person.

    if(!empty($mconfig['related'])) {
      // Process related models
      foreach($mconfig['related'] as $rmodel) {
        if(!empty($parentData->$relatedTable)) {
          foreach($parentData->$relatedTable as $rmdata) {
            $this->syncRelatedEntities(
              SqlProvisioner: $SqlProvisioner,
              parentEntityName: $relatedEntityName,
              relatedEntityName: $rmodel,
              parentData: $rmdata,
              eligibility: $eligibility,
              dataSource: $dataSource
            );
          }
        }
      }
    }

    // Delete any dropped related entities

    $toDelete = [];

    foreach($curEntities as $c) {
      if(!isset($srcEntities[$c->id])) {
        $toDelete[] = $c;
      }
    }

    if(!empty($toDelete)) {
      $SpTable->deleteMany($toDelete);
    }

    // We don't currently return errors up the stack, should we?
  }

  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   * @throws InvalidArgumentException
   * @throws RecordNotFoundException
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('provisioning_target_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('provisioning_target_id');
    
    $validator->add('server_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('server_id');

    $this->registerStringValidation($validator, $schema, 'table_prefix', true);

    // Table prefixes must be alphanumeric and end in an underscore
    $validator->add('table_prefix', [
      'format' => [
        'rule' => function ($value, $context) {
          return (preg_match('/[\w]+_/', $value) ? true : __d('sql_connector', 'error.table_prefix'));
        }
      ]
    ]);
    
    return $validator; 
  }
}