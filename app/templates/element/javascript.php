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
    
    // USER PANEL
    $('#user-panel-user-settings').click(function(e) {
      e.stopPropagation();  
    });
    
    // DESKTOP MENU DRAWER BEHAVIOR
    $('#co-menu-collapse').click(function(){
      // Desktop mode
      if ($("#navigation-drawer").hasClass("closed")) {
        setApplicationState(
          "open",
          $("#navigation-drawer"),
          false
        );
      } else {
        setApplicationState(
          "closed",
          $("#navigation-drawer"),
          false
        );
      }
      $('#navigation-drawer').toggleClass('closed');
    });

    $('#co-hamburger').click(function() {
      $('#navigation-drawer').toggleClass('visible');
    });
    
    $('.menu-panel-toggle').click(function() {
      panel = $(this).next('.menu-panel');
      if($(panel).hasClass('visible')) {
        $(panel).removeClass('visible'); 
      } else {
        $('.menu-panel').removeClass('visible');
        $(panel).addClass('visible');
      }
    });

    $('.menu-panel-close').click(function() {
      $(this).closest('.menu-panel').removeClass('visible');
    });
    
    // END DESKTOP MENU DRAWER BEHAVIOR

    // SEARCH
    // Persistent search bar form:
    $('#global-search form').submit(function() {
      // Disallow submit on blank
      if($.trim($('#q').val()) == '') {
        return false;
      }
    });
    $('#global-search-clear').click(function(e) {
      e.stopPropagation();
      $('#q').val('');
      $('#q').removeClass('has-value');
      $('#q').focus();
    });
    // Select search text on focus
    $('#q').focus(function() {
      $(this).select();
    });
    // Hide and reveal clear button
    $('#q').on('input', function(e) {
      if($(this).val() != '') {
        $(this).addClass('has-value');
      } else {
        $(this).removeClass('has-value');
      }
    });

    // DUET DATEPICKER
    // We use the accessible Duet Datepicker only for its picker - never for its
    // input fields. Strip out the Duet input fields if they exist so the forms never
    // attempt to submit them. This will also fix any issues with missing labels.
    let dateWidgetInputs = document.querySelectorAll('duet-date-picker input');
    // Remove all the Vue related fields
    Array.prototype.slice.call(dateWidgetInputs).forEach( (el) => {
      el.parentNode.removeChild(el);
    });
    // Also convert the mobile duet datepicker labels to spans (to avoid orphaned labels)
    let duetMobileLabels = document.getElementsByClassName('duet-date__mobile-heading');
    Array.prototype.slice.call(duetMobileLabels).forEach( (el) => {
      let duetMobileLabelReplacement = document.createElement('span');
      duetMobileLabelReplacement.classList.add('duet-date__mobile-heading');
      duetMobileLabelReplacement.innerHTML = el.innerHTML;
      el.replaceWith(duetMobileLabelReplacement);
    });
    
    
    // TOP FILTER FORM
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
    
    // Hide and show filter fields using the Available Filters menu
    $('#top-filters-form .filter-selector').click(function(e) {
      e.stopPropagation();

      // Get the checkbox and the target filter id held in the checkbox value
      let cb = $(this).find('input');
      let target = $(cb).val();
      let targetFromPicker = $(cb).val().replaceAll('-', '_');

      // Toggle the active and inactive state of the target filter
      if(cb.prop('checked')) {
        // XXX we need o check both for person_id and person-id because the picker has the first while form helper
        // creates the latter
        $('#' + target).closest('.filter-inactive').removeClass('filter-inactive').addClass('filter-active');
        $('#' + targetFromPicker).closest('.filter-inactive').removeClass('filter-inactive').addClass('filter-active');
        setApplicationState(
          "on",
          cb,
          false
        );
      } else {
        $('#' + target).closest('.filter-active').removeClass('filter-active').addClass('filter-inactive')
        $('#' + targetFromPicker).closest('.filter-active').removeClass('filter-active').addClass('filter-inactive')
        setApplicationState(
          "off",
          cb,
          false
        );
      }
      
      // Toggle the submit container rebalance class so long as there are no datetime pickers
      if(!$('#top-filters-fields .top-filters-fields-dates').length) {
        // Count the number of standard visible fields, and apply the rebalance class on an odd number 
        // unless boolean fields are visible - in which case we remove it.
        let standardFiltersCount = $('#top-filters-fields .filter-standard.filter-active').length;
        let booleanFiltersCount = $('#top-filters-fields .filter-boolean.filter-active').length;
        if(standardFiltersCount % 2 == 1 && !booleanFiltersCount) {
          $('#top-filters-submit').addClass("tss-rebalance"); 
        } else {
          $('#top-filters-submit').removeClass("tss-rebalance");
        }
      }
    });

    // PETITION STEP / GENERAL FIELD TOGGLE
    // We allow Bootstrap to manage the accordion behavior, but we must set the state of our arrows.  
    $('.field-with-toggle').click(function() {
      let toggleButton = $(this).find('button.cm-toggle').first();
      if($(toggleButton).attr('aria-expanded') == 'false') {
        $(toggleButton).find('em').text("arrow_drop_down");
      } else {
        $(toggleButton).find('em').text("arrow_drop_up");
      }
    });
    
    // Toggle All button for Enrollment Flow Steps
    // We click all the fields that have an associated accordion just to set the arrow state. 
    $('button.enrollment-steps-toggle-all-button').click(function() {
      $('#view_Petitions').find('.field-with-toggle').trigger('click');
    });

    // Make all submit buttons pretty (Bootstrap)
    $("input:submit").addClass("spin submit-button btn btn-primary");

    // Make all select form controls Bootstrappy
    $("select").addClass("form-select");
    
    // Launch standard modal window when a link is configured to do so
    $('a.cm-modal-link').click(function(e) {
      e.preventDefault();
      launchCmModal($(this).attr('href'), $(this).attr('data-cm-modal-title'));
    });

    // Honor the "page reload" configuration when the standard modal is closed.
    // The current default is to always reload the browser.
    $('#cm-modal').on('hide.bs.modal', function (e) {
      if($(this).attr('data-reload-on-close') == 'true') {
        location.reload();
      }
    })

    // Generic row click handling
    // First capture mouse location to test if we're clicking or drag-selecting (for copy)
    var mouseDownEvent = null;
    $('table.index-table tr, .linked-row').mousedown(function(e) {
      mouseDownEvent = e;
    });

    // Generic row click handling for div-and li based rows
    $('.linked-row').click(function(e) {
      url = $(this).find('a.row-link').attr('href');
      isModal = $(this).find('a.row-link').hasClass('cm-modal-link');
      if(Math.abs(e.clientX-mouseDownEvent.clientX) < 5 &&
        Math.abs(e.clientY-mouseDownEvent.clientY < 5)) {
        if(isModal) {
          e.preventDefault();
          modalTitle = $(this).find('a.row-link').attr('data-cm-modal-title');
          launchCmModal(url, modalTitle);
        } else {
          location.href = url; 
        }
      }
    });

    // Generic row click handling for index-table rows
    $('table.index-table tr').each(function(e) {
      url = $(this).find('a.row-link').attr('href');
      isModal = $(this).find('a.row-link').hasClass('cm-modal-link');
      if(url != undefined && url != '') {
        $(this).addClass('linked-row').attr('data-cm-target',url).mouseup(function(e) {
          if(Math.abs(e.clientX-mouseDownEvent.clientX) < 5 &&
             Math.abs(e.clientY-mouseDownEvent.clientY < 5)) {
            // We have a click event on the row. Now determine what mode we're in and act accordingly.
            if($(this).closest('table.index-table').hasClass('bulk-edit-mode')) {
              // We're in bulk edit mode, so click the associated checkbox unless we mouseup on a label or checkbox input.
              if(e.target.nodeName != 'LABEL' && e.target.nodeName != 'INPUT') {
                $(this).find('.form-check-input').click();  
              }              
            } else {
              // We're in list mode, so follow the row-link target.
              if(isModal) {
                e.preventDefault();
                modalTitle = $(this).find('a.row-link').attr('data-cm-modal-title');
                modalUrl = $(this).attr('data-cm-target');
                launchCmModal(modalUrl, modalTitle);
              } else {
                location.href = $(this).attr('data-cm-target');  
              }
            }
          }
          mouseDownEvent = null;
        });
        // don't propagate on other links to avoid redirecting dialog boxes and action menus
        $(this).find('a').on('click, mouseup',function(e){
          e.stopPropagation();
        });
      }
    });
    
    // Person canvas "Add" links
    // Launch the MVEA modal window, load the Add url, and refresh the appropriate component when done.
    $('#mvea-add-menu-container ul a').click(function(e) {
      e.preventDefault();
      e.stopPropagation();
      var title = $(this).find('.action-link-text').text();
      var url = $(this).prop('href');
      var componentRef = 'mvea' + $(this).data('cm-mveatype');
      window.cmMveaModal.launch(title,url,componentRef);
    });

    // Bulk edit switch
    $('#bulk-edit-switch').click(function() {
      if($("#bulk-edit-switch").is(':checked')) {
        $("body").addClass('bulk-mode');
        $("table.index-table").removeClass('list-mode').addClass('bulk-edit-mode');
      } else {
        $("body").removeClass('bulk-mode');
        $("table.index-table").removeClass('bulk-edit-mode').addClass('list-mode');
      }
    });
    
    // Bulk edit select all checkbox
    $('#bulk-action-select-all').click(function() {
      if($(this).is(":checked")) {
        $('table.index-table.bulk-edit-mode .form-check-input').prop('checked', true);
      } else {
        $('table.index-table.bulk-edit-mode .form-check-input').prop('checked', false);
      }
    });

    // Add a .nospin class to all on-page cake error and warning links
    $(".cake-error a").addClass('nospin');
    
    // Add loading animation when a form is submitted, when any item with a "spin" class is clicked,
    // or on any anchor tag lacking the .nospin class. We do not automatically add this to buttons
    // because they are often on-page controls. Add a "spin" class to buttons that need it.
    $("input[type='submit'], a:not('.nospin'), .spin").click(function(e) {

      // Start a spinner only if CTRL, CMD, or SHIFT is not pressed (which loads a new tab or window).
      if(!(e.ctrlKey || e.metaKey || e.shiftKey)) {
        displaySpinner();

        // Test for invalid fields (HTML5) and turn off spinner explicitly if found
        if (document.querySelectorAll(":invalid").length) {
          stopSpinner();
        }
      }

    });

    // SETTINGS (from User Menu)
    // Dark Mode toggles (auto is default)
    $("#setting-darkmode-dark").click(function(e) {
      $('html')
        .removeClass('light-mode')
        .removeClass('auto-mode')
        .addClass('dark-mode');
      setApplicationState(
        "dark",
        $("#form-profile-menu-dark"),
        false
      );
    });
    $("#setting-darkmode-light").click(function(e) {
      $('html')
        .removeClass('dark-mode')
        .removeClass('auto-mode')
        .addClass('light-mode');
      setApplicationState(
        "light",
        $("#form-profile-menu-dark"),
        false
      );
    });
    $("#setting-darkmode-auto").click(function(e) {
      $('html')
        .removeClass('dark-mode')
        .removeClass('light-mode')
        .addClass('auto-mode');
      setApplicationState(
        "auto",
        $("#form-profile-menu-dark"),
        false
      );
    });
    
    // Test for dark mode OS preference, and add the 'dark-mode' body class by default if it is present.
    // XXX TODO: Once an application variable is set and the class is rendered at page build, 
    //  there will be no flash of the light color on each page load if the setting is defined.
    //  We will need to investigate this for the OS setting, and send an ajax call to set the preference
    //  if the user has not set it so that no page flash occurs.
    const darkModeOsEnabled = window.matchMedia("(prefers-color-scheme: dark)");
    if(darkModeOsEnabled.matches && !($('html').hasClass('light-mode')) &&  !($('html').hasClass('dark-mode'))) {
      $('html').addClass('dark-mode');
    }

    // Density toggles (medium is default)
    $("#setting-density-small").click(function(e) {
      $('html')
        .removeClass('density-large')
        .removeClass('density-medium')
        .addClass('density-small');
      setApplicationState(
        "small",
        $("#form-profile-menu-density"),
        false
      );
    });
    $("#setting-density-medium").click(function(e) {
      $('html')
        .removeClass('density-small')
        .removeClass('density-large')
        .addClass('density-medium');
      setApplicationState(
        "medium",
        $("#form-profile-menu-density"),
        false
      );
    });
    $("#setting-density-large").click(function(e) {
      $('html')
        .removeClass('density-small')
        .removeClass('density-medium')
        .addClass('density-large');
      setApplicationState(
        "large",
        $("#form-profile-menu-density"),
        false
      );
    });
  });

  // Define default text for confirm dialog
  var defaultConfirmOk = "<?= __d('operation', 'ok') ?>";
  var defaultConfirmCancel = "<?= __d('operation', 'cancel') ?>";
  var defaultConfirmTitle = "<?= __d('operation', 'confirm') ?>";

</script>