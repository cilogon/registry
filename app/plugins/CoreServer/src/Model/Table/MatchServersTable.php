<?php
/**
 * COmanage Registry Math Servers Table
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace CoreServer\Model\Table;

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Enum\RequiredEnum;

class MatchServersTable extends HttpServersTable {
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.1.0
   * @param  array  $config Configuration options passed to constructor
   */

  public function initialize(array $config): void {
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);

    // Define associations
    // this is defined in HttpServersTable
    // $this->belongsTo('Servers');
    $this->hasMany('CoreServer.MatchServerAttributes')
         ->setDependent(true)
         ->setCascadeCallbacks(true);

    $this->setDisplayField('hostname');

    $this->setPrimaryLink('server_id');
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
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ],
      'related' => [
        'table' => [
          'CoreServer.MatchServerAttributes'
        ]
      ]
    ]);
  }

  /**
   * Assemble attributes from a person record into a format suitable for wire
   * transfer.
   *
   * @since  COmanage Registry v4.0.0
   * @param  array $matchAttributes Match Attribute configuration
   * @param  array $queryAttributes Identity attributes for querying
   * @return array                  Array of data suitable for conversion to JSON
   * @throws InvalidArgumentException
   * @throws RuntimeException
   */

  protected function assembleRequestAttributes(
    array   $matchAttributes,
    array   $queryAttributes
  ): array {
    $matchRequest = [];

    $supportedAttrs = $this->MatchServerAttributes->supportedAttributes();

    foreach($matchAttributes as $mattr) {
      if($mattr->required == RequiredEnum::NotPermitted) {
        continue;
      }

      $found = false;
      
      // This is the key used by supportedAttributes(), which is also the value
      // stored in the database for 'attribute' by the form
      $attrKey = $mattr->attribute;
      
      // $model = (eg) EmailAddresses
      $model = $supportedAttrs[$attrKey]['model'];
      // $tableName = (eg) email_addresses
      $tableName = Inflector::tableize($model);
      // $wire = (eg) emailAddresses
      $wire = $supportedAttrs[$attrKey]['wire'];

      if(isset($supportedAttrs[$attrKey]['attribute'])) {
        // This is a singleton value on OrgIdentity, eg "date_of_birth"

        // XXX date_of_birth is expected to be YYYY-MM-DD but we don't currently try to reformat it...
        
        if(!empty($queryAttributes[ $supportedAttrs[$attrKey]['attribute'] ])) {
          $matchRequest['sorAttributes'][$wire] = $queryAttributes[ $supportedAttrs[$attrKey]['attribute'] ];
          $found = true;
        }
      } elseif(isset($supportedAttrs[$attrKey]['attributes'])) {
        // This is an MVEA, eg "emailAddress" or "telephoneNumber"
        
        // When assembling attributes from MVEAs, we include all available attributes.
        // The Match server can ignore the ones it doesn't care about.
        
        // We don't try to reformat the attribute (strip spaces, slashes, etc) since
        // the match engine should be configured to treat the attribute appropriately
        // (eg: alphanumeric). That is, unless a formatting function is specified
        // to (eg) assemble a TelephoneNumber from its parts.

        // $type = (eg) official (as configured for this Match Server instance)
        $type = $mattr->type->value;;
        
        if(!empty($queryAttributes[$tableName])) {
          $obj = Hash::extract($queryAttributes[$tableName], '{n}[type='.$type.']');

          if(!empty($obj)) {
            foreach($obj as $o) {
              // Assemble the record
              $attrs = ['type' => $type];

              if(!empty($supportedAttrs[$attrKey]['attributes'])) {
                foreach($supportedAttrs[$attrKey]['attributes'] as $ra => $ad) {
                  // $ra = Registry Attribute, $ad = Attribute Dictionary attribute
                  // We use isset() rather than !empty() to avoid issues with
                  // "blank" values, including 0
                  if(isset($o[$ra])) {
                    $attrs[$ad] = $o[$ra];
                  }
                }
              }
              /* So far we don't need a function in PE since we use virtual attributes
                 on the entity instead
              else {
                // Call the function
                $fn = $supportedAttrs[$attrKey]['function'];

                $attrs[ $supportedAttrs[$attrKey]['wireField'] ] = $fn($o);
              } */
              
              // Make sure we have something other than type to work with
              if(count(array_keys($attrs)) > 1) {
                $matchRequest['sorAttributes'][$wire][] = $attrs;
                $found = true;
              }
            }
          }
        }
      } else {
        throw new \LogicException('NOT IMPLEMENTED: ' . $attrKey);
      }

      if(!$found && $mattr->required == RequiredEnum::Required) {
        throw new \InvalidArgumentException(__d('core_server', 'error.MatchServers.attr.req', [$attrKey, $mattr->id]));
      }
    }

    if(empty($matchRequest)) {
      // We didn't find any attributes, so throw an error
      
      throw new \RuntimeException(__d('core_server', 'error.MatchServers.attr.none'));
    }

    return $matchRequest;
  }

  /**
   * Perform an ID Match Reference Identifier or Update Attributes Request. 
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $serverId       Server ID (NOT Match Server ID)
   * @param  string $sorLabel       System of Record Label
   * @param  string $sorId          System of Record ID (Source Key)
   * @param  array  $attributes     Available attributes used to perform match request with
   * @param  string $referenceId    Reference ID, for forced reconciliation request
   * @param  string $action         Requested action: "request" (Reference ID) or "update" (Match Attributes)
   * @return string|array|bool      Reference ID or (on 300 response) array of choices, or true (Update request)
   * @throws InvalidArgumentException
   * @throws UnexpectedValueException
   */

  protected function doRequest(
    int     $serverId,
    string  $sorLabel,
    string  $sorId,
    array   $attributes,
    ?string $referenceId=null,
    string  $action='request'
  ): string|array|bool {
    // Pull the Match Server configuration

    $matchServer = $this->Servers->get(
      $serverId,
      contain: ['MatchServers' => ['MatchServerAttributes' => 'Types']],
    );

    if($matchServer->status != SuspendableStatusEnum::Active) {
      throw new \InvalidArgumentException(__d('error', 'inactive', [__d('controller', 'Servers', [1]), $serverId]));
    }

    // Assemble the match request attributes using the provided entity attributes
    // Let any exceptions bubble up
    $matchRequest = $this->assembleRequestAttributes(
      $matchServer->match_server->match_server_attributes,
      $attributes
    );

    if($referenceId) {
      // Insert the requested Reference ID into the message body
      
      $matchRequest['referenceId'] = $referenceId;
    }

    $MatchClient = $this->createHttpClient($matchServer->match_server->id);

    $url = "/people/" . urlencode($sorLabel) . "/" . urlencode($sorId);
    $options = [
      'headers' =>['Content-Type' => 'application/json']
    ];

    if($action == 'request') {
      // Before we submit the PUT, we do a GET (Request Current Values) to see
      // if there is already a reference ID available. This is primarily to
      // handle a potential match (202) situation (ie: we previously tried to
      // get a reference ID but got a 202 instead). Deployers could enable the
      // Match Callback API, in which case this wouldn't be necessary.
      
      // An alternate approach here would be to store the Match Request ID
      // returned as part of the 202 response, however that ID is optional
      // (maybe it should be required?) and we would need to track it somewhere
      // (in the OIS Record?). It would be nice to link to the pending request
      // though...
      
      $response = $MatchClient->get($url);
      
      if($response->getStatusCode() == 200) {
        $body = $response->getJson();

        if(!empty($body['meta']['referenceId'])) {
          $this->llog('trace', "Received existing Reference ID " . $body['meta']['referenceId'] . " for $sorLabel / $sorId from Match server " . $matchServer->match_server->id);

          // The pending match has been resolved
          return $body['meta']['referenceId'];
        }
      }
    }

    $response = $MatchClient->put($url, json_encode($matchRequest), $options);

    $body = $response->getJson();

    $this->llog('trace', "Match server " . $matchServer->match_server->id 
                         . " returned " . $response->getStatusCode()
                         . " for $sorLabel / $sorId");
    
    // If we get anything other than a 200/201 back, throw an error. This includes
    // 202, which we handle by simply generating a slightly different error.
    
    if($response->getStatusCode() == 202) {
      $requestId = "?";
      
      if(!empty($body['matchRequest'])) {
        // Match Request is an optional part of the response
        $requestId = $body['matchRequest'];
      }
      
      throw new \UnexpectedValueException(__d('core_server', 'result.MatchServers.match.accepted', $requestId));
    }
    
    if($response->getStatusCode() == 300) {
      $candidates = [];
      
      // Inject the "new" candidate to make it easier for the calling code
      $candidates[] = [
        'referenceId' => 'new',
        'sorRecords' => [
          [
            'meta' => [
              'referenceId' => 'new'
            ],
            'sorAttributes' => $matchRequest['sorAttributes']
          ]
        ]
      ];
      
      $candidates = array_merge($candidates, $body['candidates']);
      return $candidates;
    }
    
    if($response->getStatusCode() != 200 && $response->getStatusCode() != 201) {
      $error = $response->reasonPhrase;
      
      // If an error was provided in the response, use that instead
      if(!empty($body['error'])) {
        $error = $body['error'];
      }

      $this->llog('error', "Match Server " . $matchServer->match_server->id . " returned error $error");
      
      throw new \RuntimeException(__d('core_server', 'error.MatchServers.response', [$error]));
    }
    
    if($action == 'request') {
      // We expect a reference ID
      $this->llog('trace', "Received Reference ID " . $body['referenceId'] . " for $sorLabel / $sorId from Match server " . $matchServer->match_server->id);

      return $body['referenceId'];
    }

    // There is no Reference ID returned for an Update Match Attributes request
    return true;
  }

  /**
   * Perform an ID Match Reference Identifier Request.
   *
   * @since  COmanage Registry v5.2.0
   * @param  integer $serverId      Server ID
   * @param  string $sorLabel       System of Record Label
   * @param  string $sorId          System of Record ID (Source Key)
   * @param  array  $attributes     Available attributes used to perform match request with
   * @param  string $referenceId    Reference ID, for forced reconciliation request
   * @return string|array           Reference ID or (on 300 response) array of choices
   * @throws InvalidArgumentException
   * @throws UnexpectedValueException
   */

  public function requestReferenceIdentifier(
    int     $serverId,
    string  $sorLabel,
    string  $sorId,
    array   $attributes,
    ?string $referenceId=null
  ): string|array {
    return $this->doRequest(
      serverId:     $serverId,
      sorLabel:     $sorLabel,
      sorId:        $sorId,
      attributes:   $attributes,
      referenceId:  $referenceId,
      action:       'request'
    );
  }

  /**
   * Perform an ID Match Update Match Attributes Request.
   *
   * @since  COmanage Registry v5.2.0
   * @param  int    $serverId       Server ID (NOT Match Server ID)
   * @param  string $sorLabel       System of Record Label
   * @param  string $sorId          System of Record ID (Source Key)
   * @param  array  $attributes     Available attributes used to perform match request with
   * @return boolean                true on success
   * @throws InvalidArgumentException
   */

  public function updateMatchAttributes(
    int     $serverId,
    string  $sorLabel,
    string  $sorId,
    array   $attributes
  ): bool {
    // This is basically the same request as requestReferenceIdentifier().
    // If we don't throw an exception then we successfully processed the request.

    return $this->doRequest(
      serverId:   $serverId,
      sorLabel:   $sorLabel,
      sorId:      $sorId,
      attributes: $attributes,
      action:     'update'
    );
  }


  /**
   * Set validation rules.
   *
   * @since  COmanage Registry v5.1.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */

/*
  public function validationDefault(Validator $validator): Validator {
    // We don't have any MatchServer specific configurations over the default HttpServer
    // configurations, so we can just rely on the parent table. If we ever add any, then
    // just call parrent::validationDefault() before adding the MatchServer specific rules.
  }
  */
}
