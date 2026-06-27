<?php
  /**
   * COmanage Registry HTTP Headers
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
   * @link          http://www.internet2.edu/comanage COmanage Project
   * @package       registry
   * @since         COmanage Registry v5.1.0
   * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
   */

  // As a general rule, all Registry pages are post-login and so shouldn't be cached
  header("Cache-Control: no-store, no-cache, max-age=0, must-revalidate");

  // CakePHP adds inline event handlers ("oninput" and "oninvalid") to fields as part of FormHelper.
  // So as not to throw CSP errors, we must include "script-src-attr 'unsafe-inline'".
  // To use VueJS as we do, we must also include "script-src 'unsafe-eval'" 
  $csp = implode('; ', [
    "default-src 'self'",
    "object-src 'none'",
    "base-uri 'none'",
    "frame-ancestors 'self'",
    "script-src 'self' 'nonce-$vv_js_nonce' 'unsafe-eval'",
    "script-src-attr 'unsafe-inline'",
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data:",
  ]);
  header("Content-Security-Policy: $csp");
  
  $permissionsPolicy = implode(', ', [
    'accelerometer=()',
    'autoplay=()',
    'camera=()',
    'cross-origin-isolated=()',
    'display-capture=()',
    'encrypted-media=()',
    'fullscreen=()',
    'geolocation=()',
    'gyroscope=()',
    'keyboard-map=()',
    'magnetometer=()',
    'microphone=()',
    'midi=()',
    'payment=()',
    'picture-in-picture=()',
    'publickey-credentials-get=()',
    'screen-wake-lock=()',
    'sync-xhr=(self)',
    'usb=()',
    'web-share=()',
    'xr-spatial-tracking=()',
    'gamepad=()',
    'hid=()',
    'idle-detection=()',
    'interest-cohort=()',
    'serial=()',
  ]);
  header("Permissions-Policy: $permissionsPolicy");
  header("X-Content-Type-Options: nosniff");
  header("Cross-Origin-Opener-Policy: same-origin");
  header("Cross-Origin-Embedder-Policy: require-corp");
  header("X-Permitted-Cross-Domain-Policies: none");
  header("Referrer-Policy: strict-origin-when-cross-origin");

  // Note that Strict-Transport-Security is not included here because Registry is so typically
  // served behind a reverse proxy which will handle SSL termination. If not behind a proxy,
  // you may wish to include this header once you have HTTPS in place:
  // header("Strict-Transport-Security: max-age=31536000");
