<?php
/**
 * COmanage Registry App Controller
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

use App\Lib\Enum\TemplateableStatusEnum;
use App\Lib\Events\ChangelogEventListener;
use App\Lib\Events\CoIdEventListener;
use App\Lib\Events\RuleBuilderEventListener;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Datasource\Exception;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use InvalidArgumentException;

class AppController extends Controller {
  use \App\Lib\Traits\LabeledLogTrait;

  // If set, the current requested CO. Note this may be *unauthenticated*
  // and so should not be trusted without further authorization.
  private $cur_co = null;
  
  // If set, the current primary link.
  protected $cur_pl = null;
    
  /**
   * Initialization callback.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  public function initialize(): void {
    parent::initialize();
    
    // Load Components used by most or all controllers
    
    $this->loadComponent('RequestHandler');
    
    // Add a detector so we can tell restful from non-restful calls
    $request = $this->getRequest();
    
    $request->addDetector('restful', function($request) {
      // $request->is(json|xml) well check the mimetype
      return ($request->is('json') || $request->is('xml'));
    });
    
    // COmanage specific component that handles authn/z processintg
    $this->loadComponent('RegistryAuth');
    
    $ChangelogEventListener = new ChangelogEventListener($this->RegistryAuth);
    EventManager::instance()->on($ChangelogEventListener);
    
    $RuleBuilderEventListener = new RuleBuilderEventListener();
    EventManager::instance()->on($RuleBuilderEventListener);
    
    if(!$this->request->is('restful')) {
      // Initialization for non-RESTful
      $this->loadComponent('Flash');
      
      /*
       * Enable the following components for recommended CakePHP security settings.
       * see https://book.cakephp.org/3.0/en/controllers/components/security.html
       *
       * In general, we don't need these protections for transactional API calls.
       */
      $this->loadComponent('Security');
      
      // CSRF Protection is enabled via in Middleware via Application.php.
    }
  }
  
  /**
   * Callback run prior to the request action.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */
  
  public function beforeFilter(\Cake\Event\EventInterface $event) {
    // Determine the timezone
    $this->setTZ();
    
    // Determine the requested CO
    $this->setCO();
    
    if(isset($this->RegistryAuth)) {
      // Components might not be loaded on error, so check
      
      // We need to populate this in beforeFilter (rather than beforeRender)
      // so it's available to CosController::select
      $this->populateAvailableCos();
    }
    
    return parent::beforeFilter($event);
  }
  
  /**
   * Callback run prior to the view rendering.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */
    
  public function beforeRender(\Cake\Event\EventInterface $event) {
    // $this->name = Models
    $modelsName = $this->name;
    
    // Views can also inspect the request object to determine the current
    // action, but it seems slightly easier to do it once here.
    $this->set('vv_action', $this->request->getParam('action'));
    
    if(isset($this->RegistryAuth)) {
      // Components might not be loaded on error, so check
      $this->set('vv_menu_permissions', $this->RegistryAuth->getMenuPermissions($this->getCOID()));
    }
    
    // For breadcrumbs, do we have a target model, and if so is it a configuration
    // model (eg: ApiUsers) or an object model (eg: CoPeople)?
    if(isset($this->$modelsName) // May not be set under certain error conditions
       && method_exists($this->$modelsName, "getIsConfigurationTable")) {
      $this->set('vv_is_configuration_model', $this->$modelsName->getIsConfigurationTable());
    }
    
    return parent::beforeRender($event);
  }
  
  /**
   * Get the current CO.
   *
   * @since  COmanage Registry v5.0.0
   * @return \App\Model\Entity\Co Co Entity or null
   */
  
  public function getCO(): ?\App\Model\Entity\Co {
    if(!$this->cur_co) {
      $this->setCO();
    }

    // We'll return null if no CO, since some contexts may need to know that
    return $this->cur_co;
  }
  
  /**
   * Get the current CO ID.
   *
   * @since  COmanage Registry v5.0.0
   * @return int CO ID, or null
   */
  
  public function getCOID(): ?int {
    $cur_co = $this->getCO();
    
    return $cur_co ? $cur_co->id : null;
  }

  /**
   * Obtain information about the Standard Object's Primary Link, if set.
   * The $vv_primary_link view variable is also set.
   *
   * @since  COmanage Registry v5.0.0
   * @param  boolean $lookup If true, get the value of the primary link, not just the attribute
   * @return object          Object holding the primary link attribute, and optionally its value
   * @throws \RuntimeException
   */
  
  protected function getPrimaryLink(bool $lookup=false) {
    // Did we already figure this out? (But only if $lookup)
    if($lookup && isset($this->cur_pl->value)) {
      return $this->cur_pl;
    }
    
    // $this->name = Models
    $modelsName = $this->name;
    // $modelName = Model
    $modelName = \Cake\Utility\Inflector::singularize($modelsName);

    $this->cur_pl = new \stdClass();
    
    // PrimaryLinkTrait
    if(method_exists($this->$modelsName, "getPrimaryLinks")
       && $this->$modelsName->getPrimaryLinks()) {
      // Some models, in particular MVEAs, can have multiple potential primary
      // links. In these cases, only one primary link is valid at a time, so we
      // have to look through the available primary links and find one.
      
      $availablePrimaryLinks = $this->$modelsName->getPrimaryLinks();
      
      if($lookup) {
        foreach($availablePrimaryLinks as $potentialPrimaryLink) {
          // Try to find a value
          
          if($this->request->is('get')) {
            // If this action allows unkeyed, asserted primary link IDs, check the query
            // string (eg: 'add' or 'index' allow matchgrid_id to be passed in)
            if($this->$modelsName->allowUnkeyedPrimaryLink($this->request->getParam('action'))
               && $this->request->getQuery()) {
              $this->cur_pl->value = $this->request->getQuery($potentialPrimaryLink);
            } elseif($this->$modelsName->allowLookupPrimaryLink($this->request->getParam('action'))) {
              // Try to map the requested object ID
              $param = (int)$this->request->getParam('pass.0');
              
              if(!empty($param)) {
                $this->cur_pl = $this->$modelsName->findPrimaryLink($param);
                // Break the loop here since we also have the link attribute, 
                // which might not be $potentialPrimaryLink
                $this->set('vv_primary_link', $this->cur_pl->attr);
                break;
              }
            }
          } elseif($this->request->is('post') && $this->request->getParam('action') != 'delete') {
            // Post = add, where we can have a list of objects and nothing in /objects/{id}
            // We don't support different primary links across objects, so we throw an error
            // if different parent keys are provided.
            
            $linkValue = null;
            
            // Data in API format
            $reqData = $this->request->getData($modelsName);
            
            if(!$reqData 
               // Don't create $reqData if the POST data is also empty
               && !empty($this->request->getData())) {
              // Data in POST format
              $reqData[] = $this->request->getData();
            }
            
            if(!empty($reqData)) {
              foreach($reqData as $rec) {
                if(!empty($rec[$potentialPrimaryLink])) {
                  if(!$linkValue) {
                    // This is the first record we've seen, use this primary link value
                    $linkValue = $rec[$potentialPrimaryLink];
                  } elseif($linkValue != $rec[$potentialPrimaryLink]) {
                    // We don't support multiple records with different parents
                    throw new \InvalidArgumentException(__d('error', 'primary_link.mismatch'));
                  }
                }
                
                $this->cur_pl->value = $linkValue;
              }
            } elseif($this->$modelsName->allowLookupPrimaryLink($this->request->getParam('action'))) {
              // Try to map the requested object ID (this is probably a delete, so no attribute in post body)
              $param = (int)$this->request->getParam('pass.0');
              
              if(!empty($param)) {
                $this->cur_pl = $this->$modelsName->findPrimaryLink($param);
                // Break the loop here since we also have the link attribute, 
                // which might not be $potentialPrimaryLink
                $this->set('vv_primary_link', $this->cur_pl->attr);
                break;
              }
            }
          } elseif($this->request->is('put') || $this->request->getParam('action') == 'delete') {
            // Put = edit, so we should look up the parent ID via the object itself
            if($this->$modelsName->allowLookupPrimaryLink($this->request->getParam('action'))) {
              // Try to map the requested object ID (this is probably a delete, so no attribute in post body)
              $param = (int)$this->request->getParam('pass.0');
              
              if(!empty($param)) {
                $this->cur_pl = $this->$modelsName->findPrimaryLink($param);
                // Break the loop here since we also have the link attribute, 
                // which might not be $potentialPrimaryLink
                $this->set('vv_primary_link', $this->cur_pl->attr);
                break;
              }
            }
          }
          
          if(!empty($this->cur_pl->value)) {
            // We found a populated primary link. Store the attribute and break the loop.
            $this->cur_pl->attr = $potentialPrimaryLink;
            $this->set('vv_primary_link', $this->cur_pl->attr);
            break;
          }
        }
        
        if(empty($this->cur_pl->value) && !$this->$modelsName->allowEmptyPrimaryLink()) {
          throw new \RuntimeException(__d('error', 'primary_link'));
        }
      }
      
      if(!empty($this->cur_pl->value)) {
        // Look up the link value to find the related entity
        
        $linkTableName = $this->$modelsName->getPrimaryLinkTableName($this->cur_pl->attr);
        $linkTable = $this->getTableLocator()->get($linkTableName);
        
        $this->set('vv_primary_link_model', $linkTableName);
        
        try {
          $plObj = $linkTable->findById($this->cur_pl->value)->firstOrFail();
          
          $this->set('vv_primary_link_obj', $plObj);
          
          // While we're here, note the CO since we'll probably need it soon
          if(!empty($plObj->co_id)) {
            $this->cur_pl->co_id = $plObj->co_id;
          } elseif(method_exists($linkTable, "findCoForRecord")) {
            $this->cur_pl->co_id = $linkTable->findCoForRecord((int)$this->cur_pl->value);
          }
        }
        catch(RecordNotFoundException $e) {
          $this->llog('error', "Could not find value '" . $this->cur_pl->value . "' for primary link object " . $linkTableName);
          // Mask this with a generic UnauthorizedException
          throw new UnauthorizedException(__d('error', 'perm'));
        }
      }
    }
    
    return $this->cur_pl;
  }
  
  /**
   * Get the redirect goal for this table.
   *
   * @since  COmanage Registry v5.0.0
   * @return string Redirect goal
   */
  
  protected function getRedirectGoal(): ?string {
    // $this->name = Models
    $modelsName = $this->name;
    
    // PrimaryLinkTrait
    if(method_exists($this->$modelsName, "getRedirectGoal")) {
      return $this->$modelsName->getRedirectGoal();
    }
    
    return 'index';
  }
  
  /**
   * Populate the list of Available COs, primarily for the CO Selector.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  protected function populateAvailableCos() {
    // Prepare the list of available COs, primarily for the CO Selector. We do
    // this here because the menuTop element, which renders on every page, needs it.
    
    $availableCos = [];
    
    $username = $this->RegistryAuth->getAuthenticatedUser();
    
    if(!empty($username)) {
      // There are two data sets to look at: the COs the current user is a member
      // of, and (if the current user is a Platform Admin) all other COs. We then
      // bubble the COmanage CO to the top (if present), followed by an alphabetical
      // list of member COs, then an alphabetical list of non-member COs.
      
      $Cos = TableRegistry::getTableLocator()->get("Cos");
      
      // Pull the set of COs this user is a member of, for rendering via menuMain
      $memberCos = Hash::sort($Cos->getCosForIdentifier(loginIdentifier: $username), '{n}.name', 'asc');
      $allCos = null;
      
      if($this->RegistryAuth->isPlatformAdmin()) {
        // Pull all available (active COs)
        $allCos = Hash::sort($Cos->find('all')->where(['Cos.status' => TemplateableStatusEnum::Active])->toArray(), '{n}.name', 'asc');
      }
      
      // See if the COmanage CO is in the $memberCos list. (If the user is a
      // Platform Admin it will always be in the $memberCos list.)
      
      $COmanageCO = null;
      
      foreach($memberCos as $key => $co) {
        if($co->isCOmanageCO()) {
          $COmanageCO = $co;
          unset($memberCos[$key]);
        } else {
          $availableCos[$key] = $co;
        }
      }
      
      if($COmanageCO) {
        $availableCos = array_merge([$COmanageCO->id => $COmanageCO], $availableCos);
      }
      
      if(!empty($allCos)) {
        foreach($allCos as $co) {
          if(!Hash::extract($availableCos, '{n}[id='.$co->id.']')) {
            // Not already in the list as a member
            $co->name = __d('field', 'Cos.member.not', [$co->name]);
            
            $availableCos[] = $co;
          }
        }
      }
    }
    
    $this->set('vv_available_cos', $availableCos);
  }
  
  /**
   * Determine the (requested) current CO and make it available to the
   * rest of the application.
   *
   * @since  COmanage Registry v5.0.0
   * @throws Cake\Datasource\Exception\RecordNotFoundException
   * @throws \InvalidArgumentException
   */
  
  protected function setCO() {
    if($this->cur_co) {
      // Nothing to do...
      return;
    }
    
    // $this->name = Models, unless we're in an API call
    $modelsName = $this->name;
    
    $attrs = $this->request->getAttributes();
    
    // Unlike Match, where the Matchgrid is embedded in the request API URL,
    // Registry API calls are more similar to UI calls, where we may or may
    // not be able to find the CO ID directly in the URL.
    if($this->request->is('restful') 
       && !empty($attrs['params']['model'])) {
      $modelsName = \Cake\Utility\Inflector::camelize($attrs['params']['model']);
      $this->$modelsName = TableRegistry::getTableLocator()->get($modelsName);
    }
    
    if(!method_exists($this->$modelsName, "requiresCO")
       || !$this->$modelsName->requiresCO()) {
      // Nothing to do, CO not required by this model/controller
      return;
    }
    
    // Not all models have CO as their primary link. This will also
    // trigger setting of the viewVar for breadcrumbs and anything else.
    $link = $this->getPrimaryLink(true);
    
    // Try to find the requested CO
    $coid = null;
    
    // getPrimaryLink has already done our work
    if($link->attr == 'co_id') {
      $coid = $link->value;
    } else {
      if(!empty($link->co_id)) {
        $coid = $link->co_id;
      }
    }
    
    if(!$coid 
       && !$this->$modelsName->allowEmptyCO()
       && !$this->request->is('restful')) {
      // If we get this far without a CO ID, something went wrong.
      throw new \RuntimeException(__d('error', 'coid'));
    }
    
    if($coid) {
      $this->Cos = $this->fetchTable('Cos');
      
      // This throws Cake\Datasource\Exception\RecordNotFoundException which
      // we just let pass up the stack.
      $this->cur_co = $this->Cos->findById($coid)->firstOrFail();
      
      // While the COmanage CO cannot be suspended (AR-CO-2), this is enforced
      // at cos/edit, not here.
      
      if($this->cur_co->status === TemplateableStatusEnum::Active) {
        $this->set('vv_cur_co', $this->cur_co);
      } else {
        throw new \InvalidArgumentException(__d('error', 'inactive', [__d('controller', 'Cos', [1]), $coid]));
      }
      
      // We store the CO ID in Configuration to facilitate its access from
      // model contexts such as validation where passing the value via the
      // Controller is not particularly feasible.

      // This only works for the current model, not related models. If/when we
      // need to support relatedmodels, we could have setCurCoId() cascade the
      // CO to any of its related models that require it, or use the event
      // listener approach commented out below.
      if(method_exists($this->$modelsName, "acceptsCoId") 
         && $this->$modelsName->acceptsCoId()) {
        $this->$modelsName->setCurCoId((int)$coid);
        
        /* This doesn't work for the current model since it has already been
           initialized, but it could be an option for related models later...
           (eg when we try to save a name via EIS or EF). But see also the new
           approach below.
        $CoIdEventListener = new CoIdEventListener($coid);
        EventManager::instance()->on($CoIdEventListener);*/
      }
      
      // Walk through the first level associations and pass the CO ID to them,
      // as well. We could ultimately cascade this via the table once we have
      // a use case to do so, though note it's possible a child associations
      // wants the CO ID even though the parent doesn't.
      
      foreach($this->$modelsName->associations()->getIterator() as $a) {
        $aTable = $a->getTarget();
        
        if(method_exists($aTable, "acceptsCoId") 
           && $aTable->acceptsCoId()) {
          $aTable->setCurCoId((int)$coid);
        }
      }
    }
  }
  
  /**
   * Determine the current timezone and make it available to the
   * rest of the application.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  protected function setTZ() {
    // $this->name = Models
    $modelsName = $this->name;
    
    // See if we've collected it from the browser in a previous page load. Otherwise,
    // use the system default. If the user set a preferred timezone, we'll catch that below.
    
    $tz = date_default_timezone_get();

    if(!empty($_COOKIE['cm_registry_tz_auto'])) {
      // We have an auto-detected timezone from a previous page render from the browser.
      // Note we don't call date_default_timezone_set() because we still want to record
      // times internally in UTC (at the expense of having to convert back and forth).
      $tz = $_COOKIE['cm_registry_tz_auto'];
    }
    
// XXX need to implement person-specific timezone detection (after CoPerson model and
//     login authentication are implemented)
    
    $this->set('vv_tz', $tz);
    
    if($this->$modelsName->behaviors()->has('Timezone')) {
      // Tell TimezoneBehavior what the current timezone is
      $this->$modelsName->setTimeZone($tz);
    }
  }
}