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
    $('#co-menu-collapse').click(function(){
      $('#navigation-drawer').toggleClass('closed');
    });

    $('#co-hamburger').click(function() {
      $('#navigation-drawer').toggleClass('visible');
    });
    
    $('.menu-panel-toggle').click(function() {
      $(this).next('.menu-panel').toggleClass('visible');  
    });

    $('.menu-panel-close').click(function() {
      $(this).closest('.menu-panel').removeClass('visible');
    });
    
    // END DESKTOP MENU DRAWER BEHAVIOR

    // GLOBAL SEARCH
    $('#search-bar input').focus(function() {
      $('#search-bar button').addClass('visible');
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
    $("select").not("#limit").not(".duet-date__select--month").not(".duet-date__select--year").select2({
      width: '100%',
      tags: true,
      placeholder: "-- Select --"
    });

    // Generic row click handling for div-based rows
    $('div.linked-row').click(function(e) {
      location.href = $(this).find('a.row-link').attr('href');
    });

    // Generic row click handling for index-table rows
    // First capture mouse location to test if we're clicking or drag-selecting (for copy)
    var mouseDownEvent = null;
    $('table.index-table tr').mousedown(function(e) {
      mouseDownEvent = e;
    });
    
    $('table.index-table tr').each(function(e) {
      url = $(this).find('a.row-link').attr('href');
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
              location.href = $(this).attr('data-cm-target');  
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

    // Bulk edit switch
    $('#bulk-edit-switch').click(function() {
      if($("#bulk-edit-switch").is(':checked')) {
        $("table.index-table").removeClass('list-mode').addClass('bulk-edit-mode');
      } else {
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
    
  });

  // Define default text for confirm dialog
  var defaultConfirmOk = "<?php print __d('operation', 'ok'); ?>";
  var defaultConfirmCancel = "<?php print __d('operation', 'cancel'); ?>";
  var defaultConfirmTitle = "<?php print __d('operation', 'confirm'); ?>";

</script>