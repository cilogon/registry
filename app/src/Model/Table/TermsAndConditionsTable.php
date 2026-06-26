<?php
/**
 * COmanage Registry Terms and Conditions Table
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Model\Table;

use Cake\I18n\DateTime;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Validation\Validator;
use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Enum\TAndCStatusEnum;

class TermsAndConditionsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.2.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Orderable');
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('Cos');
    $this->belongsTo('Cous');
    $this->belongsTo('MostlyStaticPages');
    $this->hasMany('TAndCAgreements');
    
    $this->setDisplayField('description');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['agree', 'proxy', 'revoke']);
    $this->setAllowLookupRelatedPrimaryLink(['status' => ['person_id']]);
    $this->setAllowUnkeyedPrimaryLink(['review']);

    $this->setIndexContains([
      'MostlyStaticPages'
    ]);

    $this->setAutoViewVars([
      'cous' => [
        'type' => 'select',
        'model' => 'Cous'
      ],
      'mostlyStaticPages' => [
        'type' => 'select',
        'model' => 'MostlyStaticPages',
        'where' => ['context' => \App\Lib\Enum\PageContextEnum::TermsAndConditions]
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'SuspendableStatusEnum'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'agree' =>    ['coMember'],
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        // We specifically exclude platform admins from proxying because
        // they may not be registered as People in the CO, and we won't be
        // able to record the actor foreign key for audit purposes.
        'proxy' =>    ['coAdmin'],
        'revoke' =>   ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin'],
        'review' =>   ['coMember'],
        'status' =>   ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.2.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    // This isn't strictly an Application Rule, but either a URL or a Mostly Static Page
    // must be specified, and it's easier to enforce that here than in validation.
    $rules->add([$this, 'ruleSpecifyDocument'],
                'specifyDocument',
                ['errorField' => 'url']);
        
    return $rules;
  }

  /**
   * Application Rule to determine if a T&C document was specified.
   *
   * @since  COmanage Registyr v5.2.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleSpecifyDocument($entity, $options) {
    if((empty($entity->url) && empty($entity->mostly_static_page_id))
       || (!empty($entity->url) && !empty($entity->mostly_static_page_id))) {
      return __d('error' , 'TermsAndConditions.document.one');
    }

    return true;
  }

  /**
   * Obtain T&C Agreement status for the specified Person.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int  $personId   Person ID
   * @return array
   */

  public function status(int $personId): array {
    $ret = [];

    // Pull the Person and their roles
    $People = TableRegistry::getTableLocator()->get('People');

    $person = $People->get($personId, contain: ['PersonRoles']);

    // Extract the COUs for use below
    $couIds = Hash::extract($person->person_roles, "{n}.cou_id");

    // Pull active T&C for the CO/COU $copersonid is a member of. This will NOT include
    // any outdated T&C, we'll pull those separately, below.

    $whereClause = [
      'co_id' => $person->co_id,
      'status' => SuspendableStatusEnum::Active
    ];

    if(!empty($couIds)) {
      $whereClause['OR'] = [
        'cou_id IS NULL',
        'cou_id IN' => array_values($couIds)
      ];
    } else {
      $whereClause[] = 'cou_id IS NULL';
    }

    $tandc = $this->find()
                  ->where($whereClause)
                  ->order('ordr ASC')
                  ->all();
    
    if(!empty($tandc)) {
      // Pull all the Agreements for $personId at once, we'll walk through them as needed.
      // Agreements always track the version of the T&C in effect when the Agreement was
      // recorded, regardless of the agree_to_updates setting. We'll walk the Agreements
      // below in accordance with the configuration.

      $agreements = $this->TAndCAgreements->find()
                                          ->where(['person_id' => $personId])
                                          ->order('agreement_time DESC')
                                          // This contain will pull the version of the T&C
                                          // that were in effect when the Agreement was made,
                                          // even if it is now outdated
                                          ->contain([
                                            'TermsAndConditions' => [
                                              // Cake appears to incorrectly inflects the 
                                              // foreign key for the contain
                                              'foreignKey' => 'terms_and_conditions_id',
                                            ]])
                                          ->all();

      // Walk through each T&C and merge in any existing agreements. There's probably
      // a more optimal way to handle this, but typically there will only be a handful
      // of agreements. We'll inject a status value, which is not part of the physical
      // data model, but by doing so here we make it easier for the invoking code to
      // see what's going on for each T&C.

      foreach($tandc as $t) {
        $r = [
          // Default is Not Agreed until calculated otherwise
          'status' => TAndCStatusEnum::NotAgreed,
          // The current T&C
          'tandc' => $t,
          // The agreement we found (if any)
          'agreement' => null,
          // If agreement was to outdated T&C, the outdated T&C
          'oldtandc' => null
        ];

        foreach($agreements as $a) {
          // We can have more than one Agreement to the same T&C (subsequent Agreements
          // to address Expired or Outdated Agreements won't delete the older ones), but
          // since we order by agreement_time DESC we should always get the newest one first.

          if($a->terms_and_conditions_id == $t->id) {
            // Agreement is to the current T&C
            $r['agreement'] = $a;

            // Calculate status
            $r['status'] = TAndCStatusEnum::Agreed;

            if(!empty($t->agreement_duration)) {
              // Check to see if the agreement time is older than the policy allows

              $agreetime = new DateTime($a->agreement_time);
              $nowtime = new DateTime();

              $timediff = $agreetime->diff($nowtime);

              if($timediff->days > $t->agreement_duration) {
                $r['status'] = TAndCStatusEnum::Expired;
              }
            }

            break;
          // Because of the Cake inflection bug in the find(), the related model
          // is available via the incorrect property name
          } elseif($a->terms_and_condition->terms_and_conditions_id == $t->id) {
            // Agreement is to a previous version of the current T&C. Whether this is
            // sufficient or not depends on the configuration on the _current_ T&C.

            $r['agreement'] = $a;
            $r['oldtandc'] = $a->terms_and_conditions;

            if($t->agree_to_updates) {
              // This Agreement is _not_ sufficient
              $r['status'] = TAndCStatusEnum::Outdated;
            } else {
              // Agreement is to a previous version of the current T&C, which is sufficient
              $r['status'] = TAndCStatusEnum::Agreed;
            }

            break;
          }
        }
       
        $ret[] = $r;
      }
    }

    return $ret;
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

    $validator->add('co_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('co_id');

    $this->registerStringValidation($validator, $schema, 'description', true);

    // One of url or mostly_static_page_id is required, we enforce this via application rules
    $this->registerStringValidation($validator, $schema, 'url', false);

    $validator->add('mostly_static_page_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('mostly_static_page_id');

    $validator->add('cou_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('cou_id');

    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $validator->add('ordr', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('ordr');

    $validator->add('agreement_duration', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('agreement_duration');

    $validator->add('agree_to_updates', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('agree_to_updates');

    return $validator; 
  }
}