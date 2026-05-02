<?php
/**
 * COmanage Registry Nester Job
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

use Cake\ORM\TableRegistry;

class NesterJob {
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
      'group_id' => [
        'help'      => __d('core_job', 'opt.nester.group_id'),
        'type'      => 'integer',
        'required'  => true
      ],
      'target_group_id' => [
        'help'      => __d('core_job', 'opt.nester.target_group_id'),
        'type'      => 'integer',
        'required'  => true
      ],
      'negate' => [
        'help'      => __d('core_job', 'opt.nester.negate'),
        'type'      => 'bool',
        'required'  => false
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
    // GMR-2 will prevent nesting Groups across COs, all we need to do is create a
    // Group Nesting and save it.

    $GroupNestings = TableRegistry::getTableLocator()->get('GroupNestings');

    $nesting = $GroupNestings->newEntity([
      'group_id' => $parameters['group_id'],
      'target_group_id' => $parameters['target_group_id'],
      'negate' => isset($parameters['negate']) && $parameters['negate']
    ]);

    $JobsTable->start(job: $job, summary: __d('core_job', 'Nester.start_summary', [$parameters['group_id'], $parameters['target_group_id']]));

    $GroupNestings->save($nesting, ['job' => $job]);

    $JobsTable->finish(job: $job, summary: __d('core_job', 'Nester.finish_summary'));
  }
}