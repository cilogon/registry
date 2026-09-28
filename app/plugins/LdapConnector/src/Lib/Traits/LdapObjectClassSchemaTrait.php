<?php
/**
 * COmanage Registry LdapConnector Schema ObjectClass Schema Trait
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace LdapConnector\Lib\Traits;

use Cake\Datasource\EntityInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\TableRegistry;

trait LdapObjectClassSchemaTrait
{
  /**
   * Return the LDAP objectclass key (eg: 'person', 'eduPerson', 'voPerson').
   *
   * @return string LDAP objectclass key
   * @throws \LogicException If the implementing class returns an empty string (implementation error)
   * @since  COmanage Registry v5.3.0
   */
  abstract public function ldapObjectClass(): string;

  /**
   * Assemble LDAP attributes contributed by this schema for the given entity.
   *
   * @param EntityInterface $schema Active schema configuration entity.
   * @param EntityInterface $ldapProvisioner The LDAP provisioner configuration entity.
   * @param string $className Name of the model being provisioned ('People' or 'Groups').
   * @param object $data Provisioned entity (eg: Person or Group).
   * @param string $op Provisioning operation: 'add', 'modify', or 'rename'.
   * @return array<string,mixed> LDAP attributes contributed by this schema.
   * @throws \Throwable
   * @since COmanage Registry v5.3.0
   */
  abstract public function assemblePluginAttributes(
    EntityInterface $schema,
    EntityInterface $ldapProvisioner,
    string $className,
    object $data,
    string $op
  ): array;

  /**
   * Generates a display field for the given schema entity.
   *
   * @param EntityInterface $entity The entity being processed.
   * @return string|null The generated display field, or null if unavailable.
   * @since COmanage Registry v5.3.0
   */
  public function generateDisplayField(EntityInterface $entity): ?string
  {
    if (!empty($entity->ldap_schema) && !empty($entity->ldap_schema->description)) {
      return (string)$entity->ldap_schema->description;
    }

    if (!empty($entity->ldap_schema_id)) {
      try {
        $LdapSchemas = TableRegistry::getTableLocator()->get('LdapConnector.LdapSchemas');
        $schema = $LdapSchemas->get((int)$entity->ldap_schema_id);
        if (!empty($schema->description)) {
          return (string)$schema->description;
        }
      } catch (\Throwable $e) {
        // Fallback to null
      }
    }

    return null;
  }

  /**
   * Default schema values for schema plugins.
   *
   * @return array<string, mixed> Column defaults
   * @since  COmanage Registry v5.3.0
   */
  public function getDefaults(): array {
    return [];
  }

  /**
   * Build application integrity rules.
   *
   * Ensures that there are no duplicate schema definitions for a given LDAP Schema.
   *
   * @param RulesChecker $rules Rules checker to be modified.
   * @return RulesChecker Modified rules checker with additional integrity rules.
   * @since COmanage Registry v5.3.0
   */
  public function buildRules(RulesChecker $rules): RulesChecker
  {
    $rules->add(
      $rules->isUnique(
        ['ldap_schema_id'],
        __d('ldap_connector', 'error.schema.duplicate')
      ),
      'uniqueSchemaPerLdapSchema',
      ['errorField' => 'ldap_schema_id']
    );

    return $rules;
  }

  /**
   * Ensure 'objectClass' includes this schema's objectclass when this schema emitted attributes.
   *
   * This helper is intended to be called at the end of assemblePluginAttributes() implementations.
   * It mutates the provided $ret array by reference.
   *
   * @param array<string,mixed> $ret Assembled attributes (mutated in-place)
   * @return void
   * @throws \LogicException|\Throwable If ldapObjectClass() returns an empty string
   * @since COmanage Registry v5.3.0
   */
  protected function ensureSchemaObjectClass(array &$ret): void
  {
    if (empty($ret)) {
      return;
    }

    $oc = $this->ldapObjectClass();
    if ($oc === '') {
      throw new \LogicException('ldapObjectClass() must not return an empty string');
    }

    if (!array_key_exists('objectClass', $ret)) {
      $ret['objectClass'] = [$oc];
      return;
    }

    if (is_array($ret['objectClass'])) {
      $ret['objectClass'] = array_values(array_unique(array_merge($ret['objectClass'], [$oc])));
      return;
    }

    $ret['objectClass'] = array_values(array_unique([(string)$ret['objectClass'], $oc]));
  }

  /**
   * Render a Name(-like) object into a string.
   *
   * @param object $name
   * @return string
   * @since COmanage Registry v5.3.0
   */
  protected function renderName(object $name): string
  {
    return (string)($name->full_name ?? '');
  }

  /**
   * Whether this schema plugin can contribute attributes when provisioning People.
   *
   * Default is true for backward compatibility; override in schema tables that
   * only apply to Groups.
   *
   * @return bool
   * @since COmanage Registry v5.3.0
   */
  public function supportsPeople(): bool
  {
    return true;
  }

  /**
   * Whether this schema plugin can contribute attributes when provisioning Groups.
   *
   * Default is false; override in schema tables that apply to Groups.
   *
   * @return bool
   * @since COmanage Registry v5.3.0
   */
  public function supportsGroups(): bool
  {
    return false;
  }

  /**
   * Convenience wrapper to check support by provisioned class name.
   *
   * @param string $className Expected 'People' or 'Groups'
   * @return bool
   * @since COmanage Registry v5.3.0
   */
  public function supportsProvisioningClass(string $className): bool
  {
    return match ($className) {
      'People' => $this->supportsPeople(),
      'Groups' => $this->supportsGroups(),
      default  => false,
    };
  }

  /**
   * Determine if "attribute options" are enabled for the given LDAP provisioner.
   *
   * @param EntityInterface $ldapProvisioner LDAP provisioner entity.
   * @return bool
   * @since COmanage Registry v5.3.0
   */
  protected function attributeOptionsEnabled(EntityInterface $ldapProvisioner): bool
  {
    return !empty($ldapProvisioner->attr_opts);
  }
}
