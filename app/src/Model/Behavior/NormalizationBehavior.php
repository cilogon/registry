<?php
/**
 * COmanage Registry Normalization Behavior
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Model\Behavior;

use Cake\Event\Event;
use Cake\Log\Log;
use Cake\ORM\Behavior;
use Cake\ORM\TableRegistry;
use \App\Lib\Enum\SuspendableStatusEnum;
use ArrayObject;

class NormalizationBehavior extends Behavior 
{
  // Supported Plugins and fields for normalization, as set by the Table using NormalizationBehavior
  protected $supportedPlugins = [];

  /**
   * Run configured normalization on $data.
   *
   * @since  COmanage Registry v5.3.0
   * @param  Event       $event   beforeMarshal event
   * @param  ArrayObject $data    Entity data
   * @param  ArrayObject $options Callback options
   */
  
  public function beforeMarshal(Event $event, ArrayObject $data, ArrayObject $options) {
    if(isset($options['skipNormalization']) && $options['skipNormalization']) {
      return;
    }

    // Normalizations are not applied to records that are frozen (AR-Normalization-1).
    if(isset($data['frozen']) && $data['frozen']) {
      return;
    }

    $Table = $event->getSubject();

    // We need the CO for the record in order to find the appropriate configuration(s) to use,
    // but for that we need $data in entity form.

    // This will recurse, so make sure to break the loop
    $entity = $Table->newEntity((array)$data, options: ['skipNormalization' => true]);

    $coId = $Table->calculateCoForRecord($entity);

    if($coId) {
      // We simply call all configured Normalization Plugins with the current table and data,
      // and they can decide if they want to do anything with them.

      $NormalizationTable = TableRegistry::getTableLocator()->get('Normalizations');

      $normalizations = $NormalizationTable->find()
                                           ->where([
                                             'co_id' => $coId,
                                             'status' => SuspendableStatusEnum::Active
                                           ])
                                           ->orderBy(['Normalizations.ordr' => 'ASC'])
                                           ->contain($NormalizationTable->getPluginRelations())
                                           ->all();

      foreach($normalizations as $n) {
        $Plugin = TableRegistry::getTableLocator()->get($n->plugin);

        $Plugin->normalize($n, $Table, $data);
      }
    }
  }

  /**
   * Get the list of supported Plugins and fields.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  string $plugin Plugin to get normalizable fields for
   * @return array          Array of fields to be normalized or null if $plugin is not supported
   */

  public function getNormalizableFields(string $plugin): ?array {
    return $this->supportedFields[$plugin] ?? null;
  }

  /**
   * Set the list of supported Plugins and fields.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  array  $normalizableModels Array in Plugin => [fields] format
   */

  public function setNormalizableFields(array $normalizableFields) {
    $this->supportedFields = $normalizableFields;
  }
}