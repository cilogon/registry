<?php
/**
 * COmanage Registry Punctuation Removers Table
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

class PunctuationRemoversTable extends Table {
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
      'normalization_id' => $normalization->id
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
    \ArrayObject $data) {

    // What fields will we maybe remove punctuation from?
    $supportedFields = $Table->getNormalizableFields('CoreNormalizer.PunctuationRemovers');

    if(!empty($supportedFields)) {
      foreach($supportedFields as $field) {
        // Following E.123 format, we only use spaces in telephone numbers
        // (the + and extension label get added by _getFormattedNumber at rendering time)

        $data[$field] = preg_replace("/[^[:alnum:]]+/", " ", $data[$field]);
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

    return $validator;
  }
}
