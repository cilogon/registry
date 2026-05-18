<?php
/**
 * COmanage Registry Historic Petition Viewers Fields
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

$this->Field->disableFormEditMode();

// There are currently no configurable options
print $this->element('notify/banner', ['info' => __d('information', 'plugin.config.none')]);

$subnav = [
  // Ordered list of Tabs
  'tabs' => ['EnrollmentFlowSteps', 'HistoricPetitionViewer.HistoricPetitionViewers'],
  // What actions will include the subnavigation header
  'action' => [
    // If a model renders in a subnavigation mode in edit/view mode, it cannot
    // render in index mode for the same use case/context
    // XXX edit should go first.
    'EnrollmentFlowSteps' => ['view'],
    'HistoricPetitionViewer.HistoricPetitionViewers' => ['view']
  ]
];