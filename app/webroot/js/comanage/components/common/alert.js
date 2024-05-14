/**
 * COmanage Registry Alert Vue.js Component
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

import {capitalize} from "../utils/helpers.js";

export default {
  data() {
    return {
      msg: null
    }
  },
  props: {
    message: {
      type: String
    },
    failed: {
      type: Number
    },
    succeed: {
      type: Number
    },
    action: {
      type: String
    },
    title: {
      type: String,
      default: null
    },
    dismissable: {
      type: Boolean,
      default: false
    }
  },
  computed: {
    getClass() {
      let state = 'success'
      if(this.hasFailed) {
        state = 'danger'
      }

      return "w-100 alert co-alert alert-" + state
    },
    titleExists() {
      return this.title != undefined && this.title != ''
    },
    hasSucceeded() {
      return this.succeed > 0
    },
    hasFailed() {
      return this.failed > 0
    },
    getMessage() {
      if(this.message != undefined && this.message != '') {
        return this.message
      } else if(this.hasFailed) {
        return capitalize(this.action) + ' Failed (#' + this.failed + ')'
      } else if(this.hasSucceeded) {
        return capitalize(this.action) + ' Succeeded (#' + this.succeed + ')'
      }
    },
    display() {
      return this.hasSucceeded || this.hasFailed
    }
  },
  template: `
    <div v-if="display" :class="getClass" role="alert">
      <div class="alert-body d-flex align-items-center justify-content-between">
        <span class="alert-content d-flex align-items-center">
          <span class="alert-title d-flex align-items-center">
            <span class="material-icons-outlined alert-icon">report_problem</span>
            <span v-if="titleExists" class="alert-title-text"> {{ this.title }}</span>
          </span>
          <span class="alert-message">{{ this.getMessage }}</span>
        </span>
        <span class="alert-button" v-if="this.dismissable">
           <button type="button" class="btn-close nospin" data-bs-dismiss="alert" aria-label="Close"></button>
        </span>
      </div>
    </div>
  `
}