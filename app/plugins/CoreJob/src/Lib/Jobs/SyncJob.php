<?php
/**
 * COmanage Registry Sync Job
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

use Cake\ORM\TableRegistry;
use \App\Lib\Enum\JobStatusEnum;
use \App\Lib\Enum\SyncModeEnum;
use \App\Lib\Util\TableUtilities;

class SyncJob {
  use \App\Lib\Traits\LabeledLogTrait;

  // Variables used for a run
  protected $runContext = null;

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
      'external_identity_source_id' => [
        'help'      => __d('core_job', 'opt.sync.external_identity_source_id'),
        'type'      => 'fk',
        'required'  => false
      ],
      'force' => [
        'help'      => __d('core_job', 'opt.sync.force'),
        'type'      => 'bool',
        'required'  => false
      ],
// XXX addd reference_id
      'source_keys' => [
        'help'      => __d('core_job', 'opt.sync.source_keys'),
        'type'      => 'string',
        'required'  => false
      ]
    ];
  }

  /**
   * Perform a full sync (Full or Update modes) of an External Identity Source.
   * 
   * @since  COmanage Registry v5.0.0
   */

  protected function fullSync() {
    // Flag the job as started, before beginning pre-run checks.
    $this->runContext->JobsTable->start(
      job: $this->runContext->job, 
      summary: __d('core_job', 'Sync.start_summary.eis', [$this->runContext->parameters['external_identity_source_id'], __d('enumeration', 'SyncModeEnum.'.$this->runContext->eis->status)])
    );

    $this->llog('trace', "Beginning sync of EIS " . $this->runContext->eis->description
                         . " in mode " . $this->runContext->eis->status
                         . " (job " . $this->runContext->job->id . ")");

    // Determine the last run time for this sync. In v4, we queried the CoJob table to
    // determine the last run of a specific source. However, this is a bit tricky in PE
    // given how parameters are used to configure a given Job, so we instead maintain
    // our own metadata about when a job was last run (started) to pass to preRunChecks,
    // getChangeList, etc.

    $lastStart = $this->lastStart($this->runContext->eis->id);

    // Note the start_time for last run calculations.
    $curStart = time();
    
    // Next see if there are pre-run checks. The plugin can interrupt the sync job,
    // but preRunChecks() will handle that.

    if(!$this->preRunChecks(lastStart: $lastStart, curStart: $curStart)) {
      // preRunChecks() will have finish()d the job, so we don't need to do anything here.
      return;
    }

    // Now perform the actual sync. Start by pulling the list of known source keys.
    // We maintain this in memory as a simple hash since this _should_ fit within the
    // memory requirements of our larger expected deployments, and it's significantly
    // simpler to perform diff calculations this way. This might need to be refactored
    // at some point...

    $knownKeys = $this->runContext->EISTable->getKnownSourceKeys($this->runContext->eis->id);
    
    // Flip the array since it's faster to check for a key than a value
    $knownKeysHash = array_flip($knownKeys);

    // We'll start by assuming the record count is the known source key count, but in full
    // mode we'll override this to be the inventory count
    $this->runContext->count = count($knownKeysHash);

    $this->llog('trace', "EIS " . $this->runContext->eis->description . " has "
                         . count($knownKeys) . " known source key(s) already synced");
    
    // We first update any already sync'd records. This should also handle deletes.
    // If the plugin supports changelists, this might also include new records.

    if($this->runContext->eis->status == SyncModeEnum::Full
       || $this->runContext->eis->status == SyncModeEnum::Update) {
      // Try to get a changelist from the Plugin. If force is set, we can't use
      // getChangeList since we need to process all records.

      $changeList = false;

      if($this->runContext->force) {
        $this->llog('trace', "EIS " . $this->runContext->eis->description
                             . " skipping changelist call due to force mode");
      } else {
        $changeList = $this->runContext->EISTable->getChangeList(
          $this->runContext->eis->id,
          $lastStart,
          $curStart
        );

        // Remove any duplicate keys
        $changeList = array_unique($changeList);
      }

      if($changeList !== false) {
        $this->llog('trace', "EIS " . $this->runContext->eis->description . " plugin returned "
                         . count($changeList) . " updated record(s)");
      } else {
        // We couldn't get a changelist, so we iterate over all known entries.

        $this->llog('trace', "EIS " . $this->runContext->eis->description
                             . " plugin does not support changelist, iterating over all known source keys");

        $changeList = $knownKeys;
      }

      // Process updates. It's possible that getChangeList will return new records
      // that we haven't seen yet, so we'll check $knownKeys and only process those
      // records that we know about. (New records will be handled below.)
      
      foreach($changeList as $sourceKey) {
        if(isset($knownKeysHash[$sourceKey])) {
          $this->llog('trace', "EIS " . $this->runContext->eis->description 
                               . " updating entry $sourceKey");
          
          $this->syncRecord($sourceKey);
        } else {
          $this->llog('trace', "EIS " . $this->runContext->eis->description 
                               . " skipping changelist entry $sourceKey on update since record is not already synced");
        }
      }
    }

    // Next, for Full mode only, look for records that haven't yet been synced.
    // We do this by comparing the plugin's inventory with our cached inventory,
    // and processing any records the plugin reported that we didn't know about.

    if($this->runContext->eis->status == SyncModeEnum::Full) {
      $allKeys = $this->runContext->EISTable->inventory($this->runContext->eis->id);

      $this->runContext->count = count($allKeys);

      $newKeys = array_diff($allKeys, $knownKeys);

      foreach($newKeys as $sourceKey) {
        $this->llog('trace', "EIS " . $this->runContext->eis->description 
                             . " processing new entry $sourceKey");
        
        $this->syncRecord($sourceKey);
      }
    }

    // Last run time is updated by postRunTasks, which won't update the time on failure.
    // Because processing has basically finished at this point, plugins can't abort
    // processing via postRunTasks.

    $this->postRunTasks(curStart: $curStart);

    // Report the Job as finished

    $this->runContext->JobsTable->finish(
      job: $this->runContext->job,
      summary: __d(
        'core_job',
        'Sync.finish_summary.count',
        [$this->runContext->created, $this->runContext->updated, $this->runContext->errors, $this->runContext->count]
      )
    );
  }

  /**
   * Cache the current run context.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  JobsTable              $JobsTable              JobsTable
   * @param  JobHistoryRecordsTable $JobHistoryRecordsTable JobHistoryRecordsTable
   * @param  Job                    $job                    Current Job
   * @param  array                  $parameters             Job Parameters (from the command line)
   * @param  int                    $eisId                  External Identity Source ID
   */

  protected function getRunContext(
    \App\Model\Table\JobsTable              $JobsTable,
    \App\Model\Table\JobHistoryRecordsTable $JobHistoryRecordsTable,
    \App\Model\Entity\Job                   $job, 
    array                                   $parameters,
    int                                     $eisId=null
  ): \StdClass {
    // We use $runContext so we don't have to pass a complicated set of parameters around.

    // It's not yet clear if we'll need to support updating the run context
    // for multiple EIS (ie: full sync for all EIS in a CO), so for now we
    // simply return the runContext if it's already set.

    if(!empty($this->runContext)) {
      return $this->runContext;
    }

    $this->runContext = new \StdClass();

    // Table handles
    $this->runContext->JobsTable = $JobsTable;
    $this->runContext->JobHistoryRecordsTable = $JobHistoryRecordsTable;
    $this->runContext->EISTable = TableRegistry::getTableLocator()->get('ExternalIdentitySources');

    // Stuff below here might need to be reset when we have a multi-EIS job running
    // (ie: run all sync jobs for a CO)
    // The Job
    $this->runContext->job = $job;
    $this->runContext->parameters = $parameters;
    $this->runContext->force = isset($parameters['force']) && $parameters['force'];

    // The EIS
    if($eisId) {
      // We're working with a specific EIS, pull it here but don't check status.

      $this->runContext->eis = $this->runContext->EISTable->get(
        $eisId,
        ['contain' => 'SqlSources']
      );
    }

    // Record counts
    $this->runContext->count = 0;       // Number of records to process
    $this->runContext->created = 0;     // Number of records created
    $this->runContext->updated = 0;     // Number of updated records
    $this->runContext->unchanged = 0;   // Number of unchanged records
    $this->runContext->errors = 0;      // Number of errors encounter
    $this->runContext->lastPct = 0;     // Last % update

    return $this->runContext;
  }

  /**
   * Determine the last start time of an EIS (Full) Sync.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int  $eisId    ExternalIdentitySource ID
   * @return int            Last start time, or 0 if not yet run
   */

  protected function lastStart(
    int $eisId
  ): int {
    // There's no formal model, but autovivification should suffice
    $LastRunTable = TableRegistry::getTableLocator()->get('CoreJob.SyncJobLastRuns');

    $lastRun = $LastRunTable->find()
                            ->where(['external_identity_source_id' => $eisId])
                            ->first();
    
    return !empty($lastRun->start_time) ? (int)$lastRun->start_time->toUnixString() : 0;
  }

  /**
   * Perform Pre Run Checks.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $lastStart  Time of the most recent run of this Job
   * @param  int    $curStart   Time of the start of the current run of this Job
   * @return bool               true if the Job should continue, false otherwise
   */

  protected function preRunChecks(
    int $lastStart,
    int $curStart
  ): bool {
    $Plugin = TableRegistry::getTableLocator()->get($this->runContext->eis->plugin);

    if(method_exists($Plugin, 'preRunChecks')) {
      $this->llog('trace', "Running Pre-Run Checks for EIS " . $this->runContext->eis->id
                          . " (job " . $this->runContext->job->id . ")");
      
      try {
        $Plugin->preRunChecks(
          source:     $this->runContext->eis,
          lastStart:  $lastStart, 
          curStart:   $curStart
        );
      }
      catch(\Exception $e) {
        // Checks failed: finish the job and return false

        $this->runContext->JobsTable->finish(
          job: $this->runContext->job,
          summary: __d('core_job', 'Sync.error.pre_run_checks', $e->getMessage()),
          result: JobStatusEnum::Failed
        );

        return false;
      }
    } else {
      $this->llog('trace', "No Pre-RunChecks defined for EIS " . $this->runContext->eis->description
                          . " (job " . $this->runContext->job->id . ")");
    }

    return true;
  }

  /**
   * Perform Post Run Tasks
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $curStart   Time of the start of the current run of this Job
   */

  protected function postRunTasks(
    int $curStart
  ) {
    // Update sync_job_last_runs

    $LastRunTable = TableRegistry::getTableLocator()->get('CoreJob.SyncJobLastRuns');

    $lastRun = $LastRunTable->find()
                            ->where(['external_identity_source_id' => $this->runContext->eis->id])
                            ->first();
    
    if($lastRun) {
      // Update the existing row
      $lastRun->start_time = $curStart;
    } else {
      $lastRun = $LastRunTable->newEntity([
        'external_identity_source_id' => $this->runContext->eis->id,
        'job_id'                      => $this->runContext->job->id,
        'start_time'                  => $curStart
      ]);
    }

    $LastRunTable->save($lastRun);

    // Call the plugin if it has anything it wants to do

    $Plugin = TableRegistry::getTableLocator()->get($this->runContext->eis->plugin);

    if(method_exists($Plugin, 'postRunTasks')) {
      $this->llog('trace', "Running Post-Run Tasks for EIS " . $this->runContext->eis->description
                          . " (job " . $this->runContext->job->id . ")");
      
      try {
        $Plugin->postRunTasks(
          source:     $this->runContext->eis
        );
      }
      catch(\Exception $e) {
        // Tasks failed: record an error but keep going

        $this->runContext->JobHistoryRecordsTable->record(
          jobId: $this->runContext->job->id,
          recordKey: null,
          comment: __d('core_job', 'Sync.error.post_run_tasks', $e->getMessage()),
          status: JobStatusEnum::Failed
        );
      }
    } else {
      $this->llog('trace', "No Pre-RunChecks defined for EIS " . $this->runContext->eis->id
                          . " (job " . $this->runContext->job->id . ")");
    }
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
    if(!empty($parameters['external_identity_source_id'])) {
      $this->getRunContext(
        $JobsTable,
        $JobHistoryRecordsTable,
        $job,
        $parameters,
        (int)$parameters['external_identity_source_id']
      );

      if($this->runContext->eis->status == SyncModeEnum::Disabled) {
        throw new \InvalidArgumentException('core_job', 'Sync.error.disabled');
      }

      if(!empty($parameters['source_keys'])) {
        // We're processing a comma separated list of source keys

        $keys = explode(',', $parameters['source_keys']);

        $this->runContext->count = count($keys);

        $JobsTable->start(
          job: $job, 
          summary: __d('core_job', 'Sync.start_summary.keys', [$parameters['external_identity_source_id'], $this->runContext->count])
        );

        foreach($keys as $key) {
          if(!$this->syncRecord($key)) {
            break;
          }
        }

        $JobsTable->finish(
          job: $job,
          summary: __d(
            'core_job',
            'Sync.finish_summary.count',
            [$this->runContext->created, $this->runContext->updated, $this->runContext->errors, $this->runContext->count]
          )
        );
      } else {
        // We're processing all records within this EIS.

        $this->fullSync();
      }
    } elseif(!empty($parameters['co_id'])) {
      // We're processing all EIS within this CO

      // In v4, we create a new Job record for each EIS - can we do the same thing here?
      // Or maybe create a sub-job and record that in the parent Job?
      // XXX pull only EIS in "Full" or "Update" mode
      // Note eis is stored in runContext, may need to update that on each run,
      // or just ignore it entirely
      // - should be able to reset context then call fullSync() for each EIS
      // XXX check for cancellations between EIS
      throw new \RuntimeException('NOT IMPLEMENTED');

      $JobsTable->finish(job: $job, summary: __d('core_job', 'Sync.finish_summary'));
    }
  }

  /**
   * Sync a single record.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $key  Source Key to process
   * @return bool         True if processing should continue, false otherwise
   */

  protected function syncRecord(string $key): bool {
    // comment and status for HistoryRecords
    $c = "unknown";
    $s = JobStatusEnum::Failed;

    try {
      $result = $this->runContext->EISTable->sync(
        id: (int)$this->runContext->parameters['external_identity_source_id'], 
        sourceKey: $key, 
        force: $this->runContext->force
      );

      switch($result) {
        case 'new':
          $this->runContext->created++;
          $s = JobStatusEnum::Complete;
          $c = __d('core_job', 'Sync.record.new');
          break;
        case 'unchanged':
          $this->runContext->unchanged++;
          $s = JobStatusEnum::Complete;
          $c = __d('core_job', 'Sync.record.unchanged');
          break;
        case 'updated':
          $this->runContext->updated++;
          $s = JobStatusEnum::Complete;
          $c = __d('core_job', 'Sync.record.updated');
          break;
        default:
          $this->runContext->errors++;
      }
    }
    catch(\Exception $e) {
      $c = $e->getMessage();
      $this->runContext->errors++;
    }

    // unchanged results are considered "no-ops", which we might be configured to not log
    if($result != 'unchanged' 
       || !isset($this->runContext->eis->suppress_noop_logs)
       || !$this->runContext->eis->suppress_noop_logs) {
      $this->runContext->JobHistoryRecordsTable->record(
        jobId: $this->runContext->job->id,
        recordKey: $key,
        comment: $c,
        status: $s
      );
    }

    // Check to see if the Job was canceled, or update the percent complete
    if($this->runContext->JobsTable->isCanceled($this->runContext->job->id)) {
      // The Job was already marked Canceled, but we can optionally add a History Record
      $this->runContext->JobHistoryRecordsTable->record(
        jobId: $job->id,
        recordKey: "",
        comment:  __d(
          'core_job',
          'Sync.finish_summary.count',
          [$this->runContext->created, $this->runContext->updated, $this->runContext->errors, $this->runContext->count]
        ),
        status: JobStatusEnum::Canceled
      );

      return false;
    } else {
      // Maybe update % complete

      $processed = $this->runContext->created + $this->runContext->unchanged + $this->runContext->updated + $this->runContext->errors;
      $newPct = ($this->runContext->count > 0
                 ? (int)round(($processed * 100) / $this->runContext->count)
                 : 0);
      
      if($newPct > $this->runContext->lastPct) {
        $this->runContext->JobsTable->setPercentComplete(job: $this->runContext->job, percent: $newPct);
        $this->runContext->lastPct = $newPct;
      }
    }

    return true;
  }
}