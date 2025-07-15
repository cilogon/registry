<?php
/**
 * COmanage Registry Email Addresses Table
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

use Cake\Collection\Collection;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Validation\Validator;
use \App\Lib\Enum\ActionEnum;
use \App\Lib\Enum\ProvisioningContextEnum;
use \App\Model\Entity\EmailAddress;

class EmailAddressesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\ProvisionableTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\SearchFilterTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\TypeTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  // Default "out of the box" types for this model. Entries here should be
  // given a default localization in app/resources/locales/*/defaultType.po
  protected $defaultTypes = [
    'type' => [
      'delivery',
      'forwarding',
      'list',
      'official',
      'personal',
      'preferred',
      'recovery'
    ]
  ];

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
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Secondary);
    
    // Define associations
    $this->belongsTo('People');
    $this->belongsTo('ExternalIdentities');
    $this->belongsTo('Types');
    $this->belongsTo('SourceEmailAddresses')
         ->setClassName('EmailAddresses')
         ->setForeignKey('source_email_address_id')
         ->setProperty('source_email_address');
    $this->hasMany('PipelinedEmailAddresses')
         ->setClassName('EmailAddresses')
         ->setForeignKey('source_email_address_id')
         ->setProperty('pipelined_email_address');    
    $this->hasOne('Verifications')
         ->setDependent(true)
         ->setCascadeCallbacks(true);

    $this->setDisplayField('mail');
    
    $this->setPrimaryLink(['external_identity_id', 'person_id']);
    $this->setAllowLookupPrimaryLink(['primary']);
    $this->setRequiresCO(true);
    $this->setRedirectGoal('self');
    $this->setRedirectGoal(action: 'delete', goal: 'deleted');
    $this->setAllowLookupPrimaryLink(['forceVerify', 'unfreeze']);
    $this->setEditContains(['ExternalIdentities', 'SourceEmailAddresses', 'Verifications']);

    $this->setAutoViewVars([
      'types' => [
        'type' => 'type',
        'attribute' => 'EmailAddresses.type'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'forceVerify' => ['platformAdmin', 'coAdmin'],
        'unfreeze' => ['platformAdmin', 'coAdmin'],
        'view' => ['platformAdmin', 'coAdmin', 'selfMember'],
      ],
      // Actions that are permitted on readonly entities (besides view)
      'readOnly' =>   ['unfreeze'],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin', 'selfMember'],
        'deleted' =>  ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Callback after data is marshaled into an entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface   $event   afterMarshal event
   * @param  Entity Interface $entity  Marshalled entity
   * @param  ArrayObject      $data    Entity data
   * @param  ArrayObject      $options Callback options
   */

  public function afterMarshal(
    EventInterface $event, 
    EntityInterface $entity, 
    \ArrayObject $data, 
    \ArrayObject $options
  ) {
    if(!$entity->isNew() && !empty($entity->person_id) && $entity->isDirty('mail')) {
      // AR-EmailAddress-2 Editing an Email Address (but not its Type) associated
      // with a Person will revert it to unverified.

      $this->llog('rule', "AR-EmailAddress-2 Flagging email address " . $entity->mail . " for Person " . $entity->person_id . " as unverified due to edit");

      $entity->verified = false;
      $data['verified'] = false;

      $this->Verifications->unverify($entity->id);
    }
  }

  /**
   * Get an Email Address suitable for message delivery for the specified Person.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $personId Person ID
   * @return string           Email address suitable for delivery
   * @throws InvalidArgumentException
   */

  public function getDeliveryAddress(int $personId): string {
    // We allow a Delivery Email Address Type to be specified via CoSettings,
    // but to check we first need to map the Person to a CO.

    $person = $this->People->get($personId);

    $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');
    $settings = $CoSettings->find()->where(['co_id' => $person->co_id])->firstOrFail();

    $whereClause = [
      'person_id' => $personId,
      // AR-EmailAddress-3 Only verified Email Addresses may be used for delivery of messages to a Person.
      'verified'  => true
    ];

    if(!empty($settings->email_delivery_address_type_id)) {
      $whereClause['type_id'] = $settings->email_delivery_address_type_id;
    }

    try {
      $email = $this->find()
                    ->where($whereClause)
                    ->firstOrFail();
    }
    catch(\Cake\Datasource\Exception\RecordNotFoundException $e) {
      // This error is probably going to render a lot, so map the type ID to the label
      $Types = TableRegistry::getTableLocator()->get('Types');
      $label = $Types->getTypeLabel($settings->email_delivery_address_type_id);

      throw new \InvalidArgumentException(
        !empty($settings->email_delivery_address_type_id)
        ? __d('error', 'EmailAddresses.mail.delivery.type', [$label, $personId])
        : __d('error', 'EmailAddresses.mail.delivery', [$personId])
      );
    }
    
    return $email->mail;
  }

  /**
   * Force an Email Address to verified status.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int $id            EmailAddress ID
   * @return string             The verified Email Address
   * @throws InvalidArgumentException
   */

  public function forceVerify(int $id): string {
    $email = $this->get($id);

    // We only permit Email Addresses associated with a Person (not External Identity)
    // to be force verified.

    if(empty($email->person_id)) {
      // AR-EmailAddress-1 Only Email Addresses associated with a Person may be verified
      // by Registry.
      throw new \InvalidArgumentException('error', 'EmailAddresses.mail.verify.force.person');
    }

    // Email Addresses that are already verified can't be re-verified.

    if($email->verified) {
      throw new \InvalidArgumentException('error', 'EmailAddresses.mail.verified');
    }

    // AR-EmailAddress-4 A frozen Email Address may be verified if it is otherwise
    // eligible for verification.

    // Flag the address as verified and record history.

    $email->verified = true;
    $this->save($email);

    // Create a Verification record
    $this->Verifications->manual($id);

    // Request Provisioning
    $this->requestProvisioning(id: $id, context: ProvisioningContextEnum::Automatic);

    return $email->mail;
  }
  
  /**
   * Callback after model save.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface  $event   Event
   * @param  EntityInterface $entity  Entity (ie: Co)
   * @param  ArrayObject     $options Save options
   * @return bool                     True on success
   */
    
  public function localAfterSave(\Cake\Event\EventInterface $event, \Cake\Datasource\EntityInterface $entity, \ArrayObject $options): bool {
    $this->recordHistory($entity);
    
    return true;
  }

  /**
   * Look up a Person ID from an email address and email address type ID.
   * Only verified addresses can be used for lookups.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $typeId     Email Address Type ID
   * @param  string $identifier Email Address
   * @return int                Person ID
   * @throws Cake\Datasource\Exception\RecordNotFoundException
   */

  public function lookupPerson(int $typeId, string $identifier): int {
    // The second parameter is called $identifier for consistency with IdentifiersTable::lookupPerson()
    $id = $this->find()
               ->where([
                'LOWER(mail)' => strtolower($identifier),
                'type_id'     => $typeId,
                'verified'    => true,
                'person_id IS NOT NULL'
               ])
               ->firstOrFail();

    return $id->person_id;
  }

  /**
   * Perform a keyword search.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $coId   CO ID to constrain search to
   * @param  string $q      String to search for
   * @param  int    $limit  Search limit
   * @return Array          Array of search results, as from find('all)
   */

  public function search(int $coId, string $q, int $limit) {
    return $this->find()
                ->where([
                  'LOWER(EmailAddresses.mail)' => strtolower($q),
                  'People.co_id' => $coId
                ])
                ->limit($limit)
                ->contain(['People' => 'PrimaryName'])
                ->all();
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
    
    $this->registerPrimaryKeyValidation($validator, $this->getPrimaryLinks());
    
    $this->registerStringValidation($validator, $schema, 'mail', true);
    $validator->add('mail', [
      'content' => ['rule'    => ['email'],
                    'message' => __d('error', 'input.invalid.email')]
    ]);
    $validator->notEmptyString('mail');
    
    $validator->add('type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('type_id');
    
    $validator->add('verified', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('verified');
    
    $this->registerStringValidation($validator, $schema, 'description', false);
    
    $validator->add('frozen', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('frozen');

    $validator->add('source_email_address_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('source_email_address_id');
    
    return $validator;
  }


  /**
   * Save attributes for a person, possibly tied to a role and parent model.
   * Each field is processed to create a new EmailAddress entity and saved.
   *
   * @since  COmanage Registry v5.1.0
   * @param int $personId Person ID
   * @param int|null $roleId Role ID (if applicable)
   * @param string $parentModel Parent model name
   * @param array $fields Array of fields containing enrollment attributes
   * @return bool                     True on success
   * @throws \Cake\Datasource\Exception\RecordNotFoundException
   * @throws \Cake\ORM\Exception\PersistenceFailedException
   */
  public function saveAttributeCollectorPetitionAttributes(int $personId, ?int $roleId, string $parentModel, array $fields): bool
  {
    foreach ($fields as $idx => $field) {
      // Check if this has already been saved
      $email = [
        'person_id'     => $personId,
        'mail'          => $field->value,
        'type_id'       => $field->enrollment_attribute->attribute_type,
      ];

      $this->saveOrFail($this->newEntity($email));
    }
    return true;
  }
}