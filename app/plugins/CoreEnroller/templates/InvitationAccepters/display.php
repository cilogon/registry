<?php
if(!empty($vv_pa)) {
  if($vv_pa['accepted']) {
    print __d('core_enroller', 'result.InvitationAccepters.accepted', [$vv_pa['modified']]);
  } else {
    print __d('core_enroller', 'result.InvitationAccepters.declined', [$vv_pa['modified']]);
  }
} else {
  print __d('core_enroller', 'result.InvitationAccepters.none');
}
