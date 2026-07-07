<?php
/**
 * COmanage Registry Case Mixers Table
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
 * @package       registry-plugins
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace CoreNormalizer\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Model\Entity\Normalization;
use \Tamtamchik\NameCase\Formatter;
use function \Tamtamchik\NameCase\str_name_case;

class CaseMixersTable extends Table {
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.3.0
   * @param  array  $config Configuration options passed to constructor
   */

  public function initialize(array $config): void {
    parent::initialize($config);

    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);

    // Define associations
    $this->belongsTo('Normalizations');

    $this->setDisplayField('id');

    $this->setPrimaryLink('normalization_id');
    $this->setRequiresCO(true);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false, // Normalization configs are sync'd automatically
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Set up a new (default) configuration for use with $normalization.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  Normalization  $normalization  New Normalization entity to attach the configuration to
   */

  public function createDefaultConfig(Normalization $normalization) {
    $n = $this->newEntity([
      'normalization_id'  => $normalization->id,
      'mix_addresses'     => true,
      'mix_names'         => false,
      'mix_person_roles'  => true
    ]);

    $this->saveOrFail($n);
  }

  /**
   * Normalize data.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  Normalization  $normalization  Normalization configuration
   * @param  Table          $Table          Table for $data
   * @param  ArrayObject    $data           Data to be normalized
   */

  public function normalize(
    \App\Model\Entity\Normalization $normalization,
    \Cake\ORM\Table $Table,
    \ArrayObject $data
  ) {
    // What fields will we maybe mix case for?
    $supportedFields = $Table->getNormalizableFields('CoreNormalizer.CaseMixers');

    if(!empty($supportedFields)) {
      $subjectModel = $Table->getAlias();
      $cfgvar = "mix_" . $Table->getTable();

      // If there is no $cfgvar defined in our configuration, we're probably being called
      // by (eg) a plugin model, so assume enabled.
      $enabled = !isset($normalization->case_mixer->$cfgvar)
               || $normalization->case_mixer->$cfgvar;

      if($enabled) {
        foreach($supportedFields as $field) {
          // For addresses, if $field is state or country and the length is 3 or shorter,
          // convert to upper case, since we're almost certainly dealing with an abbreviation.
          // (As of this writing, there do not appear to be any countries or US states
          // with English names of less than 4 characters. This may not hold true for
          // non-US states.)

          if(($field == 'state' || $field == 'country')
            && strlen($data[$field]) <= 3) {
            if(function_exists('mb_strtoupper')) {
              $data[$field] = mb_strtoupper($data[$field]);
            } else {
              $data[$field] = strtoupper($data[$field]);
            }
          } elseif($field == 'family') {
            // https://github.com/tamtamchik/namecase
            $data[$field] = str_name_case($data[$field]);
          } else {
            if(function_exists('mb_convert_case')) {
              $data[$field] = mb_convert_case($data[$field], MB_CASE_TITLE);
            } else {
              $data[$field] = ucwords(strtolower($data[$field]));
            }
          }
        }
      }
    }
  }

  /**
   * Set validation rules.
   *
   * @since  COmanage Registry v5.3.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */

  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('normalization_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('normalization_id');

    $validator->add('mix_addresses', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('mix_addresses');

    $validator->add('mix_names', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('mix_names');

    $validator->add('mix_person_roles', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('mix_person_roles');

    return $validator;
  }
}
