<?php
/**
 * COmanage Registry Enrollment Attributes Table
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

namespace CoreEnroller\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\I18n\FrozenTime;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use \App\Lib\Enum\GroupTypeEnum;
use \App\Lib\Enum\SuspendableStatusEnum;

class EnrollmentAttributesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
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

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Orderable');
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);

    // Define associations
    $this->belongsTo('CoreEnroller.AttributeCollectors');
    $this->belongsTo('AttributeTypes')
      ->setClassName('Types')
      ->setForeignKey('attribute_type')
      ->setProperty('type');


    $this->hasMany('CoreEnroller.PetitionAttributes')
// XXX do we really want to allow deletion once the definition is in use?
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    
    $this->setDisplayField('label');

    $this->setPrimaryLink('CoreEnroller.attribute_collector_id');
    $this->setRequiresCO(true);
    $this->setRedirectGoal(action: 'add', goal: 'index');
    $this->setRedirectGoal(action: 'edit', goal: 'index');

    $this->setAutoViewVars([
      'addressRequiredFields' => [
        'type' => 'enum',
        'class' => 'RequiredAddressFieldsEnum'
      ],
      'addressGroupedFields' => [
        'type' => 'enum',
        'class' => 'GroupedAddressFieldsEnum'
      ],
      'addressTypes' => [
        'type' => 'type',
        'attribute' => 'Addresses.type'
      ],
      'attributes' => [
        'type'  => 'hash',
        'hash'  => $this->supportedAttributes('list')
      ],
      'attributeLanguages' => [
        'type' => 'enum',
        'class' => 'LanguageEnum'
      ],
      // We set attributeMveaParents to the maximal set of options so that the form
      // load on an edit will render correctly. Note this list must match the validation
      // rule, below.
      'attributeMveaParents' => [
        'type' => 'array',
        'array' => ['Person', 'PersonRole']
      ],
      'defaultValueAffiliationTypes' => [
        'type' => 'type',
        'attribute' => 'PersonRoles.affiliation_type'
      ],
      'defaultValueCous' => [
        'type'  => 'select',
        'model' => 'Cous'
      ],
      'defaultValueGroups' => [
        'type'  => 'select',
        'model' => 'Groups',
        // only writeable groups are selectable
        'where' => [
          'group_type IN' => [
            GroupTypeEnum::Admins,
            GroupTypeEnum::Owners,
            GroupTypeEnum::Standard
          ]
        ]
      ],
      'emailAddressTypes' => [
        'type' => 'type',
        'attribute' => 'EmailAddresses.type'
      ],
      'identifierTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ],
      'nameRequiredFields' => [
        'type' => 'enum',
        'class' => 'RequiredNameFieldsEnum'
      ],
      'nameTypes' => [
        'type' => 'type',
        'attribute' => 'Names.type'
      ],
      'pronounTypes' => [
        'type' => 'type',
        'attribute' => 'Pronouns.type'
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'SuspendableStatusEnum'
      ],
      'telephoneNumberTypes' => [
        'type' => 'type',
        'attribute' => 'TelephoneNumbers.type'
      ],
      'urlTypes' => [
        'type' => 'type',
        'attribute' => 'Urls.type'
      ],
      // Required for attribute collection
      'cosettings' => [
        'type' => 'auxiliary',
        'model' => 'CoSettings'
      ],
      'types' => [
        'type' => 'auxiliary',
        'model' => 'Types'
      ],
    ]);

    $this->setLayout([ 'index' => 'iframe',
                       'add' => 'iframe',
                       'edit' => 'iframe',
                       'view' => 'iframe',
                     ]);

    $this->setPermissions([
      'ui' => [
        // Actions that operate over an entity (ie: require an $id)
        'entity' => [
          'delete' =>   ['platformAdmin', 'coAdmin'],
          'edit' =>     ['platformAdmin', 'coAdmin'],
          'view' =>     ['platformAdmin', 'coAdmin']
        ],
        // Actions that operate over a table (ie: do not require an $id)
        'table' => [
          'add' =>      ['platformAdmin', 'coAdmin'],
          'index' =>    ['platformAdmin', 'coAdmin']
        ]
      ]
    ]);
  }

  /**
   * Callback before data is marshaled into an entity.
   *
   * @since  COmanage Registry v5.3.0
   * @param  EventInterface $event   beforeMarshal event
   * @param  ArrayObject    $data    Entity data
   * @param  ArrayObject    $options Callback options
   */

  public function beforeMarshal(EventInterface $event, \ArrayObject $data, \ArrayObject $options)
  {
    // valid_from uses default_value_datetime for its default. Clear any default_value
    // so it can't take precedence over the configured date when the petition form is rendered.
    if(($data['attribute'] ?? null) === 'valid_from') {
      $data['default_value'] = null;
    }
  }

  /**
   * Resolve a valid_from/valid_through Petition Attribute value into a database
   * datetime string. For valid_through, the Enrollment Attribute default_value
   * is an offset in days after finalization, applied when no value was provided
   * or when the field is not modifiable by the enrollee (read only or hidden).
   * The resulting date is set to the end of that day (23:59:59).
   *
   * @param  string               $attribute           Attribute name (valid_from or valid_through)
   * @param  mixed                $value               Petition Attribute value
   * @param  EntityInterface|null $enrollmentAttribute Enrollment Attribute configuration
   * @param  FrozenTime|null      $now                 Reference timestamp (defaults to current time)
   * @return string|null                               Datetime string, or null if no date applies
   * @since  COmanage Registry v5.3.0
   */

  public function resolveValidityDate(
    string $attribute,
    mixed $value,
    ?EntityInterface $enrollmentAttribute = null,
    ?FrozenTime $now = null
  ): ?string {
    $dateFormat = 'yyyy-MM-dd HH:mm:ss';
    $now ??= FrozenTime::now();

    $isEmpty = ($value === null || (is_string($value) && trim($value) === ''));

    // valid_through: determine days-after-finalization offset if configured
    $offsetDays = null;
    if($attribute === 'valid_through'
       && $enrollmentAttribute !== null
       && is_numeric($enrollmentAttribute->default_value ?? null)) {
      $offsetDays = (int)$enrollmentAttribute->default_value;
    }

    $isLocked = $enrollmentAttribute !== null
                && ((isset($enrollmentAttribute->modifiable) && !$enrollmentAttribute->modifiable)
                    || !empty($enrollmentAttribute->hidden));

    // valid_through: apply offset from finalization time when field is blank or locked (read-only/hidden),
    // expiring at the end of that day
    if($offsetDays !== null && ($isEmpty || $isLocked)) {
      return $now->addDays($offsetDays)->endOfDay()->i18nFormat($dateFormat);
    }

    // valid_from & valid_through: an empty value means no date applies (null, not "now")
    if($isEmpty) {
      return null;
    }

    // valid_from & valid_through: format explicit datetime value entered or provided
    if($value instanceof \DateTimeInterface) {
      return FrozenTime::instance($value)->i18nFormat($dateFormat);
    }

    return FrozenTime::parse((string)$value)->i18nFormat($dateFormat);
  }

  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.3.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  public function buildRules(RulesChecker $rules): RulesChecker {
    // AR-EnrollmentAttribute-1 Single-valued Person Role attributes may only be configured
    // once per Attribute Collector.
    $rules->add([$this, 'ruleSingleValuedAttributeUnique'],
                'singleValuedAttributeUnique',
                ['errorField' => 'attribute']);

    return $rules;
  }

  /**
   * Application Rule to ensure single-valued Person and Person Role attributes are unique per Attribute Collector.
   *
   * Only Person and PersonRole attributes are covered; MVEAs, Group and Petition
   * attributes are not checked. The rule applies per Attribute Collector, so a later
   * step may still modify a value collected by an earlier step.
   *
   * @since  COmanage Registry v5.3.0
   * @param  \Cake\Datasource\EntityInterface $entity
   * @param  array $options
   * @return bool|string
   */
  public function ruleSingleValuedAttributeUnique($entity, array $options): bool|string {
    if(empty($entity->attribute) || empty($entity->attribute_collector_id)) {
      return true;
    }

    $supported = $this->supportedAttributes();
    if(!empty($supported[$entity->attribute]['model']) && in_array($supported[$entity->attribute]['model'], ['PersonRole', 'Person'])) {
      $conditions = [
        'attribute_collector_id' => $entity->attribute_collector_id,
        'attribute' => $entity->attribute
      ];
      if(!empty($entity->id)) {
        $conditions['id !='] = $entity->id;
      }
      $exists = $this->find()->where($conditions)->first();
      if($exists) {
        return __d('core_enroller', 'error.EnrollmentAttributes.single_valued_exists', [$supported[$entity->attribute]['label'] ?? $entity->attribute]);
      }
    }

    return true;
  }

  /**
   * Obtain the set of supported attributes.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string   $format   How to return attributes ('full', 'list')
   * @return array              Supported attributes
   */

  public function supportedAttributes(string $format='full'): array {
    $attrs = [];

    // Single valued Person attributes
    $attrs['date_of_birth'] = [
      'label'     => __d('field', 'date_of_birth'),
      'model'     => 'Person',
      'fieldType' => 'date'
    ];

    // Single valued Person Role attributes

    $attrs['affiliation_type_id'] = [
      'label'     => __d('field', 'affiliation'),
      'model'     => 'PersonRole',
      'fieldType' => 'string'
    ];

    $attrs['cou_id'] = [
      'label'     => __d('controller', 'Cous', [1]),
      'model'     => 'PersonRole',
      'fieldType' => 'integer'
    ];

    $attrs['department'] = [
      'label'     => __d('field', 'department'),
      'model'     => 'PersonRole',
      'fieldType' => 'string'
    ];

    $attrs['manager_person_id'] = [
      'label'     => __d('field', 'manager'),
      'model'     => 'PersonRole',
      'fieldType' => 'integer'
    ];

    $attrs['organization'] = [
      'label'     => __d('field', 'organization'),
      'model'     => 'PersonRole',
      'fieldType' => 'string'
    ];

    $attrs['sponsor_person_id'] = [
      'label'     => __d('field', 'sponsor'),
      'model'     => 'PersonRole',
      'fieldType' => 'integer'
    ];

    $attrs['title'] = [
      'label'     => __d('field', 'title'),
      'model'     => 'PersonRole',
      'fieldType' => 'string'
    ];

    $attrs['valid_from'] = [
      'label'     => __d('field', 'valid_from'),
      'model'     => 'PersonRole',
      'fieldType' => 'datetime'
    ];

    $attrs['valid_through'] = [
      'label'     => __d('field', 'valid_through'),
      'model'     => 'PersonRole',
      'fieldType' => 'datetime'
    ];

    // MVEAs, which might attach to the Person or Person Role or both

    $attrs['address'] = [
      'label'       => __d('controller', 'Addresses', [1]),
      'mveaModel'   => 'Addresses',
      'mveaParents' => ['Person', 'PersonRole'],
      'fieldType'   => 'string',
      'autoViewVar' => 'addressTypes',
    ];

    $attrs['adHocAttribute'] = [
      'label'       => __d('controller', 'AdHocAttributes', [1]),
      'mveaModel'   => 'AdHocAttributes',
      'mveaParents' => ['Person', 'PersonRole'],
      'fieldType'   => 'string'
    ];

    $attrs['emailAddress'] = [
      'label'       => __d('controller', 'EmailAddresses', [1]),
      'mveaModel'   => 'EmailAddresses',
      'mveaParents' => ['Person'],
      'fieldType'   => 'string'
    ];

    $attrs['identifier'] = [
      'label'       => __d('controller', 'Identifiers', [1]),
      'mveaModel'   => 'Identifiers',
      'mveaParents' => ['Person'],
      'fieldType'   => 'string'
    ];

    $attrs['name'] = [
      'label'       => __d('controller', 'Names', [1]),
      'mveaModel'   => 'Names',
      'mveaParents' => ['Person'],
      'fieldType'   => 'string'
    ];

    $attrs['pronoun'] = [
      'label'       => __d('controller', 'Pronouns', [1]),
      'mveaModel'   => 'Pronouns',
      'mveaParents' => ['Person'],
      'fieldType'   => 'string'
    ];

    $attrs['telephoneNumber'] = [
      'label'       => __d('controller', 'TelephoneNumbers', [1]),
      'mveaModel'   => 'TelephoneNumbers',
      'mveaParents' => ['Person', 'PersonRole'],
      'fieldType'   => 'string'
    ];

    $attrs['url'] = [
      'label'       => __d('controller', 'Urls', [1]),
      'mveaModel'   => 'Urls',
      'mveaParents' => ['Person'],
      'fieldType'   => 'string'
    ];

    // Group memberships, as reflected by the Group ID for the membership to be created in

    $attrs['group_id'] = [
      // We name the attribute group_id because the value we need to track is the Group
      // to attach the membership to, but the label says Group Member because that'll be
      // more obvious
      'label'       => __d('controller', 'GroupMembers', [1]),
      'model'       => 'Group',
      'fieldType'   => 'integer'
    ];

    // Attributes that are only stored in the Petition

    $attrs['petition_text'] = [
      'label'     => __d('core_enroller', 'field.EnrollmentAttributes.petition_text'),
      'model'     => 'Petition',
      'fieldType' => 'string'
    ];

    $attrs['petition_textarea'] = [
      'label'     => __d('core_enroller', 'field.EnrollmentAttributes.petition_textarea'),
      'model'     => 'Petition',
      'fieldType' => 'text'
    ];
    
    switch($format) {
      case 'list':
        // Return as a hash of tag => label values
        $l = [];
        foreach(array_keys($attrs) as $k) {
          $l[$k] = $attrs[$k]['label'];
        }
        asort($l);
        return $l;
    }

    return $attrs;
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

    $validator->add('attribute_collector_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('attribute_collector_id');

    $this->registerStringValidation($validator, $schema, 'label', true);
    
    $this->registerStringValidation($validator, $schema, 'description', false);
    
    $validator->notEmptyString('attribute');

    $validator->add('attribute_type', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('attribute_type');
    
    $this->registerStringValidation($validator, $schema, 'attribute_language', false);

    $validator->add('attribute_mvea_parent', [
      // Note this list must match the autoViewVar, above
      'content' => ['rule' => ['inList', ['Person', 'PersonRole']]]
    ]);
    $validator->allowEmptyString('attribute_mvea_parent');

    $this->registerStringValidation($validator, $schema, 'attribute_tag', false);

    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $validator->add('required', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('required');

    $validator->add('ordr', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('ordr');

    $this->registerStringValidation($validator, $schema, 'default_value', false);

    // For valid_through, default_value is the number of days after finalization
    $validator->add('default_value', [
      'validThroughDays' => [
        'rule'    => ['naturalNumber', true],
        'on'      => fn(array $context): bool => ($context['data']['attribute'] ?? null) === 'valid_through',
        'message' => __d('core_enroller', 'error.EnrollmentAttributes.valid_through.default_value')
      ]
    ]);

    $validator->allowEmptyString('default_value_datetime');
    
    $this->registerStringValidation($validator, $schema, 'default_value_env_name', false);

    $validator->add('modifiable', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('modifiable');

    $validator->add('hidden', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('hidden');

    return $validator;
  }
}
