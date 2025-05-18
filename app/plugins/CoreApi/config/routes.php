<?php
/**
 * CoreApi plugin specific routes.
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
 * @package       registry-plugins
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;

// CoreApi API routes

// Match Callback Receiver
// (https://spaces.at.internet2.edu/display/COmanage/Match+Resolution+Endpoint+Notification)
$routes->scope('/api/match', function (RouteBuilder $builder) {
  // Register scoped middleware for in scopes.
// Do not enable CSRF for the REST API, it will break standard (non-AJAX) clients
//  $builder->registerMiddleware('csrf', new CsrfProtectionMiddleware(['httponly' => true]));
  // BodyParserMiddleware will automatically parse JSON bodies, but we only
  // want that for API transactions, so we only apply it to the /api scope.
  $builder->registerMiddleware('bodyparser', new BodyParserMiddleware());
  /*
   * Apply a middleware to the current route scope.
   * Requires middleware to be registered through `Application::routes()` with `registerMiddleware()`
   */
// Do not enable CSRF for the REST API, it will break standard (non-AJAX) clients
//  $builder->applyMiddleware('csrf');
  $builder->setExtensions(['json']);
  $builder->applyMiddleware('bodyparser');

  $builder->post(
    '/{coid}/v1/resolution',
    ['plugin' => 'CoreApi', 'controller' => 'MatchCallbackApiV1', 'action' => 'resolveMatch']
  )
  ->setPass(['coid'])
  ->setPatterns(['coid' => '[0-9]+']);
});