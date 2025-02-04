<?php
/**
 * COmanage Registry Petition Helper
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

namespace App\View\Helper;

use App\Lib\Util\StringUtilities;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\View\Helper;
use CoreEnroller\Model\Table\EnrollmentAttributesTable;

class PetitionHelper extends Helper
{
  protected ?object $enrollmentAttributesTable = null;

  // The current entity, if edit or view
  protected ?object $entity = null;

  protected ?object $petition = null;

  public function initialize(array $config): void
  {
    parent::initialize($config);
    $this->entity = $this->getView()->get('vv_obj');
    $this->petition = $this->getView()->get('vv_petition');
    $this->enrollmentAttributesTable = new EnrollmentAttributesTable();
  }

  /**
   * Get the Enrollment Attribute hardcoded configuration
   *
   * @param   string  $attribute
   *
   * @return array
   * @since  COmanage Registry v5.0.0
   */
  public function getSupportedEnrollmentAttribute(string $attribute): array
  {
    return $this->enrollmentAttributesTable->supportedAttributes()[$attribute];
  }

  /**
   * Calculate and populate the Enrollment Attributes auto view vars
   *
   * @since  COmanage Registry v5.0.0
   */
  public function populateAutoViewVars(): void
  {
    // XXX Find the co id
    foreach (
      $this->enrollmentAttributesTable->calculateAutoViewVars($this->petition?->enrollment_flow?->co_id,$this->entity) as $vvar => $value
    ) {
      $this->getView()->set($vvar, $value);
    }
  }

  /**
   * Get the table validation rules
   *
   * @param   string  $tableName
   *
   * @return Table
   * @since  COmanage Registry v5.0.0
   */
  public function getTable(string $tableName): Table
  {
    return TableRegistry::getTableLocator()->get($tableName);
  }

  /**
   * Fetch a record by its ID and optionally include related data.
   *
   * This method retrieves a specific record from the database using its ID
   * and foreign key. If optional related data (associations) need to be loaded,
   * they can be specified with the `$contains` parameter.
   *
   * @param   string      $foreignKey
   * @param   int|string  $id
   * @param   array       $contains
   *
   * @return array
   * @since  COmanage Registry v5.1.0
   */
  public function getRecordForId(string $foreignKey, int|string $id, array $contains = []): array
  {
    $tableName = StringUtilities::foreignKeyToClassName($foreignKey);
    if($tableName === 'AffiliationTypes') {
      $tableName = 'Types';
    }
    $table = $this->getTable($tableName);
    $query = $table->find()
      ->where([$tableName . '.id' => $id]);
    if(!empty($contains)) {
      return $query
        ->contain($contains)
        ->first()
        ->toArray();
    }
    return $query->first()->toArray();
  }

  /**
   * Transform an enrollment attribute name into a class postfix
   *
   * This method modifies the given attribute name by converting it
   * to a format suitable for use as a CSS class postfix.
   *
   * @param string $attributeName Attribute name to transform
   * @return string Transformed class postfix
   * @since  COmanage Registry v5.1.0
   */
  public function getClassPostfixFromAttributeName(string $attributeName): string
  {
    if(str_ends_with($attributeName, '_id')) {
      $attributeName = substr($attributeName, 0, -3);
    }
    $attributeName = Inflector::underscore($attributeName);
    return str_replace('_', '-', $attributeName);
  }
}