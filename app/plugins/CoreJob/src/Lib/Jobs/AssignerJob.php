<?php
/**
 * COmanage Registry Assigner Job
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

class AssignerJob {
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
      'context' => [
        'help'      => __d('core_job', 'opt.assigner.context'),
        'type'      => 'select',
        'choices'   => ['Groups', 'People'],
        'required'  => true
      ],
      'entities' => [
        'help'      => __d('core_job', 'opt.entities'),
        'type'      => 'string',
        'required'  => false
      ]
    ];
  }

  /**
   * Process Identifier Assignment for an entity.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  JobsTable                  $JobsTable              Jobs Table
   * @param  JobHistoryRecordsTable     $JobHistoryRecordsTable JobHistoryRecords Table
   * @param  IdentifierAssignmentsTable $IdentifierAssignments  IdentifierAssignments Table
   * @param  Job                        $job                    Job entity
   * @param  string                     $context                Context (People, Groups)
   * @param  int                        $entityId               Entity ID to process
   * @param  int                        $count                  Total entity count
   * @param  &int                       $processed              Number of entities processed so far
   * @param  &int                       $lastPct                Last percent update
   * @param  &int                       $assigned               Number of new Identifiers assigned
   * @return bool                                               True if processing should continue, false otherwise
   */

  protected function processEntity(
    \App\Model\Table\JobsTable $JobsTable,
    \App\Model\Table\JobHistoryRecordsTable $JobHistoryRecordsTable,
    \App\Model\Table\IdentifierAssignmentsTable $IdentifierAssignments,
    \App\Model\Entity\Job $job,
    string $context,
    int $entityId,
    int $count,
    int &$processed,
    int &$lastPct,
    int &$assigned
  ): bool {
    $result = $IdentifierAssignments->assign(
      entityType: $context,
      entityId: $entityId,
      provision: true,
      // actorPersonId: null -- do we have this available?
    );

    $processed++;

    // Note IdentifierAssignmentsTable already does logging of whether Identifiers were
    // assigned or not, so we don't need to do anything more here (except record
    // appropriate JobHistory, which we do for newly assigned and errorss).

    if(!empty($result['assigned'])) {
      foreach($result['assigned'] as $ia => $msg) {
        $JobHistoryRecordsTable->record(
          jobId: $job->id,
          recordKey: (string)$entityId,
          comment: __d('core_job', 'Assigner.result.assigned', [$ia, $msg]),
          status: JobStatusEnum::Complete
        );

        $assigned++;
      }
    }

    if(!empty($result['errors'])) {
      foreach($result['errors'] as $ia => $msg) {
        $JobHistoryRecordsTable->record(
          jobId: $job->id,
          recordKey: (string)$entityId,
          comment: __d('core_job', 'Assigner.error.assign', [$ia, $msg]),
          status: JobStatusEnum::Failed
        );
      }
    }

    // Check to see if the Job was canceled, or update the percent complete
    if($JobsTable->isCanceled($job->id)) {
      // The Job was already marked Canceled, but we can optionally add a History Record
      $JobHistoryRecordsTable->record(
        jobId: $job->id,
        recordKey: "",
        comment:  __d('core_job', 'Assigner.finish_summary', [$count, $assigned]),
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

    $context = $parameters['context'];

    $count = 0;     // Number of available entities (eg: People)
    $processed = 0; // Number of entities processed so far
    $lastPct = 0;   // Last integer percent done, used for updating Percent Complete
    $assigned = 0;  // Number of _Identifiers_ (not entities) newly assigned

    // IdentifierAssignmentsTable will cache the IdentifierAssignment configuration,
    // but each assignment action still generates a bunch of database calls.
    $IdentifierAssignments = TableRegistry::getTableLocator()->get('IdentifierAssignments');
    // ie: People or Groups
    $EntityTable = TableRegistry::getTableLocator()->get($context);

    if(!empty($parameters['entities'])) {
      // We have one or more explicitly specified entities to process

      $ids = explode(',', $parameters['entities']);

      $count = count($ids);
      
      $JobsTable->start(job: $job, summary: __d('core_job', 'Assigner.start_summary', [$context, $job->co_id, $count]));

      foreach($ids as $id) {
        // First make sure $id is in $job->co_id

        $entityCoId = $EntityTable->calculateCoId((int)$id);

        if($entityCoId == $job->co_id) {
          if(!$this->processEntity(
            $JobsTable,
            $JobHistoryRecordsTable,
            $IdentifierAssignments,
            $job,
            $context,
            (int)$id,
            $count,
            $processed,
            $lastPct,
            $assigned
          )) {
            break;
          }
        } else {
          $JobHistoryRecordsTable->record(
            jobId: $job->id,
            recordKey: (string)$entity->id,
            comment: __d('core_job', 'error.co_id', [$context, $id, $job->co_id]),
            status: JobStatusEnum::Failed
          );
        }
      }
    } else {
      // We'll end up pulling entities twice, once here as part of getMembers using
      // PaginatedSqlIterator, and again because assign() needs additional information.
      // We could just pull a list of IDs here, but that's still basically the same
      // number of queries. We could pull the additional information (contain()) that
      // assign() needs here, but then we'd have that logic in both places. Running
      // Identifier Assignments for everyone is a relatively uncommon action, so it's
      // probably OK if it's more expensive than it needs to be to keep the code simpler.

      $iterator = $EntityTable->getMembers($job->co_id);

      // We use this for logging, but it shouldn't be used for iterating as it
      // could change during iteration
      $count = $iterator->count();

      $JobsTable->start(job: $job, summary: __d('core_job', 'Assigner.start_summary', [$context, $job->co_id, $count]));

      foreach($iterator as $k => $entity) {
        if(!$this->processEntity(
          $JobsTable,
          $JobHistoryRecordsTable,
          $IdentifierAssignments,
          $job,
          $context,
          $entity->id,
          $count,
          $processed,
          $lastPct,
          $assigned
        )) {
          break;
        }
      }
    }

    $JobsTable->finish(job: $job, summary: __d('core_job', 'Assigner.finish_summary', [$count, $assigned]));
  }
}