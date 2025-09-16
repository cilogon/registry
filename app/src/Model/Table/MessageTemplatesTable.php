<?php
/**
 * COmanage Registry Message Template Table
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

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Lib\Enum\MessageFormatEnum;
use App\Lib\Enum\MessageTemplateContextEnum;
use App\Lib\Enum\SuspendableStatusEnum;
use \App\Model\Entity\Notification;
use \App\Model\Entity\Person;
use \App\Model\Entity\Petition;

class MessageTemplatesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\SearchFilterTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

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
    $this->belongsTo('Cos');
    $this->hasMany('EnrollmentFlowSteps');
    $this->hasMany('EnrollmentFlowStepNotifications')
         ->setForeignKey('notification_message_template_id');
    $this->hasMany('Notifications');
    
    $this->setDisplayField('description');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);

    $this->setAutoViewVars([
      'contexts' => [
        'type'  => 'enum',
        'class' => 'MessageTemplateContextEnum'
      ],
      'formats' => [
        'type'   => 'enum',
        'class'  => 'MessageFormatEnum'
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'SuspendableStatusEnum'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>     ['platformAdmin', 'coAdmin'],
        'edit' =>       ['platformAdmin', 'coAdmin'],
        'view' =>       ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
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

    $validator->add('co_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('co_id');

    $this->registerStringValidation($validator, $schema, 'description', true);

    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $validator->add('context', [
      'content' => ['rule' => ['inList', MessageTemplateContextEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('context');

    $validator->add('format', [
      'content' => ['rule' => ['inList', MessageFormatEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('format');

    $this->registerStringValidation($validator, $schema, 'subject', true);

 // XXX body_text/body_html required should be dependent on format, maybe
 //     implement as an AR instead?
    $validator->add('body_text', [
      'filter'  => ['rule'     => ['validateInput'],
                    'provider' => 'table']
    ]);
    $validator->allowEmptyString('body_text');

    $validator->add('body_html', [
      'filter'  => ['rule'     => ['validateInput',['type' => 'html']],
                    'provider' => 'table']
    ]);
    $validator->allowEmptyString('body_html');

    $this->registerStringValidation($validator, $schema, 'cc', false);

    $this->registerStringValidation($validator, $schema, 'bcc', false);
    
    $this->registerStringValidation($validator, $schema, 'reply_to', false);

    return $validator; 
  }
}