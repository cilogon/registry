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
use \Cake\Datasource\Exception\RecordNotFoundException;
use \Cake\Event\EventInterface;
use \Cake\Http\Exception\ForbiddenException;
use \Cake\Http\Exception\UnauthorizedException;
use \Cake\ORM\ResultSet;
use \Cake\ORM\TableRegistry;
use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Enum\TemplateableStatusEnum;

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
    
    if($this->getConfig('apiUser')) {
      // There are no unauthenticated API calls, so always require a valid user
      
      try {
        if($this->authenticateApiUser()) {
          if($this->calculatePermission($request->getParam('action'), $id)) {
            // Authorization successful
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
      
      // XXX is this too broad, or are all Pages permitted? Also, should this move
      // into Controller::isAuthorized?
      if($controller->getName() == 'Pages') {
        return true;
      }
      
      // Do we have an authenticated user session?
      
      // Note we don't stuff anything into the session anymore, the only attribute
      // is the username, which is actually loaded by login.php.
      
      $auth = $session->read('Auth');
      
      if(!empty($auth['external']['user'])) {
        // We have a valid user name that is *authenticated* for the current request.
        // Note we haven't checked authorization, but this is how the authorization
        // checks can get the authenticated username.
        $controller->set('vv_user', ['username' => $auth['external']['user']]);
        $this->authenticatedUser = $auth['external']['user'];
        
        if($this->calculatePermission($request->getParam('action'), $id)) {
          // Authorization successful
          return true;
        }
        
        if(Configure::read('debug')) {
          // For testing purposes throw an error, but in production we want to
          // redirect to /login
          $controller->Flash->error("Authorization Failed (RegistryAuthComponent)");
          return $controller->redirect("/");
        }
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
    $controller = $this->_registry->getController();
    
    $perms = $controller->calculatePermissions($id);
    
    if(!isset($perms[$action])) {
      throw new UnauthorizedException('Invalid Request (RegistryAuthComponent)');
    }
    
    return $perms[$action];
  }
  
  /**
   * Calculate permissions for a Result Set.
   *
   * @since  COmanage Registry v5.0.0
   * @param  ResultSet $rs Result Set
   * @return array         Array of permissions keyed on record ID
   */
  
  public function calculatePermissionsForResultSet(ResultSet $rs): array {
    $controller = $this->_registry->getController();
    
    // We return an array since this is intended to be passed to a view
    $ret = [];
    
    // Note these are Cake ORM functions (rewind, current, etc), and not array
    // functions that PHP deprecated in 8.1.0.
    $rs->rewind();
    
    while($rs->valid()) {
      $o = $rs->current();
      
      $ret[ $o->id ] = $controller->calculatePermissions($o->id);
      
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
    $controller = $this->_registry->getController();
    
    return $controller->calculatePermissions($id);
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
   * Determine if the current user is an API user.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool True if the current user is an API user
   */
  
  public function isApiUser(): bool {
    return $this->authenticatedApiUser;
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
    
    if(!isset($this->cache['isCoAdmin'])) {
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
      if($i->person->isActive() 
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