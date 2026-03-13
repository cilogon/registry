<?php
/**
 * COmanage Registry Env Source Collectors Table
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace EnvSource\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\PetitionActionEnum;

class EnvSourceCollectorsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.1.0
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

    $this->hasMany('EnvSource.PetitionEnvIdentities')
         ->setDependent(true)
         ->setCascadeCallbacks(true);

    $this->setDisplayField('id');

    $this->setPrimaryLink('enrollment_flow_step_id');
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['dispatch', 'display']);

    $this->setAutoViewVars([
      'externalIdentitySources' => [
        'type' => 'plugin',
        'model' => 'EnvSource.EnvSources'
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
   * Check for an existing External Identity associated with the requested Source Key.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int    $eisId      External Identity Source ID
   * @param  string $sourceKey  Source Key
   * @return bool               true if the check passes and it is OK to proceed
   * @throws OverflowException
   */

  protected function checkDuplicate(int $eisId, string $sourceKey): bool {
    $EISRecords = TableRegistry::getTableLocator()->get('ExtIdentitySourceRecords');

    $dupe = $EISRecords->find()
                       ->where([
                        'source_key'                  => $sourceKey,
                        'external_identity_source_id' => $eisId
                       ])
                       ->first();

    if(!empty($dupe)) {
      $this->llog('error', "Source Key $sourceKey is already attached to External Identity " . $dupe->external_identity_id . " for External Identity Source ID " . $eisId);

      throw new \OverflowException(__d('env_source', 'error.source_key.duplicate', [$sourceKey, $dupe->external_identity_id]));
    }

    return true;
  }

  /**
   * Perform steps necessary to hydrate the Person record as part of Petition finalization.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int      $id           Env Source Collector ID
   * @param  Petition $petition     Petition
   * @return bool                   true on success
   * @throws OverflowException
   * @throws RuntimeException
   */

  public function hydrate(int $id, \App\Model\Entity\Petition $petition) {
    $cfg = $this->get($id);

    $PetitionHistoryRecords = TableRegistry::getTableLocator()->get('PetitionHistoryRecords');

    // At this point there is a Person record allocated and stored in the Petition.
    // We need to sync the EnvSource Identity (which is cached in env_source_identities)
    // to the Enrollee Person.

    // We need the Source Key to sync, which is available via the EnvSourceIdentity.

    $pei = $this->PetitionEnvIdentities->find()
                                       ->where(['petition_id' => $petition->id])
                                       ->contain(['EnvSourceIdentities'])
                                       ->first();

    if(!empty($pei->env_source_identity->source_key)) {
      $ExtIdentitySources = TableRegistry::getTableLocator()->get('ExternalIdentitySources');

      // If this is a duplicate enrollment for this External Identity (ie: there is
      // already an External Identity associated with this Petition Env Identity) we
      // need to check for that and throw a duplicate error here. If we allow it to run,
      // the Pipeline Sync code will find the existing External Identity and merge this
      // request to that record. (In a way, this is technically OK since it's the same
      // external record, but this will appear unintuitively as a successful enrollment
      // when most deployments will want to treat it as a duplicate.)

      // This will throw OverflowException on duplicate
      $this->checkDuplicate($cfg->external_identity_source_id, $pei->env_source_identity->source_key);

      try {
        // Continue on to process the sync
        $status = $ExtIdentitySources->sync(
          id: $cfg->external_identity_source_id,
          sourceKey: $pei->env_source_identity->source_key,
          personId: $petition->enrollee_person_id,
          syncOnly: true
        );

        $PetitionHistoryRecords->record(
          petitionId:           $petition->id, 
          enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
          action:               PetitionActionEnum::Finalized,
          comment:              __d('env_source', 'result.pipeline.status', [$status])
        );

        // Because PetitionsTable::hydrate() creates a skeletal Person record without
        // a Name, PipelinesTable::createPersonFromEIS() won't be called from sync(),
        // so the Pipeline won't create a Primary Name if there isn't one already.
        // As such, we need to check here if there is a Primary Name (highly dependent
        // on the Flow configuration), and if there isn't one we'll create one (even
        // though a subsequent step such as an Attribute Collector might create a new
        // one later).

        $Names = TableRegistry::getTableLocator()->get('Names');

        try {
          $Names->primaryName($petition->enrollee_person_id);
        }
        catch(RecordNotFoundException $e) {
          // No Primary Name found, create one. Note we need to honor
          //   AR-Pipeline-1 If a Pipeline creates a new Person, the first Name
          //   returned by the External Identity Source backend will be used as
          //   the initial Primary Name for the new Person.
          // so we call retrieve() for consistency (though note EnvSource only
          // supports 1 name currently).

          $EnvSources = TableRegistry::getTableLocator()->get('EnvSource.EnvSources');

          $eis = $ExtIdentitySources->get(
            $cfg->external_identity_source_id,
            contain: 'EnvSources'
          );

          $eisrecord = $EnvSources->retrieve($eis, $pei->env_source_identity->source_key);

          if(!empty($eisrecord['entity_data']['names'][0])) {
            $name = $eisrecord['entity_data']['names'][0];

            // Add the additional attributes and convert the type back to a type_id
            $name['person_id'] = $petition->enrollee_person_id;
            $name['primary_name'] = true;
            $name['type_id'] = $eis->env_source->name_type_id;
            unset($name['type']);

            $Names->saveOrFail($Names->newEntity($name));

            $this->llog('trace', 'EnvSource created Primary Name for Petition ' . $petition->id);
          } else {
            // This isn't necessarily an error since a subsequent step could create
            // a Primary Name
            $this->llog('trace', 'EnvSource could not find a Name for Petition ' . $petition->id);
          }
        }
      }
      catch(\Exception $e) {
        // We allow an error in the sync process (probably a duplicate record) to interrupt
        // finalization since it could result in an inconsistent state (multiple Person
        // records for the same External Identity). We don't bother recording Petition History
        // here though since we're about to rollback.

        $this->llog('error', 'Sync failure during hydration of Petition ' . $petition->id . ': ' . $e->getMessage());

        throw new \RuntimeException($e->getMessage());
      }
    } else {
      // If there's no source key (which is unlikely) we record an error but don't try
      // to abort finalization.

      $this->llog('error', 'No source key found during hydration of Petition ' . $petition->id);

      $PetitionHistoryRecords->record(
        petitionId:           $petition->id, 
        enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
        action:               PetitionActionEnum::Finalized,
        comment:              __d('env_source', 'error.source_key')
      );
    }

    return true;
  }

  /**
   * Load environment variables from a lookaside file based on the given configuration.
   *
   * @since  COmanage Registry v5.1.0
   * @param  string                            $filename Path to the lookaside file
   * @param  \EnvSource\Model\Entity\EnvSource $envSource EnvSource configuration entity
   * @return array                             Array of environment variables and their parsed values
   * @throws InvalidArgumentException
   */
  
  public function loadFromLookasideFile(string $filename, \EnvSource\Model\Entity\EnvSource $envSource): array {
    $src = parse_ini_file($filename);
    $ret = [];

    if(!$src) {
      throw new \InvalidArgumentException(__d('env_source', 'error.lookaside_file', [$filename]));
    }

    // We walk through our configuration and only copy the variables that were configured
    foreach($envSource->getVisible() as $field) {
      // We only want the fields starting env_ (except env_source_id, which is changelog metadata)

      if(strncmp($field, "env_", 4)==0 && $field != "env_source_id"
        && !empty($envSource->$field)          // This field is configured with an env var name
        && isset($src[$envSource->$field])     // This env var is populated
      ) {
        // Note we're using the EnvSource field name (eg: env_name_given) as the key
        // and not the configured variable name (which might be something like SHIB_FIRST_NAME)
        $ret[$field] = $src[$envSource->$field];
      }
    }

    return $ret;
  }

  /**
   * Parse the environment values as per the configuration.
   * 
   * @since  COmanag Registry v5.1.0
   * @param  EnvSource  $envSource  EnvSource configuration entity
   * @return array                  Array of env variables and their parsed values
   * @throws InvalidArgumentException
   */

  public function parse(\EnvSource\Model\Entity\EnvSource $envSource): array {
    // The filtered set of variables to return
    $ret = [];

    // XXX getenv() does not return all the environmental variables. We need to check
    //     one by one.
    if(!empty($envSource->lookaside_file)) {
      // The look aside file is for debugging purposes. If the file is specified but not found,
      // we throw an error to prevent unintended configurations.

      return $this->loadFromLookasideFile($envSource->lookaside_file, $envSource);
    }

    // We walk through our configuration and only copy the variables that were configured
    foreach($envSource->getVisible() as $field) {
      // We only want the fields starting env_ (except env_source_id, which is changelog metadata)

      if(strncmp($field, "env_", 4)==0 && $field != "env_source_id"
         && !empty($envSource->$field)          // This field is configured with an env var name
         && getenv($envSource->$field)          // This env var is populated
      ) {
        // Note we're using the EnvSource field name (eg: env_name_given) as the key 
        // and not the configured variable name (which might be something like SHIB_FIRST_NAME)
        $ret[$field] = getenv($envSource->$field);
      } 
    }

    return $ret;
  }

  /**
   * Insert or update a Petition Env Identity.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int    $id           Env Source Collector ID
   * @param  int    $petitionId   Petition ID
   * @param  array  $attributes   Env Sounce Attributes
   * @return bool                 true on success
   * @throws InvalidArgumentException
   * @throws OverflowException
   * @throws PersistenceFailedException
   */

  public function upsert(int $id, int $petitionId, array $attributes) {
    if(empty($attributes['env_identifier_sourcekey'])) {
      throw new \InvalidArgumentException(__d('env_source', 'error.source_key'));
    }

    $sourceKey = $attributes['env_identifier_sourcekey'];

    // Pulling our configuration is a bit complicated because of the indirect relations
    $envSourceCollector = $this->get($id);

    $EnvSources = TableRegistry::getTableLocator()->get('EnvSource.EnvSources');

    $envSource = $EnvSources->find()
                            ->where(['external_identity_source_id' => $envSourceCollector->external_identity_source_id])
                            ->firstOrFail();

    // We first check that there is not an External Identity in this CO that
    // already has this Source Key from this EnvSource instance. Technically we
    // could wait until finalization and let the Pipeline implement this check,
    // but it's a better user experience to detect the situation earlier in the flow.

    // Note it is OK if another Petition was started with the same Source Key, but only
    // one such Petition can successfully complete - the other(s) will fail at finalize
    // (if not sooner). This allows for abandoned enrollments, etc.

    // This will throw OverflowException on duplicate
    $this->checkDuplicate($envSourceCollector->external_identity_source_id, $sourceKey);

    // We need to update two tables here because of constraints imposed by how
    // EnvSource works. First we insert a record into EnvSourceIdentities, which
    // is basically a cache of known identities. The Source Key must be unique
    // (within the External Identity Source), so an existing record there is an
    // error _unless_ there is not yet a corresponding External Identity.
    // EnvSourceIdentities is the table used by EnvSource::retrieve in order to
    // sync the Identity to a Person (since at that point there is no concept of
    // a Petition).

    $EnvSourceIdentities = TableRegistry::getTableLocator()->get('EnvSource.EnvSourceIdentities');

    $esi = $EnvSourceIdentities->upsertOrFail(
      data: [
        'env_source_id'   => $envSource->id,
        'source_key'      => $sourceKey,
        'env_attributes'  => json_encode($attributes)
      ],
      whereClause: [
        'env_source_id'   => $envSource->id,
        'source_key'      => $sourceKey
      ]
    );

    // We then upsert PetitionEnvIdentities, which is the Petition artifact linking
    // to the EnvSourceIdentity. We allow the same source_key to exist in multiple
    // Petitions, eg to account for abandoned enrollments. However, only one Petition
    // may create the External Identity, so once that happens any other pending
    // Petitions will fail to finalize.

// XXX Each source_key must be unique across External Identities within the CO
//     We do allow more than one active, non-finalized Petition to have the same
//     source_key, however this should generate a warning as only the first one to
//     finalize will be successful.
//     - A source_key already associated with an External Identity may not be associated
//       with a new Petition within the same CO
//     - A source_key already associated with an active, non-finalized Petition may be
//       associated with another new Petition within the same CO, however only the first
//       Petition to finalize will be associated with the source_key
//     The above could be PARs, but these rules are also likely to apply to whatever
//     v4 Query mode becomes, and so should maybe be more general (like
//     AR-ExternalIdentitySourceRecord-1).

    $pei = $this->PetitionEnvIdentities->upsertOrFail(
      data: [
        'petition_id' => $petitionId,
        'env_source_collector_id' => $id,
        'env_source_identity_id' => $esi->id
      ],
      whereClause: [
        'petition_id' => $petitionId,
        'env_source_collector_id' => $id,
      ]
    );

    // Record Petition History

    $PetitionHistoryRecords = TableRegistry::getTableLocator()->get('PetitionHistoryRecords');

    $PetitionHistoryRecords->record(
      petitionId:           $petitionId, 
      enrollmentFlowStepId: $envSourceCollector->enrollment_flow_step_id,
      action:               PetitionActionEnum::AttributesUpdated,
      comment:              __d('env_source', 'result.env.saved')
    );

    return true;
  }

  /**
   * Set validation rules.
   *
   * @since  COmanage Registry v5.1.0
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

  /**
   * Obtain the set of Email Addresses known to this plugin that are eligible for
   * verification or that have already been verified.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  EntityInterface  $config       Configuration entity for this plugin
   * @param  int              $petitionId   Petition ID
   * @return array                          Array of Email Addresses and verification status
   */

  public function verifiableEmailAddresses(
    EntityInterface $config, 
    int $petitionId
  ): array {
    // We treat the email address (if any) provided by the external source (IdP)
    // as verifiable, or possibly already verified (trusted). EnvSource does not
    // support per-record verification flags, either all email addresses from this
    // source are verified or none are. This, in turn, is actually configured in the
    // Pipeline, which is where record modification happens. (We don't actually create
    // any Verification artifacts here -- that will be handled by the Pipeline
    // during finalization.)
    
    $eis = $this->ExternalIdentitySources->get(
      $config->external_identity_source_id,
      contain: 'Pipelines'
    );

    $defaultVerified = isset($eis->pipeline->sync_verify_email_addresses)
                       && ($eis->pipeline->sync_verify_email_addresses === true);

    $pei = $this->PetitionEnvIdentities->find()
                                       ->where(['petition_id' => $petitionId])
                                       ->contain(['EnvSourceIdentities'])
                                       ->first();

    if(!empty($pei->env_source_identity->env_attributes)) {
      $attrs = json_decode($pei->env_source_identity->env_attributes);

      if(!empty($attrs->env_mail)) {
        return [$attrs->env_mail => $defaultVerified];
      }
    }
    
    return [];
  }
}