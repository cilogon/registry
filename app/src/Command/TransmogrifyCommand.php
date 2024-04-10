<?php
/**
 * COmanage Registry Transmogrify Command
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

namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionInterface;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\FrozenTime;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use \App\Lib\Util\PaginatedSqlIterator;
use \App\Lib\Util\DBALConnection;
use \App\Lib\Util\TransmogrifyUtilities;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;

class TransmogrifyCommand extends Command {
  use \App\Lib\Traits\LabeledLogTrait;

  // Tables must be listed in order of primary key dependencies.
  protected $tables = [
    'cos' => [
      'source' => 'cm_cos',
      'displayField' => 'name',
      'addChangelog' => true,
      // We don't really need status, but we need something cached for co_settings
      'cache' => [ 'status' ]
    ],
    'types' => [
      'source' => 'cm_co_extended_types',
      'displayField' => 'display_name',
      'postTable' => 'insertPronounTypes',
      'fieldMap' => [
        'attribute' => '&map_extended_type',
        'name' => 'value',
        // For some reason, cm_co_extended_types never had created/modified metadata
        'created' => '&map_now',
        'modified' => '&map_now'
      ],
      'cache' => [ [ 'co_id', 'attribute', 'value' ] ]
    ],
    'co_settings' => [
      'source' => 'cm_co_settings',
      'displayField' => 'co_id',
      'addChangelog' => true,
      'booleans' => [],
      'postTable' => 'insertDefaultSettings',
      'cache' => [ 'co_id' ],
      'fieldMap' => [
        'global_search_limit' => 'search_global_limit',
        'required_fields_addr' => 'required_fields_address',
        'permitted_fields_telephone_number' => '&populate_co_settings_phone',
        // XXX CFM-80 these fields are not yet migrated
        //     be sure to add appropriate fields to 'booleans'
        'enable_nsf_demo' => null, // CFM-123
        'disable_expiration' => null,
        'disable_ois_sync' => null,
        'group_validity_sync_window' => null,
        'garbage_collection_interval' => null,
        'enable_normalization' => null,
        'enable_empty_cou' => null,
        'invitation_validity' => null,
        't_and_c_login_mode' => null,
        'sponsor_eligibility' => null,
        'sponsor_co_group_id' => null,
        'theme_stacking' => null,
        'default_co_pipeline_id' => null, // XXX was this ever used?
        'elect_strategy_primary_name' => null,
        'co_dashboard_id' => null,
        'co_theme_id' => null,
        'person_picker_email_type' => null,
        'person_picker_identifier_type' => null,
        'person_picker_display_types' => null,
        // No longer supported in PE, see CFM-316
        'group_create_admin_only' => null
      ]
    ],
    'authentication_events' => [
      'source' => 'cm_authentication_events',
      'displayField' => 'authenticated_identifier'
    ],
    'api_users' => [
      'source' => 'cm_api_users',
      'displayField' => 'username',
      'booleans' => [ 'privileged' ],
      'fieldMap' => [
        'password' => 'api_key'
      ]
    ],
    'cous' => [
      'source' => 'cm_cous',
      'displayField' => 'name',
      'sqlSelect' => 'couSqlSelect'
    ],
    //'dashboards' => [ 'source' => 'cm_co_dashboards' ]
    'people' => [
      'source' => 'cm_co_people',
      'displayField' => 'id',
      'cache' => [ 'co_id' ],
      'fieldMap' => [
        // Rename the changelog key
        'co_person_id' => 'person_id'
      ]
    ],
    'person_roles' => [
      'source' => 'cm_co_person_roles',
      'displayField' => 'id',
      // We don't currently need status specifically, just that the role exists
      'cache' => [ 'status' ],
      'fieldMap' => [
        'co_person_id' => 'person_id',
        // Rename the changelog key
        'co_person_role_id' => 'person_role_id',
        // We need to map affiliation_type_id before we null out affiliation
        'affiliation_type_id' => '&map_affiliation_type',
        'affiliation' => null,
        'manager_co_person_id' => 'manager_person_id',
        'sponsor_co_person_id' => 'sponsor_person_id',
        'o' => 'organization',
        'ou' => 'department',
// XXX temporary until tables are migrated
        'source_org_identity_id' => null
      ]
    ],
    'external_identities' => [
      'source' => 'cm_org_identities',
      'displayField' => 'id',
      'fieldMap' => [
        'co_id' => null,
        'person_id' => '&map_org_identity_co_person_id',
        // Rename the changelog key
        'org_identity_id' => 'external_identity_id',
        // These fields are migrated to external_identity_roles by split_external_identity()
        'title' => null,
        'o' => null,
        'ou' => null,
        'affiliation' => null,
        'manager_identifier' => null,
        'sponsor_identifier' => null,
        'valid_from' => null,
        'valid_through' => null
      ],
      'postRow' => 'split_external_identity',
      'cache' => [ 'person_id' ]
    ],
    'groups' => [
      'source' => 'cm_co_groups',
      'displayField' => 'name',
      'cache' => [ 'co_id', 'owners_group_id' ],
      'booleans' => [ 'nesting_mode_all', 'open' ],
      'fieldMap' => [
        // auto is implied by group_type
        'auto' => null,
        // Rename the changelog key
        'co_group_id' => 'group_id',
        // Make sure group_type is populated if not already set
        'group_type' => '?S'
      ],
      'postTable' => 'createOwnersGroups'
    ],
    'group_nestings' => [
      'source' => 'cm_co_group_nestings',
      'displayField' => 'id',
      'booleans' => [ 'negate' ],
      'fieldMap' => [
        'co_group_id' => 'group_id',
        'target_co_group_id' => 'target_group_id',
        // Rename the changelog key
        'co_group_nesting_id' => 'group_nesting_id'
      ]
    ],
    'group_members' => [
      'source' => 'cm_co_group_members',
      'displayField' => 'id',
      'booleans' => [ 'member', 'owner' ],
      'fieldMap' => [
        'co_group_id' => 'group_id',
        'co_person_id' => 'person_id',
        'member' => null,
        'owner' => null,
        'co_group_nesting_id' => 'group_nesting_id',
        // Rename the changelog key
        'co_group_member_id' => 'group_member_id',
        // Temporary until implemented
        'source_org_identity_id' => null
      ],
      'preRow' => 'check_group_membership'
    ],
    'names' => [
      'source' => 'cm_names',
      'displayField' => 'id',
      'booleans' => [ 'primary_name' ],
      'fieldMap' => [
        'co_person_id' => 'person_id',
        'org_identity_id' => 'external_identity_id',
        // We need to map type_id before we null out type
        'type_id' => '&map_name_type',
        'type' => null
      ]
    ],
    'ad_hoc_attributes' => [
      'source' => 'cm_ad_hoc_attributes',
      'displayField' => 'id',
      'fieldMap' => [
        'co_person_role_id' => 'person_role_id',
        'org_identity_id' => 'external_identity_id',
// XXX temporary until tables are migrated
        'co_department_id' => null,
        'organization_id' => null
      ],
      'postTable' => 'processExtendedAttributes'
    ],
    'addresses' => [
      'source' => 'cm_addresses',
      'displayField' => 'id',
      'fieldMap' => [
        'co_person_role_id' => 'person_role_id',
        'org_identity_id' => 'external_identity_id',
        'type_id' => '&map_address_type',
        'type' => null,
// XXX temporary until tables are migrated
        'co_department_id' => null,
        'organization_id' => null
      ]
    ],
    'email_addresses' => [
      'source' => 'cm_email_addresses',
      'displayField' => 'id',
      'booleans' => [ 'verified' ],
      'fieldMap' => [
        'co_person_id' => 'person_id',
        'org_identity_id' => 'external_identity_id',
        'type_id' => '&map_email_type',
        'type' => null,
// XXX temporary until tables are migrated
        'co_department_id' => null,
        'organization_id' => null
      ]
    ],
    'identifiers' => [
      'source' => 'cm_identifiers',
      'displayField' => 'id',
      'booleans' => [ 'login' ],
      'fieldMap' => [
        'co_group_id' => 'group_id',
        'co_person_id' => 'person_id',
        'org_identity_id' => 'external_identity_id',
        'type_id' => '&map_identifier_type',
        'type' => null,
// XXX temporary until tables are migrated
        'co_department_id' => null,
        'co_provisioning_target_id' => null,
        'organization_id' => null
      ],
      'preRow' => 'map_login_identifiers'
    ],
    'telephone_numbers' => [
      'source' => 'cm_telephone_numbers',
      'displayField' => 'id',
      'fieldMap' => [
        'co_person_role_id' => 'person_role_id',
        'org_identity_id' => 'external_identity_id',
        'type_id' => '&map_telephone_type',
        'type' => null,
// XXX temporary until tables are migrated
        'co_department_id' => null,
        'organization_id' => null
      ]
    ],
    'urls' => [
      'source' => 'cm_urls',
      'displayField' => 'id',
      'fieldMap' => [
        'co_person_id' => 'person_id',
        'org_identity_id' => 'external_identity_id',
        'type_id' => '&map_url_type',
        'type' => null,
// XXX temporary until tables are migrated
        'co_department_id' => null,
        'organization_id' => null
      ]
    ],
    'history_records' => [
      'source' => 'cm_history_records',
      'displayField' => 'id',
      'fieldMap' => [
        'actor_co_person_id' => 'actor_person_id',
        'co_person_id' => 'person_id',
        'co_person_role_id' => 'person_role_id',
        'co_group_id' => 'group_id',
        'org_identity_id' => 'external_identity_id',
// XXX temporary until tables are migrated
        'co_email_list_id' => null,
        'co_service_id' => null
      ]
    ],
    'jobs' => [
      'source' => 'cm_co_jobs',
      'displayField' => 'id',
      'fieldMap' => [
        'job_type' => 'plugin',
        'job_mode' => null,
        'queue_time' => 'register_time',
        'complete_time' => 'finish_time',
        'job_type_fk' => null,
        'job_params' => 'parameters',
        'requeued_from_co_job_id' => 'requeued_from_job_id',
        // XXX CFM-246 not yet supported
        'max_retry' => null,
        'max_retry_count' => null
      ],
      'preRow' => 'filterJobs'
    ],
    'job_history_records' => [
      'source' => 'cm_co_job_history_records',
      'displayField' => 'id',
      'fieldMap' => [
        'co_job_id' => 'job_id',
        'co_person_id' => 'person_id',
        'org_identity_id' => 'external_identity_id'
      ]
    ]
  ];
  
  // Table specific field mapping cache
  protected $cache = [];
  
  // Make some objects more easily accessible
  protected $inconn = null;
  protected $outconn = null;
  
  // Shell arguments, for easier access
  protected $args = null;
  protected $io = null;
  
  /**
   * Build an Option Parser.
   *
   * @since  COmanage Registry v5.0.0
   * @param  ConsoleOptionParser $parser ConsoleOptionParser
   * @return ConsoleOptionParser         ConsoleOptionParser
   */
  
  protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser {
    $parser->addOption('login-identifier-copy', [
                        'help' => __d('command', 'tm.login-identifier-copy'),
                        'boolean' => true
                      ]);
    
    $parser->addOption('login-identifier-type', [
                        'help' => __d('command', 'tm.login-identifier-type')
                      ]);
    
    $parser->setEpilog(__d('command', 'tm.epilog'));

    return $parser;
  }
  
  /**
   * Cache results as configured for the specified table.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $table Table to cache
   * @param  array  $row   Row of table data
   */
  
  protected function cacheResults(string $table, array $row) {
    if(!empty($this->tables[$table]['cache'])) {
      // Cache the requested fields. For now, at least, we key on row ID only.
      foreach($this->tables[$table]['cache'] as $field) {
        if(is_array($field)) {
          // This is a list of fields, create a composite key that point to the row ID
          
          $label = "";
          $key = "";
          
          foreach($field as $subfield) {
            // eg: co_id+attribute+value+
            $label .= $subfield . "+";
            
            // eg: 2+Identifier.type+eppn+
            $key .= $row[$subfield] . "+";
          }
          
          $this->cache[$table][$label][$key] = $row['id'];
        } else {
          // Map id to the requested field
          $this->cache[$table]['id'][ $row['id'] ][$field] = $row[$field];
        }
      }
    }
  }
  
  /**
   * Check if a group membership is actually asserted, and reassign ownerships.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $origRow Row of table data (original data)
   * @param  array $row     Row of table data (post fixes)
   * @throws InvalidArgumentException
   */
  
  protected function check_group_membership(array $origRow, array $row) {
    // We need to handle the various member+owner scenarios, but basically
    // (1) If 'owner' is set, manually create a Group Membership in the appropriate
    //     Owners Group (we need to be called via preRow to do this)
    // (2) If 'member' is NOT set, throw an exception so we don't create
    //     in invalid membership
    // (3) Otherwise just return so the Membership gets created

    if($origRow['owner'] && !$origRow['deleted'] && !$origRow['co_group_member_id']) {
      // Create a membership in the appropriate owners group, but not
      // on changelog entries

      if(!empty($this->cache['groups']['id'][ $origRow['co_group_id'] ]['owners_group_id'])) {
        $ownerRow = [
          'group_id' => $this->cache['groups']['id'][ $origRow['co_group_id'] ]['owners_group_id'],
          'person_id' => $origRow['co_person_id'],
          'created' => $origRow['created'],
          'modified' => $origRow['modified'],
          'group_member_id' => null,
          'revision' => 0,
          'deleted' => 'f',
          'actor_identifier' => $origRow['actor_identifier']
        ];
        
        $tableName = 'group_members';
        $qualifiedTableName = $this->outconn->qualifyTableName($tableName);
        $this->outconn->insert($qualifiedTableName, $ownerRow);
      } else {
        $this->io->error("Could not find owners group for CoGroupMember " . $origRow['id']);
      }
    }

    if(!$row['member'] && !$row['owner']) {
      throw new \InvalidArgumentException('member not set on GroupMember');
    }
  }

  /**
   * Return SQL used to select COUs from inbound database.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $tableName Name of the SQL table
   * @return string SQL string to select rows from inbound database
   */

  protected function couSqlSelect(string $tableName): string {
    if($this->inconn->isMySQL()) {
      $sqlTemplate = TransmogrifyUtilities::COU_SQL_SELECT_TEMPLATE_MYSQL;
    } else {
      $sqlTemplate = TransmogrifyUtilities::COU_SQL_SELECT_TEMPLATE_POSTGRESQL;
    }

    $sql = str_replace('{table}', $tableName, $sqlTemplate);

    return $sql;
  }

  /**
   * Create an Owners Group for an existing Group.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $origRow Row of table data (original data)
   * @param  array $row     Row of table data (post fixes)
   */
  
  protected function createOwnersGroups() {
    // Pull all Groups and create Owners Group for them. Deployments generally
    // don't have so many Groups that we need PaginatedSqlIterator, but we'll
    // use it here anyway just in case.

    // By doing this once for the table we avoid having to sort through
    // changelog metadata to figure out which rows to actually create owners
    // groups for.

    $Groups = TableRegistry::getTableLocator()->get('Groups');

    $iterator = new PaginatedSqlIterator($Groups, []);

    foreach($iterator as $k => $group) {
      try {
        // Because PaginatedSqlIterator will pick up new Groups as we create them,
        // we need to check for any Owners groups (that we just created) and skip them.
        if(!$group->isOwners()) {
          $ownersGid = $Groups->createOwnersGroup($group);

          // We need to manually populate the cache
          $this->cache['groups']['id'][$group->id]['owners_group_id'] = $ownersGid;
        }
      }
      catch(\Exception $e) {
        $this->io->error("Failed to create owners group for "
                          . $group->name . " (" . $group->id . "): "
                          . $e->getMessage());
      }
    }
  }
  
  /**
   * Execute the Transmogrify Command.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Arguments $args Command Arguments
   * @param  ConsoleIo $io   Console IO
   */
  
  public function execute(Arguments $args, ConsoleIo $io) {
    $this->args = $args;
    $this->io = $io;
    
    // Load data from the inbound "transmogrify" database to a newly created
    // (and empty) v5 database. The schema should already be applied to the
    // new database.
    
    // First, open connections to both old and new databases.
    $this->inconn = DBALConnection::factory($io, 'transmogrify');
    $this->outconn = DBALConnection::factory($io, 'default');

    // We accept a list of table names, mostly for testing purposes
    $atables = $args->getArguments();
    
    // Register the current version for future upgrade purposes
    $targetVersion = rtrim(file_get_contents(CONFIG . DS . "VERSION"));
    
    $metaTable = $this->getTableLocator()->get('Meta');
    $metaTable->setUpgradeVersion($targetVersion, true);
    
    foreach(array_keys($this->tables) as $t) {
      // If the command line args include a list of tables skip this table
      // if it is not in that list.
      if(!empty($atables) && !in_array($t, $atables))
        continue;
      
      $io->info(message: sprintf("Transmogrifying table %s(%s)", Inflector::classify($t), $t));

      // Find the maximum id in the inbound table and reset the sequence for the outbound
      // table to be that value plus one so that as we process the rows any entirely new rows
      // inserted (entirely new rows have no existing id and will be given the id by the
      // auto sequence) do not have a primary key id that conflicts with an existing row
      // from the inbound table.
      $qualifiedTableName = $this->inconn->qualifyTableName($this->tables[$t]['source']);
      $maxId = $this->inconn->fetchOne('SELECT MAX(id) FROM ' . $qualifiedTableName);
      $maxId++;

      $qualifiedTableName = $this->outconn->qualifyTableName($t);
      $this->io->info("Resetting sequence for $qualifiedTableName to $maxId");

      // Strictly speaking we should use prepared statements, but we control the
      // data here, and also we're executing a maintenance operation (so query
      // optimization is less important).
      if($this->outconn->isMySQL()) {
        $outsql = "ALTER TABLE $qualifiedTableName AUTO_INCREMENT = " . $maxId;
      } else {
        $outsql = "ALTER SEQUENCE " . $qualifiedTableName . "_id_seq RESTART WITH " . $maxId;
      }
      $this->outconn->executeQuery($outsql);
      
      // Run any preprocessing functions for the table.
      if(!empty($this->tables[$t]['preTable'])) {
        $p = $this->tables[$t]['preTable'];
        
        $this->$p();
      }
      
      // Check if the outbound table contains any rows and skip the table if it does.
      $Model = $this->getTableLocator()->get($t);
      if($Model->find()->count() > 0) {
        $io->warning("Skipping Transmogrification. Table (" . $t . ") is not empty. Drop the database (or truncate) and start over.");
        continue;
      }
      
      // Count the rows in the inbound table so that we can log the percentage processed.
      $qualifiedTableName = $this->inconn->qualifyTableName($this->tables[$t]['source']);
      $count = $this->inconn->fetchOne("SELECT COUNT(*) FROM " . $qualifiedTableName);

      // Select all the rows from the inbound table.
      if(!empty($this->tables[$t]['sqlSelect'])) {
        $p = $this->tables[$t]['sqlSelect'];
        $insql = $this->$p($qualifiedTableName);
      } else {
        $insql = "SELECT * FROM " . $qualifiedTableName . " ORDER BY id ASC";
      }
      $stmt = $this->inconn->executeQuery($insql);

      $tally = 0;
      $warns = 0;
      $err = 0;
      
      // Loop over each row from the inbound table.
      while($row = $stmt->fetch()) {
        if(!empty($row[ $this->tables[$t]['displayField'] ])) {
          $io->verbose("$t " . $row[ $this->tables[$t]['displayField'] ]);
        }

        try {
          // Make a copy of the original data for any postprocessing followups.
          $origRow = $row;
          
          // Run any preprocessing functions for the row.
          
          if(!empty($this->tables[$t]['preRow'])) {
            $p = $this->tables[$t]['preRow'];
            
            $this->$p($origRow, $row);
          }
          
          // Do this before fixBooleans since we'll insert some
          $this->fixChangelog($t, $row, isset($this->tables[$t]['addChangelog']) && $this->tables[$t]['addChangelog']);
          
          $this->fixBooleans($t, $row);
          
          $this->mapFields($t, $row);
          
          $qualifiedTableName = $this->outconn->qualifyTableName($t);
          $this->outconn->insert($qualifiedTableName, $row);
          
          $this->cacheResults($t, $row);
          
          // Run any post processing functions for the row.
          
          if(!empty($this->tables[$t]['postRow'])) {
            $p = $this->tables[$t]['postRow'];
            
            $this->$p($origRow, $row);
          }
        }
        catch(ForeignKeyConstraintViolationException $e) {
          // A foreign key associated with this record did not load, so we can't
          // load this record. This can happen, eg, because the source_field_id
          // did not load, perhaps because it was associated with an Org Identity
          // not linked to a CO Person that was not migrated.
          $warns++;
          $io->warning("Skipping $t record " . $row['id'] . " due to invalid foreign key: " . $e->getMessage());
        }
        catch(\InvalidArgumentException $e) {
          // If we can't find a value for mapping we skip the record
          // (ie: mapFields basically requires a successful mapping)
          $warns++;
          $io->warning("Skipping $t record " . $row['id'] . ": " . $e->getMessage());
        }
        catch(\Exception $e) {
          $err++;
          $io->error("$t record " . $row['id'] . ": " . $e->getMessage());
        }
        
        $tally++;
        
        if(!$this->args->getOption('quiet') && !$this->args->getOption('verbose')) {
          // We don't output the progress bar for quiet for obvious reasons,
          // or for verbose so we don't interfere with the extra output
          $this->cliLogPercentage($tally, $count);
        }
      }

      // Log warning and error count.
      $io->out("<warning>(Warnings: " . $warns . ")</warning>");
      $io->out("<error>(Errors: " . $err . ")</error>");

      
      // Run any post processing functions for the table.
      if(!empty($this->tables[$t]['postTable'])) {
        $p = $this->tables[$t]['postTable'];
        
        $this->$p();
      }
    }
  }
  
  /**
   * Filter Jobs.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $origRow Row of table data (original data)
   * @param  array $row     Row of table data (post fixes)
   * @throws InvalidArgumentException
   */
  
  protected function filterJobs(array $origRow, array $row) {
    // We don't update any of the attributes, but for rows with unsupported data
    // we throw an exception so they don't transmogrify.

    if($row['status'] == 'GO' || $row['status'] == 'Q') {
      throw new \InvalidArgumentException("Job is Queued or In Progress");
    }

    if($row['job_type'] == 'EX' || $row['job_type'] == 'OS') {
      throw new \InvalidArgumentException("Legacy Job types cannot be transmogrified");
    }
  }

  /**
   * Find the CO for a row of table data, based on a foreign key.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $row Row of table data
   * @return int         CO ID
   * @throws InvalidArgumentException
   */
  
  protected function findCoId(array $row) {
    // By the time we're called, we should have transmogrified the Org Identity
    // and CO Person data, so we can just walk the caches
    
    if(!empty($row['person_id'])) {
      if(isset($this->cache['people']['id'][ $row['person_id'] ]['co_id'])) {
        return $this->cache['people']['id'][ $row['person_id'] ]['co_id'];
      }
    } elseif(!empty($row['external_identity_id'])) {
      // Map the OrgIdentity to a CO Person, then to the CO
      if(!empty($this->cache['external_identities']['id'][ $row['external_identity_id'] ]['person_id'])) {
        $personId = $this->cache['external_identities']['id'][ $row['external_identity_id'] ]['person_id'];
        
        if(isset($this->cache['people']['id'][ $personId ]['co_id'])) {
          return $this->cache['people']['id'][ $personId ]['co_id'];
        }
      }
    } elseif(!empty($row['group_id'])) {
      if(isset($this->cache['groups']['id'][ $row['group_id'] ]['co_id'])) {
        return $this->cache['groups']['id'][ $row['group_id'] ]['co_id'];
      }
    }
    // We also support being called using the old keys for use in the preRow context
    elseif(!empty($row['org_identity_id'])) {
      // Map the OrgIdentity to a CO Person, then to the CO
      if(!empty($this->cache['external_identities']['id'][ $row['org_identity_id'] ]['person_id'])) {
        $personId = $this->cache['external_identities']['id'][ $row['org_identity_id'] ]['person_id'];
        
        if(isset($this->cache['people']['id'][ $personId ]['co_id'])) {
          return $this->cache['people']['id'][ $personId ]['co_id'];
        }
      }
    } elseif(!empty($row['co_person_id'])) {
      if(isset($this->cache['people']['id'][ $row['co_person_id'] ]['co_id'])) {
        return $this->cache['people']['id'][ $row['co_person_id'] ]['co_id'];
      }
    }
    
    throw new \InvalidArgumentException('CO not found for record');
  }
  
  /**
   * Translate booleans to string literals to work around DBAL Postgres boolean handling.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $table Table Name
   * @param  array  $row   Row of attributes, fixed in place
   */
  
  protected function fixBooleans(string $table, array &$row) {
    $attrs = ['deleted'];
    
    // We could introspect this from the schema file...
    if(!empty($this->tables[$table]['booleans'])) {
      $attrs = array_merge($attrs, $this->tables[$table]['booleans']);
    }

    foreach($attrs as $a) {
      if(isset($row[$a]) && gettype($row[$a]) == 'boolean') {
        // DBAL Postgres boolean handling seems to be somewhat buggy, see history in
        // this issue: https://github.com/doctrine/dbal/issues/1847
        // We need to (more generically than this hack) convert from boolean to char
        // to avoid errors on insert
        if($this->outconn->isMySQL()) {
          $row[$a] = ($row[$a] ? '1' : '0');
        } else {
          $row[$a] = ($row[$a] ? 't' : 'f');
        }
      }
    }
  }
  
  /**
   * Populate empty Changelog data from legacy records
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $table Table Name
   * @param  array  $row   Row of attributes, fixed in place
   * @param  bool   $force If true, always create keys
   */
  
  protected function fixChangelog(string $table, array &$row, bool $force=false) {
    if($force || (array_key_exists('deleted', $row) && is_null($row['deleted']))) {
      $row['deleted'] = false;
    }
    
    if($force || (array_key_exists('revision', $row) && is_null($row['revision']))) {
      $row['revision'] = 0;
    }
    
    if($force || (array_key_exists('actor_identifier', $row) && is_null($row['actor_identifier']))) {
      $row['actor_identifier'] = 'Transmogrification';
    }
    
    // The parent FK should remain NULL since this is the original record.
    /*
    // If the table was renamed, we need to rename the changelog key as well.
    // NOTE: We don't actually do this here because it creates issues with the
    //       order of field processing. Instead, each key must be renamed
    //       manually in the fieldMap.
    // eg: cm_org_identities -> org_identity_id
    $oldfk = Inflector::singularize(substr($this->tables[$table]['source'], 3)) . "_id";
    // eg: external_identities -> external_identity_id
    $newfk = Inflector::singularize($table) . "_id";
    
    if($oldfk != $newfk && array_key_exists($oldfk, $row)) {
      $row[$newfk] = $row[$oldfk];
      unset($row[$oldfk]);
    }*/
  }
  
  /**
   * Insert default CO Settings.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  protected function insertDefaultSettings() {
    // Create a CoSetting for any CO that didn't previously have one.
    
    $createdSettings = [];
    $createdCos = array_keys($this->cache['cos']['id']);
    
    foreach($this->cache['co_settings']['id'] as $co_setting_id => $cached) {
      $createdSettings[] = $cached['co_id'];
    }
    
    $emptySettings = array_values(array_diff($createdCos, $createdSettings));
    
    if(!empty($emptySettings)) {
      $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');
      
      foreach($emptySettings as $coId) {
        // Insert a default row into CoSettings for this CO ID
        try {
          $CoSettings->addDefaults($coId);
        } catch (\ConflictException $e) {
          // skip
        }
      }
    }
  }
  
  /**
   * Insert default Pronoun types.
   * 
   * @since  COmanage Registry v5.0.0
   */

  protected function insertPronounTypes() {
    // Since the Pronoun MVEA didn't exist in v4, we'll need to create the
    // default types for all COs.

    $Types = TableRegistry::getTableLocator()->get('Types');

    foreach(array_keys($this->cache['cos']['id']) as $coId) {
      $Types->addDefault($coId, 'Pronouns.type');
    }
  }
  
  /**
   * Map fields that have been renamed from Registry Classic to Registry PE.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $table Table Name
   * @param  array  $row   Row of attributes, fixed in place
   * @throws InvalidArgumentException
   */
  
  protected function mapFields(string $table, array &$row) {
    // oldname => newname, or &newname, which is a function to call.
    // Note functions can returns more than one mapping
    $fields = [];
    
    if(!empty($this->tables[$table]['fieldMap'])) {
      $fields = $this->tables[$table]['fieldMap'];
    }
    
    foreach($fields as $oldname => $newname) {
      if(!$newname) {
        // This attribute doesn't map, so simply unset it
        unset($row[$oldname]);
      } elseif($newname[0] == '&') {
        // This is a function to map the field, in which case we reuse the old name
        $f = substr($newname, 1);
        
        // We always pass the entire row so the mapping function can implement
        // whatever logic it needs
        $row[$oldname] = $this->$f($row);
        
        if(!$row[$oldname]) {
          throw new \InvalidArgumentException("Could not find value for $table $oldname");
        }
      } elseif($newname[0] == '?') {
        // This is a default value to populate if the current value is null
        $v = substr($newname, 1);

        if($row[$oldname] === null) {
          $row[$oldname] = $v;
        }
      } else {
        // Copy the value to the new name, then unset the old name
        $row[$newname] = $row[$oldname];
        unset($row[$oldname]);
      }
    }
  }
  
  /**
   * Map an address type string to a foreign key.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $row Row of table data
   * @return int        type_id
   */
  
  protected function map_address_type(array $row) {
    return $this->map_type($row, 'Addresses.type', $this->findCoId($row));
  }
  
  /**
   * Map an affiliation type string to a foreign key.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $row Row of table data
   * @return int        type_id
   */
  
  protected function map_affiliation_type(array $row) {
    return $this->map_type($row, 'PersonRoles.affiliation_type', $this->findCoId($row), 'affiliation');
  }
  
  /**
   * Map an email type string to a foreign key.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $row Row of table data
   * @return int        type_id
   */
  
  protected function map_email_type(array $row) {
    return $this->map_type($row, 'EmailAddresses.type', $this->findCoId($row));
  }
  
  /**
   * Map an Extended Type attribute name for model name changes.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $row Row of table data
   * @return string      Updated attribute name
   */
  
  protected function map_extended_type(array $row) {
    switch($row['attribute']) {
      case 'CoDepartment.type':
        return 'Departments.type';
      case 'CoPersonRole.affiliation':
        return 'PersonRoles.affiliation_type';
    }
    
    // For everything else, we need to pluralize the model name
    $bits = explode('.', $row['attribute'], 2);
    
    return Inflector::pluralize($bits[0]) . "." . $bits[1];
  }
  
  /**
   * Map login identifiers, in accordance with the configuration.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $origRow Row of table data (original data)
   * @param  array $row     Row of table data (post fixes)
   * @throws InvalidArgumentException
   */
  
  protected function map_login_identifiers(array $origRow, array $row) {
    // There might be multiple reasons to copy the row, but we only want to
    // copy it once.
    $copyRow = false;
    
    if(!empty($origRow['org_identity_id'])) {
      if($this->args->getOption('login-identifier-copy')
         && $origRow['login']) {
        $copyRow = true;
      }
      
      // Note the argument here is the old v4 string (eg "eppn") and not the
      // PE foreign key
      if($this->args->getOption('login-identifier-type')
         && $origRow['type'] == $this->args->getOption('login-identifier-type')) {
        $copyRow = true;
      }
      
      // Identifiers attached to External Identities do not have login flags in PE
      $row['login'] = false;
    }
    
    if($copyRow) {
      // Find the Person ID associated with this External Identity ID
      
      if(!empty($this->cache['external_identities']['id'][ $origRow['org_identity_id'] ]['person_id'])) {
        // Insert a new row attached to the Person, leave the original record
        // (ie: $row) untouched
        
        $copiedRow = [
          'person_id'   => $this->map_org_identity_co_person_id(['id' => $origRow['org_identity_id']]),
          'identifier'  => $origRow['identifier'],
          'type_id'     => $this->map_identifier_type($origRow),
          'status'      => $origRow['status'],
          'login'       => true,
          'created'     => $origRow['created'],
          'modified'    => $origRow['modified']
        ];
        
        // Set up changelog and fix booleans
        $this->fixChangelog('identifiers', $copiedRow, true);
        $this->fixBooleans('identifiers', $copiedRow);
        
        try {
          $tableName = 'identifiers';
          $qualifiedTableName = $this->outconn->qualifyTableName($tableName);
          $this->outconn->insert($qualifiedTableName, $copiedRow);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
          $this->io->warning("record already exists: " . print_r($copiedRow, true));
        }
      }
    }
  }
  
  /**
   * Map an identifier type string to a foreign key.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $row Row of table data (ignored)
   * @return int        type_id
   */
  
  protected function map_identifier_type(array $row) {
    return $this->map_type($row, 'Identifiers.type', $this->findCoId($row));
  }
  
  /**
   * Map a name type string to a foreign key.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $row Row of table data (ignored)
   * @return int        type_id
   */
  
  protected function map_name_type(array $row) {
    return $this->map_type($row, 'Names.type', $this->findCoId($row));
  }
  
  /**
   * Return a timestamp equivalent to now.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $row Row of table data (ignored)
   * @return string     Timestamp
   */
  
  protected function map_now(array $row) {
    if(empty($this->cache['now'])) {
      $created = new \Datetime('now');
      $this->cache['now'] = $created->format('Y-m-d H:i:s');
    }
    
    return $this->cache['now'];
  }
  
  /**
   * Map an Org Identity ID to a CO Person ID
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $row Row of Org Identity table data
   * @return int        CO Person ID
   */
  
  protected function map_org_identity_co_person_id(array $row) {
    // PE eliminates OrgIdentityLink, so we need to map each Org Identity to
    // a Person ID. This is a bit trickier than it sounds, since an Org Identity
    // could have been relinked.
    
    // Before Transmogrification, we require that Org Identities are unpooled.
    // (This is probably how most deployments are set up, but there may be some
    // legacy deployments out there.) This ensures whatever CO Person the Org
    // Identity currently maps to through CoOrgIdentityLink is in the same CO.
    
    // There may be multiple mappings if the Org Identity was relinked. Basically
    // we're going to lose the multiple mappings, since we can only return one
    // value here. (Ideally, we would inject multiple OrgIdentities into the new
    // table, but this ends up being rather tricky, since we have to figure out
    // what row id to assign, and for the moment we don't have a mechanism to
    // do that.) Historical information remains available in history_records,
    // and if the deployer keeps an archive of the old database.
    
    // To figure out which person_id to use, we pull the record with the
    // highest revision number. Note we might be transmogrifying a deleted row,
    // so we can't ignore deleted rows here.
    
    if(empty($this->cache['org_identities']['co_people'])) {
      $this->io->info('Populating org identity map...');
      
      // We pull deleted rows because we might be migrating deleted rows
      $tableName = "cm_co_org_identity_links";
      $qualifiedTableName = $this->inconn->qualifyTableName($tableName);
      $mapsql = "SELECT * FROM $qualifiedTableName";
      $stmt = $this->inconn->query($mapsql);
      
      while($r = $stmt->fetch()) {
        if(!empty($r['org_identity_id'])) {
          if(isset($this->cache['org_identities']['co_people'][ $r['org_identity_id'] ][ $r['revision'] ])) {
            // If for some reason we already have a record, it's probably due to
            // improper unpooling from a legacy deployment. We'll accept only the
            // first record and throw warnings on the others.
            
            $this->io->warning("Found existing CO Person for Org Identity " . $r['org_identity_id'] . ", skipping");
          } else {
            $this->cache['org_identities']['co_people'][ $r['org_identity_id'] ][ $r['revision'] ] = $r['co_person_id'];
          }
        }
      }
    }
    
    if(!empty($this->cache['org_identities']['co_people'][ $row['id'] ])) {
      // Return the record with the highest revision number
      $rev = max(array_keys($this->cache['org_identities']['co_people'][ $row['id'] ]));
      
      return $this->cache['org_identities']['co_people'][ $row['id'] ][$rev];
    }
    
    return null;
  }
  
  /**
   * Map a telephone type string to a foreign key.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $row Row of table data
   * @return int        type_id
   */
  
  protected function map_telephone_type(array $row) {
    return $this->map_type($row, 'TelephoneNumbers.type', $this->findCoId($row));
  }
  
  /**
   * Map a type string to a foreign key.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $row    Row of table data
   * @param  string $type   Type to map (types:attribute) 
   * @param  int    $coId   CO ID
   * @param  string $attr   Row column to use for type value
   * @return int          type_id
   * @throws              InvalidArgumentException
   */
  
  protected function map_type(array $row, string $type, $coId, string $attr="type") {
    if(!$coId) {
      throw new \InvalidArgumentException("CO ID not provided for $type " . $row['id']);
    }
    
    $key = $coId . "+" . $type . "+" . $row[$attr] . "+";
    
    if(empty($this->cache['types']['co_id+attribute+value+'][$key])) {
      throw new \InvalidArgumentException("Type not found for " . $key);
    }
    
    return $this->cache['types']['co_id+attribute+value+'][$key];
  }
  
  /**
   * Map a URL type string to a foreign key.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $row Row of table data
   * @return int        type_id
   */
  
  protected function map_url_type(array $row) {
    return $this->map_type($row, 'Urls.type', $this->findCoId($row));
  }
  
  /**
   * Set a default value for CO Settings Permitted Telephone Number Fields.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $row  Row of table data
   * @return string       Default value
   */
  
  protected function populate_co_settings_phone(array $row) {
    return \App\Lib\Enum\PermittedTelephoneNumberFieldsEnum::CANE;
  }
  
  /**
   * Process Extended Attributes by converting them to Ad Hoc Attributes.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  protected function processExtendedAttributes() {
    // This is intended to run AFTER AdHocAttributes so that we don't stomp on
    // the row identifiers.
    
    // First, pull the old Extended Attribute configuration.
    $extendedAttrs = [];
    
    $tableName = "cm_co_extended_attributes";
    $qualifiedTableName = $this->inconn->qualifyTableName($tableName);
    $insql = "SELECT * FROM $qualifiedTableName ORDER BY id ASC";
    $stmt = $this->inconn->query($insql);
    
    while($row = $stmt->fetch()) {
      $extendedAttrs[ $row['co_id'] ][] = $row['name'];
    }
    
    if(empty($extendedAttrs)) {
      // No need to do anything further if no attributes are configured
      return;
    }
    
    foreach(array_keys($extendedAttrs) as $coId) {
      $tableName = "cm_co" . $coId . "_person_extended_attributes";
      $qualifiedTableName = $this->inconn->qualifyTableName($tableName);
      $insql = "SELECT * FROM $qualifiedTableName";
      $stmt = $this->inconn->query($insql);
      
      while($eaRow = $stmt->fetch()) {
        // If we didn't transmogrify the parent row for some reason then trying
        // to insert the ad_hoc_attributes will throw an error.
        if(!empty($this->cache['person_roles']['id'][ $eaRow['co_person_role_id'] ])) {
          foreach($extendedAttrs[$coId] as $ea) {
            $adhocRow = [
              'person_role_id'      => $eaRow['co_person_role_id'],
              'tag'                 => $ea,
              'value'               => $eaRow[$ea],
              'created'             => $eaRow['created'],
              'modified'            => $eaRow['modified']
            ];
            
            // Extended Attributes were not changelog enabled
            $this->fixChangelog('ad_hoc_attributes', $adhocRow, true);
            $this->fixBooleans('ad_hoc_attributes', $adhocRow);

            try {
              $tableName = 'ad_hoc_attributes';
              $qualifiedTableName = $this->outconn->qualifyTableName($tableName);
              $this->outconn->insert($qualifiedTableName, $adhocRow);
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
              $this->io->warning("record already exists: " . print_r($adhocRow, true));
            }
          }
        }
      }
    }
  }
  
  /**
   * Split an External Identity into an External Identity Role.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $origRow Row of table data (original data)
   * @param  array $row     Row of table data (post fixes)
   */
  
  protected function split_external_identity(array $origRow, array $row) {
    $roleRow = [];
    
    // We could set the row ID to be the same as the original parent, but then
    // we'd have to reset the sequence after the table is finished migrating.
    
    foreach([
      // Parent Key
      'id' => 'external_identity_id',
      'o' => 'organization',
      'ou' => 'department',
      'manager_identifier' => 'manager_identifier',
      'sponsor_identifier' => 'sponsor_identifier',
      'status' => 'status',
      'title' => 'title',
      'valid_from' => 'valid_from',
      'valid_through' => 'valid_through',
      // Fix up changelog
      'org_identity_id' => 'external_identity_role_id',
      'revision' => 'revision',
      'deleted' => 'deleted',
      'actor_identifier' => 'actor_identifier',
      'created' => 'created',
      'modified' => 'modified'
    ] as $oldKey => $newKey) {
      $roleRow[$newKey] = $origRow[$oldKey];
    }
    
    // Affiliation requires special handling. We need to use the post-fixed $row
    // because map_affiliation_type calls findCoId which uses the foreign key to
    // lookup the CO ID in the cache, however by the time we've been called
    // affiliation has been null'd out (since we're moving it to the role row).
    // So shove it back in before calling map_affiliation_type.
    $row['affiliation'] = $origRow['affiliation'];
    $roleRow['affiliation_type_id'] = $this->map_affiliation_type($row);
    
    // Fix up changelog
    // Since we're creating a new row, we have to manually fix up booleans
    $roleRow['deleted'] = ($roleRow['deleted'] ? 't' : 'f');
    
    $tableName = 'external_identity_roles';
    $qualifiedTableName = $this->outconn->qualifyTableName($tableName);
    $this->outconn->insert($qualifiedTableName, $roleRow);
  }
}
