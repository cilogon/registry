<?php
/**
 * COmanage Registry Orcid Source Collectors Table
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

namespace OrcidSource\Model\Table;

use App\Model\Entity\Petition;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\PetitionActionEnum;

class OrcidSourceCollectorsTable extends Table {
    use \App\Lib\Traits\AutoViewVarsTrait;
    use \App\Lib\Traits\CoLinkTrait;
    use \App\Lib\Traits\LayoutTrait;
    use \App\Lib\Traits\LabeledLogTrait;
    use \App\Lib\Traits\PermissionsTrait;
    use \App\Lib\Traits\PrimaryLinkTrait;
    use \App\Lib\Traits\TableMetaTrait;
    use \App\Lib\Traits\ValidationTrait;
    use \App\Lib\Traits\TabTrait;

    /**
     * Perform Cake Model initialization.
     *
     * @since  COmanage Registry v5.2.0
     * @param  array  $config Configuration options passed to constructor
     */

    public function initialize(array $config): void {
        parent::initialize($config);

        $this->addBehavior('Changelog');
        $this->addBehavior('Log');
        $this->addBehavior('Timestamp');

        $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);

        // Define associations

        $this->belongsTo('EnrollmentFlowSteps');
        $this->belongsTo('ExternalIdentitySources');

        $this->setDisplayField('id');

        $this->setPrimaryLink('enrollment_flow_step_id');
        $this->setRequiresCO(true);
        $this->setAllowLookupPrimaryLink(['dispatch', 'display']);

        // All the tabs share the same configuration in the ModelTable file
        $this->setTabsConfig(
            [
                // Ordered list of Tabs
                'tabs' => ['EnrollmentFlowSteps', 'OrcidSource.OrcidSourceCollectors'],
                // What actions will include the subnavigation header
                'action' => [
                    // If a model renders in a subnavigation mode in edit/view mode, it cannot
                    // render in index mode for the same use case/context
                    // XXX edit should go first.
                    'EnrollmentFlowSteps' => ['edit', 'view'],
                    'OrcidSource.OrcidSourceCollectors' => ['edit'],
                ]
            ]
        );

        $this->setAutoViewVars([
            'externalIdentitySources' => [
                'type' => 'select',
                'model' => 'ExternalIdentitySources',
                'where' => ['plugin' => 'OrcidSource.OrcidSources']
            ]
        ]);

        $this->setPermissions([
            // Actions that operate over an entity (ie: require an $id)
            'entity' => [
                'delete' =>   false, // Delete the pluggable object instead
                'dispatch' => true,
                'display' =>  true,
                'edit' =>     ['platformAdmin', 'coAdmin'],
                'view' =>     ['platformAdmin', 'coAdmin']
            ],
            // Actions that operate over a table (ie: do not require an $id)
            'table' => [
                'add' =>      false, // This is added by the parent model
                'index' =>    ['platformAdmin', 'coAdmin']
            ]
        ]);
    }

    /**
     * Perform steps necessary to hydrate the Person record as part of Petition finalization.
     *
     * @param int $id Env Source Collector ID
     * @param Petition $petition Petition
     * @return bool                   true on success
     * @since  COmanage Registry v5.2.0
     */

    public function hydrate(int $id, \App\Model\Entity\Petition $petition) {
        $orcidSourceCollectorsEntity = $this->get($id);

        $PetitionHistoryRecords = TableRegistry::getTableLocator()->get('PetitionHistoryRecords');
        $PetitionOrcids = TableRegistry::getTableLocator()->get('OrcidSource.PetitionOrcids');
        $OrcidSources = TableRegistry::getTableLocator()->get('OrcidSource.OrcidSources');
        $OrcidTokens = TableRegistry::getTableLocator()->get('OrcidSource.OrcidTokens');
        $ExtIdentitySources = TableRegistry::getTableLocator()->get('ExternalIdentitySources');


        $orcid_source = $OrcidSources->find()
            ->where(['external_identity_source_id' => $orcidSourceCollectorsEntity->external_identity_source_id])
            ->first();

        $pOricd = $PetitionOrcids
            ->find()
            ->where(
            ['petition_id' => $petition->id, 'orcid_source_collector_id' => $id])
            ->first();

        if(!empty($pOricd->orcid_token)) {
            // Copy the Identifier to the Person record in accordance with the configuration
            $token = unserialize($pOricd->orcid_token);
            $data = [
                'orcid_identifier' => $token->orcid,
                'access_token' => $token->access_token,
                'refresh_token' => $token->refresh_token ?? '',
                'id_token' => $token->id_token ?? '',
                'orcid_source_id' => $orcid_source->id
            ];

            $OrcidTokens->upsertOrFail(
                data: $data,
                whereClause: [
                    'orcid_source_id' => $orcid_source->id,
                    'orcid_identifier' => $token->orcid
                ],
            );

            // Continue on to process the sync
            // Trigger the ExternalIdentitySource sync and push the data to the pipeline
            $status = $ExtIdentitySources->sync(
                id: $orcidSourceCollectorsEntity->external_identity_source_id,
                sourceKey: $token->orcid,
                personId: $petition->enrollee_person_id,
                syncOnly: true
            );

            // Record Petition History - Token Save
            $PetitionHistoryRecords->record(
                petitionId:           $petition->id,
                enrollmentFlowStepId: $orcidSourceCollectorsEntity->enrollment_flow_step_id,
                action:               PetitionActionEnum::AttributesUpdated,
                comment:              __d('orcid_source', 'result.orcid.saved')
            );

            // Record Petition History - Pipeline save
            $PetitionHistoryRecords->record(
                petitionId:           $petition->id,
                enrollmentFlowStepId: $orcidSourceCollectorsEntity->enrollment_flow_step_id,
                action:               PetitionActionEnum::Finalized,
                comment:              __d('orcid_source', 'result.pipeline.status', [$status])
            );
        }

        return true;
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

        $validator->add('enrollment_flow_step_id', [
            'content' => ['rule' => 'isInteger']
        ]);
        $validator->notEmptyString('enrollment_flow_step_id');

        $validator->add('external_identity_source_id', [
            'content' => ['rule' => 'isInteger']
        ]);
        $validator->notEmptyString('external_identity_source_id');

        return $validator;
    }
}