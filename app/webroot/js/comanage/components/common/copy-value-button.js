/**
 * COmanage Registry Copy Value Component JavaScript
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

import {
  constructLanguageString
} from '../utils/helpers.js';

export default {
  props: {
    valueToCopy: String,
    txt: Object
  },
  data() {
    return {
      copyIcon: "content_copy",
      ariaLabel: this.txt['operation.copy.value']
    }
  },
  methods: {
    async copyValue(val) {
      try {
        // remove extra white spaces and trim the value
        let valWithNormalizedSpaces = val.replace(/\s+/g, ' ').trim();
        // copy to clipboard
        await navigator.clipboard.writeText(valWithNormalizedSpaces);
        // provide feedback
        this.copyIcon = 'thumb_up';
        this.ariaLabel = this.txt['information.value.copied'];
        // reset feedback
        setTimeout(() => this.copyIcon = 'content_copy', 800);
        setTimeout(() => this.ariaLabel = this.txt['operation.copy.value'], 2200);
      } catch($e) {
        // this will be rendered if browser is not on HTTPS
        let msg = this.txt['error.javascript.copy'];
        if(window.location.protocol != 'https:') {
          msg += " " + this.txt['error.javascript.requires.https'];
        }
        alert(msg + "\n\n" + $e);
      }
    }
  },
  template: `
    <button 
      type="button" 
      class="cm-copy-value-button cm-hover-button cm-row-button btn btn-sm btn-default" 
      :aria-label="this.ariaLabel" 
      @click.stop.prevent="copyValue(this.valueToCopy)">
      <span class="material-icons-outlined">{{ this.copyIcon }}</span>
      <span class="cm-copy-value-text">{{ this.txt['operation.copy'] }}</span>
    </button>
  `
}
