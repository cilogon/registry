<?php
/**
 * COmanage Registry Mostly Static Pages Table
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\PageContextEnum;
use App\Lib\Enum\SuspendableStatusEnum;

class MostlyStaticPagesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\UpsertTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.1.0
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
    $this->belongsTo('Themes');
    
    $this->hasMany('TermsAndConditions');
    
    $this->setDisplayField('title');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);

    $this->setAutoViewVars([
      'contexts' => [
        'type'  => 'enum',
        'class' => 'PageContextEnum'
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'SuspendableStatusEnum'
      ],
      'themes' => [
        'type' => 'select',
        'model' => 'Themes'
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
   * Add the default Mostly Static Pages.
   *
   * @since  COmanage Registry v5.1.0
   * @param  int    $coId      CO ID
   * @return bool              true on success
   * @throws PersistenceFailedException
   */

  public function addDefaults(int $coId) {
    // Any pages added here should also be added to MostlyStaticPage.php::isDefaultPage
    // if they should not be deleted
    $records = [
      [
        'co_id'       => $coId,
        'name'        => 'duplicate-landing',
        'title'       => __d('field', 'MostlyStaticPages.default.de.title'),
        'description' => __d('field', 'MostlyStaticPages.default.de.description'),
        'status'      => SuspendableStatusEnum::Active,
        'context'     => PageContextEnum::EnrollmentHandoff,
        'body'        => __d('field', 'MostlyStaticPages.default.de.body')
      ],
      [
        'co_id'       => $coId,
        'name'        => 'default-handoff',
        'title'       => __d('field', 'MostlyStaticPages.default.dh.title'),
        'description' => __d('field', 'MostlyStaticPages.default.dh.description'),
        'status'      => SuspendableStatusEnum::Active,
        'context'     => PageContextEnum::EnrollmentHandoff,
        'body'        => __d('field', 'MostlyStaticPages.default.dh.body')
      ],
      [
        'co_id'       => $coId,
        'name'        => 'error-landing',
        'title'       => __d('field', 'MostlyStaticPages.default.el.title'),
        'description' => __d('field', 'MostlyStaticPages.default.el.description'),
        'status'      => SuspendableStatusEnum::Active,
        'context'     => PageContextEnum::ErrorLanding,
        'body'        => __d('field', 'MostlyStaticPages.default.el.body')
      ],
      [
        'co_id'       => $coId,
        'name'        => 'mfa-required',
        'title'       => __d('field', 'MostlyStaticPages.default.mr.title'),
        'description' => __d('field', 'MostlyStaticPages.default.mr.description'),
        'status'      => SuspendableStatusEnum::Active,
        'context'     => PageContextEnum::ErrorLanding,
        'body'        => __d('field', 'MostlyStaticPages.default.mr.body')
      ],
      [
        'co_id'       => $coId,
        'name'        => 'petition-complete',
        'title'       => __d('field', 'MostlyStaticPages.default.pc.title'),
        'description' => __d('field', 'MostlyStaticPages.default.pc.description'),
        'status'      => SuspendableStatusEnum::Active,
        'context'     => PageContextEnum::EnrollmentHandoff,
        'body'        => __d('field', 'MostlyStaticPages.default.pc.body')
      ]
    ];

    foreach($records as $record) {
      $this->upsert($record, ['co_id' => $coId, 'name' => $record['name']]);
    }

    return true;
  }

  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.1.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */

  public function buildRules(RulesChecker $rules): RulesChecker {
    // AR-MostlyStaticPage-1 Two Mostly Static Pages within the same CO cannot share the same name
    $rules->add($rules->isUnique(['name', 'co_id'], __d('error', 'exists', [__d('field', 'MostlyStaticPages.name')])));

    // AR-MostlyStaticPage-3 Default Pages can not be deleted, or have their names, status, or
    // context changed
    $rules->addUpdate([$this, 'ruleModifiedDefaultPage'],
                       'modifiedDefaultPage',
                       ['errorField' => 'name']);
    
    $rules->addDelete([$this, 'ruleIsDefaultPage'],
                       'isDefaultPage',
                       ['errorField' => 'name']);

    return $rules;
  }

  /**
   * Application Rule to determine if the current entity is a default Page.
   *
   * @param   Entity  $entity   Entity to be validated
   * @param   array   $options  Application rule options
   *
   * @return string|bool true if the Rule check passes, false otherwise
   * @since  COmanage Registry v5.1.0
   */

  public function ruleIsDefaultPage($entity, array $options): string|bool {
    if($entity->isDefaultPage()) {
      return __d('error', 'MostlyStaticPages.default.delete');
    }

    return true;
  }

  /**
   * Application Rule to determine if a default Page has been modified.
   *
   * @param   Entity  $entity   Entity to be validated
   * @param   array   $options  Application rule options
   *
   * @return string|bool true if the Rule check passes, false otherwise
   * @since  COmanage Registry v5.1.0
   */

  public function ruleModifiedDefaultPage($entity, array $options): string|bool {
    if($entity->isDefaultPage()
       && ($entity->isDirty('name') || $entity->isDirty('status') || $entity->isDirty('context'))) {
      return __d('error', 'MostlyStaticPages.default.modify');
    }

    return true;
  }

  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('co_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('co_id');

    $this->registerStringValidation($validator, $schema, 'name', true);

    // AR-MostlyStaticPage-2 A Mostly Static Page name may consist only of lowercase alphanumeric
    // characters and dashes
    $validator->add('name', [
      'slugfilter' => [
        'rule' => ['custom', '/^[a-z0-9-]+$/'],
        'message' => __d('error', 'MostlyStaticPages.slug.invalid')
      ]
    ]);

    $this->registerStringValidation($validator, $schema, 'title', true);

    $this->registerStringValidation($validator, $schema, 'description', false);

    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $validator->add('context', [
      'content' => ['rule' => ['inList', PageContextEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('context');

    $validator->add('body', [
      'filter'  => ['rule'     => ['validateInput', ['type' => 'html']],
                    'provider' => 'table']
    ]);
    $validator->allowEmptyString('body');

    $validator->add('theme_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('theme_id');
    
    return $validator; 
  }
}