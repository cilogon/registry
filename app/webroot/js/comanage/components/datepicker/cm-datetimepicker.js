/**
 * COmanage Registry Date/Time Picker Component JavaScript
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
import CmTimePicker from './cm-timepicker.js';
export default {
  props: {
    id: String,
    target: String,
    date: String,
    timed: Boolean,
    ampm: Boolean,
    txt: Object
  },
  components: {
    CmTimePicker
  },
  data() {
    return {
      showingTimePicker: false
    }
  },
  methods: {
    setFieldDate(date) {
      const dateField = document.getElementById(this.target);
      if(dateField.value === '') {
        // There's no existing value, so just format what's given.
        dateField.value = date + " 00:00:00";
        // If using a Date object, use the following instead:
        // dateField.value = this.formatDate(date);  
      } else {
        // We need to apply the given date to our existing field value so we can retain the time.
        const dateTime = dateField.value.split(' ');
        dateTime[0] = date;
        // A sanity check to see if someone messed up the field.
        // XXX Pattern should also be enforced by the browser.
        if(dateTime[1] === undefined || dateTime[1] === '') {
          dateTime[1] = '00:00:00';
        }
        dateField.value = dateTime.join(' ');
      }
    },
    formatDate(date) {
      // Consider using Luxon for JavaScript Date/Time formatting if
      // we get any more complicated than this.
      let formattedDate = date.getFullYear();
      formattedDate += '-' + ('0' + (date.getMonth()+1)).slice(-2);
      formattedDate += '-' + ('0' + date.getDate()).slice(-2);
      formattedDate += ' ' + ('0' + date.getHours()).slice(-2);
      formattedDate += ':' + ('0' + date.getMinutes()).slice(-2);
      formattedDate += ':' + ('0' + date.getSeconds()).slice(-2);
      return(formattedDate);
    },
    showTimePicker() {
      // This is our toggle.
      this.showingTimePicker = !this.showingTimePicker;
    },
    hideTimePicker() {
      // This is to explicitly hide the picker.
      this.showingTimePicker = false;
    },
    setFieldTime(type,val) {
      const dateField = document.getElementById(this.target);
      let dateTime = [];
      if(dateField.value === '') {
        // Date field is empty - start with today
        dateTime = this.formatToday();
      } else {
        // We need to apply the given time to our existing field value so we can retain the date.
        dateTime = dateField.value.split(' ');
        // A sanity check to see if someone messed up the field.
        // XXX Pattern should also be enforced by the browser.
        if (dateTime[1] === undefined || dateTime[1] === '') {
          // Clear the field and start with today
          dateField.value = '';
          dateTime = this.formatToday();
        }
      }
      // Now process the time.
      let time = dateTime[1].split(':');
      if(type == 'hour') {
        time[0] = val;
      }
      if(type == 'minute') {
        time[1] = val;
      }
      dateTime[1] = time.join(':');
      dateField.value = dateTime.join(' ');
    },
    formatToday() {
      const today = new Date();
      // zero out the time so we can set it cleanly
      today.setHours(0);
      today.setMinutes(0);
      today.setSeconds(0);
      const formattedToday = this.formatDate(today);
      return formattedToday.split(' ');
    }
  },
  mounted() {
    // Listen for changes to the Duet datepicker child component
    const curPicker = document.querySelector('duet-date-picker[name=' + this.id + ']');
    const ref = this;
    curPicker.addEventListener("duetChange", function (e) {
      ref.setFieldDate(e.detail.value); // use valueAsDate to return an actual Date object
    });

    // Add event listener to strip out Duet's inputs on submit.
    // (We don't control Duet's datepicker directly, but we want to strip 
    //  out Duet's input fields prior to form submit to avoid conflicts with Cake. We only want 
    //  to use the widget part of Duet to set the value in our text input field, so in this case we'll 
    //  directly manipulate the DOM. If we change the way we're using the Duet datepicker 
    //  (i.e. if we use it to set an actual Registry field) we might instead integrate it 
    //  directly with Cake fields.)
    let dateWidgets = document.getElementsByTagName('duet-date-picker');
    let curForm = dateWidgets[0].closest('form');

    // We should distinguish between POST and GET requests.
    // POST: For the POST requests we want to strip out all the Vue Related fields but send all the other form fields
    // GET: For GET Requests empty fields are useless, So we need to strip them out as well.
    curForm.addEventListener('submit', e => {
      // For the GET request send using the default flow
      if (curForm.getAttribute('method') != 'get') {
        e.preventDefault();
      }
      let dateWidgetInputs = document.querySelectorAll('duet-date-picker input');
      // Remove all the Vue related fields
      Array.prototype.slice.call(dateWidgetInputs).forEach( (el) => {
        el.parentNode.removeChild(el);
      });
      // For the GET request send using the default flow
      if (curForm.getAttribute('method') != 'get') {
        curForm.submit();
      }
    });
  },
  template: `
    <div class="cm-datetime-picker">
      <duet-date-picker 
        :name="this.id" 
        :identifier="this.id"
        :value="this.date"    
        @duetChange="setFieldDate" 
        ref="curDuetPicker">
      </duet-date-picker>
      <div v-if="this.timed" class="cm-time-picker">
        <button @click.stop.prevent="showTimePicker" class="btn">
          <em class="material-icons">schedule</em>
        </button>
        <Transition>
          <cm-time-picker
            v-if="this.showingTimePicker"
            @set-time="setFieldTime"
            @hide="hideTimePicker"
            :target="this.target"
            :ampm="this.ampm"
            :txt="this.txt">
          </cm-time-picker>
        </Transition>  
      </div>
    </div>  
  `
}