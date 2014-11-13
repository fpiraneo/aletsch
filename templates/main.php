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
        <div id="aletsch_tabs" data-actualarn="">
            <ul>
                <li><a href="#tabSpooler"><?php p($l->t('Spooler')) ?></a></li>
                <li><a href="#tabArchiver"><?php p($l->t('Archiver')) ?></a></li>
                <li><a href="#tabInventory"><?php p($l->t('Inventory')) ?></a></li>
                <li><a href="#tabJobList"><?php p($l->t('Jobs list')) ?></a></li>
            </ul>

            <div id="tabInventory">
                <div id="inventoryContent" style="display: none;">
                    <div style="text-align: left; padding-left: 5px; background-color: lightgray; margin-bottom: 5px;">
                        <button id="btnDownloadArchive"><?php p($l->t('Download archive')) ?></button>
                        <button id="btnDeleteArchive"><?php p($l->t('Delete archive')) ?></button>
                        <div style="float: right; background-color: lightgray; padding: 5px;"><?php p($l->t('Updated on')) ?>: 
                            <span id="aletsch_inventoryDate">&nbsp;</span>
                            <span id="aletsch_inventoryOutdated" style="font-weight: bold; color: red;">&nbsp;</span>
                        </div>
                    </div>

                    <div id="aletsch_archives">&nbsp;</div>
                </div>
                <div id="noInventory">
                    <div class="aletsch_emptylist"><?php p($l->t('Select a vault to get it\'s inventory.')); ?></div>
                </div>
            </div>

            <div id="tabArchiver" style="text-align: left;">
                <div style="text-align: left; padding-left: 5px; background-color: lightgray; margin-bottom: 5px;">
                    <button id="btnSelectAll"><?php p($l->t('Select all')) ?></button>
                    <button id="btnUnselectAll"><?php p($l->t('Unselect all')) ?></button>
                    <span style="margin: 0px 5px 0px 0px;">&nbsp;</span>
                    <button id="btnBuildArchive"><?php p($l->t('Build archive')) ?></button>
                    <div id="aletsch_actualSelection" style="float: right; background-color: lightgray; padding: 5px;">&nbsp;</div>
                </div>
                <table id="archiverTree" class="aletsch_resultTable">
                    <colgroup>
                        <col width="30px"></col>
                        <col width="*"></col>
                        <col width="*"></col>
                        <col width="*"></col>
                    </colgroup>
                    <thead>
                        <tr>
                            <th></th>
                            <th><?php p($l->t('File name')); ?></th>
                            <th><?php p($l->t('Mime type')); ?></th>
                            <th><?php p($l->t('Size')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div id="tabJobList">
                <div id="jobContent" style="display: none;">&nbsp;</div>
                
                <div id="noJobs">
                    <div class="aletsch_emptylist"><?php p($l->t('Select a vault to get it\'s jobs.')); ?></div>
                </div>                
            </div>
            
            <div id="tabSpooler">
                <div style="padding-left: 5px; background-color: lightgray; text-align: left; border: 1px solid darkgray;">
                    <button id="btnReleaseJob"><?php p($l->t('Release job')) ?></button>
                    <button id="btnResetStatus"><?php p($l->t('Reset status')) ?></button>
                    <button id="btnDeleteJob"><?php p($l->t('Delete job')) ?></button>
                </div>
                
                <div id="spoolerContent">&nbsp;</div>
                <p style="font-size: 0.85em;"><strong><?php p($l->t('Note:')) ?></strong> <?php p($l->t('Spooler content is common to all vaults.')) ?></p>
            </div>
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

<!-- Download selected archives -->
<div id="aletsch_downloadArchivesDlog" title="<?php p($l->t('Download archives')) ?>">
    <p><?php p($l->t('The selected archives will be queued for retriveal and download; are you sure?')) ?></p>
</div>