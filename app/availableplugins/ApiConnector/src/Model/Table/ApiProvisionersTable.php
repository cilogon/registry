<?php
/**
 * COmanage Registry API Provisioners Table
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace ApiConnector\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Validation\Validator;
use ApiConnector\Lib\Enum\ApiProvisionerModeEnum;
use App\Lib\Enum\ProvisioningEligibilityEnum;
use App\Lib\Enum\ProvisioningStatusEnum;
use App\Model\Entity\ProvisioningTarget;

class ApiProvisionersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\ProvisionerTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.3.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('ProvisioningTargets');
    $this->belongsTo('Servers');
    $this->belongsTo('Types');
    
    $this->hasManyPlugins([
      'Servers' => [
        [
          'targetModel' => 'ApiConnector.ApiProvisioners'
        ]
      ],
      'Types' => [
        [
          'targetModel' => 'ApiConnector.ApiProvisioners'
        ]
      ]
    ]);

    $this->setDisplayField('server_id');
    
    $this->setPrimaryLink(['provisioning_target_id']);
    $this->setRequiresCO(true);

    $this->setAutoViewVars([
      'modes' => [
        'type' => 'enum',
        'class' => 'ApiConnector.ApiProvisionerModeEnum'
      ],
      'servers' => [
        'type' => 'select',
        'model' => 'Servers',
        'where' => ['plugin' => 'CoreServer.HttpServers']
      ],
      'types' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false, //['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);

    $this->setProvisionableModels([
      'People'
    ]);
  }

  /**
   * Filter attributes from a COmanage model to an API representation.
   *
   * @since  COmanage Registry v5.3.0
   * @param  ProvisioningTarget   $provisioningTarget ApiProvisioner configuration
   * @param  EntityInterface      $attribute          COmanage model attribute
   * @param  array                $subattributes      Subattributes to process, COmanage name to API name
   * @param  array                $meta               Metadata attributes to process, COmanage name to API name
   * @return array                                    Filtered attributes
   */
  
  protected function filterAttribute(
    ProvisioningTarget  $provisioningTarget,
    EntityInterface     $attribute,
    array               $subattributes,
    array               $meta
  ): array {
    $ret = [];
    
    foreach($meta as $c => $t) {
      if(!$t) {
        // No corresonding API name was provided, so skip this attribute
      } elseif($c == 'id' && !empty($attribute->$c)) {
        // We need to cast to a string (in case of integer)
        $ret['meta'][$t] = strval($attribute->$c);
      } elseif(is_object($attribute->$c) 
               && get_class($attribute->$c) == 'Cake\I18n\DateTime') {
        // This is a DateTime (eg created, modified), convert it
        $ret['meta'][$t] = $attribute->$c->format('Y-m-d\TH:i:s\Z');
      } elseif(isset($attribute->$c)) {
        // We use isset rather than !empty here because we want to output
        // zero values, eg revision = 0
        $ret['meta'][$t] = $attribute->$c;
      }
    }
    
    // Map COmanage subattribute name to TAP Core Schema name
    foreach($subattributes as $c => $t) {
      if(!$t) {
        // No corresonding API name was provided, so skip this attribute
      } elseif(is_object($attribute->$c) 
               && get_class($attribute->$c) == 'Cake\I18n\DateTime') {
        // This is a DateTime (eg created, modified), convert it
        $ret[$t] = $attribute->$c->format('Y-m-d\TH:i:s\Z');
      } elseif(in_array($c, ['affiliation_type_id', 'type_id'])
               && !empty($attribute->$c)) {
        // Inject the type string instead of the type
        $propertyName = substr($c, 0, strlen($c)-3);
        $ret[$t] = $attribute->$propertyName->value;
      } elseif($c == 'cou_id' && !empty($attribute->$c)) {
        // Inject the COU name instead of the COU. Note that because of AR-COU-3
        // there will be no ambiguity here, though if a COU is renamed the
        // target system(s) will need to know how to handle that.
        $Cous = TableRegistry::getTableLocator()->get('Cous');
        $cou = $Cous->get($attribute->$c);
        $ret[$t] = $cou->name;
      } elseif(in_array($c, ['manager_person_id', 'sponsor_person_id'])
               && !empty($attribute->$c)) {
        // Inject the Identifier of the configured Type rather than the Person ID
        $Identifiers = TableRegistry::getTableLocator()->get('Identifiers');
        $ret[$t] = $Identifiers->lookupForPerson($provisioningTarget->api_provisioner->type_id, $attribute->$c);
      } elseif(isset($attribute->$c)) {
        // We use isset rather than !empty here because we want to output
        // zero values, eg revision = 0
        $ret[$t] = $attribute->$c;
      }
    }
    
    return $ret;
  }

  /**
   * Filter attributes from multiple instances of a COmanage model to an API representation.
   *
   * @since  COmanage Registry v5.3.0
   * @param  ProvisioningTarget   $provisioningTarget ApiProvisioner configuration
   * @param  array                $attributes         COmanage model attributes (eg: array of names)
   * @param  array                $subattributes      Subattributes to process, COmanage name to API name
   * @param  array                $meta               Metadata attributes to process, COmanage name to API name
   * @return array                                    Filtered attributes
   */
  
  protected function filterAttributes(
    ProvisioningTarget  $provisioningTarget,
    array               $attributes,
    array               $subattributes,
    array               $meta
  ): array {
    $ret = [];

    foreach($attributes as $a) {
      $ret[] = $this->filterAttribute($provisioningTarget, $a, $subattributes, $meta);
    }
    
    return $ret;
  }

  /**
   * Marshal Person attributes into the format expected for the API.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  ProvisioningTarget   $provisioningTarget ApiProvisioner configuration
   * @param  object               $data               Person data
   * @return array                                    Array of Person attributes
   */

  protected function marshalPersonAttributes(
    ProvisioningTarget $provisioningTarget,
    object $data
  ): array {
    $ret = [];

    // Note v4 never explicitly tied the schema definition to the TAP API,
    // but in general we'll follow those standards when reasonable.

    // Common metadata fields to include for most/all MVEAs, mapped from
    // database/entity name to API name.
    $metaFields = [
      'id' => 'id',
      'created' => 'created',
      'cri' => 'cri',
      'do_not_clone' => null,
      'modified' => 'lastModified',
      'revision' => 'revision',
      'uuid' => 'uuid'
    ];

    // Status is defined in schema.json simply as a string, not as an enum.
    // We'll follow the v4 pattern of using the localized text string for the
    // value, unlike the Model Specific API which just uses the enum itself.

    $ret['status'] = strtolower(__d('enumeration', 'StatusEnum.' . $data->status));

    if(!empty($data->date_of_birth)) {
      $ret['dateOfBirth'] = $data->date_of_birth->format('Y-m-d\TH:i:s\Z');
    }

    $ret['names'] = $this->filterAttributes(
      $provisioningTarget,
      $data->names,
      [
        'honorific' => 'prefix',
        'given' => 'given',
        'middle' => 'middle',
        'family' => 'family',
        'suffix' => 'suffix',
        'language' => 'language',
        'full_name' => 'formatted',
        'type_id' => 'type'
      ],
      $metaFields
    );

    $ret['addresses'] = $this->filterAttributes(
      $provisioningTarget,
      $data->addresses,
      [
        'street' => 'streetAddress',
        'room' => 'room',
        'locality' => 'locality',
        'state' => 'region',
        'postal_code' => 'postalCode',
        'country' => 'country',
        'language' => 'language',
        'formatted_address' => 'formatted',
        'type_id' => 'type'
      ],
      $metaFields
    );

    $ret['adHocAttributes'] = $this->filterAttributes(
      $provisioningTarget,
      $data->ad_hoc_attributes,
      [
        'tag' => 'tag',
        'value' => 'value'
      ],
      $metaFields
    );

    $ret['emailAddresses'] = $this->filterAttributes(
      $provisioningTarget,
      $data->email_addresses,
      [
        'mail' => 'address',
        'verified' => 'verified',
        'type_id' => 'type'
      ],
      $metaFields
    );

    $ret['identifiers'] = $this->filterAttributes(
      $provisioningTarget,
      $data->identifiers,
      [
        'identifier' => 'identifier',
        'type_id' => 'type'
      ],
      $metaFields
    );

    $ret['pronouns'] = $this->filterAttributes(
      $provisioningTarget,
      $data->pronouns,
      [
        'pronouns' => 'pronouns',
        'language' => 'language',
        'type_id' => 'type'
      ],
      $metaFields
    );

    $ret['telephoneNumbers'] = $this->filterAttributes(
      $provisioningTarget,
      $data->telephone_numbers,
      [
        'formatted_number' => 'number',
        'type_id' => 'type'
      ],
      $metaFields
    );

    $ret['urls'] = $this->filterAttributes(
      $provisioningTarget,
      $data->urls,
      [
        'url' => 'url',
        'type_id' => 'type'
      ],
      $metaFields
    );

    foreach($data->person_roles as $role) {
      $roleAttrs = $this->filterAttribute(
        $provisioningTarget,
        $role,
        [
          // Several of these fields will be mapped in filterAttribute()
          'cou_id' => 'cou',
          'title' => 'title',
          'organization' => 'organization',
          'department' => 'department',
          'valid_from' => 'validFrom',
          'valid_through' => 'validThrough',
          'affiliation_type_id' => 'affiliation',
          'sponsor_person_id' => 'sponsor',
          'manager_person_id' => 'manager',
          'ordr' => 'rank'
        ],
        $metaFields
      );

      $roleAttrs['status'] = strtolower(__d('enumeration', 'StatusEnum.' . $role->status));

      $roleAttrs['addresses'] = $this->filterAttributes(
        $provisioningTarget,
        $role->addresses,
        [
          'street' => 'streetAddress',
          'room' => 'room',
          'locality' => 'locality',
          'state' => 'region',
          'postal_code' => 'postalCode',
          'country' => 'country',
          'language' => 'language',
          'formatted_address' => 'formatted',
          'type_id' => 'type'
        ],
        $metaFields
      );

      $roleAttrs['adHocAttributes'] = $this->filterAttributes(
        $provisioningTarget,
        $role->ad_hoc_attributes,
        [
          'tag' => 'tag',
          'value' => 'value'
        ],
        $metaFields
      );
      
      $roleAttrs['telephoneNumbers'] = $this->filterAttributes(
        $provisioningTarget,
        $role->telephone_numbers,
        [
          'formatted_number' => 'number',
          'type_id' => 'type'
        ],
        $metaFields
      );
      
      $ret['person']['roles'][] = $roleAttrs;
    }

    foreach($data->group_members as $g) {
      $ret['person']['members'][] = $g->group->name;
    }

    return $ret;
  }

  /**
   * Provision object data to the provisioning target.
   *
   * @since  COmanage Registry v5.3.0
   * @param  ProvisioningTarget  $provisioningTarget  ApiProvisioner configuration
   * @param  string              $entityName
   * @param  object              $data                Provisioning data in Entity format (eg: \App\Model\Entity\Person)
   * @param  string              $eligibility         Provisioning Eligibility Enum
   * @return array                                    Array of status, comment, and optional identifier
   */

  public function provision(
    ProvisioningTarget $provisioningTarget,
    string $entityName,
    object $data,
    string $eligibility
  ): array {
    if($entityName != 'People') {
      // We currently only support People, not Groups, etc

      return [
        'status'      => ProvisioningStatusEnum::NotProvisioned,
        'comment'     => 'NOT IMPLEMENTED',
        'identifier'  => null
      ];
    }

    // Find the identifier in $data. If we don't have one we can't proceed.
    $typeId = $provisioningTarget->api_provisioner->type_id;

    $identifiers = Hash::extract($data, 'identifiers.{n}[type_id='.$typeId.']');

    if(empty($identifiers)) {
      return [
        'status'      => ProvisioningStatusEnum::NotProvisioned,
        'comment'     => __d('api_connector', 'error.ApiProvisioners.identifier'),
        'identifier'  => null
      ];
    }

    // If we get more than one identifier (which we shouldn't by convention),
    // we simply use the first one.
    $identifier = $identifiers[0]['identifier'];

    // We treat this record as deleted if the Provisioning Eligibility is Deleted
    // or Expunged, or if the Person record itself is deleted (which should have
    // implied one of those Eligibilities). For anything else (eg: Suspended), we
    // include the full person record (if "include attributes" is enabled).
    $deleted = (in_array($eligibility, [
                          ProvisioningEligibilityEnum::Deleted,
                          ProvisioningEligibilityEnum::Expunged
                        ])) 
               || (isset($data->deleted) && $data->deleted);

    $message = [
      'meta' => [
        'version' => '2.0.0',
        'objectType' => 'person'
      ],
      'person' => [
        'meta' => [
          // Cast to string in case of an all numeric identifier
          'id' => (string)$identifier,
          'deleted' => $deleted,
          'created' => $data->created->format('Y-m-d\TH:i:s\Z'),
          'lastModified' => $data->modified->format('Y-m-d\TH:i:s\Z')
        ]
      ]
    ];

    if(!$deleted) {
      // Always include the URL back to the person record when the record
      // is not deleted.

      $message['person']['meta']['url'] = \Cake\Routing\Router::url(
        url: "/api/v2/people/" . $data->id,
        full: true
      );
    }

    if(!$deleted && $eligibility == ProvisioningEligibilityEnum::Eligible) {
      // Insert attributes, if configured, and if eligible for provisioning

      if($provisioningTarget->api_provisioner->include_attributes) {
        $message['person'] = array_merge($message['person'], $this->marshalPersonAttributes($provisioningTarget, $data));
      }
      // else the url included above is sufficient
    }

    // Connect to the endpoint and send the message

    $serverCfg = $this->Servers->get(
      $provisioningTarget->api_provisioner->server_id,
      contain: ['HttpServers']
    );

    $Http = $this->Servers->HttpServers->createHttpClient($serverCfg->http_server->id);

    $url = '/people';

    $options = [
      'headers' =>[
        'Accept' => 'application/json',
        'Content-Type' => 'application/json'
      ]
    ];

    if($provisioningTarget->api_provisioner->mode == ApiProvisionerModeEnum::POST) {
      // POST

      $response = $Http->post($url, json_encode($message), $options);
    } elseif($deleted) {
      // DELETE

      $url .= '/' . urlencode($identifier);

      $response = $Http->delete($url, json_encode($message), $options);
    } else {
      // PUT

      $url .= '/' . urlencode($identifier);

      $response = $Http->put($url, json_encode($message), $options);
    }

    $responseCode = $response->getStatusCode();

    if($responseCode >= 200 && $responseCode < 300) {
      return [
        'status'      => ProvisioningStatusEnum::Provisioned,
        'comment'     => __d('api_connector', 'result.ApiProvisioners.response', [$responseCode]),
        'identifier'  => null
      ];
    } else {
      return [
        'status'      => ProvisioningStatusEnum::NotProvisioned,
        'comment'     => __d('api_connector', 'result.ApiProvisioners.response', [$responseCode]),
        'identifier'  => null
      ];
    }
  }

  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   * @throws InvalidArgumentException
   * @throws RecordNotFoundException
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('provisioning_target_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('provisioning_target_id');

    $validator->add('server_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('server_id');

    $validator->add('type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('type_id');

    $validator->add('include_attributes', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('include_attributes');
    
    $validator->add('mode', [
      'content' => ['rule' => ['inList', ApiProvisionerModeEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('mode');

    return $validator; 
  }
}