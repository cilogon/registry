<?php
/*
 * COmanage Registry Information Renderers Cell
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

namespace CoreEnroller\View\Cell;

use Cake\View\Cell;

class InformationRenderersCell extends Cell
{
  /**
   * @var mixed
   */
  public $vv_obj;

  /**
   * @var mixed
   */
  public $vv_step;

  /**
   * @var mixed
   */
  public $viewVars;

  /**
   * List of valid options that can be passed into this
   * cell's constructor.
   *
   * @var array<string, mixed>
   */
  protected array $_validCellOptions = [
    'vv_obj',
    'vv_step',
    'viewVars',
  ];

  /**
   * Initialization logic run at the end of object construction.
   *
   * @return void
   */
  public function initialize(): void
  {
  }

  /**
   * Default display method.
   *
   * @since  COmanage Registry v5.3.0
   * @param  int $petitionId
   * @return void
   */

  public function display(int $petitionId): void {
    // There's nothing for us to render in the Petition
    /*
    $vv_pa = $this->fetchTable('CoreEnroller.PetitionApprovals')
      ->find()
      ->where([
        'approval_collector_id' => $this->vv_step->approval_collector->id,
        'petition_id' => $this->vv_obj->id
      ])
      ->contain(['ApproverPeople' => 'PrimaryName'])
      ->first();

    $this->set('vv_pa', $vv_pa);*/
  }
}
