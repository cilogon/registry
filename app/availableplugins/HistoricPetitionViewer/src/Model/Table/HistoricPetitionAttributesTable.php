<?php
/**
 * COmanage Registry Historic Petition Attributes Table
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace HistoricPetitionViewer\Model\Table;

use App\Lib\Enum\TableTypeEnum;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class HistoricPetitionAttributesTable extends Table {
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \App\Lib\Traits\CoLinkTrait;


  public function initialize(array $config): void {
    parent::initialize($config);

    // Map model to shorter physical table name
    $this->setTable('petition_hist_attrs');
    $this->setPrimaryKey('id');
    $this->setDisplayField('id');

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->setTableType(TableTypeEnum::Artifact);

    // Define associations
    $this->belongsTo('Petitions');

    $this->setDisplayField('attribute');

    $this->setPrimaryLink('petition_id');
    $this->setRequiresCO(false);
  }

  /**
   * Set validation rules.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */

  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    // petition_id (required, integer)
    $validator->add('petition_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('petition_id');

    // attribute (required, string up to 128)
    $validator->add('attribute', [
      'size' => ['rule' => ['maxLength', 128]]
    ]);
    $validator->notEmptyString('attribute');

    // value (text, optional)
    $validator->allowEmptyString('value');

    // created/modified handled by Timestamp behavior; no explicit validation needed
    return $validator;
  }
}