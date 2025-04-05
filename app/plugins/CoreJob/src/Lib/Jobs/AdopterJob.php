<?php
/**
 * COmanage Registry Adopter Job
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace CoreJob\Lib\Jobs;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use \App\Lib\Enum\JobStatusEnum;
use \App\Lib\Enum\SyncModeEnum;

class AdopterJob {
  use \App\Lib\Traits\LabeledLogTrait;

  /**
   * Obtain the list of parameters supported by this Job.
   *
   * @since  COmanage Registry v5.1.0
   * @return Array Array of supported parameters.
   * @throws \InvalidArgumentException
   * @throws \RuntimeException
   */

  public function parameterFormat(): array {
    return [
      'external_identity_source_id' => [
        'help'      => __d('core_job', 'opt.adopter.external_identity_source_id'),
        'type'      => 'fk',
        'required'  => true
      ],
      'source_keys' => [
        'help'      => __d('core_job', 'opt.adopter.source_keys'),
        'type'      => 'string',
        'required'  => false
      ]
    ];
  }

  /**
   * Run the requested Job.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  JobsTable              $JobsTable              Jobs table, for updating the Job status
   * @param  JobHistoryRecordsTable $JobHistoryRecordsTable Job History Records table, for recording additional history
   * @param  Job                    $job                    Job entity
   * @param  array                  $parameters             Parameters for this Job
   */

  public function run(
    \App\Model\Table\JobsTable $JobsTable,
    \App\Model\Table\JobHistoryRecordsTable $JobHistoryRecordsTable,
    \App\Model\Entity\Job $job, 
    array $parameters
  ) {
    $count = 0;   // Count of records successfully processed
    $errors = 0;  // Count of records that had errors
    $todo = [];   // The set of source keys to process

    // Pull the EIS configuration
    $EISTable = TableRegistry::getTableLocator()->get('ExternalIdentitySources');
    $EITable = TableRegistry::getTableLocator()->get('ExternalIdentities');

    $eis = $EISTable->get($parameters['external_identity_source_id']);

    // The EIS must be disabled to prevent conflicts (and to encourage the admin to
    // consider whether it should be enabled after the adoption process runs.)

    if($eis->status != SyncModeEnum::Disabled) {
      throw new \InvalidArgumentException(__d('core_job', 'Adopter.error.status'));
    }

    if(!empty($parameters['source_keys'])) {
      $todo = explode(',', $parameters['source_keys']);

      $JobsTable->start(
        job: $job, 
        summary: __d('core_job', 'Adopter.start_summary.keys', [count($todo), $eis->id])
      );
    } else {
      // Note inventory() loads all records into memory, so we might have issues with
      // extremely large datasets.
      $todo = $EISTable->inventory($eis->id);

      $JobsTable->start(
        job: $job, 
        summary: __d('core_job', 'Adopter.start_summary.eis', [$eis->id])
      );
    }

    $this->llog('trace', "Adopting " . count($todo) . " record(s) from EIS "
                          . $eis->description . " (job " . $job->id . ")");

    foreach($todo as $sourceKey) {
      try {
        // We need to map the $sourceKey to an External Identity, which we do via
        // the EIS Record.

        $eisrecord = $EISTable->ExtIdentitySourceRecords->find()
                                                        ->where([
                                                          'external_identity_source_id' => $eis->id,
                                                          'source_key' => $sourceKey
                                                        ])
                                                        ->firstOrFail();

        if(!empty($eisrecord->adopted_person_id)) {
          throw new \InvalidArgumentException(__d('core_job', 'Adopter.error.adopted', [$eisrecord->adopted_person_id]));
        }

        if(empty($eisrecord->external_identity_id)) {
          throw new \InvalidArgumentException(__d('core_job', 'Adopter.error.synced'));
        }

        $this->llog('trace', "Mapped source key $sourceKey to External Identity " . $eisrecord->external_identity_id);
        
        $personId = $EITable->adopt($eisrecord->external_identity_id);

        $this->llog('trace', "Adopted External Identity " . $eisrecord->external_identity_id . " as Person " . $personId);

        $JobHistoryRecordsTable->record(
          jobId: $job->id,
          recordKey: $sourceKey,
          comment: __d('core_job', 'Adopter.result.adopted', [$eisrecord->external_identity_id, $personId]),
          status: JobStatusEnum::Complete
        );

        $count++;
      }
      catch(\Exception $e) {
        $this->llog('trace', "$sourceKey could not be adopted: " . $e->getMessage());

        $JobHistoryRecordsTable->record(
          jobId: $job->id,
          recordKey: $sourceKey,
          comment: $e->getMessage(),
          status: JobStatusEnum::Failed
        );

        $errors++;
      }
    }

    $JobsTable->finish(job: $job, summary: __d('core_job', 'Adopter.finish_summary.count', [$count, $errors]));
  }
}