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
            <button id="btnRefrInventory"><?php p($l->t('Refresh inventory')) ?></button>
        </div>
        
        <div id="aletsch_vaults">
        <?php
            foreach($_['actVaults'] as $vaultarn => $vault) {
                $vaultName = \OCA\aletsch\aletsch::explodeARN($vaultarn, TRUE);
                
                printf("<h3 data-vaultarn=\"%s\">%s</h3>\n", $vaultarn, $vaultName);
                print("<div>\n");
                printf("<p><strong>ARN:</strong> %s</p>\n", $vaultarn);
                printf("<p><strong>Creation date:</strong> %s UTC</p>\n", $vault['creationdate']);
                printf("<p><strong>Last inventory:</strong> %s UTC</p>\n", $vault['lastinventory']);
                printf("<p><strong>Number of archives:</strong> %s</p>\n", $vault['numberofarchives']);
                printf("<p><strong>Size:</strong> %s bytes</p>\n", \OCA\aletsch\utilities::formatBytes($vault['sizeinbytes'], 2, FALSE));
                print("</div>\n");
            }
        ?>
        </div>
    </div>

    <div class="center" id="resPane" style="border: 1px dotted red;">
        <div id="aletsch_tabs" data-actualarn="<?php p($_['actualArn']); ?>">
            <ul>
                <li><a href="#tabInventory"><?php p($l->t('Inventory')) ?></a></li>
                <li><a href="#tabJobList"><?php p($l->t('Jobs list')) ?></a></li>
            </ul>

            <div id="tabInventory">
                <div style="text-align: left; padding-left: 5px; background-color: lightgray;">
                    <button id="btnUploadArchive"><?php p($l->t('Upload archive')) ?></button>
                    <button id="btnDownloadArchive"><?php p($l->t('Download archive')) ?></button>
                    <button id="btnDeleteArchive"><?php p($l->t('Delete archive')) ?></button>
                </div>

                <div id="aletsch_emptylist"><?php p($l->t('No inventory - Click on "Refresh inventory" to refresh.')) ?></div>
            </div>

            <div id="tabJobList">
                <?php
                    print \OCA\aletsch\utilities::prepareJobList($_['jobs']);
                ?>
            </div>
        </div>        
    </div>
</div>
