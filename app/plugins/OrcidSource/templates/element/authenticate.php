<?php
/**
 * COmanage Registry Orcid Source Authenticate
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

$btnAuthenticateLabel = '<span class="material-symbols-outlined my-auto me-1" aria-hidden="true">login</span>'
    . __d('orcid_source', 'information.OrcidSourceCollectors.authenticate');

print $this->Form->hidden('op', ['value' => 'authenticate']);

?>

<div class="text-center py-3">
    <?= $this->Html->image('OrcidSource.orcid_128x128.png', ['alt' => 'Logo', 'class' => 'mb-3']) ?>
    <h2 class="fw-bold mb-2"><?= __d('orcid_source', 'information.OrcidSourceCollectors.authenticate') ?></h2>
    <p class="mb-4 text-muted"><?= __d('orcid_source', 'information.OrcidSourceCollectors.sign_in') ?></p>
    <?= $this->Form->button(
        $btnAuthenticateLabel,
        [
            'id' => 'orcid-auth-btn',
            'escapeTitle' => false,
            'type' => 'submit',
            'class' => 'spin submit-button btn btn-primary d-flex mx-auto',
        ]
    )
    ?>
</div>
