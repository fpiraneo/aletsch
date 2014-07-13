<?php
// Handle translations
$l = new \OC_L10N('aletsch');
?>

<div id="notification" style="display:none;"></div>

<div class='aletsch_toolbar'>
    <?php p($l->t('Actual vaults on ')); p($_['serverLocation']); ?>
</div>

<div div data-layout='{"type": "border", "hgap": 5, "vgap": 3}' class="aletsch_content" id="aletsch_content">
    <div class="west" id="tagscontainer">
        Vaults
    </div>
    
    <div class="center" id="fileTable">
        <p class="aletsch_title"><?php p($l->t('Associated files')) ?></p>        
        <div id="aletsch_fileList"></div>
        <div id="aletsch_emptylist"><?php p($l->t('Select one or more tags to view the associated files.')) ?></div>
    </div>
</div>
