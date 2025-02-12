<?php
/**
 * COmanage Registry Env Source Detours Table
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

namespace EnvSource\Model\Table;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use \App\Lib\Enum\ActionEnum;
use \App\Lib\Events\CoIdEventListener;

class EnvSourceDetoursTable extends Table {
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\TrafficDetourTrait;
  use \App\Lib\Traits\ValidationTrait;

  // Cache of Table Models
  // protected $tableCache = [];
  
  // Cache of the type map, for flat mode
  // protected $typeCache = [];

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.1.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('TrafficDetours');

    $this->setDisplayField('id');
    
    $this->setPrimaryLink(['traffic_detour_id']);
// XXX Move this to parent model? (and add all possible values)
    $this->setAllowEmptyPrimaryLink(['postlogin']);
    $this->setRequiresCO(false);

    // We want to update attributes after login
    $this->setSupportedDetourContext('postlogin');

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin'],
        'view' =>     ['platformAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false, //['platformAdmin'],
        'index' =>    ['platformAdmin']
      ]
    ]);
  }

  /**
   * Refresh Env Source attributes associated with the authenticated identifier.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int                $detourId     Traffic Detour ID
   * @param  string             $sourceKey    Source Key
   * @param  CoIdEventListener  $coidListener CoIdEventListener
   */

  public function refresh(
    int $detourId,
    string $sourceKey,
    CoIdEventListener $coidListener
  ) {
    // While a given Source Key can only be associated with one External Identity 
    // within a CO, it _can_ be associated with multiple External Identities across
    // multiple COs.

    // Start by pulling all Env Source Identities with this Source Key.
    // (This is the cache of Env Source data that is ultimately read by the
    // retrieve() during the Pipeline.)

    $EnvSourceIdentities = TableRegistry::getTableLocator()->get('EnvSource.EnvSourceIdentities');
    $EnvSourceCollectors = TableRegistry::getTableLocator()->get('EnvSource.EnvSourceCollectors');
    $ExternalIdentitySources = TableRegistry::getTableLocator()->get('ExternalIdentitySources');
    $ExtIdentitySourceRecords = TableRegistry::getTableLocator()->get('ExtIdentitySourceRecords');

    $esis = $EnvSourceIdentities->find()
                                ->where(['EnvSourceIdentities.source_key' => $sourceKey])
                                ->contain(['EnvSources'])
                                ->all();
    
    if(!empty($esis)) {
      $this->llog('trace', "Found " . $esis->count() . " EnvSourceIdentities for source key " . $sourceKey);

      foreach($esis as $esi) {
      //  debug($esi);

        if($esi->env_source->sync_on_login) {
          // Use EnvSourceCollectorsTable to parse the attributes
          $attrs = $EnvSourceCollectors->parse($esi->env_source);

          if(!empty($attrs['env_identifier_sourcekey']
             && $attrs['env_identifier_sourcekey'] === $sourceKey)) {
            // Update the EnvSourceIdentity (strictly speaking this should always
            // be an update since we just pulled the matching record...)

            $esi->env_attributes = json_encode($attrs);

            try {
              $EnvSourceIdentities->saveOrFail($esi, ['associated' => false]);

              // Because we operate outside of a CO context, we need to manually update
              // the CO for the Models that require it for validation purposes. We do
              // this via CoIdEventListener so we don't have to enumerate the list of
              // supported models.
              $coId = $ExternalIdentitySources->findCoForRecord($esi->env_source->external_identity_source_id);

              $coidListener->updateCoId($coId);

              // Resync the External Identity (rerun the Pipeline)

              $status = $ExternalIdentitySources->sync(
                id: $esi->env_source->external_identity_source_id,
                sourceKey: $sourceKey,
                // Unlike our initial run during Enrollment, we _do_ want provisioning to 
                // run here. (This will also run Identifier Assignment, but in general
                // identifiers should have already been assigned.)
                syncOnly: false
              );

              $this->llog('trace', "Sync of Env Source ID " . $esi->env_source->id . " Source Key " . $sourceKey . " completed: " . $status);

              if($status == 'updated') {
                // Record a History Record, but in order to do that we need to map the $sourceKey
                // (which was done in the sync process but not bubbled up)

                $eisr = $ExtIdentitySourceRecords->find()
                                                 ->where([
                                                  'ExtIdentitySourceRecords.external_identity_source_id' => $esi->env_source->external_identity_source_id,
                                                  'ExtIdentitySourceRecords.source_key' => $sourceKey
                                                 ])
                                                 ->contain(['ExternalIdentities'])
                                                 ->firstOrFail();

                $ExtIdentitySourceRecords->ExternalIdentities
                                         ->recordHistory(
                                          entity: $eisr->external_identity,
                                          action: ActionEnum::ExternalIdentityLoginUpdate,
                                          comment: __d('env_source', 'result.env.saved.login')
                                         );
              }
            }
            catch(\Exception $e) {
              $this->llog('error', "Sync of Env Source ID " . $esi->env_source->id . " Source Key " . $sourceKey . " failed: " . $e->getMessage());
            }
          } else {
            // We shouldn't get here since we just did a find based on $sourceKey
            $this->llog('error', "Source Key mismatch for Env Source ID " . $esi->env_source->id . " Source Key " . $sourceKey);
          }
        } else {
          $this->llog('trace', "sync_on_login disabled for Env Source ID " . $esi->env_source->id); 
        }
      }
    }
  }

  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   * @throws InvalidArgumentException
   * @throws RecordNotFoundException
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('traffic_detour_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('traffic_detour_id');
    
    return $validator; 
  }
}