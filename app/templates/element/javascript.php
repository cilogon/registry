<?php
/**
 * COmanage Registry jQuery onload JavaScript
 * Applies jQuery widgets and flash messages
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
?>

<script>
  $(function() {
    // Focus any designated form element
    $('.focusFirst').focus();

    // DESKTOP MENU DRAWER BEHAVIOR
    // Check the drawer half-closed cookie on first load and set the drawer state appropriately
    if (Cookies.get("desktop-drawer-state") == "half-closed") {
      $("#navigation-drawer").addClass("half-closed");
      $("#main").addClass("drawer-half-closed");
    }

    // Hamburger menu-drawer toggle
    $('#co-hamburger').click(function () {
      if($(window).width() < 768) {
        // Mobile mode
        $("#navigation-drawer").removeClass("half-closed").toggle();
      } else {
        // Desktop mode
        if ($("#navigation-drawer").hasClass("half-closed")) {
          $("#navigation-drawer").removeClass("half-closed");
          $("#main").removeClass("drawer-half-closed");
          // set a cookie to hold drawer half-open state between requests
          Cookies.set("desktop-drawer-state", "open");
        } else {
          $("#navigation-drawer").addClass("half-closed");
          $("#main").addClass("drawer-half-closed");
          // set a cookie to hold drawer half-open state between requests
          Cookies.set("desktop-drawer-state", "half-closed");
        }
      }
    });

    // Catch the edge-case of browser resize causing menu-drawer
    // to remain hidden and vice versa.
    $(window).resize(function() {
      if($( window ).width() > 767) {
        $("#navigation-drawer").show();
      } else {
        $("#navigation-drawer").hide();
      }
    });

    // Desktop half-closed drawer behavior & expandable menu items
    $('#navigation-drawer a.menuTop').click(function () {
      if (Cookies.get("desktop-drawer-state") == "half-closed") {
        $("#navigation-drawer").toggleClass("half-closed");
      }
    });
    // END DESKTOP MENU DRAWER BEHAVIOR

    // USER MENU BEHAVIORS
    $("#global-search label").click(function () {
      $("#global-search-box").toggle();
    });

    // Accordion - XXX Deprecated?
    // $(".accordion").accordion();

    // Click outside behaviors
    // XXX Enable when / if needed (also enable popopvers below)
    /*$(document).on('click', function (e) {
      // Hide popovers on click outside but don't close current popover when interacting with content inside it
      $('#content [data-bs-toggle="popover"]').each(function () {
        if (!$(this).is(e.target) && $('.popover.show').has(e.target).length === 0) {
          $(this).popover('hide');
        }
      });
    });*/

    // TOP SEARCH FILTER FORM
    // Send only non-empty fields in the form
    $("#top-search-form").submit(function() {
      $("#top-search-form *").filter(':input').each(function () {
        if($(this).val() == '') {
          $(this).prop('disabled',true);
        }
      });
    });

    // Toggle the top search filter box
    $("#top-search-toggle, #top-search-toggle button.cm-toggle").click(function(e) {
      e.preventDefault();
      e.stopPropagation();
      if ($("#top-search-fields").is(":visible")) {
        $("#top-search-fields").hide();
        $("#top-search-toggle button.cm-toggle").attr("aria-expanded","false");
        $("#top-search-toggle button.cm-toggle .drop-arrow").text("arrow_drop_down");
      } else {
        $("#top-search-fields").show();
        $("#top-search-toggle button.cm-toggle").attr("aria-expanded","true");
        $("#top-search-toggle button.cm-toggle .drop-arrow").text("arrow_drop_up");
      }
    });

    // Clear a specific top search filter by clicking the filter button
    $("#top-search-toggle button.top-search-active-filter").click(function(e) {
      e.preventDefault();
      e.stopPropagation();
      $(this).hide();
      filterId = '#' + $(this).attr("aria-controls");
      $(filterId).val("");
      $(this).closest('form').submit();
    });

    // Clear all top filters from the filter bar
    $("#top-search-clear-all-button").click(function(e) {
      e.preventDefault();
      e.stopPropagation();
      $(this).hide();
      $("#top-search-toggle .top-search-active-filter").hide();
      $("#top-search-clear").click();
    });

    // Make all submit buttons pretty (Bootstrap)
    $("input:submit").addClass("spin submit-button btn btn-primary");

    // Make all select form controls Bootstrappy
    $("select").addClass("form-select");

    // Enable Bootstrap Popovers. Unless needed elsewhere, constrain this to #content
    // XXX Enable when/if needed
    // $('#content [data-bs-toggle="popover"]').popover();

    // Generic row click handling for div-based rows
    $('div.linked-row').click(function (e) {
      location.href = $(this).find('a.row-link').attr('href');
    });

    // Generic row click handling for table rows
    $('td.row-link').each(function (e) {
      url = $(this).find('a').attr('href');
      if(url != undefined && url != '') {
        $(this).closest('tr').addClass('linked-row').attr('data-cm-target',url).click(function (e) {
          location.href = $(this).attr('data-cm-target');
        }).find('a').on('click',function(e){
          // don't propagate on other links to avoid redirecting dialog boxes
          e.stopPropagation();
        });
      }
    });

    // Datepickers

    <?php /* For all calls to datepicker, wrap the calling date field in a
      container of class .modelbox-data: this allows us to show the datepicker next to
      the appropriate field because jQuery drops the div at the bottom of the body and
      that approach doesn't work well with Material Design Light (MDL). If you do not
      do this, the datepicker will float up to the top of the browser window. See
      app/View/CoGroupMembers for an example. */ ?>

    $(".datepicker-f").datepicker({
      changeMonth: true,
      changeYear: true,
      dateFormat: "yy-mm-dd 00:00:00",
      numberOfMonths: 1,
      showButtonPanel: false,
      showOtherMonths: true,
      selectOtherMonths: true,
      onSelect: function(selectedDate) {
        $(this).closest('.mdl-textfield').addClass('is-dirty');
      }
    }).bind('click',function () {
      $("#ui-datepicker-div").appendTo($(this).closest('.modelbox-data'));
    });

    $(".datepicker-u").datepicker({
      changeMonth: true,
      changeYear: true,
      dateFormat: "yy-mm-dd 23:59:59",
      numberOfMonths: 1,
      showButtonPanel: false,
      showOtherMonths: true,
      selectOtherMonths: true,
      onSelect: function(selectedDate) {
        $(this).closest('.mdl-textfield').addClass('is-dirty');
      }
    }).bind('click',function () {
      $("#ui-datepicker-div").appendTo($(this).closest('.modelbox-data'));
    });

    // Dialog
    // This generic dialog gets modified by the calling function
    $("#dialog").dialog({
      autoOpen: false,
      resizable: false,
      modal: true,
      buttons: {
        '<?php print __d('operation', 'cancel'); ?>': function() {
          $(this).dialog('close');
        },
        '<?php print __d('operation', 'ok'); ?>': function() {
          $(this).dialog('close');
        }
      }
    });

    // Add loading animation when a form is submitted, when any item with a "spin" class is clicked,
    // or on any button or anchor tag lacking the .nospin class.
    $("input[type='submit'], button:not(.nospin), a:not(.nospin), .spin").click(function() {

      displaySpinner();

      // Test for invalid fields (HTML5) and turn off spinner explicitly if found
      if(document.querySelectorAll(":invalid").length) {
        stopSpinner();
      }

    });

    // Flash Messages
    <?php
      print $this->Flash->render('error');
      print $this->Flash->render('success');
      print $this->Flash->render('information');
    ?>
  });

  // Define default text for confirm dialog
  var defaultConfirmOk = "<?php print __d('operation', 'ok'); ?>";
  var defaultConfirmCancel = "<?php print __d('operation', 'cancel'); ?>";
  var defaultConfirmTitle = "<?php print __d('operation', 'confirm'); ?>";

</script>
