<?php
/**
 * COmanage Registry Orcid Source Collectors Controller
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

use App\Controller\StandardEnrollerController;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use OrcidSource\Lib\Enum\OrcidSourceScopeEnum;

class OrcidSourceCollectorsController extends StandardEnrollerController {
    protected $OrcidSources;
    protected $PetitionOrcids;

    public $paginate = [
        'order' => [
            'OrcidSourceCollectors.id' => 'asc'
        ]
    ];

    /**
     * Callback run prior to the request action.
     *
     * @since  COmanage Registry v5.2.0
     * @param  EventInterface $event Cake Event
     */
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        $this->OrcidSources = TableRegistry::getTableLocator()->get('OrcidSource.OrcidSources');
        $this->PetitionOrcids = TableRegistry::getTableLocator()->get('OrcidSource.PetitionOrcids');

        return parent::beforeFilter($event);
    }

    /**
     * Callback run prior to the request render.
     *
     * @param   EventInterface  $event  Cake Event
     *
     * @return Response|void
     * @since  COmanage Registry v5.2.0
     */

    public function beforeRender(EventInterface $event) {
        $link = $this->getPrimaryLink(true);

        if(!empty($link->value)) {
            $this->set('vv_bc_parent_obj', $this->OrcidSourceCollectors->EnrollmentFlowSteps->get($link->value));
            $this->set('vv_bc_parent_displayfield', $this->OrcidSourceCollectors->EnrollmentFlowSteps->getDisplayField());
            $this->set('vv_bc_parent_primarykey', $this->OrcidSourceCollectors->EnrollmentFlowSteps->getPrimaryKey());
        }

        return parent::beforeRender($event);
    }

    /**
     * Dispatch an Enrollment Flow Step.
     *
     * @since  COmanage Registry v5.2.0
     * @param  string  $id    Env Source Collector ID
     */

    public function dispatch(string $id) {
        $request = $this->getRequest();
        $session = $request->getSession();

        $op = $this->requestParam('op');
        $code = $this->getRequest()->getQuery('code') ?? null;
        $petition = $this->getPetition();

        $this->set('vv_op', $op);

        $oricdSourceEntity = $this->OrcidSourceCollectors->get(
            (int)$id,
            [
                'contain' => [
                    'ExternalIdentitySources' => ['OrcidSources' => ['Servers']]
                ]]
        );

        $ServerModel = $oricdSourceEntity->external_identity_source->orcid_source->server->plugin;
        $PluginServersTable = TableRegistry::getTableLocator()->get($ServerModel);
        $serverId = $oricdSourceEntity->external_identity_source->orcid_source->server->id;
        $PluginServerEntity = $PluginServersTable    ->find()
            ->where(['server_id' => $serverId])
            ->first();


        $this->set('vv_config', $oricdSourceEntity);
        $this->set('vv_config_server', $PluginServerEntity);
        $this->set('controller', $this);

        try {
            // Let's authenticate first
            if ($op == 'authenticate') {
                $this->authenticate($id, $PluginServerEntity);
            } else if (!empty($code) && $op !== 'savetoken') {
                $response = $PluginServersTable->exchangeCode(
                    $id,
                    $code,
                    $this->OrcidSources->redirectUri(
                        [
                            $id,
                            '?' => ['petition_id' => $petition->id],
                        ]
                    ),
                    false
                );

                // Use the response and save the data to petitions table
                if(empty($response->orcid)) {
                    throw new \RuntimeException(__d('orcid_source', 'error.orcid_source.no_orcid'));
                }
                $this->set('vv_orcid', $response->orcid);
                $this->set('vv_token', $response);
            } if (!empty($code) && $op === 'savetoken') {
                $orcid_token = $this->requestParam('orcid_token');
                $this->PetitionOrcids->record(
                    petitionId: $petition->id,
                    enrollmentFlowStepId: $oricdSourceEntity->enrollment_flow_step_id,
                    orcidToken: $orcid_token,
                    orcidSourceCollectorId: (int)$id,
                );
                // On success, indicate the step is completed and generate a redirect
                // to the next step

                return $this->finishStep(
                    enrollmentFlowStepId: $oricdSourceEntity->enrollment_flow_step_id,
                    petitionId:           $petition->id,
                    comment:              __d('orcid_source', 'result.orcid.saved')
                );
            } else {
                // Fall Through. Let the view render
            }

        }
        catch(\Exception $e) {
            $this->Flash->error($e->getMessage());
        }

        // Fall through and let the form render

        $this->render('/Standard/dispatch');
    }


    /**
     * Authenticate the user with ORCID OAuth2 server
     *
     * @param string|int $id ID of the collector
     * @param EntityInterface $serverCfg ORCID Server configuration
     * @return void
     * @since COmanage Registry v5.2.0
     */
    protected function authenticate(string|int $id, EntityInterface $serverCfg): void
    {
        $petition = $this->getPetition();
        $callback = $this->OrcidSources->redirectUri([
            $id,
            '?' => ['petition_id' => $petition->id],
        ]);
        // Build the redirect URI
        $redirectUri = Router::url($callback, true);

        $scope = OrcidSourceScopeEnum::DEFAULT_SCOPE;
        if (!empty($serverCfg->scope_inherit)) {
            $scope = $serverCfg->scope_inherit;
        }

        $url = $serverCfg->url . '/authorize?';
        $url .= 'client_id=' . $serverCfg->clientid;
        $url .= '&response_type=code';
        $url .= '&scope=' . str_replace(' ', '%20', $scope);
        $url .= '&redirect_uri=' . urlencode($redirectUri);

        $this->redirect($url);
    }

    /**
     * Indicate whether this Controller will handle some or all authnz.
     *
     * @since  COmanage Registry v5.2.0
     * @param  EventInterface   $event  Cake event, ie: from beforeFilter
     * @return string                   "no", "open", "authz", or "yes"
     */

    public function willHandleAuth(\Cake\Event\EventInterface $event): string {
        $request = $this->getRequest();
        $action = $request->getParam('action');

        if($action == 'dispatch') {
            // We need to perform special logic (vs StandardEnrollerController)
            // to ensure that web server authentication is triggered.
            // (This is the same logic as IdentifierCollectorsController.)
// XXX We could maybe move this into StandardEnrollerController with a flag like
// $this->alwaysAuthDispatch(true);

            // To start, we trigger the parent logic. This will return
            //  notauth: Some error occurred, we don't want to override this
            //  authz: No token in use
            //  yes: Token validated

            $auth = parent::willHandleAuth($event);

            // The only status we need to override is 'yes', since we always want authentication
            // to run in order to be able to grab $REMOTE_USER.

            return ($auth == 'yes' ? 'authz' : $auth);
        }

        return parent::willHandleAuth($event);
    }
}
