<?php
$PetitionIdentifiers = $this->Petition->getTable('CoreEnroller.PetitionIdentifiers');

$vv_pi = $PetitionIdentifiers->find()->where(['petition_id' => $vv_obj->id])->first();

if(!empty($vv_pi->identifier)) {
  print __d('core_enroller', 'result.IdentifierCollector.collected', [$vv_pi->identifier]);
}