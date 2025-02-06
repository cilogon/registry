<?php
$PetitionAcceptances = $this->Petition->getTable('CoreEnroller.PetitionAcceptances');

$vv_pa = $PetitionAcceptances->find()->where(['petition_id' => $vv_obj->id])->first();

if(!empty($vv_pa)) {
  if($vv_pa['accepted']) {
    print __d('core_enroller', 'result.InvitationAccepters.accepted', [$vv_pa['modified']]);
  } else {
    print __d('core_enroller', 'result.InvitationAccepters.declined', [$vv_pa['modified']]);
  }
} else {
  print __d('core_enroller', 'result.InvitationAccepters.none');
}
