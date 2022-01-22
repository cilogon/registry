<?php
/**
 * COmanage Registry Badge Helper
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

declare(strict_types = 1);

namespace App\View\Helper;
use \Cake\View\Helper;

class BadgeHelper extends Helper {

  public $helpers = ['Html'];

  /**
   * Helper which will produce Bootstrap based Badge
   *
   * @param string $title            The title of the badge
   * @param string $type             Define the type of Badge. The value should be one of
   *                                  [primary,secondary,success,danger,warning,info,light,dark]
   *                                  Defaults to light
   * @param boolean $badge_pill      Is this a bg-pill. Defaults to false
   * @param boolean $badge_outline   Is this an outlined badge. Defaults to false
   * @param array $class_list        List of extra classes
   * @param string|null $fa_class    Fonts Awesome Icon we will prepend to the badge text
   * @param boolean $dis_text_dark   Disable dark-text fonts for light|info color mode.
   * @return mixed
   * @since  COmanage Registry v5.0.0
   */
  public function badgeIt(
    string $title,
    string $type = 'secondary',
    bool $badge_pill = false,
    bool $badge_outline = false,
    array $class_list = [],
    string $fa_class = null,
    bool $dis_text_dark = false
  ) {

    $badge_classes = [];

    if($badge_pill) {
      $badge_classes[] = "rounded-pill";
    }
    if($badge_outline) {
      $badge_classes[] = "bg-outline-" . $type;
    } else {
      $badge_classes[] = "bg-" . $type;
    }

    if(in_array($type, ['light', 'info'])
       && !$dis_text_dark) {
      $badge_classes[] = "text-dark";
    }

    if(!empty($class_list)) {
      $badge_classes[] = implode(' ', $class_list);
    }

    if(!empty($fa_class)) {
      $fa_element = '<i class="mr-1 fa ' . $fa_class .'"></i>';
    }

    return $this->Html->tag(
      'span',
      $fa_element . $title,
      [
        'class' => 'mr-1 badge ' . implode(' ', $badge_classes),
        'escape' => false,
      ]
    );
  }

  /**
   * Get the Badge Color
   *
   * @param string $color
   * @return string|null
   *
   * @since  COmanage Registry v5.0.0
   */
  public function getBadgeColor(string $color): ?string {
    if(empty($color)) {
      return null;
    }

    $color_map = [
      'Success'   => 'success',
      'Danger'    => 'danger',
      'Warning'   => 'warning',
      'Primary'   => 'primary',
      'Secondary' => 'secondary',
      'Light'     => 'light',
      'Info'      => 'info',
      'Dark'      => 'dark',
    ];

    if(!isset($color_map[$color])) {
      return null;
    }

    return $color_map[$color];
  }

}