<?php
/**
 * COmanage Match Login Handler
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

// We need to be in webroot and not use index.php in order to not interfere with
// Cake's desire to turn everything into a Controller.

// The webserver should be configured to do the bulk of the work here, we
// simply grab REMOTE_USER and stuff it into the session so the app can see it.

session_name("REGISTRYPECAKEPHP");
session_start();

// Set the user

if(empty($_SERVER['REMOTE_USER'])) {
  print "ERROR: REMOTE_USER is empty. Please check your configuration.";
  exit;
}

$_SESSION['Auth']['external']['user'] = $_SERVER['REMOTE_USER'];

// After storing the user in the session, we redirect into the TrafficController
// which handles the rest of the login process. To construct the URL, we look at
// REQUEST_URI to figure out what our application prefix is (eg: /registry).

$re = '/(.*)\/auth\/login\/login(?:.php)?(.*)/m';
$subst = '$1' . '/traffic/process-login' . '$2';
$path = preg_replace($re, $subst, urldecode($_SERVER['REQUEST_URI']), 1);

header("Location: " . $path);