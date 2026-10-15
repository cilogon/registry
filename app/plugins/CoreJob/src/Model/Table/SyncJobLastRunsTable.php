<?php
/**
 * COmanage Registry Sync Job Last Runs Table
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

namespace CoreJob\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class SyncJobLastRunsTable extends Table {
  use \App\Lib\Traits\CoLinkTrait;
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

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Artifact);

    // Define associations
    $this->belongsTo('ExternalIdentitySources');
    $this->belongsTo('Jobs');

    $this->setPrimaryLink('external_identity_source_id');
    $this->setRequiresCO(true);
  }

  /**
   * Set validation rules.
   *
   * @since  COmanage Registry v5.3.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */

  public function validationDefault(Validator $validator): Validator {
    $validator->add('external_identity_source_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('external_identity_source_id');

    $validator->add('job_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('job_id');

    $validator->add('start_time', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('start_time');

    return $validator;
  }
}
