/**
 * COmanage Registry Default JavaScript
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

// On page load, call any defined initialization functions.
// Make sure function is defined before calling.
function jsOnLoadCallHooks() {
  if(window.jsLocalOnLoad) {
    jsLocalOnLoad();
  }
}

// On form submit, call any defined functions.
// Make sure function is defined before calling.
function jsOnSubmitCallHooks() {
  if(window.jsLocalOnSubmit) {
    jsLocalOnSubmit();
  }
}

// Generate a loading animation by revealing a persistent hidden div with CSS animation.
// An element's onclick action will trigger this to appear if it has the class "spin" class on an element.
// (See Template/Elements/javascript.ctp)
// For dynamically created elements, we can generate the loading animation by calling this function.

// show loading animation
function displaySpinner() {
  $("#co-loading").show();
}

// stop a spinner explicitly
function stopSpinner() {
  $("#co-loading").hide();
}

// Show fields in .form-list output.
// fields          - array of field IDs
// isPageLoad      - boolean, true for first page load
function showFields(fields, isPageLoad) {
  for(const field of fields) {
    if(isPageLoad !== undefined && isPageLoad) {
      $('#' + field).closest('li').addClass('collapse show');
    } else {
      $('#' + field).closest('li').collapse('show');
    }
  }
}

// Hide fields in .form-list output.
// fields          - array of field IDs
// isPageLoad      - boolean, true for first page load
function hideFields(fields, isPageLoad) {
  for(const field of fields) {
    if(isPageLoad !== undefined && isPageLoad) {
      $('#' + field).closest('li').addClass('collapse');
    } else {
      $('#' + field).closest('li').collapse('hide');
    }
  }
}

// Returns an i18n string with tokens replaced.
// For use in JavaScript dialogs.
// text          - body text for the array with tokens {0}, {1}, etc
// replacements  - Array of strings to replace tokens
function replaceTokens(text,replacements) {
  var processedString = text;
  for (var i = 0; i < replacements.length; i++) {
    processedString = processedString.replace("{"+i+"}", replacements[i]);
  }
  return processedString;
}

// Generate a dialog box confirming <txt>.  If a clickId is supplied, on confirmation, click a DOM element referenced by <clickId>.
// The clickId DOM element is intended to be a CakePHP postButton or postLink (which may be visually hidden).
// txt                - body text              (string, required)
// confirmUrl         - url for confirm button (string, required)
// clickId            - id of DOM element      (string, required) 
// confirmbtxt        - confirm button text    (string, optional)
// cancelbtxt         - cancel button text     (string, optional)
// titletxt           - dialog title text      (string, optional)
// tokenReplacements  - strings to replace tokens in dialog body text (array, optional)
function jsConfirmGeneric(txt, confirmUrl, clickId, confirmbtxt, cancelbtxt, titletxt, tokenReplacements) {

  var bodyText = txt;
  var confUrl = confirmUrl;
  var clickId = (clickId != '' ? clickId : undefined);
  var confbutton = confirmbtxt;
  var cxlbutton = cancelbtxt;
  var title = titletxt;
  var replacements = tokenReplacements;

  // Perform token replacements on the body text if they exist
  if (replacements != undefined) {
    bodyText = replaceTokens(bodyText,replacements);
  }

  // Set defaults for confirm, cancel, and title
  // Values for the default variables are set globally
  if(confbutton == undefined) {
    confbutton = defaultConfirmOk;
  }
  if(cxlbutton == undefined) {
    cxlbutton = defaultConfirmCancel;
  }
  if(title == undefined) {
    title = defaultConfirmTitle;
  }

  // Set the title of the dialog
  $("#dialog-title").text(title);

  // Set the body text of the dialog
  $("#dialog-text").text(bodyText);
  
  $("#dialog-confirm-button").click(function() {
    if(clickId !== undefined) {
      // If we have a clickId, set the dialog confirmation button to click the DOM
      // element referenced by it.
      $("#" + clickId).click();
    } else {
      // Otherwise just redirect to the confirmation URL
      location.href = confirmUrl;
    }
  });

  // Override the button texts if set
  if(confbutton != '') {
    $("#dialog-confirm-button").text(confbutton);
  }
  if(cxlbutton != '') {
    $("#dialog-cancel-button").text(cxlbutton);
  }

  // Open the dialog
  $("#dialog").modal('show');
}

/**
 * COmanage Registry Modal Launcher: general function for launching a page in our generic modal window.
 * This function uses the same persistent dialog box as the jsConfirmGeneric() function.
 * @param url              {string}  Url contained by the modal iframe
 * @param title            {string}  Title for the modal window
 * @param reloadOnClose   {boolean} [True to reload the browser after closing the modal]
 */
function launchCmModal(
  url,
  title,
  reloadOnClose = true
) {
  // Set the title text of the dialog
  $("#cm-modal-title").html(title);
  
  // Set the body text of the dialog
  $("#cm-modal-text").html('<iframe src="' + url + '"></iframe>');
  
  // Set the data-reload-on-close parameter that will be observed when the modal is closed
  $("#cm-modal").attr('data-reload-on-close', reloadOnClose);
  
  // Open the modal window
  window.cmModal = new bootstrap.Modal(document.getElementById('cm-modal'));
  window.cmModal.show();
}

/**
 * COmanage Registry Modal Hider: 
 * function for hiding our modal windows from within a contained iframe.
 */
function hideCmModal() {
  $("#cm-modal,#mvea-modal").modal('hide');
}

// Generic goto page form handling for multi-page listings.
// We handle this in javascript to avoid special casing controllers.
// pageNumber         - page number         (int, required)
// maxPage            - largest page number allowed (int, required)
// intErrMsg          - error message for entering a non-integer value (string, required)
// maxErrMsg          - error message for entering a page number greater than last page (string, required)
// paginationUrl      - pagination URL used to build request  (string, required)
function gotoPage(pageNumber,maxPage,intErrMsg,maxErrMsg,paginationUrl) {
  // Just return if no value
  if (pageNumber == "") {
    stopSpinner();
    return false;
  }

  var pageNum = parseInt(pageNumber,10);
  var max = parseInt(maxPage,10);

  // Not an integer?  Return an error message.
  if (isNaN(pageNum)) {
    stopSpinner();
    alert(intErrMsg);
    return false;
  }

  // Page number too large? Return an error message.
  if(pageNum > max) {
    stopSpinner();
    alert(maxErrMsg);
    return false;
  }

  // Page number < 1? Set the page = 1.
  if(pageNum < 1) {
    pageNum = 1;
  }

  // Build pagination URL (this assumes the URL will always contain an existing ? in the path)
  var url = paginationUrl.replace(new RegExp('&page=[0-9]*', 'g'), '')+'&page=' + pageNum;

  // Redirect to the new page:
  window.location = url
}

// Generic limit page form handling for setting the page size (records shown on a page).
// We handle this in javascript to avoid special casing controllers.
// pageLimit         - page limit                            (int, required)
// recordCount       - total number of records returned      (int, requried)
// currentPage       - current page number                   (int, required)
function limitPage(pageLimit,recordCount,currentPage) {
  var limit = parseInt(pageLimit,10);
  var count = parseInt(recordCount,10);
  var page = parseInt(currentPage,10);

  // Just cancel this if we have bad inputs
  if (isNaN(limit) || isNaN(count) || isNaN(page)) {
    stopSpinner();
    return false;
  }

  // Add the new limit parameter to the url
  var currentUrl =  window.location.pathname;
  currentUrl = currentUrl.replace(new RegExp('\/limit:[0-9]*', 'g'), '')+'/limit:' + limit;

  // Test to see if the new limit allows the current page to exist
  if (count / page >= limit) {
    // Current page can exist - keep the current page number
    currentUrl = currentUrl.replace(new RegExp('\/page:[0-9]*', 'g'), '')+'/page:' + page;
  } else {
    // Force the url back to page one because the new page size cannot include our current page number
    currentUrl = currentUrl.replace(new RegExp('\/page:[0-9]*', 'g'), '')+'/page:1';
  }

  // Redirect to the new page:
  window.location = currentUrl;
}

// Clear the top search form for index views
// formObj         - form object (DOM form obj, required)
function clearTopSearch(formObj) {
  for (let i=0; i<formObj.elements.length; i++) {
    if(formObj.elements[i].type != 'hidden') {
      formObj.elements[i].disabled = true;
    }
  }
  formObj.submit();
}

/**
 * COmanage Registry API AJAX Calls: general function for making an ajax call to Registry API v.2
 * @param url              {string} API Url
 * @param method           {string} HTTP Method (GET, POST, PUT, DELETE)
 * @param dataType         {string} Data type (json, html)
 * @param data             {Object} [POST or PUT data in JSON]
 * @param successCallback  {string} [Name of the callback function for success]
 * @param entityId         {string} [ID used to identify an entity in the DOM]
 * @param failureCallback  {string} [Name of the callback function for failure]
 * @param alwaysCallback   {string} [Name of the callback function for always]
 */
function callRegistryAPI(
  url, 
  method, 
  dataType,  
  successCallback= undefined, 
  failureCallback= undefined,
  data = undefined,
  alwaysCallback = undefined,
  entityId= undefined
) {
  var apiUrl = url;
  var httpMethod = method;
  var dataType = dataType;
  var successCallback = successCallback;
  var failureCallback = failureCallback;
  var data = data;
  var alwaysCallback = alwaysCallback;
  var entityId = entityId;
  
  if(data === undefined) {
    data = '';
  }

  if(entityId === undefined) {
    entityId = '';
  }

  var xhr = $.ajax({
    url: apiUrl,
    method: httpMethod,
    dataType: dataType,
    data: data,
    encode: true
  })
    .done(function() {
      if(successCallback != undefined) {
        successCallback(xhr, entityId);
      } else {
        return xhr;
      }
    })
    .fail(function() {
      if(failureCallback != undefined) {
        failureCallback(xhr, entityId);
      } else {
        return xhr;
      }
    })
    .always(function() {
      if(alwaysCallback != undefined) {
        alwaysCallback(xhr, entityId);
      } else {
        return xhr;
      }
    });
}
