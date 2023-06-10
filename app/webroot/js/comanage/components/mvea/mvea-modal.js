/**
 * COmanage Registry MVEA Modal JavaScript
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

export default {
  props: {
    modal: Object,
    core: Object,
    txt: Object
  },
  template: `
    <div className="modal fade mvea-modal" id="mvea-modal" aria-labelledby="mvea-modal-title" tabIndex="-1" aria-hidden="true">
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content">
          <div className="modal-header">
            <h2 class="modal-title" id="mvea-modal-title">{{ this.modal.title }}</h2>
            <button type="button" className="btn-close nospin" data-bs-dismiss="modal"
                    :aria-label="txt.close"></button>
          </div>
          <div id="mvea-modal-text" className="modal-body">
            <iframe :src="this.modal.url"/>
          </div>
        </div>
      </div>
    </div>
  `
}