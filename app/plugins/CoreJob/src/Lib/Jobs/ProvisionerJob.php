<?php
/**
 * COmanage Registry Provisioner Job
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
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace CoreJob\Lib\Jobs;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use \App\Lib\Enum\JobStatusEnum;
use \App\Lib\Enum\ProvisionerModeEnum;
use \App\Lib\Enum\ProvisioningContextEnum;

class ProvisionerJob {
  /**
   * Obtain the list of parameters supported by this Job.
   *
   * @since  COmanage Registry v5.0.0
   * @return Array Array of supported parameters.
   * @throws \InvalidArgumentException
   * @throws \RuntimeException
   */

  public function parameterFormat(): array {
    return [
      'entities' => [
        'help'      => __d('core_job', 'opt.entities'),
        'type'      => 'string',
        'required'  => false
      ],
      'model' => [
        'help'      => __d('core_job', 'opt.provisioner.model'),
        'type'      => 'select',
        'choices'   => ['Groups', 'People'],
        'required'  => true
      ],
      'provisioning_target_id' => [
        'help'      => __d('core_job', 'opt.provisioner.provisioning_target_id'),
        'type'      => 'integer',
        'required'  => true
      ]
    ];
  }

  /**
   * Process Reprovisioning for an entity.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  JobsTable                  $JobsTable              Jobs Table
   * @param  JobHistoryRecordsTable     $JobHistoryRecordsTable JobHistoryRecords Table
   * @param  EntityTable                $EntityTable            Entity Table (PeopleTable, etc)
   * @param  Job                        $job                    Job entity
   * @param  ProvisioningTarget         $target                 Provisioning Target
   * @param  string                     $model                  Model (People, Groups)
   * @param  int                        $entityId               Entity ID to process
   * @param  int                        $count                  Total entity count
   * @param  &int                       $processed              Number of entities processed so far
   * @param  &int                       $errors                 Number of errors encountered
   * @param  &int                       $lastPct                Last percent update
   * @return bool                                               True if processing should continue, false otherwise
   */

  protected function processEntity(
    \App\Model\Table\JobsTable $JobsTable,
    \App\Model\Table\JobHistoryRecordsTable $JobHistoryRecordsTable,
    $EntityTable,
    \App\Model\Entity\Job $job,
    $target,
    string $model,
    int $entityId,
    int $count,
    int &$processed,
    int &$errors,
    int &$lastPct
  ): bool {
    try {
      $EntityTable->requestProvisioning(
        id: $entityId,
        context: ProvisioningContextEnum::Queue,
        provisioningTargetId: $target->id,
        job: $job
      );

      $JobHistoryRecordsTable->record(
        jobId: $job->id,
        recordKey: (string)$entityId,
        comment: __d('core_job', 'Provisioner.result.provisioned'),
        status: JobStatusEnum::Complete
      );
    }
    catch(\Exception $e) {
      $JobHistoryRecordsTable->record(
        jobId: $job->id,
        recordKey: (string)$entityId,
        comment: $e->getMessage(),
        status: JobStatusEnum::Failed
      );

      $errors++;
    }

    $processed++;

    // Check to see if the Job was canceled, or update the percent complete
    if($JobsTable->isCanceled($job->id)) {
      // The Job was already marked Canceled, but we can optionally add a History Record
      $JobHistoryRecordsTable->record(
        jobId: $job->id,
        recordKey: "",
        comment:  __d('core_job', 'Provisioner.finish_summary', [$processed, $errors]),
        status: JobStatusEnum::Canceled
      );

      return false;
    } else {
      // Maybe update % complete

      $newPct = (int)round(($processed * 100) / $count);

      if($newPct > $lastPct) {
        $JobsTable->setPercentComplete(job: $job, percent: $newPct);
        $lastPct = $newPct;
      }
    }

    return true;
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
    // Before we get started, figure out what we're doing

    $model = $parameters['model'];

    $count = 0;     // Number of available entities (eg: People)
    $processed = 0; // Number of entities processed so far
    $errors = 0;    // Number of errors encountered
    $lastPct = 0;   // Last integer percent done, used for updating Percent Complete

    // IdentifierAssignmentsTable will cache the IdentifierAssignment configuration,
    // but each assignment action still generates a bunch of database calls.
    $ProvisioningTargets = TableRegistry::getTableLocator()->get('ProvisioningTargets');
    // ie: People or Groups
    $EntityTable = TableRegistry::getTableLocator()->get($model);

    $target = $ProvisioningTargets->get($parameters['provisioning_target_id']);

    if($target->co_id != $job->co_id) {
      throw new \InvalidArgumentException(__d('core_job', 'error.co_id', ['ProvisioningTarget', $id, $job->co_id]));
    }

    if($target->status == ProvisionerModeEnum::Disabled) {
      throw new \InvalidArgumentException(__d('core_job', 'Provisioner.error.status'));
    }

    if(!empty($parameters['entities'])) {
      // We have one or more explicitly specified entities to process.
      // Entities might be an int (single request) or comma separated string
      // (because PHP).

      $ids = [];

      if(is_int($parameters['entities'])) {
        $ids[] = $parameters['entities'];
      } else {
        $ids = explode(',', $parameters['entities']);
      }

      $count = count($ids);
      
      $JobsTable->start(job: $job, summary: __d('core_job', 'Provisioner.start_summary', [$count, $model, $target->id]));

      foreach($ids as $id) {
        // First make sure $id is in $job->co_id

        $entityCoId = $EntityTable->calculateCoId((int)$id);

        if($entityCoId == $job->co_id) {
          if(!$this->processEntity(
            $JobsTable,
            $JobHistoryRecordsTable,
            $EntityTable,
            $job,
            $target,
            $model,
            (int)$id,
            $count,
            $processed,
            $errors,
            $lastPct
          )) {
            break;
          }
        }
      }
    } else {
      // We'll end up pulling entities twice, once here as part of getMembers using
      // PaginatedSqlIterator, and again because requestProvisioning() needs to
      // marshall the provisioning data. We could just pull a list of IDs here, but
      // that's still basically the same number of queries. We could pull the
      // additional information (contain()) that assign() needs here, but then we'd 
      // have that logic in both places. Also, this keeps the design consistent with
      // AssignerJob.

      $iterator = $EntityTable->getMembers($job->co_id);

      // We use this for logging, but it shouldn't be used for iterating as it
      // could change during iteration
      $count = $iterator->count();

      $JobsTable->start(job: $job, summary: __d('core_job', 'Provisioner.start_summary', [$count, $model, $target->id]));

      foreach($iterator as $k => $entity) {
        if(!$this->processEntity(
          $JobsTable,
          $JobHistoryRecordsTable,
          $EntityTable,
          $job,
          $target,
          $model,
          $entity->id,
          $count,
          $processed,
          $errors,
          $lastPct
        )) {
          break;
        }
      }
    }

    $JobsTable->finish(job: $job, summary: __d('core_job', 'Provisioner.finish_summary', [$processed, $errors]));
  }
}