<?php
/**
 * COmanage Registry File Sources Table
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

namespace FileConnector\Model\Table;

use App\Model\Entity\ExternalIdentitySource;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use \App\Model\Entity\ExternalIdentity;
use \FileConnector\Lib\Enum\FileSourceFormatEnum;

class FileSourcesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  // Cache of the field configuration
  protected $fieldCfg = null;

  // Cache of archive file paths
  protected $archive1 = null;
  protected $archive2 = null;

  // Whether postRunTasks should rotate the archive
  protected $rotate = false;

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
    
    $this->setDisplayField('filename');
    
    $this->setPrimaryLink(['external_identity_source_id']);
    $this->setRequiresCO(true);
    
    $this->setAutoViewVars([
      'formats' => [
        'type' => 'enum',
        'class' => 'FileConnector.FileSourceFormatEnum'
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
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */

  public function buildRules(RulesChecker $rules): RulesChecker {
    // The requested file must exist and be readable.

    $rules->add([$this, 'ruleIsFileReadable'],
                'isFileReadable',
                ['errorField' => 'filename']);

    $rules->add([$this, 'ruleIsArchiveWriteable'],
                'isArchiveWriteable',
                ['errorField' => 'archivedir']);

    return $rules;
  }

  /**
   * Obtain the set of changed records from the source file.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  ExternalIdentitySource $source     External Identity Source
   * @param  int                    $lastStart  Timestamp of last run
   * @param  int                    $curStart   Timestamp of current run
   * @return array|bool                         An array of changed source keys, or false
   * @throws RuntimeException
   */

  public function getChangeList(
    \App\Model\Entity\ExternalIdentitySource $source,
    int $lastStart, // timestamp of last run
    int $curStart   // timestamp of current run
  ): array|bool {
    $changeList = $this->processChangeList($source, $lastStart, $curStart);

    return ($changeList == false) ? false : $changeList['changeList'];
  }

  /**
   * Obtain the full set of records from the source database.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  ExternalIdentitySource $source     External Identity Source
   * @return array                              An array of source keys
   */

  public function inventory(
    \App\Model\Entity\ExternalIdentitySource $source
  ): array {
    $ret = [];

    $handle = fopen($source->file_source->filename, "r");

    if(!$handle) {
      throw new \RuntimeException(__d('file_connector', 'error.filename.readable', [$source->file_source->filename]));
    }

    // The first line of a CSV v3 file is our configuration
    fgetcsv($handle);

    while(($data = fgetcsv($handle)) !== false) {
      // The source key is always the first field in each line, make sure it is not empty

      if(!empty($data[0]) && !ctype_space($data[0])) {
        $ret[] = $data[0];
      }
    }

    fclose($handle);

    // It's not clear we really need to sort the array, but why not...
    sort($ret);
    
    return $ret;
  }

  /**
   * Perform checks before a Sync Job proceeds.
   * 
   * @since  COmanage Registry v5.2.0
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
    // (by percent).

    // By default, we'll rotate the archive files in postRunTasks. (If we throw an
    // exception, that hook won't be called.)

    $this->rotate = true;

    if(!empty($source->file_source->threshold_check)
       && $source->file_source->threshold_check > 0) {
      // threshold_check requires archive directories since we can't otherwise
      // efficiently calculate diffs.

      if(empty($source->file_source->archivedir)) {
        $this->llog('debug', 'Threshold Check for ' . $source->description . ' is configured but no Archive Directory is available, ignoring');
        throw new \RuntimeException(__d('file_connector', 'error.FileSource.threshold.config'));
      }

      // Check the number of changed records vs warning threshold. Note this
      // check (correctly) does not run the first time a file is processed
      // since there will be no archive file to compare against.

      if($source->file_source->threshold_override) {
        // Ignore thresholds, but unset this configuration for our next run

        $source->file_source->threshold_override = false;
        $this->saveOrFail($source->file_source, ['associated' => false]);
        
        $this->llog('trace', 'Threshold Check for ' . $source->description . ' is overridden, ignoring this time only');
      } else {
        $info = $this->processChangeList($source, $lastStart, $curStart);
        
        if($info['knownCount'] > 0) {
          $changed = count($info['changeList']) + $info['newCount'];
          $pct = floor(($changed * 100) / $info['knownCount']);

          if($pct > $source->file_source->threshold_check) {
            $this->llog('trace', 'Threshold Check for ' . $source->description . ' exceeded, stopping processing (changed=' . $changed . ', known=' . $info['knownCount'] . ', percent=' . $pct . ')');

            throw new \RuntimeException(__d('file_connector', 'error.FileSource.threshold', [
              $changed, $info['knownCount'], $pct, $source->file_source->threshold_check
            ]));
          }
        }
        // else no previous records, so treat as all new

        if(empty($info['changeList']
           && $info['newCount'] == 0)
           && $info['knownCount'] > 0) {
          // We don't want to rotate the Archive files if there were no changed records
          // (since nothing happened).

          $this->rotate = false;
        }
      }
    }
  }

  /**
   * Obtain the set of changed records from the source file.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  ExternalIdentitySource $source     External Identity Source
   * @param  int                    $lastStart  Timestamp of last run
   * @param  int                    $curStart   Timestamp of current run
   * @return array|bool                         An array of
   *                                              changelist: Changed record Source Keys
   *                                              newCount: Count of new records
   *                                              knownCount: Count of known records
   *                                            or false
   * @throws RuntimeException
   */

  protected function processChangeList(
    \App\Model\Entity\ExternalIdentitySource $source,
    int $lastStart, // timestamp of last run
    int $curStart   // timestamp of current run
  ): array|bool {
    if(empty($source->file_source->archivedir)) {
      // If there is no archivedir we don't support changelist calculation
      return false;
    }

    $ret = [];
    $knownCount = 0;
    $newCount = 0;

    $infile = $source->file_source->filename;
    $basename = basename($infile);
    $this->archive1 = $source->file_source->archivedir . DS . $basename . ".1";
    $this->archive2 = $source->file_source->archivedir . DS . $basename . ".2";

    // We could either read the files simultaneously in order (lower memory requirement),
    // or read one and hash it (can read records out of sequence). For now we'll take
    // the second approach.

    if(is_readable($this->archive1)) {
      // Start by creating a set of previously known records.
      $knownRecords = [];

      $handle = fopen($this->archive1, "r");

      if(!$handle) {
        throw new \RuntimeException(__d('file_connector', 'error.filename.readable', [$this->archive1]));
      }

      // Ignore the header line
      fgetcsv($handle);

      while(($data = fgetcsv($handle)) !== false) {
        // Implode the record back together for string comparison purposes.
        // This may not be the same as the original line due to quotes, etc.
        // $data[0] is the SORID
        $knownRecords[ $data[0] ] = implode(',', $data);
      }

      $knownCount = count($knownRecords);

      fclose($handle);

      // Now read the new file and look for changes.
      $handle = fopen($infile, "r");

      if(!$handle) {
        throw new \RuntimeException(__d('file_connector', 'error.filename.readable', [$infile]));
      }

      // Ignore the header line
      fgetcsv($handle);

      while(($data = fgetcsv($handle)) !== false) {
        // $data[0] is the SORID
        if(array_key_exists($data[0], $knownRecords)) {
          $newData = implode(',', $data);

          if($newData != $knownRecords[ $data[0] ]) {
            // This record changed, push the SORID onto the change list
            $ret[] = $data[0];
          }

          // Unset the key so we can see which records were deleted.
          unset($knownRecords[ $data[0] ]);
        } else {
          // This is a new record (ie: in $infile, not in $archive1),
          // so we ignore it, except to count it.
          $newCount++;
        }
      }

      fclose($handle);

      // Finally, any remaining keys in $knownRecords are delete operations.
      if(!empty($knownRecords)) {
        $ret = array_merge($ret, array_keys($knownRecords));
      }
    } else {
      // If there is no archive file, we've either never run at all, or the admin
      // updated the configuration and we have no idea what changed. In either case
      // we'll report all records as new, which will cause them all to be
      // (re)processed. In the latter case, admins can avoid this by manually creating
      // the .1 file before updating the configuration.

      $ret = $this->inventory($source);
      $newCount = count($ret);
    }

    return [
      'changeList'  => $ret,
      'newCount'    => $newCount,
      'knownCount'  => $knownCount
    ];
  }

    /**
     * Perform tasks following a Sync Job.
     *
     * @param ExternalIdentitySource $source External Identity Source
     * @since  COmanage Registry v5.2.0
     */

  public function postRunTasks(
    \App\Model\Entity\ExternalIdentitySource $source
  ): void {
    // Update the archive file. updateCache() is called after processing is complete,
    // and only if at least one record changed. It's possible an irregular exit will
    // prevent the cache from being updated, in that case we'll just end up reprocessing
    // some records, which should effectively be a no-op. Historically, we kept two backup
    // copies in case something went wrong, we still do so here, though it's less critical now.

    if(!$this->rotate) {
      $this->llog('trace', 'Not rotating archive files due to no changes');
      return;
    }

    if($this->archive1 !== null && is_readable($this->archive1)) {
      $this->llog('trace', 'Copying ' . $this->archive1 . ' to ' . $this->archive2);

      if(!copy($this->archive1, $this->archive2)) {
        throw new \RuntimeException(__d('file_connector', 'error.FileSource.copy', [
          $this->archive1, $this->archive2
        ]));
      }
    }

    if(
      $source->file_source->filename !== null
      && $this->archive1 !== null
      && is_readable($source->file_source->filename)
    ) {
      $this->llog('trace', 'Copying ' . $source->file_source->filename . ' to ' . $this->archive1);

      if(!copy($source->file_source->filename, $this->archive1)) {
        throw new \RuntimeException(__d('file_connector', 'error.FileSource.copy', [
          $source->file_source->filename, $this->archive1
        ]));
      }
    }
  }

  /**
   * Obtain the file field configuration.
   *
   * @since  COmanage Registry v4.0.0
   * @return array Configuration array
   */

  protected function readFieldConfig(
    \FileConnector\Model\Entity\FileSource $filesource
  ): array {
    if($this->fieldCfg) {
      return $this->fieldCfg;
    }

    // The only supported format is CSV3, so we don't currently need to check
    // $this->pluginCfg['format']

    $this->fieldCfg = [];

    // The field configuration is described in the first line of the file
    $handle = fopen($filesource->filename, "r");

    if(!$handle) {
      throw new \RuntimeException(__d('file_connector', 'error.filename.readable', $filesource->filename));
    }

    // The first line is our configuration
    $cfg = fgetcsv($handle);

    fclose($handle);

    if(empty($cfg)) {
      throw new \RuntimeException(__d('error.header'));
    }

    // Calculate the CO for $filesource, which we'll need for field type validation
    $coId = $this->calculateCoForRecord($filesource);

    foreach($cfg as $i => $label) {
      // Labels are of the forms described in the switch statement.
      // Parse them out into the fieldcfg array. We also validate them as we parse them.

      $bits = explode('.', $label, 5);

      switch(count($bits)) {
        case 1:
          // SORID (special case)
          // While we're here check to make sure the field is as expected
          if($bits[0] != 'SORID') {
            throw new \RuntimeException(__d('file_connector', 'error.header.sorid'));
          }
          $this->fieldCfg[ $bits[0] ] = $i;
          break;
        case 2:
          // external_identity.field
          // ad_hoc_attributes.tag (attached to EI)
          // related_model.field (not currently used)
          if(!in_array($bits[0], ['ad_hoc_attributes', 'external_identity'])) {
            throw new \RuntimeException(__d('file_connector', 'error.header.invalid', [$label]));
          }

          if($bits[0] != 'ad_hoc_attributes' && !$this->validField($bits[0], $bits[1], $coId)) {
            throw new \RuntimeException(__d('file_connector', 'error.header.invalid', [$label]));
          }

          $this->fieldCfg[ $bits[0] ][ $bits[1] ] = $i;
          break;
        case 3:
          // related_models.field.type
          // external_identity_roles.#.field (special case)
          // Note the old v2 order model/type/field is inverted here
          // and identifier+login is no longer supported
          if($bits[0] == 'external_identity_roles') {
            // Store based on role

            if(!$this->validField($bits[0], $bits[2], $coId)) {
              throw new \RuntimeException(__d('file_connector', 'error.header.invalid', [$label]));
            }
            
            $this->fieldCfg[ $bits[0] ]['roles'][ $bits[1] ]['fields'][ $bits[2] ] = $i;
          } else {
            // Store based on type

            if(!$this->validField($bits[0], $bits[1], $coId, $bits[2])) {
              throw new \RuntimeException(__d('file_connector', 'error.header.invalid', [$label]));
            }
            
            $this->fieldCfg[ $bits[0] ]['types'][ $bits[2] ][ $bits[1] ] = $i;
          }
          break;
        case 4:
          // external_identity_roles.#.ad_hoc_attributes.tag (attached to EIRole)
          $this->fieldCfg[ $bits[0] ]['roles'][ $bits[1] ]['related'][ $bits[2] ][ $bits[3] ] = $i;
          break;
        case 5:
          // external_identity_roles.#.related_models.field.type
          // Note these are keyed on an SOR Role ID
          $this->fieldCfg[ $bits[0] ]['roles'][ $bits[1] ]['related'][ $bits[2] ]['types'][ $bits[4] ][ $bits[3] ] = $i;
          break;
      }
    }

    return $this->fieldCfg;
  }

  /**
   * Convert a record from the FileSource data to a record suitable for
   * construction of an Entity. readFieldConfig() must be called before
   * this function.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  array  $result   FileSource record
   * @return array            Entity record (in array format)
   */

  protected function resultToEntityData(array $result): array {
    // Build the External Identity as an array
    $eidata = [];

    // We copy whatever attributes the inbound file asserts for a given model,
    // leaving it to the validation rules to worry about correctness.

    // Start with ExternalIdentity attributes (case 2)
    if(!empty($this->fieldCfg['external_identity'])) {
      foreach($this->fieldCfg['external_identity'] as $attr => $col) {
        if(!empty($result[$col])) {
          // Note we don't appear to need to convert date_of_birth manually,
          // it appears to correctly marshal to a DateTime object
          $eidata[$attr] = $result[$col];
        }
      }
    }

    // Walk through MVEAs (case 3)
    foreach([
      'addresses',
      'email_addresses',
      'identifiers',
      'names',
      'telephone_numbers',
      'urls'
    ] as $model) {
      if(!empty($this->fieldCfg[$model])) {
        foreach(array_keys($this->fieldCfg[$model]['types']) as $type) {
          $rdata = [];

          foreach($this->fieldCfg[$model]['types'][$type] as $attr => $col) {
            if(!empty($result[$col])) {
              $rdata[$attr] = $result[$col];
            }
          }

          if(!empty($rdata)) {
            // We found at least one field, so insert the type and the record
            $rdata['type'] = $type;

            $eidata[$model][] = $rdata;
          }
        }
      }
    }
/* External Identities no longer have Primary Names
    // Make sure we have a Primary Name
    $primaryNameSet = false;

    foreach($eidata['names'] as $n) {
      if(isset($n['primary_name']) && $n['primary_name']) {
        $primaryNameSet = true;
        break;
      }
    }

    if(!$primaryNameSet) {
      $eidata['names'][0]['primary_name'] = true;
    }*/

    // Process Ad Hoc Attributes (case 2)
    if(!empty($this->fieldCfg['ad_hoc_attributes'])) {
      foreach($this->fieldCfg['ad_hoc_attributes'] as $tag => $col) {
        if(!empty($result[$col])) {
          $eidata['ad_hoc_attributes'][] = [
            'tag'   => $tag,
            'value' => $result[$col]
          ];
        }
      }
    }

    // Handle External Identity Roles. This is similar to much of the above.
    if(!empty($this->fieldCfg['external_identity_roles'])) {
      foreach($this->fieldCfg['external_identity_roles']['roles'] as $roleId => $role) {
        $eirdata = [ 'role_key' => $roleId ];

        // Start with the EIR fields
        foreach($role['fields'] as $attr => $col) {
          if(!empty($result[$col])) {
            $eirdata[$attr] = $result[$col];
          }
        }

        // Next add the related models (case 5)

        foreach([
          'addresses',
          'email_addresses',
          'telephone_numbers',
          'urls'
        ] as $model) {
          if(!empty($role['related'][$model])) {
            foreach(array_keys($role['related'][$model]['types']) as $type) {
              $rdata = [];

              foreach($role['related'][$model]['types'][$type] as $attr => $col) {
                if(!empty($result[$col])) {
                  $rdata[$attr] = $result[$col];
                }
              }

              if(!empty($rdata)) {
                // We found at least one field, so insert the type and the record
                $rdata['type'] = $type;

                $eirdata[$model][] = $rdata;
              }
            }
          }
        }

        // Finally process any Ad Hoc Attributes (case 4)
        if(!empty($role['related']['ad_hoc_attributes'])) {
          foreach($role['related']['ad_hoc_attributes'] as $tag => $col) {
            if(!empty($result[$col])) {
              $eirdata['ad_hoc_attributes'][] = [
                'tag'   => $tag,
                'value' => $result[$col]
              ];
            }
          }
        }

        $eidata['external_identity_roles'][] = $eirdata;
      }
    }

    // XXX we're back to returning arrays rather than entities here because
    // the validation rules get built even though validate = false
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
    // Read the field configuration (for resultToEntity)
    $this->readFieldConfig($source->file_source);

    $ret = [
      'source_key' => $source_key
    ];

    // In v4 we did a field by field search, but v5 is free form.

    $handle = fopen($source->file_source->filename, "r");

    if(!$handle) {
      throw new \RuntimeException(__d('file_connector', 'error.filename.readable', [$source->file_source->filename]));
    }

    // We simply walk through the file until we find the matching record.
    // If there is more than one record, we'll return the first one we find.

    // The first line of a CSV v3 file is our configuration
    fgetcsv($handle);

    // Set null defaults in case we don't find a matching record
    $ret['source_record'] = null;
    $ret['entity_data'] = null;

    while(($data = fgetcsv($handle)) !== false) {
      if($data[0] == $source_key) {
        // This is our record

        $ret['source_record'] = json_encode($data);
        $ret['entity_data'] = $this->resultToEntityData($data);

        break;
      }
    }

    fclose($handle);

    if(!isset($ret['source_record'])) {
      // We didn't find a record
      throw new \InvalidArgumentException(__d('error', 'notfound', [$source_key]));
    }

    return $ret;
  }

  /**
   * Application Rule to determine if the current entity has a writeable archive directory.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Entity  $entity   Entity to be validated
   * @param  array   $options  Application rule options
   * @return string|bool       true if the Rule check passes, false otherwise
   */

  public function ruleIsArchiveWriteable($entity, array $options): string|bool {
    // Archive Directory is optional, so we only complain if it's set but not writeable

    if(!empty($entity->archivedir)) {
      // We also check if the archive directory is absolute or relative. This is partly to give a
      // more helpful message, and partly because if a deployer configures the directory into
      // $webroot it'll be readable by the web server but not the command line.

      if(mb_substr($entity->archivedir, 0, 1) != '/') {
        return __d('file_connector', 'error.filename.absolute', [$entity->archivedir]);
      }

      if(!is_writable($entity->archivedir)) {
        return __d('file_connector', 'error.filename.writable', [$entity->archivedir]);
      }
    }

    return true;
  }

  /**
   * Application Rule to determine if the current entity has a readable file.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity   Entity to be validated
   * @param  array   $options  Application rule options
   * @return string|bool       true if the Rule check passes, false otherwise
   */

  public function ruleIsFileReadable($entity, array $options): string|bool {
    // We also check if the file path is absolute or relative. This is partly to give a
    // more helpful message, and partly because if a deployer drops the file into $webroot
    // it'll be readable by the web server but not the command line.
    
    if(mb_substr($entity->filename, 0, 1) != '/') {
      return __d('file_connector', 'error.filename.absolute', [$entity->filename]);
    }

    if(!is_readable($entity->filename)) {
      return __d('file_connector', 'error.filename.readable', [$entity->filename]);
    }

    return true;
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
    // Read the field configuration (for resultToEntity)
    $this->readFieldConfig($source->file_source);

    $ret = [];

    // In v4 we did a field by field search, but v5 is free form.

    $handle = fopen($source->file_source->filename, "r");

    if(!$handle) {
      throw new \RuntimeException(__d('file_connector', 'error.filename.readable', [$source->file_source->filename]));
    }

    // The first line of a CSV v3 file is our configuration
    fgetcsv($handle);

    while(($data = fgetcsv($handle)) !== false) {
      // strtolower, previous behavior was full string only so dupe that

      $match = collection($data)
        ->map(fn($value, $key) => strtolower($value ?? ''))
        ->filter(fn($item, $key) => strtolower($searchAttrs['q']) === $item);

      if($match->count() > 0) {
        // $match will be the CSV column that matched, but for now we ignore that
        // since we just need to know that the row matched somewhere. Note the first
        // column is always the SORID.

        $ret[ $data[0] ] = $this->resultToEntityData($data);
      }
    }

    fclose($handle);

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
   * Determine if $field is a valid field for $model.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  string $model  Model, in under_score format
   * @param  string $field  Field name
   * @param  int    $coId   Current CO ID (only used for Type validation)
   * @param  string $type   Field type, for MVEAs
   * @return bool           true if $field is a field of $model, false otherwise
   */

  protected function validField(
    string $model,
    string $field,
    int $coId,
    ?string $type=null
  ): bool {
    // As a first pass, we just check the schema for the field, which means metadata
    // such as revision or created will be accepted as valid. A better approach would
    // be to do something like TabelMetaTrait::filterMetadataFields, since we probably
    // don't want to accept metadata fields as valid.

    $tableName = Inflector::pluralize(Inflector::classify($model));

    $Table = TableRegistry::getTableLocator()->get($tableName);

    $schema = $Table->getSchema();

    // We need to handle the ExternalIdentityRoles.affiliation csv header specially.
    if($field == 'affiliation' && $tableName == 'ExternalIdentityRoles') {
      // We do not know the type. Because the type is part of each record. As a result we will return true
      // and the validation will fail later.
      $field = 'affiliation_type_id';
    }

    if(!$schema->hasColumn($field)) {
      return false;
    }

    if($type) {
      // We need to see if $type is a valid Type value. This will mostly be
      // $tableName.type (eg: Names.type), except for ExternalIdentityRoles

      try {
        $Types = TableRegistry::getTableLocator()->get("Types");

        // This will throw an exception if not valid
        $Types->getTypeId($coId, $tableName . ".type", $type);
      }
      catch(\Exception $e) {
        return false;
      }
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
    
    $this->registerStringValidation($validator, $schema, 'filename', true);

    $validator->add('format', [
      'content' => ['rule' => ['inList', FileSourceFormatEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('format');

    $this->registerStringValidation($validator, $schema, 'archivedir', false);

    $validator->add('threshold_check', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->add('threshold_check', [
      'range'   => ['rule' => 'range', 0, 100]
    ]);
    $validator->allowEmptyString('threshold_check');

    $validator->add('threshold_override', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('threshold_override');
    
    return $validator; 
  }
}