<?php
/**
 * COmanage Registry Application States Trait
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

use Cake\Collection\Collection;
use Cake\Controller\ControllerFactory;
use Cake\ORM\TableRegistry;

trait ApplicationStatesTrait
{
  /**
   * Return a string of structure: <prefix>.<body>
   *
   * @param   array  $nameParts
   *
   * @return string
   * @since  COmanage Registry v5.1.0
   */
  public function constructComplexStateTag(array $nameParts): string
  {
    $nameParts = (new Collection($nameParts))->map(fn($item) => str_replace(' ', '_', $item))->toArray();
    return implode('.', $nameParts);
  }

  /**
   * @param string $stateTag
   * @param string|int $defaultValue
   * @param bool $invalidate
   * @return string
   * @since  COmanage Registry v5.1.0
   */
  public function getValue(string $stateTag, string|int $defaultValue, $invalidate = false): string
  {
    $vv_app_prefs = [];

    if ($invalidate) {
      // View, refetch
      $curController = $this->getView()->get('controller');
      $curController->getAppPrefs();
      $vv_app_prefs = $curController->viewBuilder()->getVar('vv_app_prefs');
    } elseif(method_exists($this, 'getView')) {
      // View
      $vv_app_prefs = $this->getView()?->get('vv_app_prefs');
    } elseif(method_exists($this, 'viewBuilder')) {
      // Controller
      $vv_app_prefs = $this->viewBuilder()?->getVar('vv_app_prefs');
    }

    $value = $defaultValue;
    if(!empty($vv_app_prefs)) {
      $appState = (collection($vv_app_prefs))
        ?->filter(fn ($value, $key) => $value->tag === $stateTag)
        ?->first();
      $value = $appState?->value ?? $defaultValue;
    }

    return (string)$value;
  }

  /**
   * @param string $stateTag
   * @param bool $invalidate
   * @return string
   * @since  COmanage Registry v5.1.0
   */
  public function getId(string $stateTag, $invalidate = false): string
  {
    $vv_app_prefs = [];

    if ($invalidate) {
      // View, refetch
      $curController = $this->getView()->get('controller');
      $curController->getAppPrefs();
      $vv_app_prefs = $curController->viewBuilder()->getVar('vv_app_prefs');
    } elseif(method_exists($this, 'getView')) {
      // View
      $vv_app_prefs = $this->getView()?->get('vv_app_prefs');
    } elseif(method_exists($this, 'viewBuilder')) {
      // Controller
      $vv_app_prefs = $this->viewBuilder()?->getVar('vv_app_prefs');
    }

    $appStateId = '';
    if(!empty($vv_app_prefs)) {
      $appState = (collection($vv_app_prefs))
        ?->filter(fn ($value, $key) => $value->tag === $stateTag)
        ?->first();
      $appStateId = $appState?->id ?? '';
    }

    return (string)$appStateId;
  }
}