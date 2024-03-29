<?php
/**
 * COmanage Registry Schema Manager Utility
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

namespace App\Lib\Util;

use Cake\Console\ConsoleIo;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;

// Database schema management. We use Doctrine DBAL rather than Cake's migrations
// (phinx) because migrations make development annoying (want to add a field
// to a table after you've created it? that's a new migration!), and can't
// provide a single representation of a given table (since you're recording
// diffs, not desired end state). ADOdb (used in earlier versions) was hard to
// debug and poorly maintained. DBAL doesn't have a schema format (like axmls)
// but it does everything else, and specifying a schema format is easy.

class SchemaManager {
  use \App\Lib\Traits\LabeledLogTrait;

  // Console for output
  protected $io = null;

  // The database connection
  protected $conn = null;

  // The column library from the main config
  protected $columnLibrary = null;

  /**
   * Construct a new SchemaManager.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  ConsoleIo  $io         Cake ConsoleIo object
   * @param  string     $connection Database connection name
   */

  public function __construct(?ConsoleIo $io=null, string $connection='default') {
    if($io) {
      $this->io = $io;
    }

    $this->conn = DBALConnection::factory($io, $connection);
  }

  /**
   * Apply a schema file.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $schemaFile   Schema file to apply
   * @param  bool   $parseOnly    If true, attempt to parse the file only, but perform no other actions
   * @param  bool   $diffOnly     If true, generate a diff against the current database state, but do not apply it
   * @param  string $tablePrefix  String to prefix to table names
   */

  public function applySchemaFile(
    string  $schemaFile,
    bool    $parseOnly=false,
    bool    $diffOnly=false,
    string  $tablePrefix=""
  ) {
    if(!is_readable($schemaFile)) {
      throw new \RuntimeException(__d('error', 'file', [$schemaFile]));
    }
    
    $this->llog('debug', __d('command', 'db.schema', [$schemaFile]));
    
    $json = file_get_contents($schemaFile);
    
    $schemaConfig = json_decode($json);
    
    if(!$schemaConfig) {
      // json_last_error[_msg]() are pretty useless. If you are debugging here,
      // it's most likely because of one of the following:
      // - An unmatched brace { }
      // - A trailing comma (permitted in PHP but not JSON)
      // - Single quotes instead of double quotes
      throw new \RuntimeException(__d('error', 'schema.parse', [$schemaFile]));
    }

    // If there is a column library (which should only be in the main config),
    // cache it since plugins may reference it
    if(!empty($schemaConfig->columnLibrary)) {
      $this->columnLibrary = $schemaConfig->columnLibrary;
    }

    if(!$parseOnly) {
      $this->processSchema(schemaConfig: $schemaConfig, diffOnly: $diffOnly);
    }
  }

  /**
   * Apply an already parsed schema object.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  object $schemaObject Schema object
   * @param  string $tablePrefix  String to prefix to table names
   */

  public function applySchemaObject(object $schemaObject, string $tablePrefix="") {
    if(!$this->columnLibrary) {
      // We need the column library from the core config
      $this->applySchemaFile(schemaFile: ROOT . DS . 'config' . DS . 'schema' . DS . 'schema.json',
                             parseOnly: true);
    }

    $this->processSchema(schemaConfig: $schemaObject, tablePrefix: $tablePrefix);
  }

  /**
   * Process a schema object.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  object $schemaConfig Schema object
   * @param  bool   $diffOnly     If true, generate a diff against the current database state, but do not apply it
   * @param  string $tablePrefix  String to prefix to table names
   */

  protected function processSchema(
    object  $schemaConfig,
    bool    $diffOnly=false,
    string  $tablePrefix=""
  ) {
    $schema = new Schema();

    // Walk through $schemaConfig and build our schema in DBAL format.
    
    foreach($schemaConfig->tables as $tName => $tCfg) {
      $qualifiedTableName = $this->conn->qualifyTableName($tablePrefix.$tName);
      $table = $schema->createTable($qualifiedTableName);
      
      foreach($tCfg->columns as $cName => $cCfg) {
        // We allow "inherited" definitions from the fieldLibrary, so merge together
        // the configurations (if appropriate)
        
        $colCfg = (object)array_merge((isset($this->columnLibrary->columns->$cName)
                                       ? (array)$this->columnLibrary->columns->$cName
                                       : []),
                                      (array)$cCfg);
        
        if(!isset($colCfg->type)) {
          throw new \RuntimeException(__d('error', 'schema.column', [$tName, $cName]));
        }
        
        // For type definitions see https://www.doctrine-project.org/projects/doctrine-dbal/en/2.12/reference/types.html#types
        $options = [];
        
        if(isset($colCfg->autoincrement)) {
          $options['autoincrement'] = $colCfg->autoincrement;
        }
        
        if($colCfg->type == "string") {
          $options['length'] = $colCfg->size;
        }
        
        if(isset($colCfg->notnull)) {
          $options['notnull'] = $colCfg->notnull;
        } else {
          $options['notnull'] = false;
        }
        
        $table->addColumn($cName, $colCfg->type, $options);
        
        if(isset($colCfg->primarykey) && $colCfg->primarykey) {
          $table->setPrimaryKey(["id"]);
        }
        
        if(isset($colCfg->foreignkey)) {
          $foreignTableName = $this->conn->qualifyTableName($tablePrefix.$colCfg->foreignkey->table);
          $table->addForeignKeyConstraint($foreignTableName,
                                          [$cName],
                                          [$colCfg->foreignkey->column],
                                          [],
                                          // We name our foreign keys the same way they
                                          // were previously named by adodb
                                          $tablePrefix.$tName . "_" . $cName . "_fkey");
        }
      }
      
      // (For Registry) If MVEA models are specified, emit the appropriate
      // columns and indexes. MVEA attributes must be added before indexes, in
      // case the table has composite indexes referencing MVEA columns.
      
      if(!empty($tCfg->mvea)) {
        $i = 1;
        
        foreach($tCfg->mvea as $m) {
          $mColumn = $m . "_id";
          $fkTable = \Cake\Utility\Inflector::tableize($m);
          $foreignTableName = $this->conn->qualifyTableName($tablePrefix.$fkTable);
          
          // Insert a foreign key to this model and index it
          $table->addColumn($mColumn, "integer", ['notnull' => false]);
          $table->addForeignKeyConstraint($foreignTableName, [$mColumn], ['id'], [], $tablePrefix.$tName . "_" . $mColumn . "_fkey");
          $table->addIndex([$mColumn], $tablePrefix.$tName . "_im" . $i++);
        }

        // MVEA tables also support frozen flags
        $table->addColumn("frozen", "boolean", ['notnull' => false]);
      }
      
      if(isset($tCfg->indexes)) {
        // We don't autogenerate names for indexes so if the definition of an index
        // changes DBAL can just rebuild that index instead of recreating every index
        // on the table. (This should speed up schema updates vs ADOdb.) This does
        // require each index to be named in the schema file, but we had to do that
        // in axmls too, even though it rebuilt every index every time through.
        
        foreach($tCfg->indexes as $iName => $iCfg) {
          // $flags and $options as passed to Index(), but otherwise undocumented
          $flags = [];
          $options = [];
          
          if(isset($iCfg->unique) && $iCfg->unique) {
            $table->addUniqueConstraint($iCfg->columns, $iName, $flags, $options);
          } else {
            $table->addIndex($iCfg->columns, $iName, $flags, $options);
          }
        }
      }
      
      // (For Registry) If an attribute is "sourced" it is a CO Person attribute
      // that is copied via a Pipeline from an External Identity that was created from
      // an External Identity Source, so we need a foreign key into ourself.
      
      if(isset($tCfg->sourced) && $tCfg->sourced) {
        $sColumn = "source_" . $tablePrefix.\Cake\Utility\Inflector::singularize($tName) . "_id";
        $foreignTableName = $this->conn->qualifyTableName($tablePrefix.$tName);
        
        // Insert a foreign key to this model and index it
        $table->addColumn($sColumn, "integer", ['notnull' => false]);
        $table->addForeignKeyConstraint($foreignTableName, [$sColumn], ['id'], [], $tablePrefix.$tName . "_" . $sColumn . "_fkey");
        $table->addIndex([$sColumn], $tablePrefix.$tName . "_im" . $i++);
      }
      
      // Default is to insert timestamp and changelog fields, unless disabled
      
      if(!isset($tCfg->timestamps) || $tCfg->timestamps) {
        // Insert Cake metadata fields
        $table->addColumn("created", "datetime");
        $table->addColumn("modified", "datetime", ['notnull' => false]);
      }
      
      if(!isset($tCfg->changelog) || $tCfg->changelog) {
        // Insert ChangelogBehavior metadata fields
        $clColumn = \Cake\Utility\Inflector::singularize($tName) . "_id";
        $table->addColumn($clColumn, "integer", ['notnull' => false]);
        $table->addColumn("revision", "integer", ['notnull' => false]);
        $table->addColumn("deleted", "boolean", ['notnull' => false]);
        $table->addColumn("actor_identifier", "string", ['length' => 256, 'notnull' => false]);
        
        $table->addForeignKeyConstraint($table, [$clColumn], ['id'], [], $tName . "_" . $clColumn . "_fkey");
        $table->addIndex([$clColumn], $tablePrefix.$tName . "_icl", [], []);
      }
    }
    
    // This is the SQL that represents the desired state of the database
    $toSql = $schema->toSql($this->conn->getDatabasePlatform());
    
    // SchemaManager provides info about the database
    $sm = $this->conn->createSchemaManager();
    
    // The is the current database representation
    $curSchema = $sm->createSchema();
    
    $fromSql = $curSchema->toSql($this->conn->getDatabasePlatform());
    
    try {
      // We manually call compare so we can get the SchemaDiff object. We need
      // this for toSaveSql(), which we use to avoid dropping undocumented tables
      // (like the matchgrids, which are dynamically created and so won't be in the
      // schema file).
      $comparator = new Comparator();
      $schemaDiff = $comparator->compareSchemas($curSchema, $schema);
      
      $diffSql = $schemaDiff->toSaveSql($this->conn->getDatabasePlatform());
      
      // We don't start a transaction since in general we always want to move to
      // the desired state, and if we fail in flight it's probably a bug that
      // needs to be fixed.
      
      foreach($diffSql as $sql) {
        if($this->io) $this->io->out($sql);
        
        if($this->conn->driver == 'Cake\Database\Driver\Postgres'
           && preg_match("/^DROP SEQUENCE [a-z]*_id_seq/", $sql)) {
          // Remove the DROP SEQUENCE statements in $fromSql because they're Postgres automagic
          // being misinterpreted. (Note toSaveSql might mask this now.)
          // XXX Maybe debug and file a PR to not emit DROP SEQUENCE on PG for autoincrementesque fields?
          if($this->io) $io->out("Skipping sequence drop");
        } else {
          if(!$diffOnly) {
            $stmt = $this->conn->executeQuery($sql);
            // $stmt just returns the query string so we don't bother examining it
          }
        }
      }

      $this->alog('debug', $diffSql);
    }
    catch(\Exception $e) {
      if($this->io) $this->io->out($e->getMessage());
      else throw new \RuntimeException($e->getMessage());
    }
    
    // We might run bin/cake schema_cache clear or
    // bin/cake schema_cache build --connection default
    // but so far we don't have an example indicating it's needed.
  }

}