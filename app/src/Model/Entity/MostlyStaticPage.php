<?php
/**
 * COmanage Registry Mostly Static Page Entity
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

namespace App\Model\Entity;

use Cake\ORM\Entity;
use App\Lib\Util\StringUtilities;

class MostlyStaticPage extends Entity {
  use \App\Lib\Traits\EntityMetaTrait;
  
  protected array $_accessible = [
    '*' => true,
    'id' => false,
    'slug' => false, 
  ];

  /**
   * Determine if this entity record can be deleted.
   *
   * @since  COmanage Registry v5.2.0
   * @return bool True if the record can be deleted, false otherwise
   */

  public function canDelete(): bool {
    // AR-MostlyStaticPage-3 Default Pages can not be deleted, or have their names, status,
    // or context changed.
    return !$this->isDefaultPage();
  }

  /**
   * Determine if this entity is a default Page (shipped out of the box and relied on by other
   * parts of the Application).
   *
   * @since  COmanage Registry v5.1.0
   * @return bool true if this entity is a defalut Page, false otherwise
   */

  public function isDefaultPage(): bool {
    // We use the original value because if we're in the middle of a save we'll have
    // the proposed new value even though we haven't persisted it yet
    return in_array($this->getOriginal('name'), [
      'default-handoff',
      'duplicate-landing',
      'error-landing',
      'mfa-required',
      'petition-complete'
    ]);
  }
  
  /**
   * Get the URL for this Mostly Static Page.
   *
   * @since  COmanage Registry v5.3.0
   * @return string   URL
   */
  
  protected function _getUrl(): ?string {
    // Note this only works once the entity has been persisted, so it's suitable
    // for lookup from other contexts (eg: SqlProvisioner), and not for the MSP fields.inc
    // template, since we need to dynamically recalculate the URL there.

    if(!empty($this->name)) {
      return \Cake\Routing\Router::url(
        url: StringUtilities::pagesUrl($this->co_id, $this->name),
        full: true
      );
    }

    return null;
  }
}