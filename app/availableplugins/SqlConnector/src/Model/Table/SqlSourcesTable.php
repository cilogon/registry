<?php
/**
 * COmanage Registry SQ: Sources Table
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
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use \App\Lib\Util\TableUtilities;
use \SqlConnector\Lib\Enum\SqlSourceTableModeEnum;

class SqlSourcesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  // Cache of Table Models
  protected $tableCache = [];
  
  // Cache of the type map, for flat mode
  protected $typeCache = [];

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
    $this->belongsTo('ExternalIdentitySources');
    $this->belongsTo('Servers');
    $this->belongsTo('AddressTypes')
         ->setClassName('Types')
         ->setForeignKey('address_type_id')
         ->setProperty('address_type');
    $this->belongsTo('EmailAddressTypes')
         ->setClassName('Types')
         ->setForeignKey('email_address_type_id')
         ->setProperty('email_address_type');
    $this->belongsTo('IdentifierTypes')
         ->setClassName('Types')
         ->setForeignKey('identifier_type_id')
         ->setProperty('identifier_type');
    $this->belongsTo('NameTypes')
         ->setClassName('Types')
         ->setForeignKey('name_type_id')
         ->setProperty('name_type');
    $this->belongsTo('PronounsTypes')
         ->setClassName('Types')
         ->setForeignKey('pronouns_type_id')
         ->setProperty('pronouns_type');
    $this->belongsTo('TelephoneNumberTypes')
         ->setClassName('Types')
         ->setForeignKey('telephone_number_type_id')
         ->setProperty('telephone_number_type');
    $this->belongsTo('UrlTypes')
         ->setClassName('Types')
         ->setForeignKey('url_type_id')
         ->setProperty('url_type');
    
    $this->setDisplayField('server_id');
    
    $this->setPrimaryLink(['external_identity_source_id']);
    $this->setRequiresCO(true);
    
    $this->setAutoViewVars([
      'addressTypes' => [
        'type' => 'type',
        'attribute' => 'Addresses.type'
      ],
      'emailAddressTypes' => [
        'type' => 'type',
        'attribute' => 'EmailAddresses.type'
      ],
      'identifierTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ],
      'nameTypes' => [
        'type' => 'type',
        'attribute' => 'Names.type'
      ],
      'pronounsTypes' => [
        'type' => 'type',
        'attribute' => 'Pronouns.type'
      ],
      'servers' => [
        'type' => 'select',
        'model' => 'Servers',
        'where' => ['plugin' => 'CoreServer.SqlServers']
      ],
      'telephoneNumberTypes' => [
        'type' => 'type',
        'attribute' => 'TelephoneNumbers.type'
      ],
      'urlTypes' => [
        'type' => 'type',
        'attribute' => 'Urls.type'
      ],
      'tableModes' => [
        'type' => 'enum',
        'class' => 'SqlConnector.SqlSourceTableModeEnum'
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false, //['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Obtain the set of changed records from the source database.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  ExternalIdentitySource $source     External Identity Source
   * @param  int                    $lastStart  Timestamp of last run
   * @param  int                    $curStart   Timestamp of current run
   * @param  bool                   $count      If true, return a count of changed records
   * @return int|array|bool                     An array of changed source keys, or a count of changed source keys (if $count), or false
   */

  protected function getChanges(
    \App\Model\Entity\ExternalIdentitySource $source,
    int $lastStart,
    int $curStart,
    bool $count=false
  ): int|array|bool {
    $SourceTable = $this->getRecordTable($source->sql_source);

    // Our first preference for building a changelist is the use of the modified column
    // on the table (or for relational mode the primary table)

    if($SourceTable->getSchema()->getColumnType('modified')) {
      $this->llog('trace', "Calculating changes via modified timestamp for " . $source->description);

      $query = $SourceTable->find('list', [
                             'keyField' => 'source_key',
                             'valueField' => 'modified'
                           ])
                           ->where([
                             'modified >' => date('Y-m-d H:i:s', $lastStart),
                             'modified <=' => date('Y-m-d H:i:s', $curStart)
                           ]);
      
      if($count) {
        return $query->count();
      } else {
        $records = $query->toArray();

        return array_keys($records);
      }
    }

    // If there is an _archive table defined, use that to perform a diff.
    // If not, this will return false. At that point, we don't have an efficient way
    // of determining a changelist, so we fall back to the default behavior.

    // NOTYETIMPLEMENTED return $this->getChangeListFromArchive();

    return false;
  }

  /**
   * Obtain the set of changed records from the source database.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  ExternalIdentitySource $source     External Identity Source
   * @param  int                    $lastStart  Timestamp of last run
   * @param  int                    $curStart   Timestamp of current run
   * @return array|bool                         An array of changed source keys, or false
   */

  public function getChangeList(
    \App\Model\Entity\ExternalIdentitySource $source,
    int $lastStart, // timestamp of last run
    int $curStart   // timestamp of current run
  ): array|bool {
    return $this->getChanges($source, $lastStart, $curStart);
  }

  /**
   * Obtain the full set of records from the source database.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  ExternalIdentitySource $source     External Identity Source
   * @param  bool                   $count      If true, return a count of records
   * @return int|array                          An array of source keys, or a count of source keys (if $count)
   */

  protected function getInventory(
    \App\Model\Entity\ExternalIdentitySource $source,
    bool $count=false
  ): int|array {
    $SourceTable = $this->getRecordTable($source->sql_source);

    $query = $SourceTable->find('list', [
                            'keyField' => 'source_key',
                            'valueField' => 'modified'
                          ]);
    
    if($count) {
      return $query->count();
    } else {
      $records = $query->toArray();

      return array_keys($records);
    }
  }

  /**
   * Obtain a Table for Source Records.
   *
   * @since  COmanage Registry v5.0.0
   * @param  SqlSource  $SqlSource    SqlSource configuration entity
   * @param  string     $relatedModel If specified, obtain the Table for this related model
   * @param  bool       $archive      If true, get the archive table version
   * @return Table                    Cake Table
   */

  protected function getRecordTable(
    \SqlConnector\Model\Entity\SqlSource $SqlSource,
    $relatedModel=null, 
    $archive=false
  ) {
    // We need a special database connection to talk to the inbound server.
    // The configuration gets passed to TableRegistry, but Cake only allows a
    // given Table Alias to be passed a configuration once. If we're called
    // multiple times with different configurations (eg: during a sync process)
    // this can be problematic, so we need to append the server ID to both
    // the database connection and the table alias to ensure the correct
    // connections are maintained and retrieved.

    $cxnLabel = "sqlsource" . $SqlSource->server_id;
// XXX add support for archive
    $sourceAlias = "SourceRecord" . $relatedModel . $SqlSource->server_id;
    $sourceTableName = $SqlSource->source_table;

    if(!empty($relatedModel)) {
      $sourceTableName .= "_" . Inflector::tableize($relatedModel);
    }

    // To avoid some overhead, we also cache tables on a per server basis.
    if(!empty($this->tableCache[$cxnLabel][$sourceTableName])) {
      return $this->tableCache[$cxnLabel][$sourceTableName];
    }

    $SqlServer = TableRegistry::getTableLocator()->get('CoreServer.SqlServers');

    $SqlServer->connect($SqlSource->server_id, $cxnLabel);

    $options = [
      'table'       => $sourceTableName,
      'alias'       => $sourceAlias,
      'connection'  => ConnectionManager::get($cxnLabel)
    ];

    $SourceTable = TableUtilities::getTableFromRegistry(
      alias: $sourceAlias,
      options: $options
    );

    $this->tableCache[$cxnLabel][$sourceTableName] = $SourceTable;

    return($SourceTable);
  }

  /**
   * Obtain the full set of records from the source database.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  ExternalIdentitySource $source     External Identity Source
   * @return array                              An array of source keys
   */

  public function inventory(
    \App\Model\Entity\ExternalIdentitySource $source
  ): array {
    return $this->getInventory($source);
  }

  /**
   * Map a Type ID back to its label.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int  $typeId   Type ID
   * @return string         Type label
   */

  protected function mapTypeToLabel(int $typeId): string {
    // We maintain a local cache since we'll probably look these up a lot.

    if(empty($this->typeCache[$typeId])) {
      $Types = TableRegistry::getTableLocator()->get('Types');

      $this->typeCache[$typeId] = $Types->getTypeLabel($typeId);
    }

    return $this->typeCache[$typeId];
  }

  /**
   * Perform checks before a Sync Job proceeds.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  ExternalIdentitySource $source     External Identity Source
   * @param  int                    $lastStart  Timestamp of last run
   * @param  int                    $curStart   Timestamp of current run
   * @throws RuntimeException
   */

  public function preRunChecks(
    \App\Model\Entity\ExternalIdentitySource $source,
    int $lastStart,
    int $curStart
  ) {
    // If a threshold is set, check to make sure less than that many records changed
    // (by percent)

    if(!empty($source->sql_source->threshold_check)
       && $source->sql_source->threshold_check > 0) {
      // threshold_check is not supported if neither modified timestamps nor
      // archive tables are available, since we can't efficiently calculate diffs.

      // In flat mode, any number of changes to a single row will count as "1"
      // change out of the total count (number of rows in table).
      // In relational mode, each change in each related table will count as "1"
      // change (so name + email address = 2 changes), but total count is still the
      // same (so it's possible for more than 100% of records to change).

      $changeCount = $this->getChanges(
        source: $source,
        lastStart: $lastStart,
        curStart: $curStart,
        count: true
      );

      if($changeCount === false) {
        // Could not calculated diff count, probably because neither modified timestamps
        // nor archive tables are in use.
        $this->llog('error', "Could not calculate change set, ignoring threshold_check for " . $source->description);
      } else {
        $totalCount = $this->getInventory(source: $source, count: true);
        $percent = (int)round(($changeCount * 100) / $totalCount);

        $this->llog('trace', "$percent% of records changed ($changeCount of $totalCount) for " . $source->description);

        if($percent >= $source->sql_source->threshold_check) {
          if(isset($source->sql_source->threshold_override)
             && $source->sql_source->threshold_override) {
            // Override is set, clear the flag and allow this run to proceed
            $this->llog('trace', "$percent% of records changed, threshold is " . $source->sql_source->threshold_check . "%, but override is set, so continuing with sync for " . $source->description);

            $source->sql_source->threshold_override = false;
            $this->save($source->sql_source);
          } else {
            // Threshold met, abort
            $this->llog('error', "$percent% of records changed, threshold is " . $source->sql_source->threshold_check . "%, aborting sync for " . $source->description);
            throw new \RuntimeException(__d('sql_connector', 'error.SqlSources.threshold', [$percent, $source->sql_source->threshold_check]));
          }
        }
      }
    }
  }

  /**
   * Perform tasks following a Sync Job.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  ExternalIdentitySource $source     External Identity Source
   */

  public function postRunTasks(
    \App\Model\Entity\ExternalIdentitySource $source
  ) {
    // XXX If archive tables are in use, update them here
  }

  /**
   * Convert one or more records from the SqlSource data to a record suitable for
   * construction of an Entity. This call is for use with Flat Mode.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  SqlSource  $SqlSource  SqlSource configuration entity
   * @param  array      $results    Array of SqlSource records (in entity format)
   * @return array                  Entity record (in array format)
   */

  protected function resultsToEntityData(
    \SqlConnector\Model\Entity\SqlSource $SqlSource,
    array $results
  ): array {
    // Because the EIS Pipeline code expect a type label instead of a type ID
    // we have to map back to the type label even though we have type IDs from
    // the plugin configuration. (This applies to flat mode only.)

    // Build the External Identity as an array
    $eidata = [];

    // There is some inherent ambiguity in supporting multiple roles via flat mode,
    // especially around MVEAs and single valued attributes (ie: date_of_birth).
    // We make very specific design decisions here that are most consistent with the
    // data model; use cases not met here should use Relational mode.

    $eidata['date_of_birth'] = null;

    foreach($results as $result) {
      // Start with the role key and other role specific attributes
      // that require no special handling
      $eirdata = [ 
        // We use the row ID as the role_key, even if there is only one role
        'role_key'            => $result->id,
        'affiliation'         => $result->affiliation,
        'department'          => $result->department,
        'manager_identifier'  => $result->manager_identifier,
        'organization'        => $result->organization,
        'sponsor_identifier'  => $result->sponsor_identifier,
        'title'               => $result->title,
        'valid_from'          => $result->valid_from,
        'valid_through'       => $result->valid_through
      ];

      if(!empty($result->date_of_birth) && empty($eidata['date_of_birth'])) {
        // We take the first DoB we see. Multiple rows should have the same
        // DoB, if they don't that's a problem in the data that needs to be fixed.

        // We have to convert the DateTime back to a string
        $eidata['date_of_birth'] = $result->date_of_birth->format('Y-m-d');
      }

      // MVEAs that have a foreign key to EIR get attached to the EIR

      if(!empty($result->address)) {
        $eirdata['addresses'][] = [
          'street'    => $result->address,
          'type'      => $this->mapTypeToLabel($SqlSource->address_type_id)
        ];
      }

      if(!empty($result->telephone_number)) {
        $eirdata['telephone_numbers'][] = [
          'number'    => $result->telephone_number,
          'type'      => $this->mapTypeToLabel($SqlSource->telephone_number_type_id)
        ];
      }

      if(!empty($result->url)) {
          $eirdata['urls'][] = [
          'url'       => $result->url,
          'type'      => $this->mapTypeToLabel($SqlSource->url_type_id)
        ];
      }

      // Any field beginning a_ is an AdHoc Attribute

      $eidata['ad_hoc_attributes'] = [];

      foreach($result->getVisible() as $field) {
        if(strncmp($field, "a_", 2)==0) {
          $eirdata['ad_hoc_attributes'][] = [
            // Remove the a_ from the column name to construct the tag
            'tag' => substr($field, 2),
            'value' => $result->$field
          ];
        }
      }

      // MVEAs that do not have foreign key to EIR get attach to the EI,
      // but we need to check for duplicates. The check for existing names
      // is a bit more complicated than the simple Hash check we can do
      // fot the other MVEAs, which have only one meaningful attribute.

      $nameFound = false;

      if(!empty($eidata['names'])) {
        foreach($eidata['names'] as $n) {
          if($n['honorific'] == $result->honorific
             && $n['given'] == $result->given
             && $n['middle'] == $result->middle
             && $n['family'] == $result->family
             && $n['suffix'] == $result->suffix) {
            $nameFound = true;
            break;
          }
        }
      }

      if(!$nameFound) {
        $eidata['names'][] = [
          'honorific' => $result->honorific,
          'given'     => $result->given,
          'middle'    => $result->middle,
          'family'    => $result->family,
          'suffix'    => $result->suffix,
          'type'      => $this->mapTypeToLabel($SqlSource->name_type_id)
        ];
      }

      // We use Hash to perform a simple test to avoid duplicates. Using the
      // model notation on the search path allows us to avoid testing if
      // (eg) $eidata['email_addresses'] is empty.

      if(!empty($result->mail)
         && empty(Hash::extract($eidata, 'email_addresses.{n}[mail=' . $result->mail . ']'))) {

        $eidata['email_addresses'][] = [
          'mail'      => $result->mail,
          'type'      => $this->mapTypeToLabel($SqlSource->email_address_type_id)
        ];
      }

      if(!empty($result->identifier)
         && empty(Hash::extract($eidata, 'identifiers.{n}[identifier=' . $result->identifier . ']'))) {
          $eidata['identifiers'][] = [
          'identifier'  => $result->identifier,
          'type'        => $this->mapTypeToLabel($SqlSource->identifier_type_id)
        ];
      }

      if(!empty($result->pronouns)) {
        $eidata['pronouns'][] = [
          'pronouns'  => $result->pronouns,
          'type'      => $this->mapTypeToLabel($SqlSource->pronouns_type_id)
        ];
      }

      $eidata['external_identity_roles'][] = $eirdata;
    }

    return $eidata;
  }

  /**
   * Convert a record from the SqlSource data to a record suitable for
   * construction of an Entity. This call is for use with Relational Mode.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  SqlSource  $SqlSource  SqlSource configuration entity
   * @param  Entity     $result     SqlSource record
   * @return array                  Entity record (in array format)
   */

  protected function resultToEntityData(
    \SqlConnector\Model\Entity\SqlSource $SqlSource,
    \Cake\ORM\Entity $result
  ): array {
    // Build the External Identity as an array
    $eidata = [];

    $eidata['date_of_birth'] = null;
    
    if(!empty($result->date_of_birth)) {
      // We have to convert the DateTime back to a string
      $eidata['date_of_birth'] = $result->date_of_birth->format('Y-m-d');
    }

    // Convert the entities back to arrays
    foreach([
      'addresses',
      'email_addresses',
      'external_identity_roles',
      'identifiers',
      'names', 
      'pronouns', 
      'telephone_numbers',
      'urls'
    ] as $m) {
      if(!empty($result->$m)) {
        foreach($result->$m as $n) {
          $a = $n->toArray();

          // source_key as the foreign key just adds noise in the array
          unset($a['source_key']);

          // id is the de facto role key
          unset($a['id']);

          $eidata[$m][] = $a;
        }
      }
    }

    return $eidata;
  }

  /**
   * Retrieve a record from the External Identity Source.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  ExternalIdentitySource $source     EIS Entity with instantiated plugin configuration
   * @param  string                 $source_key Backend source key for requested record
   * @return array                              Array of source_key, source_record, and entity_data
   * @throws InvalidArgumentException
   */

  public function retrieve(
    \App\Model\Entity\ExternalIdentitySource $source,
    string $source_key
  ): array {
    $ret['source_key'] = $source_key;

    if($source->sql_source->table_mode == SqlSourceTableModeEnum::Flat) {
      // Establish a connection to the source database
      $SourceTable = $this->getRecordTable($source->sql_source);

      try {
        $results = $SourceTable->find()
                              ->where(['source_key' => $source_key])
                              // We support retrieving more than one row for
                              // multi-role support in flat mode
                              ->all();
        
        if($results->count()==0) {
          // Record was probably deleted
          $ret['entity_data'] = null;
          $ret['source_record'] = null;
        } else {
          $ret['entity_data'] = $this->resultsToEntityData($source->sql_source, $results->toArray());
          $ret['source_record'] = json_encode($results);
        }
      }
      catch(\Exception $e) {
        throw new \InvalidArgumentException(__d('error', 'notfound', [$source_key]));
      }
    } else {
      // Relational mode, pull data from the associated models. It's easier to just
      // retrieve the related models ourselves than to try to force containable to do it.

      // Be careful with $result (the entity we're building), $results (the MVEA set
      // returned from a query), and $r (the iteration of the MVEA set).

      $SourceTable = $this->getRecordTable($source->sql_source);

      // This will hold the initial entity, and then we'll attach related
      // entities to it manually.
      $result = null;

      try {
        $result = $SourceTable->find()
                              ->where(['source_key' => $source_key])
                              ->firstOrFail();
      }
      catch(\Exception $e) {
        throw new \InvalidArgumentException(__d('error', 'notfound', [$source_key]));
      }

      if($results->count()==0) {
        // Record was probably deleted, so just return
        $ret['entity_data'] = null;
        $ret['source_record'] = null;

        return $ret;
      }

      // From here on out if a table doesn't exist we simply ignore it.
      
      // We pull roles before the MVEAs because we'll manually process each MVEA record,
      // and if it attaches to a role we need to put it in the right place.

      try {
        $SourceTable = $this->getRecordTable($source->sql_source, "Role");

        $results = $SourceTable->find()
                               ->where(['source_key' => $source_key])
                               ->all();

        $result->external_identity_roles = [];

        foreach($results as $r) {
          // Note we key the role on its ID to make it easier to work with
          $r->role_key = $r->id;
          $result->external_identity_roles[ $r->id ] = $r;
        }
      }
      catch(\Exception $e) {
        // Strictly speaking, we should fail here, since this table is documented
        // as required
        $this->llog('trace', "Could not find Roles table for " . $source->description);
      }

      // MVEAs that do not have a Role FK
      foreach(['EmailAddress', 'Identifiers', 'Name', 'Pronouns'] as $model) {
        $table = Inflector::tableize($model);

        try {
          $SourceTable = $this->getRecordTable($source->sql_source, $model);

          $results = $SourceTable->find()
                                ->where(['source_key' => $source_key])
                                ->all();
          
          $result->$table = [];

          foreach($results as $r) {
            $result->$table[] = $r;
          }
        }
        catch(\Exception $e) {
          $this->llog('trace', "Could not find $model table for " . $source->description . ", skipping");
        }
      }

      // MVEAs that do have a Role FK
      foreach(['Addresses', 'TelephoneNumbers', 'Url'] as $model) {
        $table = Inflector::tableize($model);

        try {
          $SourceTable = $this->getRecordTable($source->sql_source, $model);

          $results = $SourceTable->find()
                                ->where(['source_key' => $source_key])
                                ->all();
          
          // These models can attach either to the External Identity or the
          // External Identity Role, depending on role_id being set
          $result->$table = [];

          foreach($results as $r) {
            if(!empty($r->role_id)) {
              if(!isset($result->external_identity_roles[$r->role_id]->$table)) {
                $result->external_identity_roles[$r->role_id]->$table = [];
              }

              $result->external_identity_roles[$r->role_id]->$table[] = $r;
            } else {
              $result->$table[] = $r;
            }
          }
        }
        catch(\Exception $e) {
          $this->llog('trace', "Could not find $model table for " . $source->description . ", skipping");
        }
      }

      // Now that we're done, create the structure the interface expects.
      $ret['entity_data'] = $this->resultToEntityData($source->sql_source, $result);
      $ret['source_record'] = json_encode($result);
    }

    return $ret;
  }

  /**
   * Search the External Identity Source.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  ExternalIdentitySource $source       EIS Entity with instantiated plugin configuration
   * @param  array                  $searchAttrs  Array of search attributes and values, as configured by searchAttributes()
   * @return array                                Array of matching records
   * @throws InvalidArgumentException
   */

  public function search(
    \App\Model\Entity\ExternalIdentitySource $source,
    array $searchAttrs
  ): array {
    $ret = [];

    if($source->sql_source->table_mode == SqlSourceTableModeEnum::Flat) {
      // Flat Mode

      // Establish a connection to the source database
      $SourceTable = $this->getRecordTable($source->sql_source);

      // We use OR to search each supported field, but we don't substring identifiers

      $whereClause = [];

      // source_key and identifiers require exact search
      foreach([
        'source_key',
        'identifier'
      ] as $f) {
        $whereClause['OR'][$f] = $searchAttrs['q'];
      }

      // email requires case insensitive exact serarch
      foreach([
        'mail'
      ] as $f) {
        $whereClause['OR']['LOWER('.$f.')'] = strtolower($searchAttrs['q']);
      }

      // other fields allow substrings
      foreach([
        'given',
        'family'
      ] as $f) {
        $whereClause['OR']['LOWER('.$f.') LIKE'] = '%' . strtolower($searchAttrs['q']) . '%';
      }

      $results = $SourceTable->find()
                             ->where($whereClause)
                             ->all();

      // Because we allow multiple rows to describe multiple Roles for the same
      // External Identity, handling search results gets a bit more complicated.
      // We'll group the results by source_key, then process each source_key once,
      // even if it appears multiple times, so that the "combined" record is displayed.

      $groupedResults = $results->groupBy('source_key')->toArray();

      $sourceKeys = array_keys($groupedResults);
      sort($sourceKeys);

      foreach($sourceKeys as $source_key) {
        $ret[$source_key] = $this->resultsToEntityData($source->sql_source, $groupedResults[$source_key]);
      }
    } else {
      // Relational searches are a bit more complicated, but basically we'll
      // perform a search on each supported attribute and then OR the results
      // together. We call retrieve() on each resulting record to ensure we
      // have a consistent set of attributes.

      $results = [];

      // Start with source_key, the only attribute in the primary table

      $SourceTable = $this->getRecordTable($source->sql_source);

      $results = $SourceTable->find()
                             ->where(['source_key' => $searchAttrs['q']])
                             // This should really just return max(1)
                             ->all();

      foreach($results as $result) {
        $data = $this->retrieve($source, $result->source_key);

        $ret[ $result->source_key ] = $data['entity_data'];
      }

      // Identifiers are case sensitive
      $SourceTable = $this->getRecordTable($source->sql_source, "Identifier");

      $results = $SourceTable->find()
                             ->where(['identifier' => $searchAttrs['q']])
                             // This should really just return max(1)
                             ->all();

      foreach($results as $result) {
        $data = $this->retrieve($source, $result->source_key);

        $ret[ $result->source_key ] = $data['entity_data'];
      }

      // Email addresses are case insensitive
      $SourceTable = $this->getRecordTable($source->sql_source, "EmailAddress");

      $results = $SourceTable->find()
                             ->where(['LOWER(mail)' => strtolower($searchAttrs['q'])])
                             ->all();

      foreach($results as $result) {
        $data = $this->retrieve($source, $result->source_key);

        $$ret[ $result->source_key ] = $data['entity_data'];
      }

      // Names allow substrings
      $SourceTable = $this->getRecordTable($source->sql_source, "Names");

      $results = $SourceTable->find()
                             ->where([
                              'OR' => [
                                'LOWER(given) LIKE' => '%'.strtolower($searchAttrs['q']).'%',
                                'LOWER(family) LIKE' => '%'.strtolower($searchAttrs['q']).'%'
                              ]])
                             ->all();

      foreach($results as $result) {
        $data = $this->retrieve($source, $result->source_key);

        $ret[ $result->source_key ] = $data['entity_data'];
      }
    }

    return $ret;
  }
  
  /**
   * Obtain the set of searchable attributes for this backend.
   * 
   * @since  COmanage Registry v5.0.0
   * @return array    Array of searchable attributes and localized descriptions
   */

  public function searchableAttributes(): array {
    // In v4 we accepted structured search attributes (name, email, etc), but
    // with CSV v2 (the only currently supported format) it's not clear what
    // the benefit of this is anymore, so for PE we switch to a simple search
    // string.

    return [
      'q' => __d('field', 'search.placeholder')
    ];
  }

  /**
   * Validate that a type is set for Flat mode.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $value   Value to validate
   * @param  array  $context Validation context, which must include the schema definition
   * @return mixed           True if $value validates, or an error string otherwise
   */

  public function validateSqlSourceType($value, array $context) {
    // When in Flat mode, Type IDs must be set for the various MVEAs.

    if(empty($value)
       && isset($context['data']['table_mode'])
       && $context['data']['table_mode'] == SqlSourceTableModeEnum::Flat) {
      return __d('sql_connector', 'error.SqlSources.type');
    }

    return true;
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
    
    $validator->add('external_source_identity_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('external_source_identity_id');

    $validator->add('server_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('server_id');

    $validator->add('table_mode', [
      'content' => ['rule' => ['inList', SqlSourceTableModeEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('table_mode');

    $validator->add('source_table', [
      'content' => [
        'rule' => 'validateSqlIdentifier',
        'provider' => 'table'
      ]
    ]);
    $validator->notEmptyString('source_table');

    // These all effectively become required when table_mode is flat
    foreach([
      'address_type_id',
      'email_address_type_id',
      'identifier_type_id',
      'name_type_id',
      'pronouns_type_id',
      'telephone_number_type_id',
      'url_type_id',
    ] as $field) {
      $validator->add($field, [
        'content' => [
          'rule' => ['validateSqlSourceType'],
          'provider' => 'table'
        ]
      ]);
    }
    
    $validator->add('threshold_check', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->add('threshold_check', [
      'range'   => ['rule' => 'range', 0, 100]
    ]);
    $validator->allowEmptyString('threshold_check');

    return $validator; 
  }
}