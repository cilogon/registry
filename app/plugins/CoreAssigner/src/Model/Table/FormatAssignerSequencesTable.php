<?php
/**
 * COmanage Registry Format Assigner Sequences Table
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
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace CoreAssigner\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class FormatAssignerSequencesTable extends Table {
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */

  public function initialize(array $config): void {
    parent::initialize($config);

    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Timestamp');

    // This is sort of a hybrid of configuration and artifact...
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);

    // Define associations
    $this->belongsTo('CoreAssigner.FormatAssigners');

    $this->setDisplayField('affix');

    $this->setPrimaryLink('CoreAssigner.format_assigner_id');
    $this->setRequiresCO(true);
  }

  /**
   * Obtain the next sequence number for the specified identifier assignment.
   * NOTE: This method should be called from within a transaction
   *
   * @since  COmanage Registry v5.0.0
   * @param  int      $formatAssignerId Format Assigner ID
   * @param  string   $affix            Affix to obtain a sequence number for
   * @param  int      $start            Initial value to return if sequence not yet started
   * @return int                        Next sequence
   */
  
  public function next(
    int $formatAssignerId,
    string $affix,
    int $start
  ): int {
    // We're basically implementing sequences. We don't actually use sequences
    // because dynamically creating sequences is a recipe for platform dependent
    // coding.
    
    $newCount = 1;
    
    if($start && $start > -1) {
      $newCount = $start;
    }
    
    // Get the current value for this affix. We need to use FOR UPDATE in case
    // another process is trying to assign the same sequence number at the same time.
    
    $cur = $this->find()
                ->where([
                  'FormatAssignerSequences.format_assigner_id' => $formatAssignerId,
                  'FormatAssignerSequences.affix'              => $affix
                ])
                ->epilog('FOR UPDATE')
                ->first();

    if(!empty($cur)) {
      // Increment an existing counter

      $newCount = $cur->last + 1;
      
      $cur->last = $newCount;

      $this->save($cur);
    } else {
      // Start a new counter

      $seq = $this->newEntity([
        'format_assigner_id'  => $formatAssignerId,
        'affix'               => $affix,
        'start'               => $newCount
      ]);

      $this->save($seq);
    }

    return $newCount;
  }

  /**
   * Set validation rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */

  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('format_assigner_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('format_assigner_id');

    $this->registerStringValidation($validator, $schema, 'affix', true);

    $validator->add('last', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('last');

    return $validator;
  }
}
