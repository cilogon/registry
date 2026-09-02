<?php
/**
 * COmanage Registry Terms and Conditions Entity
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

class TermsAndConditions extends Entity {
  use \App\Lib\Traits\EntityMetaTrait;
  use \App\Lib\Traits\LabeledLogTrait;

  protected array $_accessible = [
    '*' => true,
    'id' => false,
    'slug' => false, 
  ];

  /**
   * Get the URL for this Terms and Conditions as a virtual field.
   *
   * @param string|null $url Existing URL field value
   * @return string|null      URL
   * @throws \Exception
   * @since  COmanage Registry v5.3.0
   */
  protected function _getUrl(?string $url = null): ?string {
    if(!empty($url)) {
      return $url;
    }

    if(!empty($this->mostly_static_page?->url)) {
      return $this->mostly_static_page->url;
    }

    if(!empty($this->mostly_static_page_id)) {
      try {
        $MSPTable = TableRegistry::getTableLocator()->get('MostlyStaticPages');
        $msp = $MSPTable->get($this->mostly_static_page_id);
        return $msp->url;
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