<?php
/**
 * COmanage Registry Auth Component
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

/**
 * As of Cake 4.0.0, Cake's native authnz stuff has been refactored into two plugins.
 * In theory this sounds great, but (1) the plugins are super complicated, (2) poorly
 * documented (configuration documentation is completely lacking, but also lack of
 * documentation on how to add a new Authenticator type), and (3) don't really seem to
 * support our use case well (see inability to detect restful, and a general lack
 * of support for externalizing authn to Apache).
 * On top of all that, it seems like this stuff gets rewritten in every major release,
 * creating unnecessary code churn in one of the hardest to debug parts of the code.
 * So let's do it ourselves with a targeted solution.
 *
 * The concept here is that RegistryAuthComponent takes control of the request
 * until authnz is complete. So if RegistryAuthComponent determines that the
 * request is not from a valid user, it is the component's responsibility to
 * generate the appropriate response (401 for REST, redirect to login for UI).
 *
 * After the initial authnz is completed, Controllers may make calls into the
 * Component to get specific information (eg: the authenticated username).
 */

declare(strict_types = 1);

namespace App\Controller\Component;

use \Cake\Controller\Component;
use \Cake\Core\Configure;
use \Cake\Chronos\Chronos;
use \Cake\Datasource\Exception\RecordNotFoundException;
use \Cake\Event\EventInterface;
use \Cake\Http\Exception\ForbiddenException;
use \Cake\Http\Exception\UnauthorizedException;
use \Cake\ORM\ResultSet;
use \Cake\ORM\TableRegistry;
use \Cake\Utility\Inflector;
use \App\Lib\Enum\AuthenticationEventEnum;
use \App\Lib\Enum\SuspendableStatusEnum;
use \App\Lib\Enum\TemplateableStatusEnum;

class RegistryAuthComponent extends Component
{
  use \App\Lib\Traits\LabeledLogTrait;
  
  // The successfully authenticated user
  protected ?string $authenticatedUser = null;
  
  // Was this an API user?
  protected bool $authenticatedApiUser = false;
  
  // Cached results
  protected array $cache = [];
  
  /**
   * Authenticate an API User.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool True if authentication was successful, false otherwise.
   * @throws InvalidArgumentException
   */
  
  protected function authenticateApiUser(): bool {
    if(empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW'])) {
      $this->llog('error', "Empty value(s) received for PHP_AUTH_USER and/or PHP_AUTH_PW");
      throw new \InvalidArgumentException(__d('error', 'auth.api.invalid'));
    }
    
    $ApiUsers = TableRegistry::getTableLocator()->get('ApiUsers');
    
    try {
      // validateKey takes care of all validity logic, as well as rehashing (if needed)
      if($ApiUsers->validateKey($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_SERVER['REMOTE_ADDR'])) {
        $this->authenticatedUser = $_SERVER['PHP_AUTH_USER'];
        $this->authenticatedApiUser = true;
        $this->llog('debug', "Authenticated API User \"" . $this->authenticatedUser . "\"");
        
        return true;
      }
    }
    catch(\Exception $e) {
      $this->llog('debug', "User authentication failed: " . $e->getMessage());
      throw new \InvalidArgumentException($e->getMessage());
    }
    
    return false;
  }
  
  /**
   * Callback run prior to the request action.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */
  
  public function beforeFilter(EventInterface $event) {
    $controller = $event->getSubject();
    $request = $controller->getRequest();
    $session = $request->getSession();
    
    $id = null;
    $passed = $request->getParam('pass');
    
    if(!empty($passed[0])) {
      $id = (int)$passed[0];
    }
    
    // Perform authorization check

    // Controllers can handle their own authn and/or authz, as indicated
    // by implementing the willHandleAuth() function. This applies to both
    // regular and API requests.

    $controllerAuthz = false;

    if(method_exists($controller, 'willHandleAuth')) {
      // The Controller might handle its own authn/z

      $mode = $controller->willHandleAuth($event);

      switch($mode) {
        case 'authz':
          // The controller will handle authorization, but we still need
          // to make sure we have an authenticated user
          $controllerAuthz = true;
          break;
        case 'open':
          // The current request is open/public, no auth required
          return true;
          break;
        case 'no':
          // The controller will not do either authn or authz, so apply
          // standard behavior
          break;
        case 'yes':
          // The controller will handle both authn and authz, simply return
          return true;
          break;
        default:
          throw new \InvalidArgumentException("Unknown willHandleAuth return value $mode");
          break;
      }
    }

    // Do we have an authenticated user session?

    // Note we don't stuff anything into the session anymore, the only attribute
    // is the username, which is actually loaded by login.php.

    $auth = $session->read('Auth');

    // Registry UI is now a hybrid implementation of VUE and CAKEPHP MVC.
    // In order to allow a logged-in user to reach out to the backend without
    // the need of an API User, but just with the use of the Session, we will
    // skip the API user authorization if a user Session is available.
    if(empty($auth) && $this->getConfig('apiUser')) {
      // There are no unauthenticated API calls, so always require a valid user
      
      try {
        if($this->authenticateApiUser()) {
          $authok = false;

          if($controllerAuthz) {
            // Don't merge these if statements together! We want to hand off
            // to the controller to determine if authz was met, and if not redirect
            // appropriately. We _don't_ want to call our own calculatePermission().
            if($controller->calculatePermission()) {
              // Controller asserts authorization successful
              $authok = true;
            }
          } elseif($this->calculatePermission(action: $request->getParam('action'), id: $id)) {
            // Authorization successful
            $authok = true;
          }

          if($authok) {
            $AuthenticationEvents = TableRegistry::getTableLocator()->get('AuthenticationEvents');
            
            $AuthenticationEvents->record(identifier: $this->authenticatedUser,
                                          eventType: AuthenticationEventEnum::ApiLogin,
                                          remoteIp: $_SERVER['REMOTE_ADDR']);
            
            return true;
          }
        }
        
        // Permission denied
        throw new ForbiddenException(__d('error', 'perm'));
      }
      catch(RecordNotFoundException $e) {
        // Requested record does not exist. For platform API users, we can return
        // a RecordNotFoundException, otherwise we recast to generate permission denied.
        $this->llog('debug', "User authorization failed: " . $e->getMessage());
        
        $ApiUsers = TableRegistry::getTableLocator()->get('ApiUsers');
        
        if($ApiUsers->getUserPrivilege($this->authenticatedUser) === true) {
          throw $e;
        } else {
          throw new UnauthorizedException(__d('error', 'auth.api.failed'));
        }
      }
      catch(\Exception $e) {
        $this->llog('debug', $e->getMessage());
        // Obfuscate the error message, which is available in the logs
        throw new UnauthorizedException(__d('error', 'auth.api.failed'));
      }
    } else {
      // Certain requests do not require authentication

      if(!empty($auth['external']['user'])) {
        // We have a valid username that is *authenticated* for the current request.
        // Note we haven't checked authorization, but this is how the authorization
        // checks can get the authenticated username.
        $controller->set('vv_user', ['username' => $auth['external']['user']]);
        $this->authenticatedUser = $auth['external']['user'];
        
        if($controllerAuthz) {
          // Don't merge these if statements together! We want to hand off
          // to the controller to determine if authz was met, and if not redirect
          // appropriately. We _don't_ want to call our own calculatePermission().
          if($controller->calculatePermission()) {
            return true;
          }
        } elseif($this->calculatePermission($request->getParam('action'), $id)) {
          // Authorization successful
          return true;
        }
        
        if(Configure::read('debug')) {
          // For testing purposes, throw an error, but in production we want to
          // redirect to /login
          if($request->is('ajax') || $request->is('restful')) {
            // Permission denied
            throw new ForbiddenException(__d('error', 'perm'));
          }
          $controller->Flash->error("Authorization Failed (RegistryAuthComponent)");
          return $controller->redirect("/");
        }
      }

      if($request->is('ajax') || $request->is('restful')) {
        // Permission denied
        throw new ForbiddenException(__d('error', 'perm'));
      }
      
      // No authentication, redirect to login
      
      // We want to come back to where we started
      $session->write('Auth.target', $request->getRequestTarget());
      
      return $controller->redirect("/auth/login/login.php");
    }
  }
  
  /**
   * Calculate permissions for this action.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $action Action requested
   * @param  int    $id     Subject id, if applicable
   * @return bool           true if the action is permitted, false otherwise
   * @throws UnauthorizedException
   */
  
  protected function calculatePermission(string $action, ?int $id=null): bool {
    $perms = $this->calculatePermissions($id);
  
    if(!isset($perms[$action])) {
      throw new UnauthorizedException('Invalid Request (RegistryAuthComponent)');
    }
    
    return $perms[$action];
  }
  
  /**
   * Obtain the permission set for this request.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $id   Subject ID, if applicable
   * @return array        Array of actions and authorized roles
   */
  
  protected function calculatePermissions(?int $id=null): array {
    $controller = $this->getController();

    $ret = [];
    
    // This will need to be prefixed to the model, if set
    $pluginName = $controller->getPlugin();
    
    // $this->name = Models (ie: from ModelsTable)
    $modelsName = ($pluginName ? "$pluginName." : "") . $controller->getName();
    // $table = the actual table object
    $table = $controller->getTableLocator()->get($modelsName);
    
    // Do we have an authenticated user?
    $authenticatedUser = (bool)$this->getAuthenticatedUser();

    // Is this user a Platform Administrator?
    $platformAdmin = $this->isPlatformAdmin();
    
    // Is this user a CO Administrator?
    $coAdmin = $this->isCoAdmin($controller->getCOID());
    
    // Is this user a CO Member?
    $coMember = $this->isCoMember($controller->getCOID());

    // Get the action
    $reqAction = $controller->getRequest()->getParam('action');
    
    // Is this record read only?
    $readOnly = false;
    
    // Can this record be deleted?
    $canDelete = true;
    
    // Pull the table's permission definitions
    $permissions = $this->getTablePermissions($table, $id);
    
    if($id) {
      $readOnlyActions = ['view'];
      
      // Pull the record so we can interrogate it

      // XXX Get the record along with the contains
      // We use findById() rather than get() so we can apply subsequent
      // query modifications via traits
      $query = $table->findById($id);

      // QueryModificationTrait
      $getActionMethod = "get{$reqAction}Contains";
      if(method_exists($table, $getActionMethod)) {
        $query = $query->contain($table->$getActionMethod());
      }

      try {
        // Pull the current record
        $obj = $query->firstOrFail();
      }
      catch(\Exception $e) {
        // findById throws Cake\Datasource\Exception\RecordNotFoundException
        $this->Flash->error($e->getMessage());
        return $this->generateRedirect(null);
      }
      
      if(method_exists($obj, "isReadOnly")) {
        $readOnly = $obj->isReadOnly();
        
        if(!empty($permissions['readOnly'])) {
          // Merge in controller specific actions permitted on read only entities
          $readOnlyActions = array_merge($readOnlyActions, $permissions['readOnly']);
        }
      }
      
      if(method_exists($obj, "canDelete")) {
        $canDelete = $obj->canDelete();
      }
      
      // Permissions for actions that operate over individual entities
      
      foreach($permissions['entity'] as $action => $roles) {
        $ok = false;
        
        if((($action != 'delete' || $canDelete)
            &&
            !$readOnly) || in_array($action, $readOnlyActions)) {
          if(is_array($roles)) {
            // A list of roles authorized to perform this action, see if the
            // current user has any
            foreach($roles as $role) {
              // eg: $role = "platformAdmin", which corresponds to the variables set, above
              if($$role) {
                $ok = true;
                break;
              }
            }
          } elseif($roles === true) {
            // Any authenticated user is permitted
            $ok = true;
          }
        }

        $ret[$action] = $ok;
      }

      if(!empty($permissions['related']['entity'])) {
        foreach($permissions['related']['entity'] as $rtable) {
          $RelatedTable = TableRegistry::getTableLocator()->get($rtable);
          $rpermissions = $this->getTablePermissions($RelatedTable, $id);
          $robj = $obj->get(Inflector::singularize(Inflector::underscore($rtable)));
          $rreadOnlyActions = ['view'];

          // Is this record read only?
          $rreadOnly = false;

          // Can this record be deleted?
          $rcanDelete = true;

          if($robj !== null && method_exists($robj, "isReadOnly")) {
            $rreadOnly = $robj->isReadOnly();

            if(!empty($rpermissions['readOnly'])) {
              // Merge in controller specific actions permitted on read only entities
              $rreadOnlyActions = array_merge($rreadOnlyActions, $rpermissions['readOnly']);
            }
          }

          if($robj !== null &&  method_exists($robj, "canDelete")) {
            $rcanDelete = $robj->canDelete();
          }

          foreach($rpermissions['entity'] as $action => $roles) {
            $ok = false;

            if((($action != 'delete' || $rcanDelete)
                &&
                !$rreadOnly) || in_array($action, $rreadOnlyActions)) {
              if(is_array($roles)) {
                // A list of roles authorized to perform this action, see if the
                // current user has any
                foreach($roles as $role) {
                  // eg: $role = "platformAdmin", which corresponds to the variables set, above
                  if($$role) {
                    $ok = true;
                    break;
                  }
                }
              } elseif($roles === true) {
                // Any authenticated user is permitted
                $ok = true;
              }
            }

            $ret[$rtable][$action] = $ok;
          }
        }
      }
      
      if(!empty($permissions['related']['table'])) {
        foreach($permissions['related']['table'] as $rtable) {
          $RelatedTable = TableRegistry::getTableLocator()->get($rtable);
          $rpermissions = $this->getTablePermissions($RelatedTable, $id);
          
          foreach($rpermissions['table'] as $action => $roles) {
            $ok = false;
            
            if(is_array($roles)) {
              // A list of roles authorized to perform this action, see if the
              // current user has any
              foreach($roles as $role) {
                // eg: $role = "platformAdmin", which corresponds to the variables set, above
                if($$role) {
                  $ok = true;
                  break;
                }
              }
            } elseif($roles === true) {
              // Any authenticated user is permitted
              $ok = true;
            }
            
            $ret[$rtable][$action] = $ok;
          }
        }
      }

    } else { // No $id
      // Permissions for actions that operate over tables
      
      foreach($permissions['table'] as $action => $roles) {
        $ok = false;
        
        if(is_array($roles)) {
          // A list of roles authorized to perform this action, see if the
          // current user has any
          foreach($roles as $role) {
            // eg: $role = "platformAdmin", which corresponds to the variables set, above
            if($$role) {
              $ok = true;
              break;
            }
          }
        } elseif($roles === true) {
          // Any authenticated user is permitted
          $ok = true;
        }
        
        $ret[$action] = $ok;
      }
    }
    
    return $ret;
  }
  
  /**
   * Calculate permissions for a Result Set.
   *
   * @since  COmanage Registry v5.0.0
   * @param  ResultSet $rs Result Set
   * @return array         Array of permissions keyed on record ID
   */
  
  public function calculatePermissionsForResultSet(ResultSet $rs): array {
    // We return an array since this is intended to be passed to a view
    $ret = [];
    
    // Note these are Cake ORM functions (rewind, current, etc), and not array
    // functions that PHP deprecated in 8.1.0.
    $rs->rewind();
    
    while($rs->valid()) {
      $o = $rs->current();
      
      $ret[ $o->id ] = $this->calculatePermissions($o->id);
      
      $rs->next();
    }
    
    return $ret;
  }
  
  /**
   * Calculate permissions for use in a view.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $action Action requested
   * @param  int    $id     Subject id, if applicable
   * @return array          Array of permissions, suitable for the view
   */
  
  public function calculatePermissionsForView(string $action, ?int $id=null): array {
    return $this->calculatePermissions($id);
  }
  
  /**
   * Obtain the application role of the user for general use in the views
   *
   * @since  COmanage Registry v5.0.0
   * @param  int   $coId  Current CO ID, if known
   * @return array $appRoles Array of roles
   */
  
  public function getApplicationUserRoles(?int $coId): array {
    $appUserRoles = [];
    
    // True for platform administrator
    $appUserRoles['platform'] = $this->isPlatformAdmin();
    
    // True for administrator of the current CO
    $appUserRoles['co'] = $this->isCoAdmin($coId);
    
    // TODO: add other application roles such as 'cou' and 'support'
    // See: https://spaces.at.internet2.edu/display/COmanage/Registry+PE+Permissions  
    
    // True if user is authenticated
    $appUserRoles['authuser'] = $this->isAuthenticatedUser();
    
    return $appUserRoles;
  }
  
  /**
   * Obtain the identifier of the currently authenticated user.
   *
   * @since  COmanage Registry v5.0.0
   * @return string The authenticated user identifier or false if no authenticated user
   */
  
  public function getAuthenticatedUser(): ?string {
    return $this->authenticatedUser;
  }
  
  /**
   * Obtain permissions suitable for menu rendering, specifically by
   * templates/element/menuMain.php.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int   $coId  Current CO ID, if known
   * @return array        Array of permissions
   */
  
  public function getMenuPermissions(?int $coId): array {
    $permissions = [];
    
    $permissions['platform'] = $this->isPlatformAdmin();

    // Can access the Configuration Dashboard for the current CO
    $permissions['configuration'] = $this->isPlatformAdmin() 
                                    || $this->isCoAdmin($coId);
    
    // Can manage Groups in the current CO
    $permissions['groups'] = $this->isPlatformAdmin()
                             || $this->isCoAdmin($coId);
    
    // Can manage People in the current CO
    $permissions['people'] = $this->isPlatformAdmin()
                             || $this->isCoAdmin($coId);
    
    return $permissions;
  }
  
  /**
   * Obtain the Person ID of the currently authenticated user for the specified CO.
   * An Exception will be thrown if there is no currently authenticated user, however
   * null will be returned if there is a user, but they are not in the requested CO.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $coId   CO ID
   * @throws RuntimeException
   */

  public function getPersonID(int $coId): ?int {
    // We first need an authenticated Identifier, and it can't be for an API user.

    if(empty($this->authenticatedUser)) {
      throw new \RuntimeException("RegistryAuthComponent:getPersonID No authenticated user");
    }

    if($this->authenticatedApiUser) {
      throw new \RuntimeException("RegistryAuthComponent::getPersonID Current user is an API user");
    }

    $Identifiers = TableRegistry::getTableLocator()->get('Identifiers');

    try {
      return $Identifiers->lookupPersonByLogin($coId, $this->authenticatedUser);
    }
    catch(Cake\Datasource\Exception\RecordNotFoundException $e) {
      return null;
    }
  }
  
  /**
   * Obtain the set of permissions as provided by the table.
   *
   * @since  COmanage Registry v5.0.0
   * @param  table  $table  Cake Table
   * @param  int    $id     Entity ID, if applicable
   * @return array          Table permissions
   */
  
  protected function getTablePermissions($table, ?int $id): array {
    $p = $table->getPermissions();
    
    if(is_callable($p)) {
      $controller = $this->getController();
      $request = $controller->getRequest();
      
      return $p($request, $this, $id);
    } else {
      return $p;
    }
  }
  
  /**
   * Determine if the current user is an administrator (CMP/CO/COU) for the
   * provided identifier. Note that the identifier is not bound to any
   * particular CO, this function will return true if the user is an
   * administrator in any CO for which the subject identifier has an associated
   * Person record.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $identifier Identifiers
   * @return bool               true if the current user is an administrator over $identifier, false otherwise
   */
  
  public function isAdminForIdentifier(string $identifier): bool {
    if(!isset($this->cache['isAdminForIdentifier'][$identifier])) {
      $this->cache['isAdminForIdentifier'][$identifier] = false;
      
      if($this->isPlatformAdmin()) {
        // Platform Admins are admins for every identifier
        $this->cache['isAdminForIdentifier'][$identifier] = true;
      } else {
        // Map $identifier to a set of People. Note we may be crossing COs when
        // we do this. Note for now we only examine login identifiers since this
        // is largely in support of the AuthenticationEvents index view, but
        // there may be different use cases in the future.
        
        $Identifiers = TableRegistry::getTableLocator()->get('Identifiers');
        
        $identifiers = $Identifiers->find('all')
                                   ->where([
                                     'Identifiers.identifier' => $identifier,
                                     'Identifiers.status'     => SuspendableStatusEnum::Active,
                                     'Identifiers.login'      => true,
                                     'Identifiers.person_id IS NOT NULL'
                                   ])
                                   ->contain(['People' => 'Cos'])
                                   ->all();
        
        foreach($identifiers as $i) {
          if(!empty($i->person->co_id) 
             && $this->isCoAdmin($i->person->co_id)) {
            // If the current user is an admin for this Person we're done
            $this->cache['isAdminForIdentifier'][$identifier] = true;
            break;
          }
        }
      }
    }
    
    return $this->cache['isAdminForIdentifier'][$identifier];
  }
  
  /**
   * Determine if the current user is an API user.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool True if the current user is an API user
   */
  
  public function isApiUser(): bool {
    return $this->authenticatedApiUser;
  }
  
  /**
   * Determine if the current user is authenticated.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool True if the current user is authenticated
   */
  
  public function isAuthenticatedUser(): bool {
    return !empty($this->authenticatedUser);
  }
  
  /**
   * Determine if the current user is a CO Administrator.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int  $coId CO ID
   * @return bool       True if the current user is a CO Administrator
   */
  
  public function isCoAdmin(?int $coId): bool {
    // We might get called in some contexts without a coId, in which case there
    // are no CO Admins.
    
    if(!$coId) {
      return false;
    }
    
    if(!isset($this->cache['isCoAdmin'][$coId])) {
      $this->cache['isCoAdmin'][$coId] = false;
      
      if($this->authenticatedApiUser) {
        $ApiUsers = TableRegistry::getTableLocator()->get('ApiUsers');
        
        $priv = $ApiUsers->getUserPrivilege($this->authenticatedUser);
        
        $this->cache['isCoAdmin'][$coId] = ($priv === true || $priv === $coId);
      } else {
        if(!empty($this->authenticatedUser)) {
          $this->cache['isCoAdmin'][$coId] = $this->isIdentifierAdmin(identifier: $this->authenticatedUser, coId: $coId);
        }
      }
    }
    
    return $this->cache['isCoAdmin'][$coId];
  }
  
  /**
   * Determine if the current user is a member of the specified CO.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int  $coId CO ID
   * @return bool       True if the current user is a CO Administrator
   */
  
  public function isCoMember(?int $coId): bool {
    // We might get called in some contexts without a coId, in which case there
    // are no members.
    
    if(!$coId) {
      return false;
    }
    
    if(!isset($this->cache['isCoMember'][$coId])) {
      $this->cache['isCoMember'][$coId] = false;
      
      if($this->authenticatedApiUser) {
        $ApiUsers = TableRegistry::getTableLocator()->get('ApiUsers');
        
        $apiUser = $ApiUsers->find()
                            ->where([
                              'ApiUsers.username' => $this->authenticatedUser,
                              'ApiUsers.co_id'    => $coId,
                              'ApiUsers.status'   => SuspendableStatusEnum::Active
                            ])
                            ->first();
        
        if($apiUser) {
          $now = Chronos::now();
          
          if((!$apiUser->valid_from || $now->gt($apiUser->valid_from))
             && (!$apiUser->valid_through || $now->gt($apiUser->valid_through))) {
            $this->cache['isCoMember'][$coId] = true;
          }
        }
      } else {
        if(!empty($this->authenticatedUser)) {
          $Cos = TableRegistry::getTableLocator()->get('Cos');
          
          $memberCos = $Cos->getCosForIdentifier($this->authenticatedUser);
          
          $this->cache['isCoMember'][$coId] = isset($memberCos[$coId]);
        }
      }
    }
    
    return $this->cache['isCoMember'][$coId];
  }
  
  /**
   * Determine if an identifier represents an administrator in the specified CO.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $identifier Identifier
   * @param  int    $coId       CO ID
   * @return bool               true if the identifier represent an administrator, false otherwise
   */

  protected function isIdentifierAdmin(string $identifier, int $coId): bool {
    $Cos = TableRegistry::getTableLocator()->get('Cos');
    
    // First see if this Identifier is a login Identifier in the requested CO
    // This is similar to CosTable::getCosForIdentifier
    $identifiers = $Cos->People
                       ->Identifiers
                       ->find('all')
                       ->where([
                         'Identifiers.identifier' => $identifier,
                         'Identifiers.status'     => SuspendableStatusEnum::Active,
                         'Identifiers.login'      => true,
                         'Identifiers.person_id IS NOT NULL'
                       ])
                       ->contain(['People' => 'Cos'])
                       ->all();
    
    foreach($identifiers as $i) {
      // Both the Person and the CO must be active
      if($i->person && $i->person->isActive() 
         && $i->person->co->status == TemplateableStatusEnum::Active
         && $i->person->co->id == $coId) {
        // We found a Person in this CO, now see if it's an admin
        // (for which we'll need the admin group)
        
        $adminGroup = $Cos->Groups->find('adminGroup', ['co_id' => $i->person->co_id])->firstOrFail();
        
        return $Cos->Groups->GroupMembers->isMember(groupId: $adminGroup->id, personId: $i->person->id);
      }
    }
    
    return false;
  }
  
  /**
   * Determine if the current user is a Platform Administrator.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool True if the current user is a Platform Administrator
   */
  
  public function isPlatformAdmin(): bool {
    if(!isset($this->cache['isPlatformAdmin'])) {
      $this->cache['isPlatformAdmin'] = false;
      
      if($this->authenticatedApiUser) {
        $ApiUsers = TableRegistry::getTableLocator()->get('ApiUsers');
        
        $this->cache['isPlatformAdmin'] = ($ApiUsers->getUserPrivilege($this->authenticatedUser) === true);
      } else {
        if(!empty($this->authenticatedUser)) {
          $Cos = TableRegistry::getTableLocator()->get('Cos');
          
          // Find the COmanage CO
          $COmanageCO = $Cos->find('COmanageCO')->firstOrFail();
          
          $this->cache['isPlatformAdmin'] = $this->isIdentifierAdmin(identifier: $this->authenticatedUser, coId: $COmanageCO->id);
        }
      }
    }
    
    return $this->cache['isPlatformAdmin'];
  }
}