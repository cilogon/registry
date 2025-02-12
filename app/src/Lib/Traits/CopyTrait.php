<?php
/**
 * COmanage Registry Copy Trait
 * 
 * We call this "Copy Trait" rather than "Duplicate Trait" to avoid confusion with
 * "duplicate" as used in (eg) flagging a record as duplicate/duplicate status.
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

namespace App\Lib\Traits;

trait CopyTrait {
  /**
   * Copy an entity.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int              $id       Entity to copy
   * @param  ?array           $related  If not null, also copy the specified related models
   * @return EntityInterface            New, persisted entity
   */

  public function copy(
    int     $id,
    ?array  $related=null
  ): \Cake\Datasource\EntityInterface {
    // Each plugin can have its own complex configuration, which makes things complicated,
    // though for the first pass getPluginRelations() gets us as much as we need.

    // Pull the original
    $query = $this->find()->where(['id' => $id]);

    if(!empty($related)) {
      $query = $query->contain($related);
    }

    $source = $query->firstOrFail();

    // Convert to an array, which is what we need to create the new entities,
    // and filter out the metadata fields.

    $copy = $this->filterMetadataForCopy($this, $source, $related);

    // Rename the "name" field, if present.

    if(!empty($copy['name'])) {
      $copy['name'] = __d('field', 'copy-a', [$copy['name']]);
    }

    // Convert the array into a new entity. We disable validation since it's possible
    // validation rules changed since the original was persisted (either via code
    // changes or configuration) but we'll honor the original since at some point it
    // saved successfully.

    $obj = $this->newEntity($copy, ['associated' => $related, 'validated' => false]);

    // Pluggable models are NOT turned into entity heres (they remain as arrays),
    // presumably because the ORM doesn't know how to create the table since the
    // related model list is NOT in plugin.format. As a workaround, 
    // PluggableModelTrait::afterMarshal creates the new entities.
    
    // Finally save, along with the associated models. By default Cake will save one
    // level of associations, but we want to save anything in $related. We'll skip rules
    // checking here for the same reason we skipped validation.

    $this->saveOrFail($obj, ['associated' => $related, 'checkRules' => false]);

    return $obj;
  }
}
