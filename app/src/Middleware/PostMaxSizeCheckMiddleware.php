<?php
/**
 * COmanage post_max_size Check for File Uploads
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
  
declare(strict_types=1);

namespace App\Middleware;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\FlashMessage;
use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PostMaxSizeCheckMiddleware implements MiddlewareInterface {
  
  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    if ($request->is('post')) {
      $contentLength = (int)$request->getHeaderLine('Content-Length');
      $postMaxBytes = ini_parse_quantity(ini_get('post_max_size'));

      if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
        // Route the user back to where they came from with an error message if possible.
        $referer = $request->getHeaderLine('Referer');
        $session = $request->getAttribute('session');
        
        if(empty($referer) || empty($session)) {
          // There's no referer to reference (and possibly no session), so just show the more abrupt white-screen error.
          throw new BadRequestException(
            __d('error', 'upload.php.postmaxsize', [ini_get('post_max_size')])
          );
        }

        // We have a referer, so set the flash message and return.
        $flash = new FlashMessage($session);
        $flash->error(__d('error', 'upload.php.postmaxsize', [ini_get('post_max_size')]));
        return (new Response())->withStatus(302)->withHeader('Location', $referer);
      }
    }

    return $handler->handle($request);
  }
}