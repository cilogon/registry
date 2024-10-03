/**
 * COmanage Registry Bulk Actions Vue.js Component
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

import Alert from '../common/alert.js'
import Modal from '../common/modal.js'
import Datatable from './datatable.js';
import {capitalize} from "../utils/helpers.js"

export default {
  data() {
    return {
      loading: false,
      num: 0,
      progress: 0,
      ids: [],
      info: {},
      selected: '',
      failed: 0,
      succeeded: 0,
      responses: [],
      responses_json: []
    }
  },
  components: {
    Alert,
    Modal,
    Datatable
  },
  props: {
    label: {
      type: String,
      default: "Apply"
    },
    legend: {
      type: String,
      default: "Not provided"
    },
    selectprompt: {
      type: String,
      default: "Please select..."
    },
    bulkactions: {
      type: Array,
      default: []
    }
  },
  inject: ['txt', 'api', 'app'],
  mounted: function() {
    this.reload()
  },
  methods: {
    getActionDescription(action) {
      return JSON.parse(this.app.bulkactionfull)[action]
    },
    reload() {
      // Reload the view to fetch the latest changes
      if($('#bulk-actions-modal').length) {
        $('#bulk-actions-modal').on('hidden.bs.modal', function() {
          location.reload()
        })
      }
    },
    capitalize,
    async allProgress(fetches, progress_cb) {
      let d = 0;
      progress_cb(0);
      for (const p of fetches) {
        p.then(()=> {
          d ++;
          progress_cb( (d * 100) / fetches.length );
        });
      }
      return Promise.all(fetches)
    },
    async run() {
      this.loading = true;
      // Construct the url
      const urlString = window.location.protocol
        + "//" + window.location.host
        + this.api.webroot
        // We have to send the request to the ajax end point
        + 'api/ajax/v2/'
        + this.app.controller

      this.allProgress(
        this.ids.map((idx, id) => {
          let finalUrl = urlString + "/" + id
          let method = JSON.parse(this.app.bulkactionmethods)[this.selected]

          let request_init = {
            headers: new Headers({
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json',
              'X-CSRF-Token': this.api.token
            }),
            method: method.toUpperCase()
          }

          // AJAX Request
          let request = new Request(finalUrl, request_init);
          return fetch(request)
        }),
        (prog) => this.progress = prog
      ).then((responses) => {
        Promise.all(responses.map(resp => resp.json().catch(err => {
          console.error(`${err} while parsing response`)
          return {}
        }))).then(dataList => {
            this.responses_json = dataList
            return dataList
          }).then(dataList => {
            this.responses = responses
            this.failed = responses.filter((resp) => !resp.ok).length
            this.succeeded = responses.filter((resp) => resp.ok).length
            this.loading = false
        })
      }).catch((error) => {
        this.loading = false
        console.error(error.message);
      });
    },
    rowChecked() {
      let checked = $('.bulk-action-checkbox-container input.form-check-input[type=checkbox]:checked');
      this.ids = checked.map((idx, item) => item.getAttribute('data-entity-id'))
      this.info = checked.map((idx, item) => {
        let row = JSON.parse(item.getAttribute('data-entity'))
        if(row?.name) {
          return {id: row.id, label: row?.name}
        } else if(row?.person?.primary_name?.full_name) {
          return {id: row.id, label: row?.person?.primary_name?.full_name}
        } else if(row?.description) {
          return {id: row.id, label: row?.description}
        }
      })
      this.num = this.ids.length;
    }
  },
  computed: {
    roundedProgress() {
      return Math.trunc(this.progress)
    },
    calculateStyle() {
      return "width: " + this.roundedProgress + "%;"
    },
    disable() {
      return this.ids.length == 0 || this.selected == undefined || this.selected == ''
    },
    failedMessage() {
      return `${this.failed} ${this.txt['result.failed']}`
    },
    succeededMessage() {
      if(this.selected == 'delete') {
        console.log(JSON.parse(JSON.stringify(this.txt)));
        return `${this.succeeded} ${this.txt['result.removed']}`
      }
      return `${this.succeeded} ${this.txt['result.updated']}`
    }
  },
  template: `
    <legend>{{ this.legend }}</legend>
    <select id="bulk-action-select" v-model="selected">
      <template v-if="bulkactions.length > 0">
        <option value="" disabled selected>{{ selectprompt }}</option>
        <option v-for="bulkaction in bulkactions" :value="bulkaction">{{ getActionDescription(bulkaction) }}</option>
      </template>
    </select>
    <button v-if="this.loading == false"
            class="btn btn-primary btn-sm"
            :disabled="disable"
            @click="run"
            data-bs-toggle="modal"
            data-bs-target="#bulk-actions-modal"
            v-clickout="rowChecked">
      {{ this.label }}
      <span class='tab-count'>
        <span class='tab-count-item'>{{ this.num }}</span>
      </span>
    </button>
    <Modal id="bulk-actions"
           :title="capitalize(this.selected) + ' in Progress'">
      <template v-slot:body="body">
          <div class="progress">
            <div class="progress-bar"
                 role="progressbar"
                 :style="calculateStyle"
                 :aria-valuenow="this.roundedProgress"
                 aria-valuemin="0"
                 aria-valuemax="100">{{ this.roundedProgress }}%</div>
          </div>
          <Alert v-if="this.succeeded > 0"
                 :succeed="this.succeeded"
                 :message="succeededMessage"
                 :action="this.selected"/>
          <Alert v-if="this.failed > 0"
                 :failed="this.failed"
                 :message="failedMessage"
                 :action="this.selected"/>
          <Datatable v-if="this.responses.length > 0 && this.responses_json.length > 0"
                     :labels="this.info"
                     :action="capitalize(this.selected)"
                     :jsn="this.responses_json"
                     :raw="this.responses"/>
      </template>
    </Modal>
  `
}