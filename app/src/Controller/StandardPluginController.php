<?php
/**
 * COmanage Registry Standard Plugin Controller
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

namespace App\Controller;

// XXX not doing anything with Log yet
use Cake\Log\Log;

use \App\Lib\Util\StringUtilities;
use \App\Lib\Enum\SuspendableStatusEnum;

class StandardPluginController extends StandardController {
  /**
   * Determine the filesystem path to a file within a plugin.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $pluginName Physical plugin name
   * @param  string $file       File name within plugin
   * @return string             Path to file
   */

  protected function getPluginPath(string $pluginName, string $file): string {
    $PluginTable = $this->getTableLocator()->get('Plugins');

    // Because plugins are uniquely named (AR-Plugin-1) we can do a find based
    // on the name to get the object.

    $plugin = $PluginTable->find()
                          ->where([
                              'plugin' => $pluginName,
                              'status' => SuspendableStatusEnum::Active
                            ])
                          ->firstOrFail();

    return $PluginTable->pluginPath($plugin, $file);
  }
}