<?php
/**
 * COmanage Registry Person Role Mapping Entity
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
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace PipelineToolkit\Model\Entity;

use Cake\ORM\Entity;
use \App\Lib\Enum\ComparisonEnum;
use \App\Model\Entity\ExternalIdentityRole;

class PersonRoleMapping extends Entity {
  use \App\Lib\Traits\EntityMetaTrait;
  
  /**
   * Fields that can be mass assigned using newEntity() or patchEntity().
   *
   * Note that when '*' is set to true, this allows all unspecified fields to
   * be mass assigned. For security purposes, it is advised to set '*' to false
   * (or remove it), and explicitly make individual fields accessible as needed.
   *
   * @var array<string, bool>
   */
  protected array $_accessible = [
    '*' => true,
    'id' => false,
    'slug' => false,
  ];

  /**
   * Determine if the provided array of Person Role attributes matches the
   * conditions of this Mapping.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  array                 $attributes  Array of Person Role attributes, as currently assembled
   * @param  ExternalIdentityRole  $eirdata     External Identity Role data, from the backend
   * @return bool                  true if the conditions match, false otherwise
   */

  public function matches(array $attributes, ExternalIdentityRole $eirdata): bool {
    // Some conditions use special tests, most use ComparisonEnum.
    // In general, we want to use the original EIR data for comparisons.

    if($this->attribute == 'AdHocAttribute.value') {
      // We first need an AdHocAttribute that matches the configured tag.
      // Note we only look at AdHocAttributes attached to the EIR, not the
      // External Identity.

      if(!empty($this->ad_hoc_tag) && !empty($eirdata->ad_hoc_attributes)) {
        // In the event there is more than one AdHocAttribute with the configured
        // tag, we'll check each of them, and return true if any match.
        foreach($eirdata->ad_hoc_attributes as $adhoc) {
          if(!empty($adhoc->tag) && $this->ad_hoc_tag == $adhoc->tag) {
            // Correct tag, now compare the value. We only return if compare()
            // returns true, otherwise we keep iterating.

            if(ComparisonEnum::compare(
              value:      $adhoc->value,
              comparison: $this->comparison,
              pattern:    $this->pattern
            )) {
              return true;
            }
          }
        }
      }
    } elseif($this->attribute == 'ExternalIdentityRole.affiliation') {
      // We should be given the affilation type id as mapped from the inbound EIR data,
      // in which case we simply compare against the configured value
      return (!empty($eirdata->affiliation_type_id)
              && !empty($this->affiliation_type_id)
              && ($eirdata->affiliation_type_id == $this->affiliation_type_id));
    } else {
      // This is something like ExternalIdentityRole.title, etc

      $bits = explode('.', $this->attribute, 2);
      $attr = $bits[1];

      if(!empty($this->comparison) && !empty($this->pattern)) {
        return ComparisonEnum::compare(
          value:      $eirdata->$attr,
          comparison: $this->comparison,
          pattern:    $this->pattern
        );
      }
    }

    return false;
  }
}
