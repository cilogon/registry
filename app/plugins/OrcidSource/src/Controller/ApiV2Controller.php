<?php
/**
 * COmanage Registry Api Sources API v2 Controller
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace OrcidSource\Controller;

use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Event\EventInterface;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use OrcidSource\Lib\Enum\OrcidSourceScopeEnum;
use \App\Controller\StandardApiController;
use \App\Lib\Enum\HttpStatusCodesEnum;

class ApiV2Controller extends StandardApiController {
    protected $orcidSources;
    protected $orcidTokens;

    /**
     * Calculate the CO ID associated with the request.
     *
     * @since  COmanage Registry v5.2.0
     * @return int      CO ID, or null if no CO contextwas found
     */

    public function calculateRequestedCOID(): ?int {
        return (int)$this->request->getParam('coId') ?? null;
    }


    public function initialize(): void {
        parent::initialize();
        $this->OrcidSources = TableRegistry::getTableLocator()->get('OrcidSource.OrcidSources');
        $this->OrcidTokens = TableRegistry::getTableLocator()->get('OrcidSource.OrcidTokens');
    }

    /**
     * Calculate authorization for the current request.
     *
     * @since  COmanage Registry v5.2.0
     * @return bool     True if the current request is permitted, false otherwise
     */

    public function calculatePermission(): bool {
        $authUser = $this->RegistryAuth->getAuthenticatedUser();
        $coid = $this->calculateRequestedCOID();

        return $authUser !== null
            && $this->RegistryAuth->isApiUser()
            && ($this->RegistryAuth->isPlatformAdmin() || $this->RegistryAuth->isCoAdmin($coid));
    }

    /**
     * Handle a Get SOR Person Role request.
     *
     * @param string $orcid
     * @param int $coId
     * @since  COmanage Registry v5.2.0
     */

    public function get(string $orcid, int $coId) {
        try {
            $orcidSourcesRecords = $this->OrcidSources
                ->find()
                ->contain([
                    'Servers',
                    'ExternalIdentitySources',
                ])
                ->innerJoinWith('Servers')
                ->innerJoinWith('ExternalIdentitySources')
                ->where([
                    'Servers.plugin' => 'CoreServer.Oauth2Servers',
                    'ExternalIdentitySources.co_id' => $coId,
                    'ExternalIdentitySources.plugin' => 'OrcidSource.OrcidSources'
                ])
                ->disableHydration()
                ->all()
                ->toArray();

            // Extract OrcidSource IDs
            $orcid_source_ids = Hash::extract($orcidSourcesRecords, '{n}.id');

            // Find token records from the database
            $tokens = $this->OrcidTokens->find()
                ->where([
                    'OrcidTokens.orcid_identifier' => $orcid,
                    'OrcidTokens.orcid_source_id IN' => $orcid_source_ids
                ])
                ->disableHydration()
                ->all()
                ->toArray();

            $columnsToDecrypt = [
                'access_token',
                'id_token',
                'refresh_token'
            ];

            if (count($tokens) === 0) {
                throw new RecordNotFoundException(__d('orcid_source', 'error.param.notfound', [__d('orcid_source', 'information.orcid_source.identifier')]));
            }

            foreach ($tokens as $idx => $token) {
                $orcidSourceIndex = array_search($token['orcid_source_id'], $orcid_source_ids);
                $tokens[$idx]['scopes'] = $this->getOauth2ServerScopes(
                    $orcidSourcesRecords[$orcidSourceIndex]['server'],
                    $orcidSourcesRecords[$orcidSourceIndex]
                );
                foreach ($columnsToDecrypt as $column) {
                    $value = $token[$column] ?? null;
                    $tokens[$idx][$column] = !empty($value) ? $this->OrcidTokens->getUnencrypted($value) : '';
                }
            }

            // Return data in structured format
            $this->set('orcid_tokens', $tokens);
            $this->set('vv_model_name', 'OrcidTokens');
            $this->set('vv_table_name', 'orcid_tokens');
        }
        catch(RecordNotFoundException $e) {
            // Return 404
            $this->response = $this->response->withStatus(
                HttpStatusCodesEnum::HTTP_NOT_FOUND,
                $e->getMessage()
            );
            $this->autoRender = false;
            return;
        }
        catch(\Exception $e) {
            // Return 400
            $this->response = $this->response->withStatus(
                HttpStatusCodesEnum::HTTP_BAD_REQUEST,
                $e->getMessage()
            );
            $this->autoRender = false;
            return;
        }

        // Let the view render
        $this->render('/Standard/api/v2/json/index');
    }

    /**
     * Indicate whether this Controller will handle some or all authnz.
     *
     * @since  COmanage Registry v5.2.0
     * @param  EventInterface   $event  Cake event, ie: from beforeFilter
     * @return string                   "no", "open", "authz", or "yes"
     */

    public function willHandleAuth(\Cake\Event\EventInterface $event): string {
        // We always take over authz
        return 'authz';
    }

    /**
     * Get the scopes
     *
     * @param array $server         Server Record
     * @param array $orcidSource    OrcidSource record
     *
     * @return string List of scopes
     * @since  COmanage Registry v5.2.0
     */

    public function getOauth2ServerScopes(array $server, array $orcidSource): string
    {
        if(is_bool($orcidSource['scope_inherit']) && $orcidSource['scope_inherit']) {
            $Oauth2ServersTable = TableRegistry::getTableLocator()->get('Oauth2Servers');
            $oauth2Server = $Oauth2ServersTable->find()
                ->select(['scope'])
                ->where(['server_id' => $server['id']])
                ->first();

            if ($oauth2Server && !empty($oauth2Server->scope)) {
                return $oauth2Server->scope;
            }
        }

        return OrcidSourceScopeEnum::DEFAULT_SCOPE;
    }
}
