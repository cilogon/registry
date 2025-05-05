<?php
/**
 * COmanage Registry Orcid Sources Table
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

namespace OrcidSource\Model\Table;

use App\Model\Entity\ExternalIdentitySource;
use Cake\Http\Client;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Cake\Validation\Validator;
use OrcidSource\Lib\Enum\{OrcidSourceApiEnum, OrcidSourceTierEnum};
use \App\Lib\Enum\HttpStatusCodesEnum;
use \OrcidSource\Model\Entity\OrcidSource;

class OrcidSourcesTable extends Table {
    use \App\Lib\Traits\AutoViewVarsTrait;
    use \App\Lib\Traits\ChangelogBehaviorTrait;
    use \App\Lib\Traits\CoLinkTrait;
    use \App\Lib\Traits\LabeledLogTrait;
    use \App\Lib\Traits\PermissionsTrait;
    use \App\Lib\Traits\PrimaryLinkTrait;
    use \App\Lib\Traits\QueryModificationTrait;
    use \App\Lib\Traits\TabTrait;
    use \App\Lib\Traits\TableMetaTrait;
    use \App\Lib\Traits\ValidationTrait;

    // Cache of Table Models
    protected $tableCache = [];

    // Cache of the type map, for flat mode
    protected $typeCache = [];

    private $orcidSource;
    private $orcidToken;
    private $httpClient;
    private $orcidTokensTable;
    private $oauth2ServersTable;

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
        $this->addBehavior('Timestamp');

        $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);

        // Define associations
        $this->belongsTo('ExternalIdentitySources');
        $this->belongsTo('Servers');
        $this->belongsTo('AddressTypes')
            ->setClassName('Types')
            ->setForeignKey('address_type_id')
            ->setProperty('address_type');
        $this->belongsTo('DefaultAffiliationTypes')
            ->setClassName('Types')
            ->setForeignKey('default_affiliation_type_id')
            ->setProperty('default_affiliation_type');
        $this->belongsTo('EmailAddressTypes')
            ->setClassName('Types')
            ->setForeignKey('email_address_type_id')
            ->setProperty('email_address_type');
        $this->belongsTo('NameTypes')
            ->setClassName('Types')
            ->setForeignKey('name_type_id')
            ->setProperty('name_type');
        $this->belongsTo('TelephoneNumberTypes')
            ->setClassName('Types')
            ->setForeignKey('telephone_number_type_id')
            ->setProperty('telephone_number_type');

        $this->hasMany('OrcidSource.OrcidTokens')
            ->setDependent(true)
            ->setCascadeCallbacks(true);

        $this->setDisplayField('id');

        $this->setPrimaryLink(['external_identity_source_id']);
        $this->setRequiresCO(true);

        // All the tabs share the same configuration in the ModelTable file
        $this->setTabsConfig(
            [
                // Ordered list of Tabs
                'tabs' => ['ExternalIdentitySources', 'OrcidSource.OrcidSources', 'ExternalIdentitySources@action.search'],
                // What actions will include the subnavigation header
                'action' => [
                    // If a model renders in a subnavigation mode in edit/view mode, it cannot
                    // render in index mode for the same use case/context
                    // XXX edit should go first.
                    'ExternalIdentitySources' => ['edit', 'view', 'search'],
                    'OrcidSource.OrcidSources' => ['edit'],
                    'ExternalIdentitySources@action.search' => [],
                ],
            ]
        );

        $this->setEditContains([
            'Servers' => ['Oauth2Servers'],
            'ExternalIdentitySources',
        ]);

        $this->setViewContains([
            'Servers' => ['Oauth2Servers'],
            'ExternalIdentitySources',
        ]);

        $this->setAutoViewVars([
            'servers' => [
                'type' => 'select',
                'model' => 'Servers',
                'where' => ['plugin' => 'CoreServer.Oauth2Servers']
            ],
            'api_tiers' => [
                'type'  => 'enum',
                'class' => 'OrcidSource.OrcidSourceTierEnum'
            ],
            'api_types' => [
                'type'  => 'enum',
                'class' => 'OrcidSource.OrcidSourceApiEnum'
            ],
            'addressTypes' => [
                'type' => 'type',
                'attribute' => 'Addresses.type'
            ],
            'defaultAffiliationTypes' => [
                'type'      => 'type',
                'attribute' => 'PersonRoles.affiliation_type'
            ],
            'emailAddressTypes' => [
                'type' => 'type',
                'attribute' => 'EmailAddresses.type'
            ],
            'nameTypes' => [
                'type' => 'type',
                'attribute' => 'Names.type'
            ],
            'telephoneNumberTypes' => [
                'type' => 'type',
                'attribute' => 'TelephoneNumbers.type'
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

        $this->orcidTokensTable = TableRegistry::getTableLocator()->get('OrcidSource.OrcidTokens');
        $this->oauth2ServersTable = TableRegistry::getTableLocator()->get('CoreServer.Oauth2Servers');
    }


    /**
     * Get the OAuth2 redirect URI for ORCID callbacks
     *
     * @param array $extra Additional URL parameters to include in redirect
     * @return string Full URL for OAuth2 redirect
     * @since  COmanage Registry v5.2.0
     */
    public function redirectUri(array $extra = []): string
    {
        $callback = [
            'plugin' => 'OrcidSource',
            'controller' => 'OrcidSourceCollectors',
            'action' => 'dispatch',
        ];

        if (!empty($extra)) {
            $callback = array_merge($callback, $extra);
        }

        return Router::url($callback, true);
    }

    /**
     * Obtain the set of changed records from the source database.
     *
     * @since  COmanage Registry v5.2.0
     * @param  ExternalIdentitySource $source     External Identity Source
     * @param  int                    $lastStart  Timestamp of last run
     * @param  int                    $curStart   Timestamp of current run
     * @return array|bool                         An array of changed source keys, or false
     */

    public function getChangeList(
        \App\Model\Entity\ExternalIdentitySource $source,
        int $lastStart, // timestamp of last run
        int $curStart   // timestamp of current run
    ): array|bool {
        return false;
    }

    /**
     * Obtain the full set of records from the source database.
     *
     * @since  COmanage Registry v5.2.0
     * @param  ExternalIdentitySource $source     External Identity Source
     * @return array                              An array of source keys
     */

    public function inventory(
        \App\Model\Entity\ExternalIdentitySource $source
    ): array {
        return false;
    }

    /**
     * Convert a record from the OrcidSource data to a record suitable for
     * construction of an Entity. This call is for use with Relational Mode.
     *
     * @since  COmanage Registry v5.2.0
     * @param  OrcidSource  $OrcidSource  OrcidSource configuration entity
     * @param  array      $result     Array of Orcid attributes
     * @return array                  Entity record (in array format)
     */

    protected function resultToEntityData(
        OrcidSource $OrcidSource,
        array $result
    ): array {
        // Build the External Identity as an array
        $eidata = [];

        // We don't currently have a field to record DoB, so we need to null it
        $eidata['date_of_birth'] = null;

        // Single value fields that map to the External Identity Role
        $role = [
            // We only support one role per record
            'role_key' => '1',
            'affiliation' => $this->DefaultAffiliationTypes->getTypeLabel($OrcidSource->default_affiliation_type_id)
        ];

        $eidata['external_identity_roles'][] = $role;

        $name = [
            'type' => $this->NameTypes->getTypeLabel($OrcidSource->name_type_id),
            'given' => $result['name']['given-names']['value'],
            'family' => $result['name']['family-name']['value']
        ];

        $eidata['names'][] = $name;

        foreach($result['emails']['email'] as $m) {
            $eidata['email_addresses'][] = [
                'mail' => $m['email'],
                'type' => $this->EmailAddressTypes->getTypeLabel($OrcidSource->email_address_type_id),
                'verified' => $m['verified']
            ];
        }

        if (!empty($result['addresses']['address'])) {
            $address = [];
            $address['type'] = $this->AddressTypes->getTypeLabel($OrcidSource->address_type_id);
            foreach($result['addresses']['address'] as $ad) {
                $address['country'] = $ad['country']['value'];
            }
            $eidata['addresses'][] = $address;
        }

        $eidata['identifiers'][] = [
            'identifier' => $result['name']['path'],
            'type' => 'orcid'
        ];

        return $eidata;
    }

    /**
     * Retrieve a record from the External Identity Source.
     *
     * @since  COmanage Registry v5.2.0
     * @param  ExternalIdentitySource $source     EIS Entity with instantiated plugin configuration
     * @param  string                 $source_key Backend source key for requested record
     * @return array                              Array of source_key, source_record, and entity_data
     * @throws InvalidArgumentException
     */

    public function retrieve(
        \App\Model\Entity\ExternalIdentitySource $source,
        string $source_key
    ): array {
        try {
            $this->httpClient = $this->orcidConnect($source, $source_key);

            $orcidbio = $this->orcidRequest('/v3.0/' . $source_key . '/person');
//            $orcidActivities = $this->orcidRequest('/v3.0/' . $source_key . '/activities');
        }
        catch(InvalidArgumentException $e) {
            throw new \InvalidArgumentException(__d('error', 'unknown.identifier', [$source_key]));
        }

        return [
            'source_key'    => $source_key,
            'source_record' => json_encode($orcidbio),
            'entity_data'   => $this->resultToEntityData($source->orcid_source, $orcidbio)
        ];
    }

    /**
     * Search the External Identity Source.
     * The ORCID search will be triggered by the CO/Platform Admin. As a result, we want to use a privileged access key
     * i.e. the one the CO Admin got in Oauth2Server setup page
     *
     * refrence: https://info.orcid.org/documentation/api-tutorials/api-tutorial-searching-the-orcid-registry/
     *
     * @param ExternalIdentitySource $source EIS Entity with instantiated plugin configuration
     * @param array $searchAttrs Array of search attributes and values, as configured by searchAttributes()
     * @return array                                Array of matching records
     * @since  COmanage Registry v5.2.0
     */

    public function search(
        \App\Model\Entity\ExternalIdentitySource $source,
        array $searchAttrs
    ): array {
        $ret = [];

        if(!isset($searchAttrs['q'])) {
            // For now, we only support free form search (though ORCID does support
            // search by eg email).

            return [];
        }

        // We just let search exceptions pop up the stack

        $this->httpClient = $this->orcidConnect($source);

        $records = $this->orcidRequest('/v3.0/search/', $searchAttrs);

        if(isset($records['num-found']) && $records['num-found'] > 0) {
            foreach($records['result'] as $rec) {
                if(!empty($rec['orcid-identifier']['path'])) {
                    $orcid = $rec['orcid-identifier']['path'];

                    $orcidbio = $this->orcidRequest('/v3.0/' . $orcid . '/person');

                    if(!empty($orcidbio)) {
                        $ret[ $orcid ] = $this->resultToEntityData($source->orcid_source, $orcidbio);
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * Get the set of searchable attributes for this backend.
     *
     * @since  COmanage Registry v5.2.0
     * @return array    Array of searchable attributes and localized descriptions
     */

    public function searchableAttributes(): array {
        return [
            'q' => __d('operation', 'search')
        ];
    }


    /**
     * Make an HTTP request to the ORCID API
     *
     * @param string $urlPath The API endpoint path to request
     * @param array $data Request parameters or body data
     * @param string $action HTTP method to use (get, post, etc)
     * @return array Response data decoded from JSON
     * @throws InvalidArgumentException If the ORCID identifier is invalid
     * @throws RuntimeException If the API request fails
     * @since COmanage Registry v5.2.0
     */
    public function orcidRequest(string $urlPath, array $data=[], string $action="get"): array
    {
        // Get the user access_token. If none is provided, then throw an exception
        $accessToken = match(true) {
            $this->orcidToken?->access_token !== null => $this->orcidTokensTable->getUnencrypted($this->orcidToken->access_token),
            $this->orcidSource?->server?->oauth2_server?->access_token !== null => $this->orcidSource->server->oauth2_server->access_token,
            default => throw new \InvalidArgumentException(__d('orcid_source', 'error.token.none'))
        };

        $options = [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/orcid+json'
            ]
        ];

        $orcidUrlBase = $this->orcidUrl($this->orcidSource->api_type,  $this->orcidSource->api_tier);
        $fullUrl = $orcidUrlBase . $urlPath;
        $response = $this->httpClient->$action(
            url: $fullUrl,
            data: ($action == 'get' ? $data : json_encode($data)),
            options: $options
        );

        if($response->getStatusCode() == HttpStatusCodesEnum::HTTP_BAD_REQUEST) {
            // Most likely retrieving an invalid ORCID
            throw new \InvalidArgumentException(__d('orcid_source', 'error.search', [$response->getStatusCode()]));
        }

        if($response->getStatusCode() != HttpStatusCodesEnum::HTTP_OK) {
            // This is probably an RDF blob, which is slightly annoying to parse.
            // Rather than do it properly since we don't parse RDF anywhere else,
            // we return a generic error.
            throw new \RuntimeException(__d('orcid_source', 'error.search', [$response->getStatusCode()]));
        }

        return $response->getJson();
    }

    /**
     * Get the root URL for the ORCID API.
     *
     * @param  string $api API type: auth, public, or member
     * @param  string $tier API tier: prod or sandbox
     * @return string URL prefix
     * @since  COmanage Registry v5.2.0
     */

    public function orcidUrl(string $api=OrcidSourceApiEnum::PUBLIC, string $tier=OrcidSourceTierEnum::PROD): string
    {
        $orcidUrls = [
            OrcidSourceApiEnum::AUTH => [
                OrcidSourceTierEnum::PROD    => 'https://orcid.org',
                OrcidSourceTierEnum::SANDBOX => 'https://sandbox.orcid.org'
            ],
            OrcidSourceApiEnum::MEMBERS => [
                OrcidSourceTierEnum::PROD    => 'https://api.orcid.org',
                OrcidSourceTierEnum::SANDBOX => 'https://api.sandbox.orcid.org'
            ],
            OrcidSourceApiEnum::PUBLIC => [
                OrcidSourceTierEnum::PROD    => 'https://pub.orcid.org',
                OrcidSourceTierEnum::SANDBOX => 'https://pub.sandbox.orcid.org'
            ]
        ];

        return $orcidUrls[$api][$tier];
    }


    /**
     * Establish connection to ORCID API by configuring the HTTP client with appropriate credentials.
     *
     * @param ExternalIdentitySource $exterrnalIdentitySource
     * @param string|null $orcidIdentifier The ORCID identifier to use for authentication
     * @return Client Configured HTTP client for ORCID API requests
     * @since COmanage Registry v5.2.0
     */
    protected function orcidConnect(
        \App\Model\Entity\ExternalIdentitySource $exterrnalIdentitySource,
        ?string $orcidIdentifier = null
    ): \Cake\Http\Client {
        $this->orcidSource = $this->find()
            ->contain([
                'Servers.Oauth2Servers' => function ($q) {
                    return $q->where(["LOWER(Oauth2Servers.url) LIKE" => '%orcid%']);
                },
                'ExternalIdentitySources',
            ])
            ->innerJoinWith('Servers.Oauth2Servers', function ($q) {
                return $q->where([
                    "LOWER(Oauth2Servers.url) LIKE" => '%orcid%'
                ]);
            })
            ->innerJoinWith('ExternalIdentitySources')
            ->where([
                'Servers.plugin' => 'CoreServer.Oauth2Servers',
                'ExternalIdentitySources.id' => $exterrnalIdentitySource->id,
                'ExternalIdentitySources.plugin' => 'OrcidSource.OrcidSources'
            ])
            ->first();

        // Set the CO ID
        $this->setCurCoId($this->orcidSource->server->co_id);

        if ( empty($this->orcidSource->id)) {
            throw new \InvalidArgumentException(__d('error', 'notfound', [__d('core_server', 'controller.Oauth2Servers')]));
        }

        // Since this is null, we will use the master access token stored in Oauth2Server Configuration
        if ($orcidIdentifier !== null) {
            $this->orcidToken = $this->orcidTokensTable
                ->find()
                ->where([
                    'orcid_source_id' => $this->orcidSource->id,
                    'orcid_identifier' => $orcidIdentifier,
                ])
                ->first();

            if (empty($this->orcidToken->access_token)) {
                throw new \InvalidArgumentException(__d('orcid_source', 'error.token.none'));
            }
        }

        return $this->oauth2ServersTable->createHttpClient($this->orcidSource->server->oauth2_server->id);
    }

    /**
     * Set validation rules.
     *
     * @since  COmanage Registry v5.2.0
     * @param  Validator $validator Validator
     * @return Validator            Validator
     * @throws InvalidArgumentException
     * @throws RecordNotFoundException
     */

    public function validationDefault(Validator $validator): Validator {
        $schema = $this->getSchema();

        foreach([
                    'external_source_identity_id',
                    'default_affiliation_type_id',
                    'address_type_id',
                    'email_address_type_id',
                    'name_type_id',
                    'telephone_number_type_id'
                ] as $field) {
            $validator->add($field, [
                'content' => ['rule' => 'isInteger']
            ]);
            $validator->notEmptyString($field);
        }

        $validator->add('server_id', [
            'content' => ['rule' => 'isInteger']
        ]);
        $validator->allowEmptyString('server_id');

        $validator->add('api_tier', [
            'content' => ['rule' => ['inList', OrcidSourceTierEnum::getConstValues()]]
        ]);
        $validator->allowEmptyString('api_tier');

        $validator->add('api_type', [
            'content' => ['rule' => ['inList', OrcidSourceApiEnum::getConstValues()]]
        ]);
        $validator->allowEmptyString('api_type');

        $validator->add('scope_inherit', [
            'content' => ['rule' => 'boolean']
        ]);
        $validator->allowEmptyString('scope_inherit');

        return $validator;
    }
}