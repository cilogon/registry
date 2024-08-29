/**
 * COmanage Registry Item with Type Vue.js Component
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
    item: Object,
    kind: '',
    query: '',
    highlightedquery: Object,
    isMember: false
  },
  inject: ['app'],
  computed: {
    val: function() {
      if(this.kind == 'email') {
        return this.item.mail
      }
      if(this.kind == 'identifier') {
        return this.item.identifier
      }
      return ''
    },
    type: function() {
      return this.app.types?.find((t) => t.id == this.item.type_id)?.display_name
    },
    itemClasses: function() {
      if(this.app.cosettings[0]?.person_picker_display_types) {
        return "item-with-type item-type-" + this.item.type_id;
      }
      return "item-type-" + this.item.type_id;
    }
  },
  template: `
    <div :class="itemClasses">
      <span class="value">
        <span v-if="this.isMember" v-html="this.val"></span>
        <span v-else v-html="highlightedquery(this.val, query)"></span>
      </span>
      <span v-if="app.cosettings[0].person_picker_display_types" class="type">
       {{ this.type }}
      </span>
    </div>
  `
}