<?php
// Handle translations
$l = new \OC_L10N('aletsch');
?>

<div id="notification" style="display:none;"></div>

<div class='aletsch_toolbar'>
    <?php
        p($l->t('Actual vaults on '));
        p($_['serverTextLocation']);
        printf(" -- %s: %s", $l->t('All vaults size'), \OCA\aletsch\utilities::formatBytes($_['allVaultsSize'], 2 , FALSE));
    ?>
</div>

<div data-layout='{"type": "border", "hgap": 5, "vgap": 3}' class="aletsch_content" id="aletsch_content">
    <div class="west" id="navPane">
        <div style="padding-left: 5px; background-color: lightgray;">
            <button id="btnNewVault"><?php p($l->t('New vault')) ?></button>
            <button id="btnDeleteVault"><?php p($l->t('Delete vault')) ?></button>
            <button id="btnRefrInventory"><?php p($l->t('Inventory')) ?></button>
        </div>
        
        <div id="aletsch_vaults">
            <?php
                print \OCA\aletsch\utilities::prepareVaultsList($_['actVaults']);
            ?>
        </div>
    </div>

    <div class="center" id="resPane" style="border: 1px dotted red;">
        <div id="aletsch_tabs" data-actualarn="<?php p($_['actualArn']); ?>">
            <ul>
                <li><a href="#tabInventory"><?php p($l->t('Inventory')) ?></a></li>
                <li><a href="#tabJobList"><?php p($l->t('Jobs list')) ?></a></li>
                <li><a href="#tabSpooler"><?php p($l->t('Spooler')) ?></a></li>
            </ul>

            <div id="tabInventory">
                <div style="text-align: left; padding-left: 5px; background-color: lightgray; margin-bottom: 5px;">
                    <button id="btnDownloadArchive"><?php p($l->t('Download archive')) ?></button>
                    <button id="btnDeleteArchive"><?php p($l->t('Delete archive')) ?></button>
                    <div style="float: right; background-color: lightgray; padding: 5px;"><?php p($l->t('Updated on')) ?>: 
                        <span id="aletsch_inventoryDate"><?php p(($_['inventoryDate'] === '') ? $l->t('Not available') : $_['inventoryDate']); ?></span>
                        <span id="aletsch_inventoryOutdated" style="font-weight: bold; color: red;"><?php p(($_['inventoryOutdated'] === '') ? $l->t('Outdated') : ''); ?></span>
                    </div>
                </div>

                <div id="aletsch_archives">
                    <?php
                        print \OCA\aletsch\utilities::prepareArchivesList($_['inventoryArchives'], TRUE);
                    ?>
                </div>
            </div>

            <div id="tabJobList">
                <?php
                    print \OCA\aletsch\utilities::prepareJobList($_['jobs']);
                ?>
            </div>
            
            <div id="tabSpooler">&nbsp;</div>
        </div>        
    </div>
</div>

<!-- New vault dialog -->
<div id="aletsch_newVaultDlog" title="<?php p($l->t('Enter the new vault name')) ?>">
    <input type="text" name="aletsch_vaultName" id="aletsch_vaultName" style="width:95%">        
</div>

<!-- Delete vault dialog -->
<div id="aletsch_deleteVaultDlog" title="<?php p($l->t('Delete a vault')) ?>">
    <p><?php p($l->t('The following vault will be removed; are you sure?')) ?></p>
    <p id="vaultNameToDelete" style="font-weight: bold; width: 100%; text-align: center; padding: 5px 0px 5px 0px;">&nbsp;</p>
    <p style="font-size: 0.85em;"><?php p($l->t('Non-empty vaults cannot be removed and you\'ll get a failure!')) ?></p>
</div>

<!-- Delete selected archives -->
<div id="aletsch_deleteArchivesDlog" title="<?php p($l->t('Delete archives')) ?>">
    <p><?php p($l->t('The selected archives will be removed; are you sure?')) ?></p>
</div>