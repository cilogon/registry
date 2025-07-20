<?php
/**
 * COmanage Registry SQL Assigner Job
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

namespace CoreAssigner\Lib\Jobs;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use \App\Lib\Enum\IdentifierAssignmentContextEnum;
use \App\Lib\Enum\JobStatusEnum;
use \App\Lib\Enum\ProvisioningContextEnum;
use \App\Lib\Enum\SuspendableStatusEnum;
use \App\Model\Entity\Person;

class SqlAssignerJob {
  // Cache objects so we don't have to pass them around

  // Tables
  protected $IdentifierAssignments = null;
  protected $JobHistoryRecordsTable = null;
  protected $People = null;
  protected $SqlAssigners = null;
  // $TargetModel is either EmailAddresses or Identifiers, depending on the
  // Identifier Assignment configuration
  protected $TargetModel = null;

  // Configuration
  protected $derivedTypeIds = [
    'EmailAddresses' => [],
    'Identifiers' => []
  ];
  protected $ia = null;
  protected $job = null;
  protected $parameters = null;

  // Result counts
  protected $count = 0;
  protected $assigned = 0;
  protected $updated = 0;
  protected $error = 0;

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
      'identifier_assignment_id' => [
        'help'      => __d('core_assigner', 'opt.sqlassigner.identifier_assignment_id'),
        'type'      => 'fk',
        'required'  => true
      ],
      'lookback' => [
        'help'      => __d('core_assigner', 'opt.sqlassigner.lookback'),
        'type'      => 'int',
        'required'  => false
      ],
      'reprocess_assignments' => [
        'help'      => __d('core_assigner', 'opt.sqlassigner.reprocess_assignments'),
        'type'      => 'bool',
        'required'  => false
      ],
      'suppress_noops' => [
        'help'      => __d('core_assigner', 'opt.sqlassigner.suppress_noops'),
        'type'      => 'bool',
        'required'  => false
      ]
    ];
  }

  /**
   * Process the sync of a single Person.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Person $person Person to sync
   */

  protected function processSync(Person $person) {
    try {
      // We need to pull the Identifiers associated with $person, but we're only
      // interested in those associated with the configured source Identifier type.

      $person->identifiers = $this->People->Identifiers->find()->where([
        'person_id' => $person->id,
        'type_id' => $this->ia->sql_assigner->type_id
      ])->toArray();

      // We can just let SqlAssignersTable::assign() do the heavy lifting in finding a
      // candidate Identifier. $identifier will be non-empty, otherwise an Exception is
      // thrown. Note we currently don't handle deletion of an existing identifier (if
      // it is removed from the source database), see PAR-SqlAssignerJob-2.

      $identifier = $this->SqlAssigners->assign($this->ia, $person);

      // We now have $identifier from the data source, we'll use upsert so we
      // don't have to check for an existing identifier. We do have to pay attention
      // to Email Address vs Identifier.

      // Construct $data as if it were a new entity. Upsert will patch only the changed
      // fields if it's an update instead.
      $data = [
        'person_id' => $person->id,
        'type_id' => $this->ia->email_address_type_id ?? $this->ia->identifier_type_id
      ];

      $fieldName = 'identifier';

      if($this->ia->email_address_type_id) {
        $data['mail'] = $identifier;
        // PAR-SqlAssignerJob-4 If the SQL Assigner configuration is used to generate an
        // Email Address, any new or updated Email Address will be considered verified.
        $data['verified'] = true;
        $fieldName = 'mail';
      } elseif($this->ia->identifier_type_id) {
        $data['identifier'] = $identifier;
        $data['login'] = $this->ia->login;
        // PAR-SqlAssignerJob-1 If the SQL Assigner updates an existing Suspended Identifier,
        // the updated Identifier will become Active.
        $data['status'] = SuspendableStatusEnum::Active;
      }

      // Note we're specifically _not_ querying on $identifier because we might be updating
      // an older value that was then changed in the data source.
      $whereClause = [
        'person_id' => $person->id,
        'type_id' => $this->ia->email_address_type_id ?? $this->ia->identifier_type_id
      ];

      $entity = $this->TargetModel->upsertOrFail($data, $whereClause);

      // Record history based on whether we inserted, updated, or did nothing.
      // Unforunately the metadata on $entity (eg: isNew()) isn't accurate since
      // it's returned to us post-save, so instead we'll use _upsertStatus.
      $msg = "(?)";

      switch($entity->_upsertStatus) {
        case 'insert':
          // This is a new identifier, not previously synced
          $msg = __d('core_assigner', 'result.SqlAssignerJob.assigned', [$this->ia->description]);
          $this->assigned++;
          break;
        case 'update':
          // This is a different identifier from what we previously had
          $msg = __d('core_assigner', 'result.SqlAssignerJob.updated', [$this->ia->description]);
          $this->updated++;
          break;
        case 'unchanged':
          // The identifier is unchanged
          $msg = __d('core_assigner', 'result.SqlAssignerJob.unchanged', [$this->ia->description]);
          break;
      }

      if($entity->_upsertStatus != 'unchanged'
          || !isset($this->parameters['suppress_noops'])
          || !$this->parameters['suppress_noops']) {
        $this->JobHistoryRecordsTable->record(
          jobId: $this->job->id,
          recordKey: (string)$person->id,
          comment: $msg,
          status: JobStatusEnum::Complete
        );
      }

      if($entity->_upsertStatus != 'unchanged') {
        // We also want to record Person History, but not if the identifier is unchanged
        $this->People->recordHistory(entity: $entity, comment: $msg);

        // Handle additional Identifier Assignments if set
        if(isset($this->parameters['reprocess_assignments'])
            && $this->parameters['reprocess_assignments']) {
          // First we delete any Identifiers or Email Addresses of the found derived types

          foreach(array_keys($this->derivedTypeIds) as $modelName) {
            foreach($this->derivedTypeIds[$modelName] as $tid) {
              // We don't use deleteAll because we want callbacks to run
              $toDelete = $this->People->$modelName->find()->where([
                'person_id' => $person->id,
                'type_id' => $tid
              ])->all();

              foreach($toDelete as $td) {
                $this->JobHistoryRecordsTable->record(
                  jobId: $this->job->id,
                  recordKey: (string)$person->id,
                  comment: __d('core_assigner', 'result.SqlAssignerJob.derived', [$modelName, $td->identifier ?? $td->mail]),
                  status: JobStatusEnum::Notice
                );

                $this->People->$modelName->delete($td);
              }
            }
          }

          // Then we run Identifier Assignment. We just invoke a full re-assign all
          // for this Person and let the existing code do the work. We skip provisioning
          // here because we're going to run it below.

          $this->JobHistoryRecordsTable->record(
            jobId: $this->job->id,
            recordKey: (string)$person->id,
            comment: __d('core_assigner', 'result.SqlAssignerJob.ia'),
            status: JobStatusEnum::Notice
          );

          $this->IdentifierAssignments->assign(
            entityType: 'People',
            entityId: $person->id,
            provision: false
          );
        }

        // And reprovision (PAR-SqlAssignerJob-3).
        $this->People->requestProvisioning(id: $person->id, context: ProvisioningContextEnum::Automatic);
      }
    }
    catch(\Exception $e) {
      // We'll get InvalidArgumentException if $person doesn't have a valid key Identifier
      // or if the key doesn't map to a value

      $this->JobHistoryRecordsTable->record(
        jobId: $this->job->id,
        recordKey: (string)$person->id,
        comment: $e->getMessage(),
        status: JobStatusEnum::Failed
      );

      $this->error++;
    }

    $this->count++;
  }

  /**
   * Run the requested Job.
   * 
   * @since  COmanage Registry v5.2.0
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
    // Make parameters available to processSync
    $this->JobHistoryRecordsTable = $JobHistoryRecordsTable;
    $this->job = $job;
    $this->parameters = $parameters;

    // First pull the requested Identifier Assignment configuration and make sure it
    // has a SqlAssigner configuration.

    $this->IdentifierAssignments = TableRegistry::getTableLocator()->get('IdentifierAssignments');

    $this->ia = $this->IdentifierAssignments->get(
      $parameters['identifier_assignment_id'],
      ['contain' => ['SqlAssigners', 'IdentifierTypes']]
    );

    if($this->ia->plugin != 'CoreAssigner.SqlAssigners') {
      throw new \InvalidArgumentException(__d('core_assigner', 'error.SqlAssignerJob.plugin', [$this->ia->plugin]));
    }

    if($this->ia->status != SuspendableStatusEnum::Active) {
      throw new \InvalidArgumentException(__d('error', 'inactive', [__d('controller', 'IdentifierAssignments', [1]), $this->ia->id]));
    }

    if($this->ia->context != IdentifierAssignmentContextEnum::Person) {
      throw new \InvalidArgumentException(__d('core_assigner', 'error.SqlAssignerJob.context', [$this->ia->context]));
    }

    // If reprocess_assignments is true, figure out which Identifier types are derived
    // from the current Identifier Assignment configuration.

    if(isset($parameters['reprocess_assignments']) 
       && $parameters['reprocess_assignments']) {
      // We only look at Identifier Assignments that run after us (since earlier ones can't be
      // derived from this one) and only Format Assigners (since that's the only one we know of
      // that supports derived Identifiers).
      $ias = $this->IdentifierAssignments->find()->where([
        'co_id' => $this->ia->co_id,
        'status' => SuspendableStatusEnum::Active,
        'plugin' => 'CoreAssigner.FormatAssigners',
        'ordr >' => $this->ia->ordr
      ])->contain('FormatAssigners')->all();

      // The "database name" of the Identifier we are generating, which we need to check the
      // Format Assigner configuration, embedded into a Substitution string.
      $sub = "(I/" . $this->ia->identifier_type->value . ")";

      foreach($ias as $iax) {
        if(!empty($iax->format_assigner->format) 
           && str_contains($iax->format_assigner->format, $sub)) {
          // $iax is derived from $ia
          if(!empty($iax->email_address_type_id)) {
            $this->derivedTypeIds['EmailAddresses'][] = $iax->email_address_type_id;
          } elseif(!empty($iax->identifier_type_id)) {
            $this->derivedTypeIds['Identifiers'][] = $iax->identifier_type_id;
          }
        }
      }
    }
    
    // What we do next depends on whether a lookback window was specified.

    $this->SqlAssigners = TableRegistry::getTableLocator()->get('CoreAssigner.SqlAssigners');
    $this->People = TableRegistry::getTableLocator()->get('People');
    $this->TargetModel = TableRegistry::getTableLocator()->get($this->ia->email_address_type_id ? "EmailAddresses" : "Identifiers");

    if(!empty($parameters['lookback'])) {
      // Query the SqlAssigners source table for any records that changed within
      // the specified lookback window. This approach will result in some duplicate
      // queries in order to reuse code, but should overall be more efficient for
      // large sets of Person records vs iterating over all People (when most are
      // unchanged).

      $changelist = $this->SqlAssigners->getChangedRecords($this->ia, $parameters['lookback']);

      $JobsTable->start(job: $job, summary: __d('core_assigner', 'result.SqlAssignerJob.start_summary.changelist', [$changelist->count()]));

      foreach($changelist as $row) {
        // Find a Person corresponding to the key Identifier

        try {
          $pid = $this->People->Identifiers->lookupPerson($this->ia->sql_assigner->type_id, $row->key);

          $person = $this->People->get($pid);

          $this->processSync($person);
        }
        catch(\Exception $e) {
          // Most likely the identifier did not map to a Person

          $JobHistoryRecordsTable->record(
            jobId: $job->id,
            recordKey: $row->key,
            comment: $e->getMessage(),
            status: JobStatusEnum::Failed
          );

          $this->error++;
          $this->count++;
        }
      }

      $JobsTable->finish(
        job: $job, 
        summary: __d('core_assigner', 'result.SqlAssignerJob.finish_summary.changelist', 
                    [$this->count, $this->assigned, $this->updated, $this->error])
      );
    } else {
      // Obtain a Person iterator and walk through all available People

      // Note getMembers() excludes Archived People, which is probably ok for us
      $iterator = $this->People->getMembers($this->ia->co_id);

      // Note the count returned by $iterator->count() could change during iteration
      $JobsTable->start(job: $job, summary: __d('core_assigner', 'result.SqlAssignerJob.start_summary', [$iterator->count()]));

      foreach($iterator as $person) {
        $this->processSync($person);
      }

      $JobsTable->finish(
        job: $job, 
        summary: __d('core_assigner', 'result.SqlAssignerJob.finish_summary', 
                    [$this->count, $this->assigned, $this->updated, $this->error])
      );
    }
  }
}