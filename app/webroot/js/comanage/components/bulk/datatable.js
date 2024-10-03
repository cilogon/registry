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

export default {
  data() {
    return {
      body: [],
      columns: [
        {'data': 'record'},
        {'data': 'status'}
      ]
    }
  },
  props: {
    raw: {
      type: Array
    },
    jsn: {
      type: Array
    },
    labels: {
      type: Object
    },
    action: {
      type: String,
      default: 'Action'
    }
  },
  methods: {
    parseRawData() {
      return this.raw.map((resp, idx) => {
        let url = new URL(resp.url)
        let path = url.pathname
        let id = path.split('/').pop()
        let info = `<span arial-label="status=true" class="material-icons success" aria-hidden="true">check_circle</span> ${this.action} ${resp.statusText} (Code: ${resp.status})`
        if(!resp.ok) {
          info = `<p><span aria-label="status=false" class="material-icons danger" aria-hidden="true">report_problem</span> ${this.action} ${resp.statusText} (Code: ${resp.status})</p>`
          info += `<p>${this.jsn[idx].message}</p>`
          info += `<p>${this.jsn[idx].url}</p>`
        }
        return {
          'record': this.labels.filter((idx, lbl) => lbl.id == parseInt(id))[0].label,
          'status': info
        }
      })
    }
  },
  mounted() {
    this.body = this.parseRawData()
    let parsed = JSON.parse(JSON.stringify(this.body))
    $("#error-datatable").DataTable({
      data: parsed,
      columns: this.columns,
      paging: false,
      scrollCollapse: true,
      scrollY: '250px',
    });
  },
  inject: ['txt'],
  components: {
    DataTable
  },
  template: `
  <h3 class="data-table-modal-header">{{ this.txt['information.report.for'] }} {{ action }}</h3>
  <table id="error-datatable" class="display" style="width: 100%">
    <thead>
      <tr>
        <th>{{ this.txt['information.record'] }}</th>
        <th>{{ this.txt['field.status'] }}</th>
      </tr>
    </thead>
  </table>
  `
}