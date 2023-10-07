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

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use \App\Model\Entity\ExternalIdentity;
use \FileConnector\Lib\Enum\FileSourceFormatEnum;

class FileSourcesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  // Cache of the field configuration
  protected $fieldCfg = null;

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
        'delete' =>   ['platformAdmin', 'coAdmin'],
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

// XXX CFM-117 should we also check that the archive dir, if specified, is writeable?

    return $rules;
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
      throw new \RuntimeException(__d('file_connector', 'error.filename.readable', $file_source->filename));
    }

    // The first line is our configuration
    $cfg = fgetcsv($handle);

    fclose($handle);

    if(empty($cfg)) {
      throw new \RuntimeException(__d('error.header'));
    }

    foreach($cfg as $i => $label) {
      // Labels are of the forms described in the switch statement.
      // Parse them out into the fieldcfg array.

      $bits = explode('.', $label, 5);

      switch(count($bits)) {
        case 1:
          // SORID (special case)
          $this->fieldCfg[ $bits[0] ] = $i;
          break;
        case 2:
          // external_identity.field
          // ad_hoc_attributes.tag (attached to EI)
          // related_model.field (not currently used)
          $this->fieldCfg[ $bits[0] ][ $bits[1] ] = $i;
          break;
        case 3:
          // related_models.type.field
          // external_identity_roles.#.field (special case)
          // Note we _no longer_ flip the order model/type/field
          // (this is inverted from CSV v2)
          // and identifier+login is no longer supported
          if($bits[0] == 'external_identity_roles') {
            // Store based on role
            $this->fieldCfg[ $bits[0] ]['roles'][ $bits[1] ]['fields'][ $bits[2] ] = $i;
          } else {
            // Store based on type
            $this->fieldCfg[ $bits[0] ]['types'][ $bits[1] ][ $bits[2] ] = $i;
          }
          break;
        case 4:
          // external_identity_roles.#.ad_hoc_attributes.tag (attached to EIRole)
          $this->fieldCfg[ $bits[0] ]['roles'][ $bits[1] ]['related'][ $bits[2] ][ $bits[3] ] = $i;
          break;
        case 5:
          // external_identity_roles.#.related_models.type.field
          // Note these are keyed on an SOR Role ID
          $this->fieldCfg[ $bits[0] ]['roles'][ $bits[1] ]['related'][ $bits[2] ]['types'][ $bits[3] ][ $bits[4] ] = $i;
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
    // Build the External Identity as an array, then convert it to an entity.
    // Unlike v4, backends need to insert the SORID (for consistency with the role ID)
    $eidata = [ 'source_key' => $result[ $this->fieldCfg['SORID'] ] ];

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
    }

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
   * Application Rule to determine if the current entity is a readable file.
   *
   * @param   Entity  $entity   Entity to be validated
   * @param   array   $options  Application rule options
   *
   * @return string|bool true if the Rule check passes, false otherwise
   * @since  COmanage Registry v5.0.0
   */

  public function ruleIsFileReadable($entity, array $options): string|bool {
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

      $match = array_search(strtolower($searchAttrs['q']), array_map('strtolower', $data));

      if($match !== false) {
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

    $validator->add('threshold_warn', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->add('threshold_warn', [
      'range'   => ['rule' => 'range', 0, 100]
    ]);
    $validator->allowEmptyString('threshold_warn');

    $validator->add('threshold_override', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('threshold_override');
    
    return $validator; 
  }
}