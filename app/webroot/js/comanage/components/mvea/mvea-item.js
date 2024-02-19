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

import {
  constructLanguageString
} from '../utils/helpers.js';

export default {
  props: {
    mvea: Object,
    core: Object,
    txt: Object
  },
  computed: {
    mveaLink: function() {
      return this.core.webroot + this.core.mveaController + (this.core.action == 'edit' ? '/edit/' : '/view/') + this.mvea.id;
    }
  },
  methods: {
    calcLangHR(lang) {
      return constructLanguageString(lang)
    },
    followRowLink() {
      //location.href = this.mveaLink;
      this.$nextTick(() => {
        var componentReference = 'mvea' + this.core.mveaType;
        this.$parent.$parent.launchModal(this.core.mveaTitle, this.mveaLink, componentReference);
      });
    }
  },
  mounted() {
    this.$parent.$parent.isLoading = false;
  },
  template: `
    <!-- Names -->
    <li class="field-data-container linked-row" v-if="this.core.mveaType == 'names'" @click="followRowLink">
      <div class="field-data force-wrap">
        <a :href="mveaLink" class="row-link" @click.prevent>
          <!-- If there is a display name use it. Otherwise, check language and produce right-to-left 
            order or left-to-right order. This approach is similar to Model/Entity/Name.php::_getFullName(). 
            XXX We expect to get the full_name out of the API, so remove this logic when we have the full name. 
            We don't need to have the classes that distinguish what "kind" of name it is (e.g. "mvea-name-rtl"): 
            the name alone should be enough. -->
          <span v-if="this.mvea.display_name" class="mvea-name-displayname">
            {{ this.mvea.display_name }}
          </span>
          <span v-else-if="['hu', 'ja', 'ko', 'za-Hans', 'za-Hant'].indexOf(this.mvea.language) != -1" class="mvea-name-rtl">
            {{ this.mvea.family }} {{ this.mvea.given }}
          </span>
          <span v-else class="mvea-name-ltr">
            {{ this.mvea.honorific }} {{ this.mvea.given }} {{ this.mvea.middle }} {{ this.mvea.family }} {{ this.mvea.suffix }}
          </span>
        </a>                                                
      </div>
      <div class="field-data data-label">
        <span v-if="this.mvea.primary_name" class="mr-1 badge bg-outline-secondary primary">{{ this.txt.primary }}</span>
        <span class="mr-1 badge bg-light">{{ this.mvea.type.display_name }}</span>
        <span v-if="this.mvea.language" class="mr-1 badge bg-light">{{ calcLangHR(this.mvea.language) }}</span>
      </div>
    </li>
    <!-- Email Addresses -->
    <li class="field-data-container linked-row" v-if="this.core.mveaType == 'email_addresses'" @click="followRowLink">
      <div class="field-data force-wrap">
        <a :href="mveaLink" class="row-link" @click.prevent>{{ this.mvea.mail }}</a>                                              
      </div>
      <div class="field-data data-label">
        <span v-if="!(this.mvea.verified)" class="mr-1 badge bg-warning unverified">{{ this.txt.unverified }}</span>
        <span class="mr-1 badge bg-light">{{ this.mvea.type.display_name }}</span>
      </div>
    </li>
    <!-- Identifiers -->
    <li class="field-data-container linked-row" v-if="this.core.mveaType == 'identifiers'" @click="followRowLink">
      <div class="field-data force-wrap">
        <a :href="mveaLink" class="row-link" @click.prevent>{{ this.mvea.identifier }}</a>                                              
      </div>
      <div class="field-data data-label">
        <span v-if="this.mvea.status == 'S'" class="mr-1 badge bg-danger">{{ this.txt["SuspendableStatusEnum.S"] }}</span>
        <span v-if="this.mvea.login" class="mr-1 badge bg-outline-secondary login">{{ this.txt.login }}</span>
        <span class="mr-1 badge bg-light">{{ this.mvea.type.display_name }}</span>
      </div>
    </li>
    <!-- Ad Hoc Attributes -->
    <li class="field-data-container linked-row" v-if="this.core.mveaType == 'ad_hoc_attributes'" @click="followRowLink">
      <div class="field-data force-wrap">
        <a :href="mveaLink" class="row-link" @click.prevent>{{ this.mvea.value != '' ? this.mvea.value : this.txt["global.value.none"] }}</a>
      </div>
      <div v-if="this.mvea.tag != ''" class="field-data data-label">
        <span class="mr-1 badge bg-light ad-hoc">{{ this.mvea.tag }}</span>
      </div>
    </li>
    <!-- Addresses -->
    <li class="field-data-container linked-row" v-if="this.core.mveaType == 'addresses'" @click="followRowLink">
      <div class="field-data force-wrap">
        <address>
          <a :href="mveaLink" class="row-link" @click.prevent>{{ this.mvea.room }} {{ this.mvea.street }}</a>
          <span v-if="this.mvea.locality != '' || this.mvea.state != ''" class="addr-locality-state">
            <br>{{ this.mvea.locality }}{{ this.mvea.locality != '' && this.mvea.state != '' ? ', ' : ''}}{{ this.mvea.state }}
          </span>
          <span v-if="this.mvea.postal_code != '' || this.mvea.country != ''" class="addr-postalcode-country">
            <br>{{ this.mvea.postal_code }} {{ this.mvea.country }}
          </span>
        </address>                                                
      </div>
      <div class="field-data data-label">
        <span class="mr-1 badge bg-light">{{ this.mvea.type.display_name }}</span>
      </div>
    </li>
    <!-- Telephone Numbers -->
    <li class="field-data-container linked-row" v-if="this.core.mveaType == 'telephone_numbers'" @click="followRowLink">
      <div class="field-data force-wrap">
        <a :href="mveaLink" class="row-link" @click.prevent>{{ this.mvea.country_code }} {{ this.mvea.area_code }} {{ this.mvea.number }}</a>                                              
      </div>
      <div class="field-data data-label">
        <span class="mr-1 badge bg-light">{{ this.mvea.type.display_name }}</span>
      </div>
    </li>
    <!-- Urls -->
    <li class="field-data-container linked-row" v-if="this.core.mveaType == 'urls'" @click="followRowLink">
      <div class="field-data force-wrap">
        <a :href="mveaLink" class="row-link" @click.prevent>{{ this.mvea.description != '' && this.mvea.description != null ? this.mvea.description : this.mvea.url }}</a>   
        <a :href="this.mvea.url" class="canvas-url-link" :title="this.txt['global.visit.link']"><span class="material-icons">north_east</span></a>
      </div>
      <div class="field-data data-label">
        <span class="mr-1 badge bg-light">{{ this.mvea.type.display_name }}</span>
      </div>
    </li>
    <!-- Pronouns -->
    <li class="field-data-container linked-row" v-if="this.core.mveaType == 'pronouns'" @click="followRowLink">
      <div class="field-data force-wrap">
        <a :href="mveaLink" class="row-link" @click.prevent>{{ this.mvea.type_id }}</a>                                              
      </div>
      <div class="field-data data-label">
        <span class="mr-1 badge bg-light">{{ this.mvea.type.display_name }}</span>
      </div>
    </li>
  `
}
