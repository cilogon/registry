<?php
/**
 * COmanage Registry Pagination Element
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

  $paginationClass = "without-pagination-elements";
  if($this->Paginator->hasPage(2)) {
    $paginationClass = "with-pagination-elements";
  }
?>
<div id="pagination" class="<?php print $paginationClass; ?>">
  <span class="paginationCounter">
    <?= $this->Paginator->counter(__d('information', 'pagination.format')); ?>
  </span>
  <?php if($this->Paginator->hasPage(2)): ?>
    <!-- show the pagination elements if there is more than 1 page -->
    <ul class="paginationFirst">
      <?= $this->Paginator->first(__d('operation', 'first')); ?>
    </ul>
    <ul class="paginationPrev">
      <?= $this->Paginator->prev(__d('operation', 'previous'), ['class' => 'disabled']); ?>
    </ul>
    <ul class="paginationNumbers">
      <?= $this->Paginator->numbers(); ?>
    </ul>
    <ul class="paginationNext">
      <?= $this->Paginator->next(__d('operation', 'next'), ['class' => 'disabled']); ?>
    </ul>
    <ul class="paginationLast">
      <?= $this->Paginator->last(__d('operation', 'last')); ?>
    </ul>

    <form id="goto-page"
          class="pagination-form"
          method="get"
          onsubmit="gotoPage(this.pageNum.value,
            <?= $this->Paginator->counter('{{pages}}');?>,
            '<?= __d('error', 'pagenum.nan');?>',
            '<?= __d('error', 'pagenum.exceeded', [$this->Paginator->counter('{{pages}}')]);?>',
            '<?= $this->Paginator->generateUrl(); ?>');
            return false;">
      <label for="pageNum"><?= __d('operation', 'page.goto'); ?></label>
      <input type="text" size="3" name="pageNum" id="pageNum"/>
      <input type="submit" value="<?= __d('operation', 'go'); ?>"/>
    </form>
  <?php endif; ?>

  <?php
  // XXX Change this test to 25 when the default is 25
  if($this->Paginator->counter('{{count}}') > 20) {
      // Provide a form for setting the page limit.
      // Default is 20 records (XXX but will be 25), maximum is 100.
      // For now we will simply hard-code the options from 25 - 100.
      
      print $this->Form->create(null, [ 'type' => 'get', 'class' => 'pagination-form' ]);
      
      // This is similar to Paginator->limitControl, but we have to manually
      // re-insert query params that we want to maintain through the limit adjustment

      // Note: we specifically do NOT retain the 'page' param, because we should always go
      // back to page one when using this form; otherwise we can easily get a page/limit mismatch that breaks.
      $queryParams = ['sort', 'direct'];
      
      if(!empty($vv_primary_link)) {
        $queryParams[] = $vv_primary_link;
      }
      
      foreach($queryParams as $p) {
        if(!empty($this->request->getQuery($p))) {
          print $this->Form->hidden($p, ['default' => $this->request->getQuery($p)]);
        }
      }
      
      print $this->Form->control('limit', [
        'type' => 'select',
        'label' => __d('operation', 'page.display'),
        'value' => $this->request->getQuery('limit'),
        'options' => [25 => 25, 50 => 50, 75 => 75, 100 => 100]
      ]);
      
      print $this->Form->submit(__d('operation', 'go'));
      print $this->Form->end();
    }
  ?>
</div>