<?php
/**
 * COmanage Registry Alert Helper
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
 * @link          http://www.internet2.edu/comanage COmanage Project
 * @package       registry
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace App\View\Helper;

use \Cake\View\Helper;
use phpDocumentor\Reflection\Types\Boolean;

/**
 * Helper which will produce Bootstrap based alert
 *
 * @param string $message          Alert message
 * @param string $type             Define the type of Alert. The value should be one of
 *                                 [success,warning,danger,info]. Defaults to 'warning'
 * @param boolean $dismissable     Can the Alert be dismissed? Defaults to false.
 * @param string|null $title       Title to display (typically "Success", "Error", or "Warning"). Defaults to null.
 * @param boolean $dis_text_dark   Disable dark-text fonts for light|info color mode.
 * @return mixed - a constructed HTML block
 * @since  COmanage Registry v5.0.0
 */
class AlertHelper extends Helper {
  
  public $helpers = ['Html'];
  
  public function alert(
    string $message,
    string $type = 'warning',
    bool $dismissable = false,
    string $title = null ) {
    
    $closeButton = '';
    $dismissableClass = '';
    if($dismissable) {
      $closeButton = '
        <span class="alert-button">
          <button type="button" class="btn-close nospin" data-bs-dismiss="alert" aria-label="Close"></button>
        </span>
      ';
      $dismissableClass = ' alert-dismissible';
    }
  
    $titleMarkup = '';
    if(!empty($title)) {
      $titleMarkup = '<span class="alert-title-text">' . $title . '</span>';
    }
    
    return '
      <div class="alert alert-' . $type . $dismissableClass . ' co-alert" role="alert">
        <div class="alert-body d-flex align-items-center">
          <span class="alert-title d-flex align-items-center">
            ' . $this->getAlertIcon($type) . $titleMarkup .  '
          </span>
          <span class="alert-message">        
            ' . $message . '
          </span>
          ' . $closeButton . '
        </div>
      </div>
    ';
  }
  
  public function getAlertIcon(string $type) {
    switch($type) {
      case('success'): return '<span class="material-icons-outlined alert-icon">check_circle</span>';
      case('information'): return '<span class="material-icons-outlined alert-icon">info</span>';
      default: return '<span class="material-icons-outlined alert-icon">report_problem</span>';
    }
  }

}