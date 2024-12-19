<?php

use App\Lib\Enum\VerificationMethodEnum;

if(!empty($vv_pv)) {
  print "<ul>\n";

  foreach($vv_pv as $pv) {
    print "<li>" . $pv->mail . ": ";

    if(!empty($pv->verification) && $pv->verification->isVerified()) {
      print __d('result', 'Verifications.status', [ 
        VerificationMethodEnum::getLocalization($pv->verification->method),
        $this->Time->nice($pv->verification->verification_time, $vv_tz)
      ]);
    } else {
      print __d('field', 'unverified');
    }

    print "</li>";
  }

  print "</ul>\n";
}