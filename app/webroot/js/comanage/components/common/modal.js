/**
 * COmanage Registry Modal Vue Element
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
    id: {
      type: String
    },
    title: {
      type: String,
      default: 'Title'
    },
    buttonLabel: {
      type: String
    }
  },
  inject: ['txt'],
  template: `
    <div className="modal fade cm-modal"
         :id="id + '-modal'"
         aria-labelledby="modal-title"
         tabIndex="-1"
         aria-hidden="true">
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content">
          <div className="modal-header">
            <h2 class="modal-title" id="modal-title">{{ title }}</h2>
            <button type="button"
                    @click="reload"
                    className="btn-close nospin"
                    data-bs-dismiss="modal"
                    :aria-label="txt.close"></button>
          </div>
          <div id="modal-text" className="modal-body">
            <slot name="body"/>
          </div>
          <div v-if="buttonLabel != undefined" class="modal-footer">
            <button type="button" class="btn btn-primary">{{ buttonLabel }}</button>
          </div>
        </div>
      </div>
    </div>
  `
}