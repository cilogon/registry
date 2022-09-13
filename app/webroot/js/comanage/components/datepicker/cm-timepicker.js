/**
 * COmanage Registry Time Picker Component JavaScript
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
    target: String,
    ampm: Boolean,
    txt: Object
  },
  data() {
    return {
      minuteVals: ['00','15','30','45']
    }
  },
  methods: {
    setTime(e,type) {
      let timeValue = e.currentTarget.querySelector('.cm-time-picker-val').innerText;
      this.$emit('setTime', type, timeValue);
    },
    hide() {
      this.$emit('hide');
    }
  },
  mounted() {
    this.$refs.timepicker.focus();
  },
  template: `
    <div class="cm-time-picker-panel" role="dialog" ref="timepicker" v-clickout="hide" @keydown.esc="hide" tabindex="-1">
      <div class="cm-time-picker-hours">
        <div class="cm-time-picker-title">{{ txt.hour }}</div>
        <div class="cm-time-picker-vals">
          <ul>
            <li v-for="n in 24">              
              <button @click.stop.prevent="setTime($event,'hour')" type="button" class="btn">
                <span class="cm-time-picker-val" aria-hidden="true">{{ ('0' + (n-1)).slice(-2) }}</span>
                <span class="visually-hidden">{{ this.txt.hour }} {{ n-1 }}</span>
              </button>
            </li>  
          </ul>
        </div>
      </div>
      <div class="cm-time-picker-colon">:</div>
      <div class="cm-time-picker-minutes">
        <div class="cm-time-picker-title">{{ txt.minute }}</div>
        <div class="cm-time-picker-vals">
          <ul>
            <li v-for="val of minuteVals">
              <button @click.stop.prevent="setTime($event,'minute')" type="button"  class="btn">
                <span class="cm-time-picker-val" aria-hidden="true">{{ val }}</span>
                <span class="visually-hidden">{{ this.txt.minute }} {{ val }}</span>
              </button>
            </li>
          </ul>
        </div>
      </div>
      <div v-if="this.ampm" class="cm-time-picker-ampm">
        <ul><li>{{ txt.am }}</li><li>{{ txt.pm }}</li></ul>
      </div>
    </div>
  `
}