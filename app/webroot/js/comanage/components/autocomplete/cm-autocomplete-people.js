/**
 * COmanage Registry PeoplePicker Component JavaScript
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

import { camelize } from '../utils/helpers.js';
import MiniLoader from "../common/mini-loader.js";
import ItemWithType from "./item-with-type.js";

export default {
  props: {
    options: Object,
    core: Object
  },
  inject: ['txt', 'api', 'app'],
  components: {
    AutoComplete : primevue.autocomplete,
    ItemWithType,
    MiniLoader
  },
  data() {
    return {
      people: [],
      rawData: [],
      person: '',
      identifierType: {},
      emailType: {},
      loading: false,
      page: 1,
      limit: 7,
      query: null
    }
  },
  methods: {
    async findPeople(query, reset = false) {
      if(reset) {
        this.page = 1;
      }
      this.loading = true;
      // Construct the url
      const urlString = window.location.protocol + "//" + window.location.host + this.api.searchPeople;
      const url = new URL(urlString);
      let queryParams = url.searchParams;
      // Query parameters
      queryParams.append('extended', 'PrimaryName,Identifiers,EmailAddresses')
      queryParams.append('identifier', query)
      queryParams.append('mail', query)
      queryParams.append('given', query)
      queryParams.append('family', query)
      if(this.api.viewConfigParameters.groupId != undefined) {
        queryParams.append('groupid', this.api.viewConfigParameters.groupId)
      }
      if(this.api.viewConfigParameters.action != undefined) {
        queryParams.append('action', this.api.viewConfigParameters.action)
      }
      // Pagination
      // XXX Move this to configuration
      queryParams.append('limit', this.limit)
      queryParams.append('page', this.page)
      // Even though this is the default I will add it here
      // XXX Move this to configuration
      queryParams.append('direction', 'desc')

      // AJAX Request
      const request = new Request(url, {
        headers: new Headers({
          'X-Requested-With': 'XMLHttpRequest',
          "Accept": "application/json",
        }),
        method: "GET"
      });

      const resp = await (fetch(request).catch(error => this.$parent.handleNetworkError()));

      if(!resp.ok) {
        this.$parent.handleError(resp)
        this.loading = false;
        return;
      }

      this.rawData = await resp.json();
      if(reset) {
        this.people = this.parseResponse(this.rawData)
        this.page = 1;
      } else {
        this.people = this.people.concat(this.parseResponse(this.rawData))
      }
      this.loading = false;
    },
    async fetchMorePeople() {
      this.page = this.page + 1;
      await this.findPeople(this.query, false)
    },
    async searchPeople(event) {
      if(event.query == undefined || event.query == "") {
        return []
      }

      this.query = event.query
      await this.findPeople(event.query, true)
    },
    constructEmailCsv(emailList) {
      const emailWithType = emailList.map( (mail) => {
          return mail.mail + " (" + this.app.types?.find((t) => t.id == mail.type_id)?.display_name + ")"
        }
      )

      return emailWithType.join(", ")
    },
    constructIdentifierCsv(identifierList) {
      const emailWithType = identifierList.map( (ident) => {
          return ident.identifier + " (" + this.app.types?.find((t) => t.id == ident.type_id)?.display_name + ")"
        }
      )

      return emailWithType.join(", ")
    },
    highlightedquery(str, query) {
      const pattern = new RegExp(query, "i")
      const matchingstr = str?.match(pattern)?.pop()
      if(matchingstr != undefined && matchingstr != "") {
        return str.replaceAll(matchingstr, '<span class="query-highlight">' + matchingstr + '</span>')
      }
      return str
    },
    parseResponse(data) {
      return data?.People?.map((item) => {
       return {
         "value": item.id,
         "label": `${item?.primary_name?.given} ${item?.primary_name?.family} (ID: ${item?.id})`,
         "email": item?.email_addresses,
         "emailPretty": this.shortenString(this.constructEmailCsv(item?.email_addresses)),
         "emailLabel": this.txt['email'] + ": ",
         "identifier": item?.identifiers,
         "identifierPretty": this.shortenString(this.constructIdentifierCsv(item?.identifiers)),
         "identifierLabel": this.txt['Identifiers'] + ": "
        }
      })
    },
    setPerson() {
      if(this.options.type == 'default') {
        this.options.inputProps.dataPersonid = this.person.value
      } else if(this.options.type == 'field') {
        // The picker is part of a standard form field
        const field = document.getElementById(this.options.fieldName);
        field.value = this.person.value;
      } else {
        // The picker is stand-alone, and should render the configured page in a modal on @item-select
        const urlForModal = this.options.actionUrl + '&person_id=' + this.person.value;
        let titleForModal = this.txt['registry.meta.registry'];
        if(this.api.viewConfigParameters.groupId != undefined) {
          titleForModal = this.txt['GroupMembers'];
        }
        launchCmModal(urlForModal, titleForModal);
      }
    },
    shortenString(str) {
      if(str.length > 30) {
        return str.substring(0,30) + '...'
      }
      return str
    }
  },
  mounted() {
    if(this.options.inputValue != undefined
       && this.options.inputValue != ''
       && this.options.htmlId == 'person_id') {
      this.options.inputProps.value = `${this.options.formParams?.fullName} (ID: ${this.options.inputValue})`
    }
  },
  computed: {
    hasMorePages: function() {
      if(this.rawData.responseMeta !== undefined) {
        let pageCount = this.rawData.responseMeta.pageCount;
        let currentPage = this.rawData.responseMeta.currentPage;
        this.page = currentPage;
        if (pageCount > 1 && pageCount !== currentPage) {
          return true;
        }
      }
      return false;
    },
    autoCompleteLabel: function() {
      // Check to see if a label has been passed in
      if(this.options.label !== undefined) {
        return this.options.label;
      }
      // Otherwise return the default
      return this.txt['autocomplete.people.label'];
    }
  },
  template: `
    <label class="mr-2" :for="this.options.htmlId">{{ this.autoCompleteLabel }}</label>
    <MiniLoader :isLoading="loading" classes="co-loading-mini-container d-inline ms-1"/>
    <AutoComplete 
      v-model="person"
      inputClass="cm-autocomplete"
      :inputId="this.options.htmlId"
      :inputProps="this.options.inputProps"
      :placeholder="this.txt['autocomplete.people.placeholder']"
      panelClass="cm-autocomplete-panel"
      optionLabel="label"
      :minLength="this.options.minLength"
      :delay="500"
      loadingIcon=null
      :suggestions="this.people" 
      forceSelection
      @complete="searchPeople"
      @item-select="setPerson">
      <template #option="slotProps">
        <div class="cm-ac-item">
          <div class="cm-ac-item-primary cm-ac-name">
            <span v-html="this.highlightedquery(slotProps.option.label, query)"></span></div>
          <div class="cm-ac-subitems">
            <div class="cm-ac-subitem cm-ac-email" v-if="slotProps.option.email">
              <span class="cm-ac-label" v-if="slotProps.option.emailLabel">{{ slotProps.option.emailLabel }}</span>
              <span class="cm-ac-value">
                <ItemWithType 
                  v-for="item in slotProps.option.email" 
                  :item="item"
                  kind="email"  
                  :query="query"
                  :highlightedquery="highlightedquery"
                />
              </span>
            </div>
            <div class="cm-ac-subitem cm-ac-id" v-if="slotProps.option.identifier">
              <span class="cm-ac-label" v-if="slotProps.option.identifierLabel">{{ slotProps.option.identifierLabel }}</span>
              <span class="cm-ac-value">
                <ItemWithType 
                  v-for="item in slotProps.option.identifier"
                  :query="query"
                  :item="item"
                  kind="identifier"
                  :highlightedquery="highlightedquery"
                />
              </span>
            </div>
          </div>
        </div>
      </template>
      <template #footer="slotProps" v-if="hasMorePages">
        <div class="cm-ac-pager">
          <a href="#" @click="this.fetchMorePeople()">{{ this.txt['autocomplete.pager.show.more'] }}</a>
        </div>
      </template>
    </AutoComplete>
  `
}