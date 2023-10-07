<?php
/**
 * COmanage Registry Entity Meta Trait
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

namespace App\Lib\Traits;

use Cake\Utility\Inflector;

trait EntityMetaTrait {
  /**
   * Determine if the record described in $data is probably the same as the
   * current value of the Entity. This is intended to support Pipelines in
   * trying to determine if an External Identity Source associated model is
   * the same as an existing External Identity associated model.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  array  $data EIS Record associated model data
   * @return bool         True if the record is probably for this entity, false otherwise
   */

  public function isProbablyThisArray(array $data): bool {
    // Our first attempt at this is to compare the fields provided in $data
    // against the entity. This facilitates skipping the metadata, and doesn't
    // require any special hacks to introspect the entity, since Cake doesn't
    // provide a mechanism to get all fields on an entity.
    // (get_object_vars($this)['_fields'] is a hacky workaround to that.
    // (This might miss some edge cases where the backend doesn't define an
    // attribute so we don't match it against the entity.)

    if(empty($data)) {
      // Nothing to check, so assume false
      return false;
    }

    // It's a match until it isn't
    $match = true;

    foreach($data as $field => $value) {
      if((!isset($this->$field) && !empty($value))   // Value in $data but not $entity
         || (isset($this->$field) && empty($value))  // Value in $entity but not $data
         || (isset($this->$field) && $this->$field != $value)) {  // Values don't match
        // Not a match
        $match = false;
        break;
      }
    }

    return $match;
  }

  /**
   * Determine the source attribute foreign key (eg: source_name_id) for this entity.
   * 
   * @since  COmanage Registry v5.0.0
   * @return string   Source Attribute column name
   */

  public function sourceAttributeName() {
    // The class name is something like `\App\Model\Entity\TelephoneNumber', but we
    // want telephone_number (lowercased).
    $entityName = Inflector::underscore(substr(strrchr(get_class($this), '\\'),1));

    return "source_" . $entityName . "_id";
  }
}
