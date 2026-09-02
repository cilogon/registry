<?php
/**
 * COmanage Registry Terms and Conditions Agreement Entity
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

namespace App\Model\Entity;

use Cake\Core\Configure;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;

class TAndCAgreement extends Entity {
  use \App\Lib\Traits\EntityMetaTrait;
  use \App\Lib\Traits\LabeledLogTrait;

  protected array $_accessible = [
    '*' => true,
    'id' => false,
    'slug' => false, 
  ];

  /**
   * Get the URL for this Terms and Conditions Agreement.
   *
   * @since  COmanage Registry v5.3.0
   * @return string|null   URL
   */

  protected function _getUrl(): ?string {
    // If the associated TermsAndConditions is already loaded, check it first
    if(!empty($this->terms_and_conditions)) {
      return $this->terms_and_conditions->url;
    }

    // Otherwise look up the Terms and Conditions if terms_and_conditions_id is present
    if(!empty($this->terms_and_conditions_id)) {
      try {
        $TermsAndConditions = TableRegistry::getTableLocator()->get('TermsAndConditions');

        return $TermsAndConditions->get($this->terms_and_conditions_id)->url;
      } catch(\Exception $e) {
        // We will rethrow here to facilitate debugging
        if (Configure::read('debug')) {
          throw $e;
        }

        $this->llog('error', $e->getMessage());
        return null;
      }
    }

    return null;
  }
}