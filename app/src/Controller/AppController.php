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

use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Enum\TAndCLoginModeEnum;
use App\Lib\Enum\TAndCStatusEnum;
use App\Lib\Enum\TemplateableStatusEnum;
use App\Lib\Events\ActorEventListener;
use App\Lib\Events\CoIdEventListener;
use App\Lib\Events\RuleBuilderEventListener;
use App\Lib\Util\StringUtilities;
use Cake\Controller\Controller;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Event\EventManager;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Cake\Utility\Hash;

/**
 * @property \App\Controller\Component\RegistryAuthComponent $RegistryAuth
 * @property \App\Controller\Component\BreadcrumbComponent $Breadcrumb
 * @property \Cake\Controller\Component\FlashComponent $Flash
 * @property \Cake\Controller\Component\FormProtectionComponent $FormProtection
 */
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

    // Add a detector so we can tell restful from non-restful calls
    $request = $this->getRequest();
    
    $request->addDetector('restful', function($request) {
      // $request->is(json|xml) well check the mimetype
      return ($request->is('json') || $request->is('xml'));
    });
    
    // COmanage specific component that handles authn/z processintg
    $this->loadComponent('RegistryAuth');

    // Breadcrumb Manager
    $this->loadComponent('Breadcrumb');

    $ActorEventListener = new ActorEventListener($this->RegistryAuth);
    EventManager::instance()->on($ActorEventListener);
    
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
      $this->loadComponent('FormProtection');

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

    // We need to manually set the Current CO ID for models with dynamic validation rules.
    // Plugins can implement calculateRequestedCOID() to tell
    // AppController what the current CO is, we then pass that information
    // manually here.

    // It's not ideal to have a hardcoded list of models, but we don't have
    // a better solution at the moment.

    if($this->getCOID() !== null) {
      foreach(['Addresses', 'Names', 'TelephoneNumbers'] as $m) {
        $Table = TableRegistry::getTableLocator()->get($m);

        $Table->setCurCoId($this->getCOID());
      }
    }
    
    if($this->components()->has('RegistryAuth')) {
      // Components might not be loaded on error, so check
      
      // We check for MFA Indicators here since we need to do this before
      // each page load, since different pages may have different requirements.

      $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');

      $mfaConfig = $CoSettings->getMfaIndicator();

      if(!empty($mfaConfig['indicator']) && !empty($mfaConfig['value'])) {
        $this->llog('trace', "MFA is required for access to Registry");

        if(!method_exists($this, "skipMfa")
           || !$this->skipMfa($this->request->getParam('action'))) {
          // MFA is required for access to Registry, and is not skipped for this
          // action, so check for it and throw an error if not asserted

          if(getenv($mfaConfig['indicator']) !== $mfaConfig['value']) {
            $this->llog('trace', "MFA indicator was not found");

            $exempt = false;

            if($mfaConfig['exempt_groups']) {
              // If MFA exemption groups are enabled, figure out the MFA Exemption Group
              // for the CO associated with the current request and then see if the
              // Person ID associated with that CO is in that Group.

              if($this->getCOID() !== null) {
                $Groups = $CoSettings = TableRegistry::getTableLocator()->get('Groups');

                $groupId = $Groups->getMfaExemptGroupId($this->getCOID());
                $personId = $this->RegistryAuth->getPersonID($this->getCOID());

                if($personId && $groupId) {
                  $exempt = $Groups->GroupMembers->isMember(
                    groupId: $groupId,
                    personId: $personId
                  );

                  if($exempt) {
                    $this->llog('trace', "Person $personId is exempt from MFA via Group $groupId");
                  }
                }
                // else we might (eg) have a Platform Admin trying to access a CO specific page
              }
            }

            if(!$exempt) {
              // We redirect to the CO's Mostly Static Page if we can figure out which CO,
              // otherwise we default to the Platform one.

              return $this->redirect(StringUtilities::pagesUrl(($this->getCOID() ?? $mfaConfig['comanage_co_id']), "mfa-required"));
            }
          }
        } else {
          $this->llog('trace', "MFA check is skipped for this request (" . $this->name . ")");
        }
      }

      // We need to populate this in beforeFilter (rather than beforeRender)
      // so it's available to CosController::select
      $this->populateAvailableCos();

      // Get Person ID
      if(
        $this->RegistryAuth->isAuthenticatedUser()
        && !$this->RegistryAuth->isApiUser()
        && $this->getCOID() !== null
      ) {
        $this->set('vv_person_id', $this->RegistryAuth->getPersonId($this->getCOID()));
        
        // These are the same conditions we need to check for T&C enforcement
        $this->maybeEnforceTAndCs();
      }
    }

    $this->getAppPrefs();

    parent::beforeFilter($event);
  }
  
  /**
   * Callback run prior to the view rendering.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */
    
  public function beforeRender(\Cake\Event\EventInterface $event) {
    // Views can also inspect the request object to determine the current
    // controller and action, but it seems slightly easier to do it once here.
    $this->set('vv_controller', $this->request->getParam('controller'));
    $this->set('vv_action', $this->request->getParam('action'));
    
    if($this->components()->has('RegistryAuth') && !$this->request->is('restful')) {
      // Components might not be loaded on error, so check
      $this->set('vv_menu_permissions', $this->RegistryAuth->getMenuPermissions($this->getCOID()));
  
      // Provide the user's application roles to the views.
      $this->set('vv_user_roles', $this->RegistryAuth->getApplicationUserRoles($this->getCOID()));
    }

    // Generate a nonce for use in JavaScript tags with the Content-Security-Policy script-src directive
    $this->set('vv_js_nonce', base64_encode(random_bytes(16)));

    // Determine the current Theme
    $this->getTheme();

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
   * @return int|null CO ID, or null
   * @since  COmanage Registry v5.0.0
   */
  
  public function getCOID(): ?int {
    $cur_co = $this->getCO();
    
    return $cur_co ? $cur_co->id : null;
  }

  /**
   * Get the current Table instance for this controller, respecting plugin context.
   *
   * @since  COmanage Registry v5.2.0
   * @return \Cake\ORM\Table
   */
  public function getCurrentTable(): \Cake\ORM\Table
  {
    $modelsName = $this->getName();
    $plugin = $this->getPlugin();

    return $this->fetchTable(StringUtilities::getQualifiedName($plugin, $modelsName));
  }


  /**
   * Validate a potential Primary Link from a GET operation (via the UI or API).
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $potentialPrimaryLink Candidate primary link, eg "co_id"
   * @return Object|\stdClass             Primary Link information
   * @throws InvalidArgumentException
   */

  protected function primaryLinkOnGet(string $potentialPrimaryLink): Object|bool
  {
    /** @var string $modelsName */
    $modelsName = $this->getName();
    
    // If this action allows unkeyed, asserted primary link IDs, check the query
    // string (e.g.: 'add' or 'index' allow co_id to be passed in)
    $actionParam = $this->request->getParam('action');
    $allowsUnkeyed = $this->getCurrentTable()->allowUnkeyedPrimaryLink($actionParam);
    $allowsLookup = $this->getCurrentTable()->allowLookupPrimaryLink($actionParam);
    $param = (int)$this->request->getParam('pass.0');

    if($allowsUnkeyed && is_numeric($this->request->getQuery($potentialPrimaryLink))) {
      return $this->populatedPrimaryLink(
        $potentialPrimaryLink,
        (int)$this->request->getQuery($potentialPrimaryLink)
      );
    }

    // allowLookupRelatedLink() is intended to allow queries like
    //  /provisioning-targets/status?person_id=123
    // in particular to allow filtering.
    //
    // In this initial implementation, we only support keys that can be inflected
    // directly to a table, eg person_id -> People, not enrollee_person_id. The related
    // model needs to have a primary link in common (eg: co_id) with the current model.
    $allowsRelatedLookup = $this->getCurrentTable()->allowLookupRelatedLink($actionParam);

    if(!empty($allowsRelatedLookup)) {
      // We have a set of related foreign keys (person_id, group_id, etc) that can be used
      // to lookup a primary link for the current table. If one is populated, see if it
      // has a value for $potentialPrimaryLink.

      foreach($allowsRelatedLookup as $potentialRelatedKey) {
        // The value for $potentialRelatedKey, eg person_id = 2298, as passed in the query
        $potentialRelatedId = $this->request->getQuery($potentialRelatedKey);

        if(is_numeric($potentialRelatedId)) {
          // We need to find the table for $potentialRelatedKey in order to find
          // the potential common link.

          // $RelatedTable = (eg) People
          $RelatedTable = TableRegistry::getTableLocator()->get(
            StringUtilities::foreignKeyToClassName($potentialRelatedKey)
          );

          // $relatedEntity = (eg) person
          $relatedEntity = $RelatedTable->get($potentialRelatedId);

          // Check to see if the current table (eg: ProvisioningTargets) shares
          // $potentialPrimaryLink (eg: co_id) with the related table (eg: People).
          // If it does, we can return this primary link.
          if(!empty($relatedEntity->$potentialPrimaryLink)) {
            // XXX This is the same structure returned by findPrimaryLink()
            return (object)[
              'plugin'  => null,  // We don't currently support plugins
              'attr'    => $potentialPrimaryLink,
              'value'   => $relatedEntity->$potentialPrimaryLink,
              'co_id'   => $RelatedTable->calculateCoForRecord($relatedEntity)
            ];
          }
        }
      }
    }

    if(!$allowsLookup || empty($param)) {
      return false;
    }

    // For a GET with a param (ie: a record id) we allow archived records to be
    // retrieved via ChangelogBehavior. Note because $param must be an integer
    // we don't need to check it further.
    return $this->$modelsName->findPrimaryLink(id: $param, archived: true);
  }

  /**
   * Validate a potential Primary Link from a POST operation (via the UI or API).
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $potentialPrimaryLink Candidate primary link, eg "co_id"
   * @return Object|\stdClass             Primary Link information
   * @throws InvalidArgumentException
   */

  protected function primaryLinkOnPost(string $potentialPrimaryLink): Object|bool
  {
    // This function gets called for both API POST and UI Form POST. The former allows
    // multiple objects (unique even within the API), but the latter doesn't.

    $modelsName = $this->getName();

    $reqData = $this->request->getData($modelsName);

    if(!empty($reqData)) {
      // API POST

      // Unlike the other operations, POST accepts more than one entity.
      // We'll look for $potentialPrimaryLink in the first entity.
      // If we find it, then all subsequent entities must have the exact same link.

      if(empty($reqData[0][$potentialPrimaryLink])) {
        // It can't be this one
        return false;
      }

      $potentialLinkValue = $reqData[0][$potentialPrimaryLink];

      if(count($reqData) > 1) {
        // If more than one record is provided, they must all have the same primary link

        for($i = 1;$i < count($reqData);$i++) {
          if(empty($reqData[$i][$potentialPrimaryLink])
             || ($reqData[$i][$potentialPrimaryLink] !== $potentialLinkValue)) {
            // We don't support multiple records with different parents
            throw new \InvalidArgumentException(__d('error', 'primary_link.mismatch'));
          }
        }
      }

      // If we make it here, we have a valid potential link
      return $this->populatedPrimaryLink($potentialPrimaryLink, $potentialLinkValue);
    } else {
      // Form POST

      $reqData = $this->request->getData();

      if(empty($reqData[$potentialPrimaryLink])) {
        // It can't be this one
        return false;
      }

      return $this->populatedPrimaryLink($potentialPrimaryLink, (int)$reqData[$potentialPrimaryLink]);
    }
  }

  /**
   * Primary link available via the URL.
   *
   * @return Object|bool
   * @since  COmanage Registry v5.0.0
   *
   */

  protected function primaryLinkOnPut(): Object|bool
  {
    $param = (int)$this->request->getParam('pass.0');

    // Put = edit, so we should look up the parent ID via the object itself
    if (
      empty($param)
      ||
      !$this->getCurrentTable()->allowLookupPrimaryLink($this->request->getParam('action'))
    ) {
      return false;
    }

    return $this->getCurrentTable()->findPrimaryLink($param);
  }

  /**
   * Populate a Standard Object with Primary Link information.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string  $linkAttr  Link Attribute, eg "co_id"
   * @param  int     $linkValue Link Value, eg 2
   * @return Object|\stdClass   Primary Link information
   */

  protected function populatedPrimaryLink(string $linkAttr, int $linkValue): Object {
    // For plugins, $primaryLink will be something like 'attribute_collector_id' and
    // $primaryLinkTable will be something like 'CoreEnroller.AttributeCollectors'.
    // Each table maps its primary links via PrimaryLinkTrait.
    $primaryLinkTable = $this->getCurrentTable()->getPrimaryLinkTableName($linkAttr);

    // For looking up values in records here, we want only the attribute
    // itself and not the plugin name (used for hacky notation by
    // PrimaryLinkTrait::setPrimaryLink(). Note this is a field and not
    // a model, but pluginModel() gets us the bit we need.

    // Store the plugin for possible later reference.
    $linkPlugin = str_contains($primaryLinkTable, '.')
      ? StringUtilities::pluginPlugin($primaryLinkTable)
      : null;

    $cur = new \stdClass();
    $cur->attr = $linkAttr;
    $cur->value = $linkValue;

    if($linkPlugin) {
      $cur->plugin = $linkPlugin;
    }

    return $cur;
  }


  /**
   * Perform primary link lookup.
   *
   * @throws \RuntimeException  Exception thrown if a primary link is empty and it is not allowed
   * @since  COmanage Registry v5.0.0
 */

  protected function primaryLinkLookup(): void
  {
    $table = $this->getCurrentTable();
    $availablePrimaryLinks = $table->getPrimaryLinks();

    // Iterate over all the potential primary links and pick the appropriate one
    foreach($availablePrimaryLinks as $potentialPrimaryLink) {
      $cur = match(true) {
        $this->request->is('get') => $this->primaryLinkOnGet($potentialPrimaryLink),
        ($this->request->is('post') && $this->request->getParam('action') != 'delete') => $this->primaryLinkOnPost($potentialPrimaryLink),
        ($this->request->is('put') || $this->request->getParam('action') == 'delete') => $this->primaryLinkOnPut(),
        default => false,
      };

      if($cur !== false && $cur->value !== null) {
        $this->cur_pl = $cur;
        $this->set('vv_primary_link', $this->cur_pl->attr);
        // Exit the for loop
        break;
      }
    } // foreach

    // If we make it here and we are in a POST, check to see if we allow looking up
    // the primary link for this action. We want to exhaustively try to find the
    // primary link in the POST body first, so we have to do this after we walk the
    // set of available primary links.

    if(empty($this->cur_pl->value)
       && $this->request->is('post') 
       && $this->request->getParam('action') != 'delete'
       && !empty($this->request->getParam('pass.0'))
       && $table->allowLookupPrimaryLink($this->request->getParam('action'))
    ) {
      $this->cur_pl = $table->findPrimaryLink((int)$this->request->getParam('pass.0'));
      $this->set('vv_primary_link', $this->cur_pl->attr);
    }

    // At the end we need to have a Primary Link
    if(empty($this->cur_pl->value)
      && !$table->allowEmptyPrimaryLink($this->request->getParam('action'))
      && $this->request->getParam('action') != 'deleted') {
      throw new \RuntimeException(__d('error', 'primary_link'));
    }
  }

  /**
   * Get a user's Application State
   * - postcondition: Application Preferences variable set
   * @since  COmanage Registry v5.1.0
   */
  public function getAppPrefs() {
    $request = $this->getRequest();
    $session = $request->getSession();

    $username = $session->read('Auth.external.user');
    $appPrefs = null;

    // Get preferences if we have an Auth.User.co_person_id
    if(!empty($username)) {
      $ApplicationStates = $this->fetchTable('ApplicationStates');
      $appPrefs = $ApplicationStates->retrieveAll(
        username: $username,
        // If we have not selected a CO yet, there will be no co_id
        coid: $this->getCOID(),
        personid: $this->viewBuilder()->getVar('vv_person_id')
      );
    }

    $this->set('vv_app_prefs', $appPrefs);
  }

  /**
   * Collect information about the Standard Object's Primary Link, if set.
   * The $vv_primary_link view variable is also set.
   *
   * @since  COmanage Registry v5.0.0
   * @param  boolean $lookup If true, get the value of the primary link, not just the attribute
   * @return object          Object holding the primary link attribute, and optionally its value
   * @throws \RuntimeException
   */

  public function getPrimaryLink(bool $lookup=false): Object
  {
    // Did we already figure this out? (But only if $lookup)
    if($lookup && isset($this->cur_pl->value)) {
      return $this->cur_pl;
    }

    $this->cur_pl = new \stdClass();

    if(!(method_exists($this->getCurrentTable(), 'getPrimaryLinks')
         && $this->getCurrentTable()->getPrimaryLinks())
    ) {
      return $this->cur_pl;
    }

    // PrimaryLinkTrait
    // Some models, in particular MVEAs, can have multiple potential primary
    // links. In these cases, only one primary link is valid at a time, so we
    // have to look through the available primary links and find one.

    if($lookup) {
      $this->primaryLinkLookup();
    }

    if(empty($this->cur_pl->value)) {
      return $this->cur_pl;
    }

    // Look up the link value to find the related entity

    $linkTableName = $this->getCurrentTable()->getPrimaryLinkTableName($this->cur_pl->attr);
    $linkTable = $this->getTableLocator()->get($linkTableName);

    $this->set('vv_primary_link_model', $linkTableName);

    try {
      $plObj = $linkTable->findById($this->cur_pl->value)->firstOrFail();

      $this->set('vv_primary_link_obj', $plObj);

      // While we're here, note the CO since we'll probably need it soon
      if(!empty($plObj->co_id)) {
        $this->cur_pl->co_id = $plObj->co_id;
      } elseif(method_exists($linkTable, 'findCoForRecord')) {
        $this->cur_pl->co_id = $linkTable->findCoForRecord((int)$this->cur_pl->value);
      }
    }
    catch(RecordNotFoundException $e) {
      $this->llog('error', "Could not find value '" . $this->cur_pl->value . "' for primary link object " . $linkTableName);
      // Mask this with a generic UnauthorizedException
      throw new UnauthorizedException(__d('error', 'perm'));
    }

    return $this->cur_pl;
  }

  /**
   * Get the redirect goal for this table.
   *
   * @param   string  $action  Action
   *
   * @return string|null Redirect goal
   * @since  COmanage Registry v5.0.0
   */
  
  protected function getRedirectGoal(string $action): ?string {
    // PrimaryLinkTrait
    if(method_exists($this->getCurrentTable(), "getRedirectGoal")) {
      return $this->getCurrentTable()->getRedirectGoal($this->request->getParam('action'));
    }
    
    return 'index';
  }

  /**
   * Get the current theme based on the request context.
   * 
   * @since  COmanage Registry v5.3.0
   */

  protected function getTheme() {
    // We look for Themes in the reverse order vs the documented priority
    // initially so that the most specific Theme gets applied, but also
    // if/when we support theme stacking we have the themes in the correct order.

    $Themes = TableRegistry::getTableLocator()->get('Themes');
    $modelName = $this->request->getParam('controller');

    $theme = null;

    // Ask CoSettings for the current CO and/or Platform Theme

    $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');

    $cothemes = $CoSettings->getThemes($this->getCOID());

    if(!empty($cothemes['co'])) {
      // CO-specific Theme takes precedence over Platform Theme
      $theme = $cothemes['co'];
    } elseif(!empty($cothemes['platform'])) {
      $theme = $cothemes['platform'];
    }

    // We put the getSpecificTheme() in the controller rather than the model
    // because we don't necessarily know what information a model-specific Theme
    // is based on.

    if(method_exists($this, "getSpecificTheme")) {
      $theme = $this->getSpecificTheme();
    }

    $this->set('vv_theme', $theme);
  }

  /**
   * Check to see if T&Cs are required.
   * 
   * @since  COmanage Registry v5.3.0
   */

  protected function maybeEnforceTAndCs() {
    // First, if we are processing a request for certain controllers
    // and actions, skip the check and return

    $modelName = $this->request->getParam('controller');
    $action = $this->request->getParam('action');

    $bypass = [
      'EnrollmentFlows' => [
        'start'
      ],
      'Petitions' => [
        'assign',
        'continue',
        'finalize',
        'provision'
      ],
      'TermsAndConditions' => [
        'agree',
        'review'
      ]
    ];

    if(isset($bypass[$modelName]) && in_array($action, $bypass[$modelName])) {
      return;
    }

    // We always allow dispatch (which can exist under any plugin controller)

    if($action == 'dispatch') {
      return;
    }

    // Next check the session (keyed on the current CO, since that could
    // change within a login session) to see if we're already cleared

    $sessionKey = "TAndC.bypass." . $this->getCOID();

    if($this->request->getSession()->read($sessionKey) === true) {
      return;
    }

    // If the current user is not a member of the CO (probably because they're
    // a Platform Admin), don't bother doing any further checks. Note this call
    // is cached by RegistryAuthComponent, and so is efficient to call again.

    if(!$this->RegistryAuth->isCoMember($this->getCOID())) {
      return;
    }

    // Check CoSettings for the current CO

    $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');
    $settings = $CoSettings->find()->where(['co_id' => $this->getCOID()])->firstOrFail();

    if($settings->tc_login_mode == TAndCLoginModeEnum::NotEnforced) {
      // Set a session note so we don't have to look this up on every page load

      $this->request->getSession()->write($sessionKey, true);
      return;
    }

    // If we've made it this far, retrieve the T&C status for the current Person

    $personID = $this->RegistryAuth->getPersonID($this->getCOID());

    $TermsAndConditions = TableRegistry::getTableLocator()->get('TermsAndConditions');

    $status = $TermsAndConditions->status($personID);

    foreach($status as $s) {
      // If we find at least one non-current T&C, redirect

      if($s['status'] != TAndCStatusEnum::Agreed) {
        // Store the current request URL so /review knows where to send the user when they're done
        $this->request->getSession()->write('TAndC.return', Router::url($this->request->getRequestTarget(), true));

        return $this->redirect([
          'controller'  => 'terms-and-conditions',
          'action'      => 'review',
          '?' => ['co_id' => $this->getCOID()]
        ]);
      }
    }

    // Finally if we make it here the Person is current, and we can set
    // the session state to bypass since Person is up to date

    $this->request->getSession()->write($sessionKey, true);
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
   * Find a parameter that may be submitted via a request URL (for GETs)
   * or form data (for POSTs).
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $name Parameter name
   * @return string       Parameter value if found, or null
   */

  protected function requestParam(string $name): ?string {
    if($this->request->is('get')) {
      if(!empty($this->request->getQuery($name))) {
        return $this->request->getQuery($name);
      }
    } elseif($this->request->is(['post', 'put'])) {
      if(!empty($this->request->getData($name))) {
        return $this->request->getData($name);
      }
    }

    return null;
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
    if($this->cur_co || $this->request->getParam('action') == 'deleted') {
      // Nothing to do...
      return;
    }

    // Try to find the requested CO
    $coid = null;
    
    if(method_exists($this, 'calculateRequestedCOID')) {
      // This controller implements special logic

      $coid = $this->calculateRequestedCOID();
    }
    
    if(!$coid) {
      /** var string $modelsName */
      $modelsName = $this->getName();
      
      $attrs = $this->request->getAttributes();
      
      // Unlike Match, where the Matchgrid is embedded in the request API URL,
      // Registry API calls are more similar to UI calls, where we may or may
      // not be able to find the CO ID directly in the URL.
      if($this->request->is('restful') 
        && !empty($attrs['params']['model'])) {
        $modelsName = \Cake\Utility\Inflector::camelize($attrs['params']['model']);
      }
      
      if(!method_exists($this->getCurrentTable(), "requiresCO")
        || !$this->getCurrentTable()->requiresCO()) {
        // Nothing to do, CO not required by this model/controller
        return;
      }
      
      // Not all models have CO as their primary link. This will also
      // trigger setting of the viewVar for breadcrumbs and anything else.
      $link = $this->getPrimaryLink(true);

      if(!empty($link->attr)) {
        // getPrimaryLink has already done our work
        if($link->attr == 'co_id') {
          $coid = $link->value;
        } else {
          if(!empty($link->co_id)) {
            $coid = $link->co_id;
          }
        }
      }
    }

    if(!$coid 
       && $this->getCurrentTable()->allowUnkeyedCO($this->request->getParam('action'))
       && !empty($this->request->getQuery('co_id'))) {
      $coid = $this->request->getQuery('co_id');
    }
    
    if(!$coid 
       && !$this->getCurrentTable()->allowEmptyCO()
       && !$this->request->is('restful')) {
      // If we get this far without a CO ID, something went wrong.
      throw new \RuntimeException(__d('error', 'coid'));
    }
    
    if($coid) {
      $Cos = $this->fetchTable('Cos');
      
      // This throws Cake\Datasource\Exception\RecordNotFoundException which
      // we just let pass up the stack.
      $this->cur_co = $Cos->findById($coid)->firstOrFail();
      
      // While the COmanage CO cannot be suspended (AR-CO-2), this is enforced
      // at cos/edit, not here.
      
      if($this->cur_co->status === TemplateableStatusEnum::Active) {
        $this->set('vv_cur_co', $this->cur_co);
      } else {
        throw new \InvalidArgumentException(__d('error', 'inactive', [__d('controller', 'Cos', [1]), $coid]));
      }
      
      if(!empty($modelsName) && !empty($this->getCurrentTable())) {
        // We store the CO ID in Configuration to facilitate its access from
        // model contexts such as validation where passing the value via the
        // Controller is not particularly feasible. Note that for API calls
        // $modelsName may not be set, so (eg) StandardApiController does
        // something similar.

        // This only works for the current model, not related models. For 
        // relatedmodels, we use the event listener approach below.
        if(method_exists($this->getCurrentTable(), "acceptsCoId")
          && $this->getCurrentTable()->acceptsCoId()) {
          $this->getCurrentTable()->setCurCoId((int)$coid);
        }
        
        // This doesn't work for the current model since it has already been
        // initialized, but it should work for related models later...
        // (eg when we try to save a name via EIS or EF). But see also CFM-400.
        $CoIdEventListener = new CoIdEventListener((int)$coid);
        EventManager::instance()->on($CoIdEventListener);
        
        // Walk through the first level associations and pass the CO ID to them,
        // as well. We could ultimately cascade this via the table once we have
        // a use case to do so, though note it's possible a child associations
        // wants the CO ID even though the parent doesn't.
        
        foreach($this->getCurrentTable()->associations()->getIterator() as $a) {
          $aTable = $a->getTarget();
          
          if(method_exists($aTable, "acceptsCoId") 
            && $aTable->acceptsCoId()) {
            $aTable->setCurCoId((int)$coid);
          }
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
    // See if we've collected it from the browser in a previous page load. Otherwise,
    // use the system default. If the user set a preferred timezone, we'll catch that below.
    
    $tz = new \DateTimeZone(date_default_timezone_get());

    if(!empty($_COOKIE['cm_registry_tz_auto'])) {
      // We have an auto-detected timezone from a previous page render from the browser.
      // Note we don't call date_default_timezone_set() because we still want to record
      // times internally in UTC (at the expense of having to convert back and forth).
      
      try {
        // If the cookie value is invalid, this will throw an Exception, and we'll eventually
        // reset the browser cookie based on the detected timezone (for the next page load)
        $tz = new \DateTimeZone($_COOKIE['cm_registry_tz_auto']);
      }
      catch(\Exception $e) {
        // We'll fall back to the default timezone, and we'll reset the cookie when the
        // default layout renders (so no need to do anything here)
      }
    }
    
// XXX need to implement person-specific timezone detection (after CoPerson model and
//     login authentication are implemented)
    
    $this->set('vv_tz', $tz);
    
    if($this->getCurrentTable()->behaviors()->has('Timezone')) {
      // Tell TimezoneBehavior what the current timezone is
      $this->getCurrentTable()->setTimeZone($tz);
    }
  }
}