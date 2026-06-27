<?php

declare(strict_types=1);

if ($vv_attempts_count == 0) {
  return;
}

$retrySeconds = pow($vv_attempts_count, 2);

?>

<style>
  #main {
    align-content: center;
  }
  #flash-messages,
  .page-title-container {
    display: none;
  }
  #count-down-container {
    text-align: center;
    background-color: inherit;
    color: #333;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
  }
  .countdown-circle {
    position: relative;
    width: 150px;
    height: 150px;
    background: conic-gradient(#4caf50 calc(var(--percent) * 1%), #ddd 0%);
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 20px;
  }
  .countdown-circle span {
    position: absolute;
    font-size: 2rem;
    color: #333;
  }
  .message {
    font-size: 1.2rem;
    margin-bottom: 20px;
  }
  .retry-info {
    font-size: 0.9rem;
    color: #666;
  }
</style>

<div id="count-down-container"
     data-coid="<?= $vv_cur_co->id ?? '' ?>"
     data-appstateid="<?= $appStateId ?>"
     data-stateattr="<?= $stateAttr ?>"
     data-webroot="<?= $this->request->getAttribute('webroot') ?>"
     data-username="<?= $vv_user['username'] ?? '' ?>"
     data-personid="<?= $vv_person_id ?? '' ?>">
  <div class="countdown-circle" style="--percent: 100;">
    <span id="timer"><?= $retrySeconds ?> sec</span>
  </div>
  <div class="message">
    <?= __d('information', 'user.block.message') ?>
  </div>
  <div class="retry-info">
     <?= __d('information', 'user.block.retry', [$retrySeconds]) ?>
    <br>
    <?= __d('information', 'user.block.attempt', [$vv_attempts_count]) ?>
  </div>
</div>

<script nonce="<?= $vv_js_nonce ?>">
  const reloadUrl = '<?= $currentUrl ?>';
  let countdown = <?= $retrySeconds ?>;
  let initialCountdown = <?= $retrySeconds ?>;
  let currentPercent = countdown * 100 / initialCountdown
  const timer = document.getElementById('timer');
  const retryInfo = document.getElementById('retry-seconds');
  const circle = document.querySelector('.countdown-circle');

  // Countdown logic
  const interval = setInterval(() => {
    countdown--;
    timer.textContent = countdown + ' sec';
    retryInfo.textContent = countdown;
    currentPercent = countdown * 100 / initialCountdown
    circle.style.setProperty('--percent', currentPercent);

    if (countdown <= 0) {
      clearInterval(interval);
      // Unlock the user
      setApplicationState(
        'unlock',
        $('#count-down-container'),
        true
      );
    }
  }, 1000);
</script>

