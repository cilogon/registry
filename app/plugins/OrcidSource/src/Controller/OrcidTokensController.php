<?php
/**
 * COmanage Registry Orcid Tokens Controller
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

use App\Controller\StandardController;
use Cake\Event\EventInterface;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use OrcidSource\Lib\Enum\OrcidSourceScopeEnum;
use \App\Lib\Enum\HttpStatusCodesEnum;

class OrcidTokensController extends StandardController {

    /** @var OrcidSource */
    protected $orcidSources;

    /**
     * Callback run prior to the request action.
     *
     * @since  COmanage Registry v5.2.0
     * @param  EventInterface $event Cake Event
     * @return \Cake\Http\Response   HTTP Response
     */

    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        // $this->name = Models
        $modelsName = $this->name;

        $coid = $this->request->getQuery('co_id');
        if (empty($coid)) {
            $this->response = $this->response->withStatus(
                HttpStatusCodesEnum::HTTP_BAD_REQUEST,
                __d('orcid_source', 'error.param.notfound', [__d('controller', 'Cos')])
            );
            $this->response->send();
            $this->getEventManager()->off($event->getName()); // Prevent further event firing
            $this->autoRender = false;
            return;
        }

        $orcid = $this->request->getQuery('orcid');
        if (empty($orcid)) {
            $this->response = $this->response->withStatus(
                HttpStatusCodesEnum::HTTP_BAD_REQUEST,
                __d('orcid_source', 'error.param.notfound', [__d('orcid_source', 'information.orcid_source.identifier')])
            );
            $this->response->send();
            $this->getEventManager()->off($event->getName()); // Prevent further event firing
            $this->autoRender = false;
            return;
        }

        $this->orcidSources = $this->OrcidSources
            ->find()
            ->contain([]) // No related records loaded
            ->innerJoinWith('Oauth2Servers', function ($q) {
                return $q->where([
                    "LOWER(Oauth2Servers.url) LIKE" => '%orcid%'
                ]);
            })
            ->where([
                'Servers.plugin' => 'CoreServer.Oauth2Servers',
                'ExternalIdentitySources.co_id' => $coid
            ])
            ->disableHydration()
            ->toArray();

        return parent::beforeFilter();
    }


    /**
     * Retrieve ORCID tokens for a given ORCID identifier
     *
     * @return void
     * @throws \Cake\Http\Exception\MethodNotAllowedException If request method is not allowed
     * @since  COmanage Registry v5.2.0
     */
    public function token()
    {
        // Allow only AJAX and GET requests
        $this->request->allowMethod(['ajax', 'get']);

        // Set AJAX layout
        $this->viewBuilder()->setLayout('ajax');

        // Extract OrcidSource IDs
        $orcid_source_ids = Hash::extract($this->orcidSources, '{n}.OrcidSource.id');

        // Get ORCID identifier from query string
        $orcid = $this->request->getQuery('orcid');

        // Find token records from the database
        $tokens = $this->OrcidTokens->find()
            ->where([
                'OrcidTokens.orcid_identifier' => $orcid,
                'OrcidTokens.orcid_source_id IN' => $orcid_source_ids
            ])
            ->all();

        $columnsToDecrypt = [
            'access_token',
            'id_token',
            'refresh_token'
        ];

        $data = [];
        if (!$tokens->isEmpty()) {
            foreach ($tokens as $idx => $token) {
                $data[$idx] = [];
                $data[$idx]['orcid'] = $token->orcid_identifier;
                $orcidSourceIndex = array_search($token->orcid_source_id, $orcid_source_ids);
                $data[$idx]['scopes'] = $this->getOauth2ServerScopes(
                    $this->orcidSources[$orcidSourceIndex]['Server'],
                    $this->orcidSources[$orcidSourceIndex]['OrcidSource']
                );
                foreach ($columnsToDecrypt as $column) {
                    $value = $token->{$column} ?? null;
                    $data[$idx][$column] = !empty($value) ? $this->OrcidTokens->getUnencrypted($value) : '';
                }
            }
        }

        // Return data in structured format
        $this->set('orcid_tokens', $data);
        $this->set('vv_model_name', 'OrcidTokens');
        $this->set('vv_table_name', 'orcid_tokens');

        // Let the view render
        $this->render('/Standard/api/v2/json/index');
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

            if ($oauth2Server) {
                return $oauth2Server->scope;
            }
        }

        return OrcidSourceScopeEnum::DEFAULT_SCOPE;
    }
}
