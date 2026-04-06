<?php
/**
 * COmanage Registry Deletion Job
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

namespace CoreJob\Lib\Jobs;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use \App\Lib\Enum\JobStatusEnum;
use \App\Lib\Enum\SyncModeEnum;

class DeletionJob {
  use \App\Lib\Traits\LabeledLogTrait;

  /**
   * Obtain the list of parameters supported by this Job.
   *
   * @since  COmanage Registry v5.2.0
   * @return Array Array of supported parameters.
   * @throws \InvalidArgumentException
   * @throws \RuntimeException
   */

  public function parameterFormat(): array {
    return [
      'target_model' => [
        'help'      => __d('core_job', 'opt.deletion.target_model'),
        'type'      => 'select',
        'choices'   => ['Cos'],
        'required'  => true
      ],
      'target_id' => [
        'help'      => __d('core_job', 'opt.deletion.target_id'),
        'type'      => 'integer',
        'required'  => true
      ]
    ];
  }

  /**
   * Run the requested Job.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  JobsTable              $JobsTable              Jobs table, for updating the Job status
   * @param  JobHistoryRecordsTable $JobHistoryRecordsTable Job History Records table, for recording additional history
   * @param  Job                    $job                    Job entity
   * @param  array                  $parameters             Parameters for this Job
   * @throws InvalidArgumentException
   */

  public function run(
    \App\Model\Table\JobsTable $JobsTable,
    \App\Model\Table\JobHistoryRecordsTable $JobHistoryRecordsTable,
    \App\Model\Entity\Job $job, 
    array $parameters
  ) {
    // Check that the requesting CO is the COmanage CO.

    $requestingCO = $JobsTable->Cos->get($job->co_id);

    if(!$requestingCO->isCOmanageCO()) {
      throw new \InvalidArgumentException(__d('core_job', 'Deletion.error.co'));
    }

    // We currently only support deleting COs.

    if($parameters['target_model'] != 'Cos') {
      throw new \InvalidArgumentException(__d('core_job', 'Deletion.error.model'));
    }

    // We need to pull the CO to call delete on it.

    // get() will throw an exception on an invalid CO ID
    $targetCO = $JobsTable->Cos->get($parameters['target_id']);

    $JobsTable->start(job: $job, summary: __d('core_job', 'Deletion.start_summary', [$parameters['target_model'], $parameters['target_id']]));

    $JobsTable->Cos->deleteOrFail($targetCO);

    $JobsTable->finish(job: $job, summary: __d('core_job', 'Deletion.finish_summary'));
  }
}