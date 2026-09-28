<?php
/**
 * COmanage Registry LDAP Servers Table
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

namespace CoreServer\Model\Table;

use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Enum\TableTypeEnum;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use CoreServer\Lib\Enum\LdapCommonCodesEnum;
use LDAP\Connection;

class LdapServersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  const int NETWORK_TIMEOUT = 10;
  const int LDAP_PROTOCOL_VERSION = 3;

  /**
   * LDAP connection cache, keyed by server ID.
   *
   * @var array<int, \LDAP\Connection>
   */
  private array $cxnByServerId = [];

  /**
   * Perform Cake Model initialization.
   *
   * @param  array  $config Configuration options passed to constructor
   * @since         COmanage Registry v5.3.0
   */
  public function initialize(array $config): void {
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->setTableType(TableTypeEnum::Configuration);

    // Define associations
    $this->belongsTo('Servers');

    $this->setDisplayField('serverurl');

    $this->setPrimaryLink('server_id');
    $this->setRequiresCO(true);

    $this->setPermissions([
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'test' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Establish a connection to the specified LDAP server and cache it internally.
   *
   * On failure, this method logs server-side LDAP diagnostics (when available)
   * and throws a RuntimeException whose message includes those diagnostics.
   *
   * @param int $serverId Server ID.
   * @return bool True if connected and bound successfully.
   * @throws \InvalidArgumentException If the server is inactive/invalid.
   * @throws \RuntimeException If the connection or bind fails.
   * @since COmanage Registry v5.3.0
   */
  protected function connect(int $serverId): bool
  {
    $server = $this->Servers->get($serverId, contain: ['LdapServers']);

    if($server->status != SuspendableStatusEnum::Active) {
      throw new \InvalidArgumentException(__d('error', 'inactive', [__d('controller', 'Servers', [1]), $serverId]));
    }

    $cxn = @ldap_connect($server->ldap_server->serverurl);

    if(!$cxn) {
      throw new \RuntimeException(
        __d('core_server', 'error.LdapServers.connect', [$server->ldap_server->serverurl]),
        LdapCommonCodesEnum::LDAP_CONNECT_ERROR
      );
    }

    ldap_set_option($cxn, LDAP_OPT_PROTOCOL_VERSION, self::LDAP_PROTOCOL_VERSION);
    ldap_set_option($cxn, LDAP_OPT_NETWORK_TIMEOUT, self::NETWORK_TIMEOUT);

    if(empty($server->ldap_server->binddn) || empty($server->ldap_server->password)) {
      // Avoid logging secrets; note we also don't have a meaningful ldap_errno() here.
      $this->logLdapError($cxn, 'ldap_bind', [
        'serverurl' => $server->ldap_server->serverurl,
        'binddn' => $server->ldap_server->binddn,
        'reason' => 'missing bind credentials',
      ]);

      throw new \RuntimeException($this->formatLdapFailure($cxn, 'ldap_bind', [
        'serverurl' => $server->ldap_server->serverurl,
        'binddn' => $server->ldap_server->binddn,
      ]));
    }

    if(!@ldap_bind($cxn, $server->ldap_server->binddn, $server->ldap_server->password)) {
      $this->logLdapError($cxn, 'ldap_bind', [
        'serverurl' => $server->ldap_server->serverurl,
        'binddn' => $server->ldap_server->binddn,
      ]);

      throw new \RuntimeException(
        $this->formatLdapFailure($cxn, 'ldap_bind', [
          'serverurl' => $server->ldap_server->serverurl,
          'binddn' => $server->ldap_server->binddn,
        ]),
        ldap_errno($cxn)
      );
    }

    $this->cxnByServerId[$serverId] = $cxn;

    return true;
  }


  /**
   * Return an active LDAP connection handle for the given Server ID.
   *
   * This method acts as the public entry point for callers needing to perform
   * operations against the LDAP directory configured for $serverId. If no
   * connection is currently cached, it will attempt to connect and bind by invoking
   * connect($serverId), which is expected to populate the internal connection cache.
   *
   * @param int $serverId Server ID
   * @return \LDAP\Connection The LDAP connection object on success, or false if not connected.
   * @throws \InvalidArgumentException If the server is not active or is otherwise invalid.
   * @throws \RuntimeException If the connection or bind fails.
   * @since COmanage Registry v5.3.0
   */
  public function getLdapConnection(int $serverId): \LDAP\Connection
  {
    if (empty($this->cxnByServerId[$serverId])) {
      $this->connect($serverId);
    }

    return $this->cxnByServerId[$serverId];
  }

  /**
   * Test connectivity to the LDAP server.
   *
   * @param int $serverId Server ID
   * @param string $name Name of the connection test (for logging)
   *
   * @return bool True if the connection succeeds, false otherwise
   * @since COmanage Registry v5.3.0
   */
  public function checkConnectivity(int $serverId, string $name): bool
  {
    try {
      $cxn = $this->getLdapConnection($serverId);
      if ($cxn) {
        return true;
      }
    } catch (\Exception $e) {
      $this->llog('error', __METHOD__ . "::Connection test '{$name}' failed: " . $e->getMessage());
    }

    return false;
  }

  /**
   * Search an LDAP server.
   *
   * This method wraps ldap_search() with standardized error handling and logging.
   * The caller is responsible for obtaining a bound connection (eg via getLdapConnection()).
   *
   * On failure, this method logs baseDN/filter/attributes and throws a RuntimeException
   * whose message includes the LDAP diagnostic message when available.
   *
   * @param \LDAP\Connection $cxn Active LDAP connection (already connected and bound).
   * @param string $baseDn Base DN for the search.
   * @param string $filter LDAP search filter.
   * @param array<int,string> $attributes Attributes to return.
   * @return array LDAP search results (ldap_get_entries() format).
   * @throws \RuntimeException On LDAP search failure.
   * @since COmanage Registry v5.3.0
   */
  public function queryLdap(
    \LDAP\Connection $cxn,
    string $baseDn,
    string $filter,
    array $attributes = []
  ): array {
    $currentErrorReporting = error_reporting(0);
    $s = @ldap_search($cxn, $baseDn, $filter, $attributes);
    error_reporting($currentErrorReporting);

    if (!$s) {
      $this->logLdapError($cxn, 'ldap_search', [
        'baseDN' => $baseDn,
        'filter' => $filter,
        'attributes' => $attributes,
      ]);

      throw new \RuntimeException(
        $this->formatLdapFailure($cxn, 'ldap_search', [
          'baseDN' => $baseDn,
          'filter' => $filter,
          'attributes' => $attributes,
        ]),
        ldap_errno($cxn)
      );
    }

    return ldap_get_entries($cxn, $s);
  }

  /**
   * Add an LDAP entry.
   *
   * This method wraps ldap_add() with standardized error handling and logging.
   * The caller is responsible for obtaining a bound connection (eg via getLdapConnection()).
   *
   * On failure, logs dn + attribute_keys (and full attributes) and throws a RuntimeException
   * whose message includes the LDAP diagnostic message when available.
   *
   * @param \LDAP\Connection $cxn Active LDAP connection (already connected and bound).
   * @param string $dn DN of the entry to create.
   * @param array<string,mixed> $attributes LDAP attributes to set on the new entry.
   * @return void
   * @throws \InvalidArgumentException On LDAP object class violation/modification prohibited.
   * @throws \RuntimeException On LDAP add failure.
   * @since COmanage Registry v5.3.0
   */
  public function addEntry(\LDAP\Connection $cxn, string $dn, array $attributes): void {
    $attributes = $this->sanitizeAttributesForAdd($attributes);
    $attrKeys = array_keys($attributes);

    $currentErrorReporting = error_reporting(0);
    $ok = @ldap_add($cxn, $dn, $attributes);
    error_reporting($currentErrorReporting);

    if (!$ok) {
      $errno = ldap_errno($cxn);

      if (
        $errno === LdapCommonCodesEnum::LDAP_OBJECT_CLASS_MODS_PROHIBITED
        || $errno === LdapCommonCodesEnum::LDAP_OBJECT_CLASS_VIOLATION
      ) {
        // The handler will log the error and throw an exception. Execution stops here.
        $this->handleObjectClassError($cxn, 'ldap_add', $dn, $attributes, $errno);
      }

      $this->logLdapError($cxn, 'ldap_add', [
        'dn' => $dn,
        'attribute_keys' => $attrKeys,
        'attributes' => $attributes,
      ]);

      throw new \RuntimeException(
        $this->formatLdapFailure($cxn, 'ldap_add', [
          'dn' => $dn,
          'attribute_keys' => $attrKeys,
        ]),
        ldap_errno($cxn)
      );
    }
  }

  /**
   * Sanitize attributes for ldap_add().
   *
   * Some LDAP servers reject attributes with empty array values on add (eg ['owner' => []]).
   * For modify/rename we intentionally allow empty arrays to mean "clear attribute",
   * but for add we should omit such attributes entirely.
   *
   * @param array<string,mixed> $attributes
   * @return array<string,mixed>
   */
  protected function sanitizeAttributesForAdd(array $attributes): array {
    foreach ($attributes as $attr => $value) {
      if (is_array($value) && empty($value)) {
        unset($attributes[$attr]);
      }
    }

    return $attributes;
  }

  /**
   * Delete an LDAP entry.
   *
   * This method wraps ldap_delete() with standardized error handling and logging.
   * The caller is responsible for obtaining a bound connection (eg via getLdapConnection()).
   *
   * On failure, logs dn and throws a RuntimeException whose message includes the
   * LDAP diagnostic message when available.
   *
   * @param \LDAP\Connection $cxn Active LDAP connection (already connected and bound).
   * @param string $dn DN of the entry to delete.
   * @return void
   * @throws \RuntimeException On LDAP delete failure.
   * @since COmanage Registry v5.3.0
   */
  public function deleteEntry(\LDAP\Connection $cxn, string $dn): void {
    $currentErrorReporting = error_reporting(0);
    $ok = @ldap_delete($cxn, $dn);
    error_reporting($currentErrorReporting);

    if (!$ok) {
      $this->logLdapError($cxn, 'ldap_delete', [
        'dn' => $dn,
      ]);

      throw new \RuntimeException(
        $this->formatLdapFailure($cxn, 'ldap_delete', [
          'dn' => $dn,
        ]),
        ldap_errno($cxn)
      );
    }
  }

  /**
   * Modify (replace) LDAP attributes on an entry.
   *
   * This method wraps ldap_mod_replace() with standardized error handling and logging.
   * On failure, it logs details via {@see logLdapError()} and throws a RuntimeException
   * that includes the LDAP diagnostic message when available.
   *
   * @param \LDAP\Connection $cxn Active LDAP connection (already connected and bound).
   * @param string $dn DN of the entry to modify.
   * @param array<string,mixed> $attributes LDAP attributes to replace.
   * @return void
   * @throws \InvalidArgumentException On LDAP object class violation/modification prohibited.
   * @throws \RuntimeException On LDAP modify failure.
   * @since COmanage Registry v5.3.0
   */
  public function modReplace(\LDAP\Connection $cxn, string $dn, array $attributes): void {
    $currentErrorReporting = error_reporting(0);
    $ok = @ldap_mod_replace($cxn, $dn, $attributes);
    error_reporting($currentErrorReporting);

    if (!$ok) {
      $errno = ldap_errno($cxn);

      if (
        $errno === LdapCommonCodesEnum::LDAP_OBJECT_CLASS_MODS_PROHIBITED
        || $errno === LdapCommonCodesEnum::LDAP_OBJECT_CLASS_VIOLATION
      ) {
        $this->handleObjectClassError($cxn, 'ldap_mod_replace', $dn, $attributes, $errno);
      }

      $attrKeys = array_keys($attributes);

      $this->logLdapError($cxn, 'ldap_mod_replace', [
        'dn' => $dn,
        'attribute_keys' => $attrKeys,
        'attributes' => $attributes
      ]);

      throw new \RuntimeException(
        $this->formatLdapFailure($cxn, 'ldap_mod_replace', [
          'dn' => $dn,
          'attribute_keys' => $attrKeys,
        ]),
        ldap_errno($cxn)
      );
    }
  }

  /**
   * Rename (move/rename) an LDAP entry.
   *
   * This method wraps ldap_rename() with standardized error handling and logging.
   * The caller is responsible for obtaining a bound connection (eg via getLdapConnection()).
   *
   * On failure, logs oldDn/newRdn/newParentDn/deleteOldRdn and throws a RuntimeException
   * whose message includes the LDAP diagnostic message when available.
   *
   * @param \LDAP\Connection $cxn Active LDAP connection (already connected and bound).
   * @param string $oldDn Current (full) DN of the entry.
   * @param string $newRdn New RDN (relative distinguished name) for the entry.
   * @param string $newParentDn New parent DN, or an empty string to keep the same parent.
   * @param bool $deleteOldRdn Whether to delete the old RDN value from the entry.
   * @return void
   * @throws \RuntimeException On LDAP rename failure.
   * @since COmanage Registry v5.3.0
   */
  public function renameEntry(
    \LDAP\Connection $cxn,
    string $oldDn,
    string $newRdn,
    string $newParentDn = '',
    bool $deleteOldRdn = true
  ): void {
    $currentErrorReporting = error_reporting(0);
    $ok = @ldap_rename($cxn, $oldDn, $newRdn, $newParentDn, $deleteOldRdn);
    error_reporting($currentErrorReporting);

    if (!$ok) {
      $this->logLdapError($cxn, 'ldap_rename', [
        'oldDn' => $oldDn,
        'newRdn' => $newRdn,
        'newParentDn' => $newParentDn,
        'deleteOldRdn' => $deleteOldRdn,
      ]);

      throw new \RuntimeException(
        $this->formatLdapFailure($cxn, 'ldap_rename', [
          'oldDn' => $oldDn,
          'newRdn' => $newRdn,
          'newParentDn' => $newParentDn,
          'deleteOldRdn' => $deleteOldRdn,
        ]),
        ldap_errno($cxn)
      );
    }
  }

  /**
   * Read a single LDAP entry.
   *
   * This is a convenience wrapper around queryLdap() that returns the first entry
   * (index 0) or null if no entry exists at the requested DN.
   *
   * @param \LDAP\Connection $cxn Active LDAP connection (already connected and bound).
   * @param string $dn DN to read.
   * @param array $attributes Attributes to return.
   * @return array|null The first LDAP entry (ldap_get_entries() entry format) or null if not found.
   * @throws \RuntimeException On LDAP search failure.
   * @since COmanage Registry v5.3.0
   */
  public function readEntry(\LDAP\Connection $cxn, string $dn, array $attributes = []): ?array {
    $results = $this->queryLdap($cxn, $dn, '(objectclass=*)', $attributes);

    if (empty($results['count']) || (int)$results['count'] < 1) {
      return null;
    }

    return $results[0] ?? null;
  }

  /**
   * Try modify; if the entry doesn't exist, add it.
   * If add races with another writer, fall back to modify.
   *
   * @param \LDAP\Connection $cxn
   * @param string $dn
   * @param array $attributes
   * @return array{action:string,dn:string}
   */
  public function addOrModifyAt(\LDAP\Connection $cxn, string $dn, array $attributes): array {
    try {
      $this->modReplace($cxn, $dn, $attributes);
      return ['action' => 'modify', 'dn' => $dn];
    } catch (\RuntimeException $e) {
      if ((int)$e->getCode() !== LdapCommonCodesEnum::LDAP_NO_SUCH_OBJECT) {
        throw $e;
      }
    }

    try {
      $this->addEntry($cxn, $dn, $attributes);
      return ['action' => 'add', 'dn' => $dn];
    } catch (\RuntimeException $e) {
      if ((int)$e->getCode() !== 68 /* LDAP_ENTRY_ALREADY_EXISTS */) {
        throw $e;
      }

      // Race with another writer: entry exists now -> modify in place.
      $this->modReplace($cxn, $dn, $attributes);
      return ['action' => 'modify', 'dn' => $dn];
    }
  }

  /**
   * Compute the new RDN portion of $newDn relative to $baseDn.
   *
   * Performs a case-insensitive suffix match. If $baseDn is empty or not a suffix
   * of $newDn, returns $newDn unchanged.
   */
  public function removeBaseDnSuffix(string $newDn, string $baseDn): string {
    if ($baseDn === '') {
      return $newDn;
    }

    $suffix = ',' . $baseDn;

    if (strlen($newDn) > strlen($suffix)
      && strcasecmp(substr($newDn, -strlen($suffix)), $suffix) === 0) {
      return substr($newDn, 0, -strlen($suffix));
    }

    return $newDn;
  }


  /**
   * Disconnect (unbind) an LDAP connection for a given server ID.
   *
   * This method unbinds the cached connection (if any) and removes it from the internal cache.
   * It does not throw on unbind failure (unbind failures are generally not actionable).
   *
   * @param int $serverId Server ID.
   * @return void
   * @since COmanage Registry v5.3.0
   */
  public function disconnect(int $serverId): void {
    if (empty($this->cxnByServerId[$serverId])) {
      return;
    }

    $cxn = $this->cxnByServerId[$serverId];

    $currentErrorReporting = error_reporting(0);
    @ldap_unbind($cxn);
    error_reporting($currentErrorReporting);

    unset($this->cxnByServerId[$serverId]);
  }

  /**
   * Disconnect (unbind) all cached LDAP connections.
   *
   * @return void
   * @since COmanage Registry v5.3.0
   */
  public function disconnectAll(): void {
    foreach (array_keys($this->cxnByServerId) as $serverId) {
      $this->disconnect((int)$serverId);
    }
  }

  /**
   * Log useful information after an LDAP operation failure.
   *
   * This method records:
   * - the LDAP operation name (eg: ldap_mod_replace)
   * - the call parameters (as provided by the caller)
   * - the LDAP numeric error code (ldap_errno)
   * - the server-provided error string (ldap_error)
   * - the library error string for the code (ldap_err2str)
   * - the optional diagnostic message (LDAP_OPT_DIAGNOSTIC_MESSAGE), if available
   *
   * @param \LDAP\Connection $cxn Active LDAP connection (already connected/bound).
   * @param string $functionName LDAP function name being executed (eg: 'ldap_mod_replace').
   * @param array<string,mixed> $functionParameters Parameters relevant to the operation (for debugging).
   * @return void
   * @since COmanage Registry v5.3.0
   */
  public function logLdapError(\LDAP\Connection $cxn, string $functionName, array $functionParameters = []): void {
    $errno = ldap_errno($cxn);

    $context = [
      'method' => $functionName,
      'parameters' => $functionParameters,
      'error_code' => $errno,
      'error' => ldap_error($cxn),
      'error_string' => ldap_err2str($errno),
    ];

    ldap_get_option($cxn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $err);
    if (!empty($err)) {
      $context['diagnostic_message'] = $err;
    }

    $this->alog('error', [
        'message' => __METHOD__ . "::LDAP error during $functionName"
      ] + $context);
  }

  /**
   * Handle object class violation or modification prohibited errors.
   *
   * Logs detailed failure information with full stack trace, then throws an
   * InvalidArgumentException with a meaningful diagnostic message for the UI.
   *
   * @param \LDAP\Connection $cxn Active LDAP connection.
   * @param string $operation Operation label (e.g., 'ldap_mod_replace' or 'ldap_add').
   * @param string $dn Target DN.
   * @param array<string,mixed> $attributes Attributes payload.
   * @param int $errno LDAP error code.
   * @return void
   * @throws \InvalidArgumentException
   * @since COmanage Registry v5.3.0
   */
  protected function handleObjectClassError(
    \LDAP\Connection $cxn,
    string           $operation,
    string           $dn,
    array            $attributes,
    int              $errno
  ): void {
    $attrKeys = array_keys($attributes);
    $trace = (new \Exception())->getTraceAsString();

    // Single structured log containing full payload and stack trace
    $this->logLdapError($cxn, $operation, [
      'dn' => $dn,
      'attribute_keys' => $attrKeys,
      'attributes' => $attributes,
      'stack_trace' => $trace,
    ]);

    // Extract human-readable diagnostics for UI provisioning comment
    $diag = '';
    ldap_get_option($cxn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diag);
    $diag = is_string($diag) ? trim($diag) : '';
    $serverMsg = ldap_error($cxn);

    $meaningfulComment = !empty($diag) ? "$serverMsg: $diag" : "$serverMsg (code $errno)";

    throw new \InvalidArgumentException($meaningfulComment, $errno);
  }

  /**
   * Format a detailed LDAP failure message for exception propagation.
   *
   * This is intended to improve debuggability when callers catch a RuntimeException
   * and only have access to $e->getMessage(). It attempts to include:
   * - ldap_error() (server message)
   * - ldap_errno() and ldap_err2str() (code + meaning)
   * - LDAP_OPT_DIAGNOSTIC_MESSAGE (if provided by the server)
   * - a small JSON-encoded context payload (eg DN, attribute keys)
   *
   * @param \LDAP\Connection $cxn Active LDAP connection (already connected/bound).
   * @param string $operation Operation label (eg: 'ldap_mod_replace').
   * @param array<string,mixed> $context Optional additional context to embed in the message.
   * @return string Human-readable, log-friendly error message.
   * @since COmanage Registry v5.3.0
   */
  protected function formatLdapFailure(\LDAP\Connection $cxn, string $operation, array $context = []): string
  {
    $errno = ldap_errno($cxn);

    $msg = $operation
      . ' failed: '
      . ldap_error($cxn)
      . ' (code '
      . $errno
      . ': '
      . ldap_err2str($errno)
      . ')';

    ldap_get_option($cxn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diag);
    if (!empty($diag)) {
      $msg .= '; diagnostic=' . (is_string($diag) ? $diag : json_encode($diag));
    }

    if (!empty($context)) {
      $msg .= '; context=' . json_encode($context);
    }

    return $msg;
  }

  /**
   * Set validation rules.
   *
   * @param  Validator $validator Validator
   * @return Validator            Validator
   * @since         COmanage Registry v5.3.0
   */
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('server_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('server_id');

    $this->registerStringValidation($validator, $schema, 'serverurl', true);

    $validator->add('serverurl', [
      'validUrl' => [
        'rule' => ['custom', '/^ldaps?:\/\/.*/'],
        'message' => __d('core_server', 'error.LdapServers.serverurl.valid')
      ]
    ]);

    $this->registerStringValidation($validator, $schema, 'binddn', true);
    $this->registerStringValidation($validator, $schema, 'password', true);

    // Core base DN (used for People)
    $this->registerStringValidation($validator, $schema, 'basedn', true);

    // Group base DN
    $this->registerStringValidation($validator, $schema, 'group_basedn', false);

    return $validator;
  }
}
