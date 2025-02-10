<?php

use App\Lib\Enum\VerificationMethodEnum;

$PetitionVerifications = $this->Petition->getTable('CoreEnroller.PetitionVerifications');

// Because Petition Verifications are not tracked on a per-step basis, we just pull all
// associated with the Petition

$vv_pv =  $PetitionVerifications->find()
  ->where(['PetitionVerifications.petition_id' => $vv_obj->id])
  ->contain(['Verifications'])
  ->all();

?>

<?php if(!empty($vv_pv)): ?>
  <ul>
    <?php foreach($vv_pv as $pv): ?>
    <li><?= $pv->mail ?>:
    <?php if(!empty($pv->verification) && $pv->verification->isVerified()): ?>
      <?= __d('result', 'Verifications.status', [
        VerificationMethodEnum::getLocalization($pv->verification->method),
        $this->Time->nice($pv->verification->verification_time, $vv_tz)
      ]) ?>
    <?php else: ?>
      <span class="mr-1 badge bg-warning unverified"><?= __d('field','unverified') ?></span>
    <?php endif; ?>
    </li>
  <?php endforeach; ?>
  </ul>
<?php endif; ?>