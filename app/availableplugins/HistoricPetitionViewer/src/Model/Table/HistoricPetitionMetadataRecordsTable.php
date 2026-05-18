<?php
/**
 * COmanage Registry Historic Petition Metadata Records Table
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

declare(strict_types=1);

namespace HistoricPetitionViewer\Model\Table;

use App\Lib\Enum\TableTypeEnum;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class HistoricPetitionMetadataRecordsTable extends Table {
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  public function initialize(array $config): void {
    parent::initialize($config);

    // Map model to shorter physical table name
    $this->setTable('petition_meta_hist_recs');
    $this->setPrimaryKey('id');
    $this->setDisplayField('id');

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->setTableType(TableTypeEnum::Artifact);

    // Define associations
    $this->belongsTo('Petitions');
    $this->belongsTo('EnrollmentFlows')
      ->setForeignKey('enrollment_flow_id');

    $this->belongsTo('HistoricPetitionViewers')
      ->setForeignKey('historic_petition_viewer_id');

    $this->belongsTo('EnrolleePersonRoles')
      ->setClassName('PersonRoles')
      ->setForeignKey('enrollee_person_role_id')
      ->setProperty('enrollee_person_role');

    $this->belongsTo('SponsorPeople')
      ->setClassName('People')
      ->setForeignKey('sponsor_person_id')
      ->setProperty('sponsor_person');

    $this->belongsTo('ApproverPeople')
      ->setClassName('People')
      ->setForeignKey('approver_person_id')
      ->setProperty('approver_person');

    $this->belongsTo('EnrolleeExternalIdentities')
      ->setClassName('ExternalIdentities')
      ->setForeignKey('enrollee_external_identity_id')
      ->setProperty('enrollee_external_identity');

    $this->belongsTo('ArchivedExternalIdentities')
      ->setClassName('ExternalIdentities')
      ->setForeignKey('archived_external_identity_id')
      ->setProperty('archived_external_identity');

    $this->setDisplayField('petition_id');
    $this->setRequiresCO(true);
  }
}