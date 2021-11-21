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
use Cake\Utility\Inflector;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;

class TransmogrifyCommand extends Command {
  // Tables must be listed in order of primary key dependencies.
  protected $tables = [
    'cos' => [
      'source' => 'cm_cos',
      'displayField' => 'name'
    ],
    'types' => [
      'source' => 'cm_co_extended_types',
      'displayField' => 'display_name',
      'fieldMap' => [
        'attribute' => '&map_extended_type',
        'name' => 'value',
        // For some reason, cm_co_extended_types never had created/modified metadata
        'created' => '&map_now',
        'modified' => '&map_now'
      ],
      'cache' => [ [ 'co_id', 'attribute', 'value' ] ]
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
      'displayField' => 'name'
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
    'external_identities' => [
      'source' => 'cm_org_identities',
      'displayField' => 'id',
      'fieldMap' => [
        'co_id' => null,
        'person_id' => '&map_org_identity_co_person_id',
        'o' => 'organization',
        'ou' => 'department',
        // Rename the changelog key
        'org_identity_id' => 'external_identity_id'
      ],
      'cache' => [ 'person_id' ]
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
    'identifiers' => [
      'source' => 'cm_identifiers',
      'displayField' => 'id',
      'booleans' => [ 'login' ],
      'fieldMap' => [
        'co_person_id' => 'person_id',
        'org_identity_id' => 'external_identity_id',
        'type_id' => '&map_identifier_type',
        'type' => null,
// XXX temporary until tables are migrated
        'co_department_id' => null,
        'co_group_id' => null,
        'co_provisioning_target_id' => null,
        'organization_id' => null
      ]
    ]
  ];
  
  // Table specific field mapping cache
  protected $cache = [];
  
  // Make some objects more easily accessible
  protected $inconn = null;
  
  /**
   * Build an Option Parser.
   *
   * @since  COmanage Registry v5.0.0
   * @param  ConsoleOptionParser $parser ConsoleOptionParser
   * @return ConsoleOptionParser         ConsoleOptionParser
   */
  
  protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser {
    $parser->setEpilog('An optional, space separated list of tables to transmogrify may be specified');

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
   * Execute the Transmogrify Command.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Arguments $args Command Arguments
   * @param  ConsoleIo $io   Console IO
   */
  
  public function execute(Arguments $args, ConsoleIo $io) {
    // Load data from the inbound "transmogrify" database to a newly created
    // (and empty) v5 database. The schema should already be applied to the
    // new database.
    
    // First, open connections to both old and new databases.
    // Use the Cake ConnectionManager to get the database configs to pass to DBAL.
    $indb = ConnectionManager::get('transmogrify');
    $incfg = $indb->config();
    
    if(empty($incfg)) {
      throw new \InvalidArgumentException(__d('error', 'db.config', ["transmogrify"]));
    }
    
    $outdb = ConnectionManager::get('default');
    $outcfg = $outdb->config();
    
    if(empty($incfg)) {
      throw new \InvalidArgumentException(__d('error', 'db.config', ["default"]));
    }
    
    $inconfig = new \Doctrine\DBAL\Configuration();
    
    $cargs = [
      'dbname'   => $incfg['database'],
      'user'     => $incfg['username'],
      'password' => $incfg['password'],
      'host'     => $incfg['host'],
      'driver'   => ($incfg['driver'] == 'Cake\Database\Driver\Postgres' ? "pdo_pgsql" : "pdo_mysql")
    ];
    
    $this->inconn = DriverManager::getConnection($cargs, $inconfig);
    
    $outconfig = new \Doctrine\DBAL\Configuration();
    
    $cargs = [
      'dbname'   => $outcfg['database'],
      'user'     => $outcfg['username'],
      'password' => $outcfg['password'],
      'host'     => $outcfg['host'],
      'driver'   => ($outcfg['driver'] == 'Cake\Database\Driver\Postgres' ? "pdo_pgsql" : "pdo_mysql")
    ];
    
    $outconn = DriverManager::getConnection($cargs, $outconfig);
    
    // We accept a list of table names, mostly for testing purposes
    $atables = $args->getArguments();
    
    foreach(array_keys($this->tables) as $t) {
      // If we were given a list of tables see if this table is in the list
      if(!empty($atables) && !in_array($t, $atables))
        continue;
      
      $io->out("===" . $t . "===");
      
      $count = $this->inconn->fetchOne("SELECT COUNT(*) FROM " . $this->tables[$t]['source']);
      
      $io->out("= Processing " . $count . " records");
      
      $insql = "SELECT * FROM " . $this->tables[$t]['source'] . " ORDER BY id ASC";
      $stmt = $this->inconn->query($insql);
      
      $tally = 0;
      
      while($row = $stmt->fetch()) {
        if(!empty($row[ $this->tables[$t]['displayField'] ])) {
          $io->out($row[ $this->tables[$t]['displayField'] ] . "...", 0);
        }
        
        try {
          // Do this before fixBooleans since we'll insert some
          $this->fixChangelog($t, $row);
          
          $this->fixBooleans($t, $row);
          
          $this->mapFields($t, $row);
          
          $outconn->insert($t, $row);
          
          $this->cacheResults($t, $row);
        }
        catch(ForeignKeyConstraintViolationException $e) {
          // A foreign key associated with this record did not load, so we can't
          // load this record. This can happen, eg, because the source_field_id
          // did not load, perhaps because it was associated with an Org Identity
          // not linked to a CO Person that was not migrated.
          
          $io->err("WARNING: Skipping record " . $row['id'] . " due to invalid foreign key: " . $e->getMessage());
        }
        catch(\InvalidArgumentException $e) {
          // If we can't find a value for mapping we skip the record
          // (ie: mapFields basically requires a successful mapping)
          
          $io->err("WARNING: Skipping record " . $row['id'] . ": " . $e->getMessage());
        }
        
        $tally++;
        $io->out(floor(($tally * 100)/$count) . "% done");
      }
      
      $max = $this->inconn->fetchOne('SELECT MAX(id) FROM ' . $this->tables[$t]['source']);
      $max++;
      
      $io->out("= New max: " . $max);
      
      // Strictly speaking we should use prepared statements, but we control the
      // data here, and also we're executing a maintenance operation (so query
      // optimization is less important)
      $outsql = "ALTER SEQUENCE " . $t . "_id_seq RESTART WITH " . $max;
      $outconn->query($outsql);
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
        $row[$a] = ($row[$a] ? 't' : 'f');
      }
    }
  }
  
  /**
   * Populate empty Changelog data from legacy records.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $table Table Name
   * @param  array  $row   Row of attributes, fixed in place
   */
  
  protected function fixChangelog(string $table, array &$row) {
    if(array_key_exists('deleted', $row) && is_null($row['deleted'])) {
      $row['deleted'] = false;
    }
    
    if(array_key_exists('revision', $row) && is_null($row['revision'])) {
      $row['revision'] = 0;
    }
    
    if(array_key_exists('actor_identifier', $row) && is_null($row['actor_identifier'])) {
      $row['actor_identifier'] = 'Transmogrification';
    }
    
    // The parent FK should remain NULL since this is the original record.
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
      } else {
        // Copy the value to the new name, then unset the old name
        $row[$newname] = $row[$oldname];
        unset($row[$oldname]);
      }
    }
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
        return 'Department.type';
      case 'CoPersonRole.affiliation':
        return 'PersonRole.affiliation';
    }
    
    return $row['attribute'];
  }
  
  /**
   * Map an identifier type string to a foreign key.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $row Row of table data (ignored)
   * @return int        type_id
   */
  
  protected function map_identifier_type(array $row) {
    return $this->map_type($row, 'Identifier.type', $this->findCoId($row));
  }
  
  /**
   * Map a name type string to a foreign key.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $row Row of table data (ignored)
   * @return int        type_id
   */
  
  protected function map_name_type(array $row) {
    return $this->map_type($row, 'Name.type', $this->findCoId($row));
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
      //$this->io('Populating org identity map...');
      
      // We pull deleted rows because we might be migrating deleted rows
      $mapsql = "SELECT * FROM cm_co_org_identity_links";
      $stmt = $this->inconn->query($mapsql);
      
      while($r = $stmt->fetch()) {
        if(!empty($r['org_identity_id'])) {
          $this->cache['org_identities']['co_people'][ $r['org_identity_id'] ][ $r['revision'] ] = $r['co_person_id'];
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
   * Map a type string to a foreign key.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $row  Row of table data (ignored)
   * @param  string $type Type to map (types:attribute) 
   * @param  int    $coId CO ID
   * @return int          type_id
   * @throws              InvalidArgumentException
   */
  
  protected function map_type(array $row, string $type, $coId) {
    if(!$coId) {
      throw new \InvalidArgumentException("CO ID not provided for $type " . $row['id']);
    }
    
    $key = $coId . "+" . $type . "+" . $row['type'] . "+";
    
    if(empty($this->cache['types']['co_id+attribute+value+'][$key])) {
      throw new \InvalidArgumentException("Type not found for " . $key);
    }
    
    return $this->cache['types']['co_id+attribute+value+'][$key];
  }
}
