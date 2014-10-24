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
        <div id="aletsch_vaults">
        <?php
            foreach($_['actVaults'] as $vaultarn => $vault) {
                $explodedArn = explode(':', $vaultarn);
                $vaultName = substr($explodedArn[5], 7);
                
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
        <p class="aletsch_title"><?php p($l->t('Associated files')) ?></p>        
        <div id="aletsch_fileList"></div>
        <div id="aletsch_emptylist"><?php p($l->t('Select one or more tags to view the associated files.')) ?></div>
    </div>
</div>
