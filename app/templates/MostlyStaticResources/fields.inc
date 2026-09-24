<?php
/**
 * COmanage Registry Mostly Static Resources Fields
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

// Load vuejs helper functions
$vueHelper = $this->loadHelper('Vue');

$fields = [
  'title',
  'name',
  'description',
  'status'
];

if($vv_action != 'add') {
  $fields['mime_type'] = [
    'readonly' => true
  ];
  
  // Provide a "copy URL" button
  $msrDisplay = '
    <div class="field msr-media-field">
      <div class="field-name ">
        <div class="field-title">
          <label for="description">
            ' . __d('field','MostlyStaticResources.url') . '
          </label>
        </div>
      </div>  
      <div class="field-info"> 
        <div id="msr-url-container">
          <a id="msr-url" href="' . $vv_base_url . h($vv_obj->name) .'">' . $vv_base_url . h($vv_obj->name) . '</a>
          <div id="msr-url-copy-button"></div>
        </div>
      </div>
    </div>
  ';

  // If we have an image, let's also display it.
  if(str_starts_with($vv_obj['mime_type'], 'image/')) {
    $encodedImg = base64_encode(stream_get_contents($vv_obj['file_content']));
    $imgSrc = "data:{$vv_obj['mime_type']};base64,{$encodedImg}";
    $msrDisplay .= '
      <div class="field msr-media-field msr-media-image-field">
        <div class="field-name ">
          <div class="field-title">
            <label for="description">
              ' . __d('field','MostlyStaticResources.image') . '
            </label>
          </div>
        </div>  
        <div class="field-info">
          <div id="msr-image-container">
            <img id="msr-image" src="' . h($imgSrc) . '" alt="' . h($vv_obj['title']) . '"/>
          </div>
        </div>
      </div>
    ';
  }

  $fields['HTML'] = [
    'html' => $msrDisplay
  ];
}

if($vv_action != 'view') {
  $fields['file_content'] = [
    'type' => 'file'
  ];
}

// Top Links
if($vv_action != 'add') {
  $topLinks[] = [
    'icon'  => 'arrow_outward',
    'order' => 'Default',
    'label' => __d('operation', 'view'),
    'url'  => $vv_base_url . h($vv_obj->name),
    'class' => ''
  ];
}
?>

<script nonce="<?= $vv_js_nonce ?>">
  function setPageUrl() {
    var baseurl = "<?= $vv_base_url ?>";
    var slug = $('#name').value;

    var fullurl = "";

    if(slug) {
      fullurl = baseurl + slug;
    }

    $('#pageurl').value = fullurl;
  }

  function slugifyFilename(filename) {
    return filename
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')  // Convert most accented characters to ascii.
      .toLowerCase()                                     // This aligns with backend validation.
      .trim()                                            // Trim any wrapping spaces.
      .replace(/[\s_]+/g, '-')                           // Convert all remaining spaces and underscores to hyphens.
      .replace(/[^a-z0-9.\-]/g, '');                     // This aligns with backend validation.
  }

  $(function() {
    // run on first load
    setPageUrl();

    // register onchange events
    // note that underscores in field names above must be represented with hyphens here.
    $('#name').on('change', function() {
      setPageUrl();
    });
    
    // Set the default slug on file upload.
    $('#file-content').on('change', function() {
      const filename = this.files[0]?.name;
      $('#name').val(slugifyFilename(filename));
    });
  });
</script>

<script type="module" nonce="<?= $vv_js_nonce ?>">
  <?php if(Cake\Core\Configure::read('debug')): ?>
  import CopyValueButton from "<?= $this->Url->script('comanage/components/common/copy-value-button.js') ?>?time=<?= time() ?>";
  <?php else: ?>
  import CopyValueButton from "<?= $this->Url->script('comanage/components/common/copy-value-button.js') ?>";
  <?php endif; ?>

  const app = Vue.createApp({
    data() {
      return {
        txt: JSON.parse('<?= json_encode($vueHelper->locales()) ?>'),
        isLoading: true,
        resourceUrl: '<?= $vv_base_url . $vv_obj->name ?>'
      }
    },
    components: {
      CopyValueButton
    },
    template: `
      <copy-value-button
        :txt="this.txt"
        :valueToCopy="this.resourceUrl">
      </copy-value-button>
     `
  });

  // Mount the component
  window.msrUrlCopyButton = app.mount("#msr-url-copy-button");
</script>