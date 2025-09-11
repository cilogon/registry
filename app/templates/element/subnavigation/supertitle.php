<?php
/**
 * COmanage Registry Supertitle Subnav Element
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

use App\Lib\Util\StringUtilities;
use App\Lib\Util\TableUtilities;
use \Cake\Utility\Inflector;

extract($vv_sub_nav_attributes, EXTR_PREFIX_ALL, 'vv_subnavigation');


/*
 * Person with deep nested dependency
 */

$person_id = (int)($vv_mvea_person_id ?? $vv_obj?->person_id ?? $this->getRequest()->getQuery('person_id'));
if ($person_id) {
  $personFullName = $this->Tab->getPersonPrimaryName($person_id);
}

// If we have a multi-level navigation layout but the first level is based on the `Person` then the `Supertitle` will always
// be the full name of the Person. The reason for calculating this separately is that the Full Name of the person
// is not part of the People table
if (
  isset($personFullName)
  && \in_array('People', $vv_sub_nav_attributes['tabs'], true)
) {
  $vv_subnavigation_tabsSupertitle = $personFullName;
}

/*
 * Plugin with deep nested dependency
 * The supertitle is going to be the display value of the first tab,
 * i.e., of the plugin instantiation wrapper
*/
$objectName = Inflector::tableize($vv_controller);
if (
  (!empty($vv_obj) || !empty($$objectName))
  && !empty($this->getPlugin())
  && $vv_subnavigation_tabs[0] !== StringUtilities::entityToClassName($vv_bc_parent_obj)
) {
  $object = $vv_obj ?? $$objectName?->items()?->first();
  if ($object === null) {
    // This is a deep nested association that has not been initialized yet. The controller name
    // will become the supertitle
    $vv_subnavigation_tabsSupertitle = Inflector::humanize($vv_controller);
  } else {
    // If we get here, it means that neither the request object nor its parent can give us a supertitle.
    // We need to fetch all the ids and get the supertitle from the root tab/node
    $results = [];
    TableUtilities::treeTraversalFromId(StringUtilities::entityToClassName($object), (int)$object->id, $results);
    $superTitleModelReference = $this->Tab->getModelTableReference($vv_subnavigation_tabs[0]);
    $superTitleModelDisplayField = $superTitleModelReference->getDisplayField();
    $superTitleModelId = $results[$vv_subnavigation_tabs[0]];

    $root_obj = $superTitleModelReference->get($superTitleModelId);
    $vv_subnavigation_tabsSupertitle = $root_obj->$superTitleModelDisplayField;
  }
}

$supertitle = match (true) {
  // Directly from the Configuration
  !empty($vv_subnavigation_tabsSupertitle)                   => $vv_subnavigation_tabsSupertitle,
  // Display parent's display field
  isset($vv_bc_parent_primarykey, $vv_bc_parent_obj)
  && $vv_bc_parent_primarykey !== $vv_bc_parent_obj
  && !empty($vv_bc_parent_obj?->$vv_bc_parent_displayfield)  => $vv_bc_parent_obj?->$vv_bc_parent_displayfield,
  // Set in the Controller
  !empty($vv_supertitle)                                     => $vv_supertitle,
  // Get the object display field
  !empty($vv_obj->$vv_display_field)                         => $vv_obj->$vv_display_field,
  !empty($vv_bc_title_links)                                 => $vv_bc_title_links[0]['label'],
  !empty($vv_title)                                          => $vv_title,
  default                                                    => __d('information','global.title.none')
};

// This allows us to know that we have subnavigation in the
// Standard views (index.php, add-edit-view.php).
$this->set('hasSupertitle', true);

?>

<h1><?= $supertitle ?></h1>
