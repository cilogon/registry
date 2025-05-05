<?php
/**
 * COmanage Registry Petition Orcid Table
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

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\PetitionActionEnum;

class PetitionOrcidsTable extends Table {
    use \App\Lib\Traits\CoLinkTrait;
    use \App\Lib\Traits\PermissionsTrait;
    use \App\Lib\Traits\PrimaryLinkTrait;
    use \App\Lib\Traits\TableMetaTrait;
    use \App\Lib\Traits\UpsertTrait;
    use \App\Lib\Traits\ValidationTrait;

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

        $this->setTableType(\App\Lib\Enum\TableTypeEnum::Artifact);

        // Define associations
        $this->belongsTo('Petitions');
        $this->belongsTo('OrcidSource.OrcidSourceCollectors');

        $this->setDisplayField('orcid_token');

        $this->setPrimaryLink('petition_id');
        $this->setRequiresCO(true);

        $this->setPermissions([
            // Actions that operate over an entity (ie: require an $id)
            'entity' => [
                'delete' =>   false,
                'edit' =>     false,
                'view' =>     ['platformAdmin', 'coAdmin']
            ],
            // Actions that operate over a table (ie: do not require an $id)
            'table' => [
                'add' =>      false,
                'index' =>    false
            ]
        ]);
    }

    /**
     * Record an ORCID Token.
     *
     * @param int $petitionId Petition ID
     * @param int $enrollmentFlowStepId Enrollment Flow Step ID
     * @param int $orcidSourceCollectorId ORCID Source Collector ID
     * @param string $orcid_token ORCID Token Response serialized
     * @return void
     * @since  COmanage Registry v5.2.0
     */

    public function record(
        int $petitionId,
        int $enrollmentFlowStepId,
        int $orcidSourceCollectorId,
        string $orcidToken,
    ): void {
        // Record the Identifier. We use upsert since at least initially we only support
        // one Identifier per Petition.

        $orcid = unserialize($orcidToken);

        $orcidData = [
            'petition_id' => $petitionId,
            'orcid_token'  => $orcidToken,
            'orcid_identifier' => $orcid->orcid,
            'orcid_source_collector_id' => $orcidSourceCollectorId,
        ];

        $this->upsertOrFail(
            data: $orcidData,
            whereClause: ['petition_id' => $petitionId, 'orcid_identifier' => $orcid->orcid, 'orcid_source_collector_id' => $orcidSourceCollectorId],
        );

        // Record PetitionHistory
        $PetitionHistoryRecords = TableRegistry::getTableLocator()->get('PetitionHistoryRecords');
        $PetitionHistoryRecords->record(
            petitionId:           $petitionId,
            enrollmentFlowStepId: $enrollmentFlowStepId,
            action:               PetitionActionEnum::AttributesUpdated,
            comment:              __d('orcid_source', 'result.OrcidSourceCollector.collected', [$orcid->orcid])
// We don't have $actorPersonId yet...
//    ?int $actorPersonId=null
        );
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

        $validator->add('orcid_source_collector_id', [
            'content' => ['rule' => 'isInteger']
        ]);
        $validator->notEmptyString('orcid_source_collector_id');

        $validator->add('petition_id', [
            'content' => ['rule' => 'isInteger']
        ]);
        $validator->notEmptyString('petition_id');

        $this->registerStringValidation($validator, $schema, 'orcid_token', true);

        return $validator;
    }
}
