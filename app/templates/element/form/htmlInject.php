<?php

/**
 * COmanage Registry Inject H3 Element to a list
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

declare(strict_types=1);

// View vars
// $content - the text or markup to be rendered OR an element spec array
// $type    - accepts "subtitle" or "html"
// $vv_action

if (empty($content)) {
  return;
}

?>

<li class="fields-subsection fields-subsection-<?= $type ?>">
  <?php if($type === 'subtitle'): ?>
    <h3><?= $content ?></h3>
  <?php else: ?>
    <?php
      // If content is an element spec, render it now (after Form->create()).
      if (is_array($content) && !empty($content['element'])) {
        $params = $content['params'] ?? [];
        echo $this->element($content['element'], $params);
      } else {
        // Otherwise treat as raw HTML/text.
        echo $content;
      }
    ?>
  <?php endif; ?>
</li>