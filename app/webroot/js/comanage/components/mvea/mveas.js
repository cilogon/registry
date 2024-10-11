/**
 * COmanage Registry MVEA Component JavaScript
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

import MveaItem from './mvea-item.js';
import {
  camelize
} from '../utils/helpers.js';

export default {
  props: {
    mveas: Object,
    core: Object,
    txt: Object
  },
  components: {
    MveaItem
  },
  data() {
    return {
      mveaTypeLookup: ''
    }
  },
  computed: {
    mveaModel: function() {
      return this.mveas?.[camelize(this.core.mveaType)]
    }
  },
  methods: {
    refresh() {
      this.$parent.refreshComponent();
    }
  },
  template: `
    <ul class="cm-mvea fields data-list">
      <mvea-item 
        :txt="this.txt"
        :core="this.core"
        v-for='mvea in mveaModel'
        :mvea="mvea">
      </mvea-item>
      <li v-show="this.mveaModel?.length < 1" class="field-data-container cm-mvea-no-attributes-msg">
        <div class="field-data">{{ this.txt['information.global.attributes.none'] }}</div>
      </li>
    </ul>
  `
}