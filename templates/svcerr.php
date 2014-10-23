<?php
// Handle translations
$l = new \OC_L10N('aletsch');
?>

<div class='aletsch_center'>
    <div class="aletsch_errTitle"><?php p($l->t('Aletsch returned an error')); ?></div>
    <div class="aletsch_errCode"><strong><?php p($l->t('Exception code: ')); ?></strong><?php p($_['errCode']); ?></div>
    <div class="aletsch_errMessage"><strong><?php p($l->t('Exception message: ')); ?></strong><?php p($_['errMessage']); ?></div>
</div>
