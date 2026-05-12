<?php
  /*
   * COmanage Registry Flash Message Container
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
?>

<?php if ($this->request->getSession()->check('Flash.flash') || !empty($vv_alerts)): ?>
  <!-- Flash Messages and defined Info Banners/Alerts -->
  <div class="alert-container" id="flash-messages">
    <?php 
      /* Render any Flash messages that have bubbled up. 
         These will adopt the styles defined in the 
         app/templates/element/flash/ directory. */
      print $this->Flash->render();
  
      /* Render information banners explicitly defined in Configuration.
         These are defined in the alerts[] array in columns.inc and fields.inc files.
         See app/templates/ApiUsers/columns.inc for an example. These must
         be passed in as $vv_alerts to this element from the calling template. 
      */ 
      if(!empty($vv_alerts)) {
        foreach($vv_alerts as $a) {
          print $this->element('notify/alert', $a);
        }
      }
    ?>
  </div>
<?php endif; ?>