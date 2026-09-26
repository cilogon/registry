<?php
/**
 * COmanage Registry Multi Valued Entity Attributes (MVEA) Controller
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
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use \App\Lib\Util\StringUtilities;

class MVEAController extends StandardController {
  use \App\Lib\Traits\BreadcrumbsTrait;

  /**
   * Callback run prior to the request action.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   * @return \Cake\Http\Response   HTTP Response
   */
  
  public function beforeFilter(\Cake\Event\EventInterface $event) {
    if(!$this->request->is('restful') && $this->request->getParam('action') != 'deleted') {
      // Provide additional hints to BreadcrumbsComponent. This needs to be here
      // and not in beforeRender because the component beforeRender will run first.
      
      // This is all we need where person_id is the primary link, but for MVEAs
      // that are more deeply linked (to person_role_id, external_identity_id,
      // or external_identity_role_id) we need to look up the further links.
      $primaryLink = $this->getPrimaryLink(true);

      $this->Breadcrumb->injectPrimaryLink($primaryLink);
    }
    
    parent::beforeFilter($event);
  }

  /**
   * Callback run prior to the request render.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */

  public function beforeRender(\Cake\Event\EventInterface $event) {
    /** var string $modelsName */
    $modelsName = $this->getName();
    $table = $this->getCurrentTable();
    // field = model (or model_name)
    $fieldName = Inflector::underscore(Inflector::singularize($modelsName));

    if($this->request->is('restful') || $this->request->getParam('action') === 'deleted') {
      return parent::beforeRender($event);
    }

    // If there is a default type setting for this model, pass it to the view
    if($table->getSchema()->hasColumn('type_id')) {
      $defaultTypeField = "default_" . $fieldName . "_type_id";

      $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');

      $settings = $CoSettings->find()->where(['co_id' => $this->getCOID()])->firstOrFail();

      $this->set('vv_default_type', $settings->$defaultTypeField);
    }

    // Set up the supertitle and links for subnavigation
    $primaryLink = $this->getPrimaryLink(true);
    if(!empty($primaryLink->value)) {
      $this->set('vv_primary_link_attr', $primaryLink->attr);
      $this->set('vv_primary_link_id', $primaryLink->value);

      $personId = match($primaryLink->attr) {
        'external_identity_role_id' => $this->setupExternalIdentityRolePrimaryLink((int)$primaryLink->value),
        'external_identity_id'      => $this->setupExternalIdentityPrimaryLink((int)$primaryLink->value),
        'person_role_id'            => $this->setupPersonRolePrimaryLink((int)$primaryLink->value),
        'person_id'                 => (int)$primaryLink->value,
        default                     => null,
      };

      if(!empty($personId)) {
        $Names = $this->getTableLocator()->get('Names');
        $personName = $Names->primaryName($personId);
        $this->set('vv_person_name', $personName);
        $this->set('vv_supertitle', $personName->full_name);
        $this->set('vv_mvea_person_id', $personId);
      }
    }


    // Person Breadcrumb link
    // Get current breadcrumb parents
    $vv_bc_parents = (array)$this->viewBuilder()->getVar('vv_bc_parents');

    // Fetch the linked entity resolved by getPrimaryLink(true)
    $plObj = $this->viewBuilder()->getVar('vv_primary_link_obj') ?? null;
    if ($plObj === null) {
      // No primary link object available; nothing to add
      throw new \Exception('No primary link object available');
    }

    // Build additional parents for MVEA context
    if ($plObj->person_id !== null) {
      $mveaBreadcrumb = $this->buildMveaBreadcrumbs($plObj);
    }

    if (!empty($mveaBreadcrumb)) {
      $vv_bc_parents = [...$mveaBreadcrumb, ...$vv_bc_parents];
    }

    // Disambiguate External Identity crumb when displayed alongside Person breadcrumbs
    $hasPersonCrumb = false;
    foreach ($vv_bc_parents as $key => $crumb) {
      if (str_starts_with((string)$key, 'people:') || str_starts_with((string)$key, 'cos:')) {
        $hasPersonCrumb = true;
        break;
      }
    }

    if ($hasPersonCrumb) {
      foreach ($vv_bc_parents as $key => &$crumb) {
        if (str_starts_with((string)$key, 'external_identities:')) {
          $eiLabel = StringUtilities::localizeController('ExternalIdentities', null, false);
          if (!str_starts_with($crumb['label'], $eiLabel)) {
            $crumb['label'] = sprintf('%s (%s)', $eiLabel, $crumb['label']);
          }
        }
      }
      unset($crumb);
    }

    $this->set('vv_bc_parents', $vv_bc_parents);

    return parent::beforeRender($event);
  }

  /**
   * Set up view variables for an External Identity Role primary link.
   *
   * @since  COmanage Registry v5.3.0
   * @param  int $roleId External Identity Role ID
   * @return int         Person ID
   */
  protected function setupExternalIdentityRolePrimaryLink(int $roleId): int {
    $ExternalIdentityRoles = $this->getTableLocator()->get('ExternalIdentityRoles');
    $roleEntity = $ExternalIdentityRoles->findById($roleId)->firstOrFail();

    // Note this is a string, but vv_person_name is an entity
    $this->set('vv_ei_role', $ExternalIdentityRoles->generateDisplayField($roleEntity));
    $this->set('vv_ei_role_id', $roleId);

    return $this->setupExternalIdentityPrimaryLink((int)$roleEntity->external_identity_id);
  }

  /**
   * Set up view variables for an External Identity primary link.
   *
   * @since  COmanage Registry v5.3.0
   * @param  int $externalIdentityId External Identity ID
   * @return int                     Person ID
   */
  protected function setupExternalIdentityPrimaryLink(int $externalIdentityId): int {
    $ExternalIdentity = $this->getTableLocator()->get('ExternalIdentities');
    $externalIdentity = $ExternalIdentity->findById($externalIdentityId)->firstOrFail();

    $Names = $this->getTableLocator()->get('Names');
    // What's the primary name for the External Identity? The first name found...
    $this->set('vv_ei_name', $Names->primaryName($externalIdentity->id, 'external_identity'));
    $this->set('vv_ei_id', $externalIdentity->id);

    return (int)$externalIdentity->person_id;
  }

  /**
   * Set up view variables for a Person Role primary link.
   *
   * @since  COmanage Registry v5.3.0
   * @param  int $roleId Person Role ID
   * @return int         Person ID
   */
  protected function setupPersonRolePrimaryLink(int $roleId): int {
    $PersonRoles = $this->getTableLocator()->get('PersonRoles');
    $roleEntity = $PersonRoles->findById($roleId)->firstOrFail();

    // Note this is a string, but vv_person_name is an entity
    $this->set('vv_person_role', $PersonRoles->generateDisplayField($roleEntity));
    $this->set('vv_person_role_id', $roleId);

    return (int)$roleEntity->person_id;
  }

  /**
   * Build breadcrumb parents for MVEA pages based on the current primary link.
   *
   * Returns only the extra parents to prepend (eg: People index and the specific person),
   * avoiding duplicates by checking existing vv_bc_parents.
   *
   * @since  COmanage Registry v5.2.0
   * @return array<string,array{label:string,target:array}>
   */
  protected function buildMveaBreadcrumbs($plObj): array
  {
    $table = $this->getCurrentTable();

    // Resolve person_id via PrimaryLinkTrait helper on the table
    $personId = (int)$table->lookupPersonId($plObj);
    if (!$personId) {
      return [];
    }

    return $this->buildPersonBreadcrumbs($personId, true);
  }
}