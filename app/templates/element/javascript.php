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
    $("#top-filters-form").submit(function() {
      $("#top-filters-form *").filter(':input').each(function () {
        if($(this).val() == '') {
          $(this).prop('disabled',true);
        }
      });
    });

    // Toggle the top search filter box
    $("#top-filters-toggle, #top-filters-toggle button.cm-toggle").click(function(e) {
      e.preventDefault();
      e.stopPropagation();
      if ($("#top-filters-fields").is(":visible")) {
        $("#top-filters-fields").hide();
        $("#top-filters-toggle button.cm-toggle").attr("aria-expanded","false");
        $("#top-filters-toggle button.cm-toggle .drop-arrow").text("arrow_drop_down");
      } else {
        $("#top-filters-fields").show();
        $("#top-filters-toggle button.cm-toggle").attr("aria-expanded","true");
        $("#top-filters-toggle button.cm-toggle .drop-arrow").text("arrow_drop_up");
      }
    });

    // Clear a specific top search filter by clicking the filter button
    $("#top-filters-toggle button.top-filters-active-filter").click(function(e) {
      e.preventDefault();
      e.stopPropagation();
      $(this).hide();
      $(this)[0].dataset.identifier.split(':').forEach( (ident) => {
        // CAKEPHP transforms snake case variables to kebab when the name has the format of a foreign key.
        // As a result searching for the initial key will fail. This is used for use cases
        // like the models that use the Tree behavior and have the column parent_id
        let ident_to_snake = ident.replace(/_/g, "-");
        let filterId = '#' + ident_to_snake;
        $(filterId).val("");
      });

      // Remove the Vue date fields if exist
      let dateWidgetInputs = document.querySelectorAll('duet-date-picker input');
      // Remove all the Vue related fields
      Array.prototype.slice.call(dateWidgetInputs).forEach( (el) => {
        el.parentNode.removeChild(el);
      });

      $(this).closest('form').submit();
    });

    // Clear all top filters from the filter bar
    $("#top-filters-clear-all-button").click(function(e) {
      e.preventDefault();
      e.stopPropagation();
      $(this).hide();
      $("#top-filters-toggle .top-filters-active-filter").hide();
      $("#top-filters-clear").click();
    });

    // Make all submit buttons pretty (Bootstrap)
    $("input:submit").addClass("spin submit-button btn btn-primary");

    // Make all select form controls Bootstrappy
    $("select").addClass("form-select");

    // Use select2 library everywhere except
    // - duet-date
    $("select").not(".duet-date__select--month").not(".duet-date__select--year").select2({
      width: '100%',
      tags: true,
      placeholder: "-- Select --"
    });

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

    // Add loading animation when a form is submitted, when any item with a "spin" class is clicked,
    // or on any anchor tag lacking the .nospin class. We do not automatically add this to buttons
    // because they are often on-page controls. Add a "spin" class to buttons that need it.
    $("input[type='submit'], a:not('.nospin'), .spin").click(function() {

      displaySpinner();

      // Test for invalid fields (HTML5) and turn off spinner explicitly if found
      if(document.querySelectorAll(":invalid").length) {
        stopSpinner();
      }

    });
    
  });

  // Define default text for confirm dialog
  var defaultConfirmOk = "<?php print __d('operation', 'ok'); ?>";
  var defaultConfirmCancel = "<?php print __d('operation', 'cancel'); ?>";
  var defaultConfirmTitle = "<?php print __d('operation', 'confirm'); ?>";

</script>