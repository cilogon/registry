<?php
/**
 * COmanage Registry Dashboards Dashboard View
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

// Start with the Primary Registry objects
foreach(['People', 'Groups'] as $pm) {
  if(!empty($vv_results[$pm])) {
    print "<h2>" . __d('controller', $pm, 2) . "</h2>\n";
    print "<ul>\n";

    foreach($vv_results[$pm] as $pkey => $matches) {
      $url = [
        'controller'  => \Cake\Utility\Inflector::dasherize($pm),
        'action'      => 'edit',
        $pkey
      ];

      // The same entity can match on more than one searchable model

      foreach($matches as $m => $entity) {
        $displayField = $vv_supported_models[$m]['displayField'];
        $displayLabel = __d('field', $displayField);
        $displayString = $entity->$displayField;

        // If we match on a related model (for example PersonRoles for People)
        // indicate what actually matched
        $matchInfo = __d('result', 'search.result.id', $pkey);

        // Do we have a more informative string to render?
        if(!empty($entity->person->primary_name->full_name)) {
          $displayString = $entity->person->primary_name->full_name;

          $matchInfo = __d('result', 'search.result.related', $pkey, $displayLabel, $entity->$displayField);
        } elseif(!empty($entity->group->name)) {
          $displayString = $entity->group->name;

          $matchInfo = __d('result', 'search.result.related', $pkey, $displayLabel, $displayString);
        }

        // XXX This construction isn't ideal, but presumably will get rewritten when the
        // design of this page is redone
        print "<li>" . $this->Html->link($displayString, $url) . " " . filter_var($matchInfo, FILTER_SANITIZE_SPECIAL_CHARS). "</li>\n";
      }
    }

    print "</ul>\n";
  }
}
