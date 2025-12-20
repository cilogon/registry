<?php
/**
 * COmanage Registry Jobs Table
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

namespace App\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\JobStatusEnum;
use App\Lib\Util\StringUtilities;
use App\Model\Entity\Job;

class JobsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PluggableModelTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Artifact);
    
    // Define associations
    $this->belongsTo('Cos');
    $this->belongsTo('RequeuedFromJobs')
         ->setClassName('Jobs')
         ->setForeignKey('requeued_from_job_id')
         // Property is set so ruleValidateCO can find it. We don't use the
         // _id suffix to match Cake's default pattern.
         ->setProperty('requeued_from_job');
    
    $this->hasMany('JobHistoryRecords')
         ->setDependent(true)
         ->setCascadeCallbacks(true);

    $this->setPluginRelations();
    
    $this->setDisplayField('id');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['cancel']);
    
    $this->setAutoViewVars([
      'plugins' => [
        'type'        => 'plugin',
        'pluginType'  => 'job'
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'JobStatusEnum'
      ]
    ]);
    
    $this->setViewContains([
      'RequeuedFromJobs'
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'cancel' =>  ['platformAdmin', 'coAdmin'],
        'delete' =>  false,//   ['platformAdmin', 'coAdmin'],
        'edit' =>    false,//   ['platformAdmin', 'coAdmin'],
        'view' =>    ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false,  // generic add of jobs via the UI is not yet supported
        'index' =>    ['platformAdmin', 'coAdmin']
      ],
      'readOnly' => ['cancel'],
      // Related models whose permissions we'll need, typically for table views
      'related' => [
        'table' => [
          'JobHistoryRecords'
        ]
      ]
    ]);
  }

  /**
   * Table specific logic to generate a display field.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Job $entity Entity to generate display field for
   * @return string         Display field
   */

  public function generateDisplayField(Job $entity): string {
    // Try to find something renderable

    if(!empty($entity->plugin)) {
      return $entity->plugin . " (" . $entity->id . ")";
    }

    return (string)$entity->id;
  }
  
  /**
   * Assign a Job to a worker. This function should be called by the process
   * that will be processing the specified job.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Job $job Job to assign
   * @throws ArgumentException
   */

  public function assign(Job $job) {
    // The Job must be Queued to be Assigned
    if($job->status != JobStatusEnum::Queued) {
      throw new \InvalidArgumentException(
        __d('error',
            'Jobs.status.invalid', 
            [
              $jobs->id, 
              __d('enumeration', 'JobStatusEnum.Queued'),
              __d('enumeration', 'JobStatusEnum.Assigned'),
              __d('enumeration', 'JobStatusEnum.'.$job->status)
            ]
           )
      );
    }

    $job->status = JobStatusEnum::Assigned;
    $job->assigned_host = gethostname() ?: "unknown";
    $job->assigned_pid = getmypid() ?: -1;

    $this->saveOrFail($job);
  }

  /**
   * Assign the next Job in the queue to the current worker. This function is
   * intended to be called by JobShell.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int  $coId   CO ID to assign job from
   * @return Job          Job to process, or null if there are no more jobs in the queue
   */

  public function assignNext(int $coId): ?Job {
    // Start a new transaction. When we select from the Job queue, we need a read lock
    // to ensure another worker doesn't grab the same process.
    $cxn = $this->getConnection();
    $cxn->begin();

    $job = $this->find()
                ->where([
                  'status'      => JobStatusEnum::Queued,
                  'co_id'       => $coId,
                  'OR'          => [
                    'start_after_time IS NULL',
                    'start_after_time <' => date('Y-m-d H:i:s', time())
                  ]
                ])
                // We sort by id to pull the oldest job first
                ->orderBy(['id' => 'ASC'])
                ->epilog('FOR UPDATE')
                ->first();
    
    if($job) {
      // Assign the Job while we're still in the transaction
      $this->assign($job);
    }

    $cxn->commit();

    return $job;
  }

  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    // AR-Job-1 A Job may not be registered if an existing Job with the same plugin
    // and parameters is registered in either Queued or In Progress status
    $rules->addCreate([$this, 'ruleAlreadyRegistered'],
                      'alreadyRegistered',
                      ['errorField' => 'parameters']);
    
    // If another rule is added here, register() will need to be updated with a more
    // sophisticated mechanism to disable AR-Job-1 whene passed $concurrent=true.

    return $rules;
  }

  /**
   * Cancel a Job. The Job must be in a cancelable state.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $id     Job ID
   * @param  string $actor  Login Identifier of actor who requested cancelation
   * @throws InvalidArgumentException
   * @throws RecordNotFoundException
   */

  public function cancel(int $id, string $actor) {
    $job = $this->get($id);

    if(!$job->canCancel()) {
      throw new \InvalidArgumentException(__d('error',
                                              'Jobs.status.invalid.cancel',
                                              [
                                                $job->id,
                                                __d('enumeration', 'JobStatusEnum.'.$job->status)
                                              ]));
    }

    $this->finish(
      job:      $job, 
      summary:  __d('result', 'Jobs.canceled.by', [$actor]),
      result:   JobStatusEnum::Canceled
    );
  }

  /**
   * Confirm a Job was properly finished.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int  $pid    Process ID (on the current host)
   */

  public function confirmFinished(int $pid) {
    // Ordinarily we want the plugin to mark the Job as finished so it can update the
    // finish summary, but if the process exits abnormally we want to capture that
    // and clean up the Job. Note under extremely rare circumstances it's possible for
    // the PID we're looking for to be reassigned before we can run this check, but
    // that probably implies a host with runaway processes (since the PID count must cycle).

    $job = $this->find()
                ->where([
                  'status'        => JobStatusEnum::InProgress,
                  'assigned_pid'  => $pid,
                  'assigned_host' => gethostname() ?: "unknown"
                ])
                ->first();
    
    if(!empty($job)) {
      // Terminate the job
      $this->finish(
        job:      $job, 
        summary:  __d('error', 'Jobs.failed.abnormal'),
        result:   JobStatusEnum::Failed
      );
    }
  }
  
  /**
   * Mark a job as completed.
   *
   * @since  COmanage Registry v2.0.0
   * @param  Job            $job        Job to update
   * @param  string         $summary    Summary
   * @param  JobStatusEnum  $result     Result
   * @throws InvalidArgumentException
   */

  public function finish(Job $job, string $summary="", string $result=JobStatusEnum::Complete) {
    // The Job must be InProgress to be finished, unless we're canceling it
    // or we're recording a failure
    if($job->status != JobStatusEnum::InProgress
       && !($result == JobStatusEnum::Canceled && $job->canCancel())
       && $result != JobStatusEnum::Failed) {
      throw new \InvalidArgumentException(
        __d('error',
            'Jobs.status.invalid', 
            [
              $job->id, 
              __d('enumeration', 'JobStatusEnum.InProgress'),
              __d('enumeration', 'JobStatusEnum.'.$result),
              __d('enumeration', 'JobStatusEnum.'.$job->status)
            ]
           )
      );
    }

    $job->status = $result;
    $job->finish_summary = $summary;
    $job->finish_time = date('Y-m-d H:i:s', time());

    $this->saveOrFail($job);

    // On success, if a requeue_interval is specified then register a new job
    // with the same parameters, but with a delayed start time. Do the same thing
    // on failure if a retry_interval is specified. We need to do this after we
    // update the status of $id to avoid issues with concurrent jobs.

    if($result == JobStatusEnum::Complete
       && !empty($job->requeue_interval)
       && $job->requeue_interval > 0) {
      // The new job will be substantially the same as the last one...

      $this->register($job->co_id,
                      $job->plugin,
                      json_decode($job->parameters, true),
                      $job->register_summary,
                      false,
                      // we only support serialized jobs, not concurrent
                      false,
                      $job->requeue_interval,
                      $job->requeue_interval,
                      $job->retry_interval,
                      $job->id);
    } elseif($result == JobStatusEnum::Failed
             && !empty($job->retry_interval)
             && $job->retry_interval > 0) {
      // The new job will be substantially the same as the last one...

      $this->register(job->co_id,
                      $job->plugin,
                      json_decode($job->parameters, true),
                      $job->register_summary,
                      false,
                      // we only support serialized jobs, not concurrent
                      false,
                      $job->retry_interval,
                      $job->requeue_interval,
                      $job->retry_interval,
                      $job->id);
    }
  }

  /**
   * Determine if a Job has been canceled.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int  $id   Job ID
   * @return bool       True if the Job has been canceled, false otherwise
   */

  public function isCanceled(int $id): bool {
    // Make sure to skip any cached records, since a Job InProgress needs up to
    // date status to determine if it should stop.

    $job = $this->get($id, cache: null);

    return $job->status == JobStatusEnum::Canceled;
  }

  /**
   * Process a Job. Jobs must be in Ready status (ie: assigned) in order to be processed.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Job                      $job  Job to process
   * @throws InvalidArgumentException
   */

  public function process(Job $job) {
    // The Job must be Assigned to be processed
    if($job->status != JobStatusEnum::Assigned) {
      throw new \InvalidArgumentException(
        __d('error',
            'Jobs.status.invalid', 
            [
              $jobs->id, 
              __d('enumeration', 'JobStatusEnum.Assigned'),
              __d('enumeration', 'JobStatusEnum.InProgress'),
              __d('enumeration', 'JobStatusEnum.'.$job->status)
            ]
           )
      );
    }

    // First create an instance of the Entry Point Model
    $pClass = $this->instantiatePluginModel($job->plugin, '\Lib\Jobs');

    $JobHistoryRecords = TableRegistry::getTableLocator()->get('JobHistoryRecords');

    // Maybe set the connection on the JobHistoryTable (if we were run via
    // the queue runner).
    try {
      $cxn = ConnectionManager::get('plugin');

      if(!empty($cxn)) {
        $JobHistoryRecords->setConnection($cxn);
      }
    }
    catch(\Cake\Datasource\Exception\MissingDatasourceConfigException $e) {
      // plugin datasource not defined, so we're not in the queue runner
    }
    catch(\Exception $e) {
      $this->finish($job, $e->getMessage(), JobStatusEnum::Failed);
    }

    // We need a separate try block here because we want to specially handle
    // MissingDatasourceConfigException, above
    try {    
      $pClass->run(
        $this,
        $JobHistoryRecords,
        $job,
        json_decode(json: $job->parameters, associative: true)
      );
    }
    catch(\Exception $e) {
      $this->finish($job, $e->getMessage(), JobStatusEnum::Failed);
    }
  }

  /**
   * Register a new Job.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $coId             CO ID
   * @param  string $plugin           Plugin Entry Point Model, in Plugin.Model format
   * @param  array  $parameters       Plugin parameters
   * @param  string $registerSummary  Summary
   * @param  bool   $synchronous      Whether the Job is started (true) or queued (false)
   * @param  bool   $concurrent       Whether multiple instances of this Job with the same parameters are permitted to run concurrently
   * @param  int    $delay            Minimum number of seconds to delay the start of this Job
   * @param  int    $requeueInterval  If non-zero, number of seconds after successful completion to requeue the same Job
   * @param  int    $retryInterval    If non-zero, number of seconds after failed completion to requeue the same Job
   * @param  int    $requeuedFrom     If requeued, the ID of the Job that created this Job
   * @return Job                      Job entity
   * @throws InvalidArgumentException
   */

  public function register(
    int     $coId,
    string  $plugin,
    array   $parameters=[],
    string  $registerSummary="",
    bool    $synchronous=false,
    bool    $concurrent=false,
    int     $delay=0,
    ?int    $requeueInterval=null,
    ?int    $retryInterval=null,
    ?int    $requeuedFrom=null
  ): Job {
    // Start a transaction. In addition to ruleAlreadyRegistered needing a read lock,
    // if we're synchronous we need to make sure the current caller gets assigned the Job.

    $cxn = ConnectionManager::get('default');
    $cxn->begin();

    // Insert a new Job into the job table. A synchronous job is queued with status
    // "In Progress" on the assumption that the caller will immediately begin
    // processing it. Otherwise, the job is given status "Queued".

    $errors = $this->validateJobParameters($plugin, $coId, $parameters);

    if(!empty($errors)) {
      // Convert the error array to a string
      $err = "";

      foreach($errors as $p => $e) {
        $err .= "$p: $e,";
      }

      $cxn->rollback();

      $this->llog(level: 'error', msg: rtrim($err, ","));
      throw new \InvalidArgumentException(rtrim($err, ","));
    }

    $entity = $this->newEntity([
      'co_id'                 => $coId,
      'plugin'                => $plugin,
      'parameters'            => json_encode($parameters),
      'register_summary'      => $registerSummary,
      'register_time'         => date('Y-m-d H:i:s', time()),
      'status'                => JobStatusEnum::Queued,
      'requeue_interval'      => $requeueInterval,
      'retry_interval'        => $retryInterval,
      'requeued_from_job_id'  => $requeuedFrom,
      'start_after_time'      => date('Y-m-d H:i:s', time()+$delay)
      // We don't set percent_complete here since not all jobs might use that field,
      // and then a null vs 0 can be used to distinguish.
    ]);

    // If $concurrent is true, we want to disable AR-Job-1. Right now, since this
    // is the only application rule we can simply disable rule checking, but if
    // another rule is added this won't work.
    $this->saveOrFail($entity, ['checkRules' => !$concurrent]);

    if($synchronous) {
      // Assign the job within the transaction to make sure it doesn't get
      // picked up by a queue runner
      $this->assign($entity);
    }

    $cxn->commit();

    return $entity;
  }

  /**
   * Application Rule to determine if the Job is already registered.
   *
   * @since  COmanage Registyr v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleAlreadyRegistered($entity, $options) {
    // We can't SELECT COUNT ... FOR UPDATE, so we select first which is good enough
    $count = $this->find()
                  ->where([
                      'plugin'      => $entity->plugin,
                      'parameters'  => $entity->parameters,
                      'status IN'   => [JobStatusEnum::InProgress, JobStatusEnum::Queued]
                    ])
                  ->epilog('FOR UPDATE')
                  ->first();

    if(!empty($count)) {
      $this->llog(
        level: 'error',
        msg: "AR-Job-1 A Job matching the requested plugin (" . $entity->plugin . ") and parameters is already registered",
        id: $entity->id
      );
      return __d('error', 'Jobs.registered.already', [$entity->plugin]);
    }
    
    return true;
  }

  /**
   * Set the percent complete for a job.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Job    $job      Job to update
   * @param  int    $percent  Percent (between 0 and 100 inclusive)
   */

  public function setPercentComplete(Job $job, int $percent) {
    $job->percent_complete = $percent;

    $this->saveOrFail($job);
  }

  /**
   * Mark a job as in progress.
   *
   * @since  COmanage Registry v2.0.0
   * @param  Job            $job        Job to update
   * @param  string         $summary    Summary
   * @throws InvalidArgumentException
   */

  public function start(Job $job, string $summary="") {
    // The Job must be Assigned to be started
    if($job->status != JobStatusEnum::Assigned) {
      throw new \InvalidArgumentException(
        __d('error',
            'Jobs.status.invalid',
            [
              $job->id, 
              __d('enumeration', 'JobStatusEnum.Assigned'),
              __d('enumeration', 'JobStatusEnum.InProgress'),
              __d('enumeration', 'JobStatusEnum.'.$job->status)
            ]
           )
      );
    }

    $job->status = JobStatusEnum::InProgress;
    $job->start_summary = $summary;
    $job->start_time = date('Y-m-d H:i:s', time());

    $this->saveOrFail($job);
  }

  /**
   * Validate Job parameters.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string   $plugin   Plugin Entry Point Model, in Plugin.Model form
   * @param  int      $coId     CO ID
   * @param  array    $params   Parameters to validate
   * @return array              An array of validation errors, keyed on parameter name
   * @throws InvalidArgumentException
   */

  protected function validateJobParameters(string $plugin, int $coId, array $params): array {
    $ret = [];

    $pClass = $this->instantiatePluginModel($plugin, '\Lib\Jobs');

    // Validate each provided parameter

    $pluginParameters = $pClass->parameterFormat();

    foreach($params as $p => $val) {
      if(!empty($pluginParameters[$p])) {
        switch($pluginParameters[$p]['type']) {
          case 'bool':
          case 'boolean':
            // Because we want code that uses these parameters to be able to do
            // something like if($params['p']), we only accept values that PHP
            // will correctly parse in that context. For simplicity, we allow only
            // 0 and 1.
            if($val != 0 && $val != 1) {
              $ret[$p] = __d('error', 'Jobs.plugin.parameter.bool');
            }
            break;
          case 'fk':
            // The provided parameter must be in $coId. We don't actually need
            // to verify the format since the value either exists in the database
            // or it doesn't.
            $className = StringUtilities::foreignKeyToClassName($p);
            $Table = TableRegistry::getTableLocator()->get($className);
            
            $vals = [];
            
            if(is_int($val)) {
              $vals = [$val];
            } else {
              $vals = explode(',', $val);
            }

            foreach($vals as $v) {
              $entity = $Table->get($val);
              
              if($Table->calculateCoForRecord($entity) != $coId) {
                $ret[$p] = __d('error', 'Jobs.plugin.parameter.fk', [$v, $coId]);
                break;
              }
            }
            break;
          case 'int':
          case 'integer':
            if(!preg_match('/^[0-9.+-]*$/', $val)) {
              $ret[$p] = __d('error', 'Jobs.plugin.parameter.int');
            }
            break;
          case 'select':
            if(!in_array($val, $pluginParameters[$p]['choices'])) {
              $ret[$p] = __d('error', 'Jobs.plugin.parameter.select');
            }
            break;
          case 'string':
            // For now, anything can pass as a string
            break;
          default:
            $ret[$p] = __d('error', 'Jobs.plugin.parameter.type', [$pluginParameters[$p]['type']]);
            break;
        }
      } else {
        // Requested parameter is not defined
        $ret[$p] = __d('error', 'Jobs.plugin.parameter.invalid');
      }
    }

    // Check that required parameters were provided

    foreach(array_keys($pluginParameters) as $p) {
      if(isset($pluginParameters[$p]['required'])
         && $pluginParameters[$p]['required']
         && empty($params[$p])) {
        $ret[$p] = __d('error', 'parameter.required', [$p]);
      }
    }

    return $ret;
  }
  
  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('co_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('co_id');

    $this->registerStringValidation($validator, $schema, 'plugin', true);
/*
    $validator->add('parameters', [
      'content' => ['rule' => 'isArray']
    ]);
    $validator->allowEmptyArray('parameters');*/
// This doesn't work because parameters is passed as an array but stored as a string
//    $this->registerStringValidation($validator, $schema, 'parameters', false);

    $validator->add('requeue_interval', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('requeue_interval');

    $validator->add('retry_interval', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('retry_interval');

    $validator->add('requeued_from_job_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('requeued_from_job_id');

    $validator->add('status', [
      'content' => ['rule' => ['inList', JobStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $this->registerStringValidation($validator, $schema, 'assigned_host', false);

    $validator->add('assigned_pid', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('assigned_pid');

    $this->registerStringValidation($validator, $schema, 'register_summary', false);

    $this->registerStringValidation($validator, $schema, 'start_summary', false);

    $this->registerStringValidation($validator, $schema, 'finish_summary', false);

    $validator->add('register_time', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('register_time');

    $validator->add('start_after_time', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('start_after_time');

    $validator->add('start_time', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('start_time');

    $validator->add('finish_time', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('finish_time');

    $validator->add('percent_complete', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->add('percent_complete', [
      'range'   => ['rule' => 'range', 0, 100]
    ]);
    $validator->allowEmptyString('percent_complete');
    
    return $validator; 
  }
}