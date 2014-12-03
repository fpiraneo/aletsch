<?php
    if($_SESSION['OC_Version'][0] === 7) {
        print '<div class="section">';
    }
?>
    <fieldset class="personalblock">
        <h2>Aletsch</h2>
        <form id="aletsch_settings">
            <table style="padding-bottom: 15px; margin-bottom: 5px;">
                <tr>
                    <td style="padding-right: 7px;">
                        <table>
                            <tr>
                                <td style="text-align: right; padding-right: 10px;">
                                    <?php p($l->t('Server location:')) ?>
                                </td>
                                <td>
                                    <select id="aletsch_serverLocation" name="serverLocation" data-credid="<?php p($_['credID']) ?>">
                                        <option value="" disabled="disabled" ><?php p($l->t('Set server location')) ?></option>

                                        <?php
                                        $serverAvailableLocations = \OCA\aletsch\aletsch::getServersLocation();

                                        foreach($serverAvailableLocations as $serverID => $serverDescr) {
                                            $selected = ($serverID === $_['serverLocation']) ? ' selected="selected"' : '';
                                            ?>
                                            <option value="<?php print $serverID ?>"<?php print $selected ?>><?php print $serverDescr ?></option>
                                            <?php
                                        }
                                        ?>
                                    </select>                                        
                                </td>
                            </tr>

                            <tr>
                                <td style="text-align: right; padding-right: 10px;">
                                    <?php p($l->t('Username:')) ?>
                                </td>
                                <td>
                                    <input id="aletsch_username" name="username" style="width:500px;" data-credid="<?php p($_['credID']) ?>" value="<?php p($_['username']) ?>" />
                                </td>
                            </tr>

                            <tr>
                                <td style="text-align: right; padding-right: 10px;">
                                    <?php p($l->t('Password:')) ?>
                                </td>
                                <td>
                                    <input id="aletsch_password" name="password" style="width:500px;" data-credid="<?php p($_['credID']) ?>" value="<?php p($_['password']) ?>" />
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="margin-left: 7px; padding-left: 7px; vertical-align: center; border-left: 2px solid darkgray;">
                        <button id="aletsch_saveCredentials"><?php p($l->t('Save credentials')) ?></button>                        
                    </td>
                </tr>
            </table>
            
        </form>
        
        <table style="padding-bottom: 15px;">
            <tr>
                <td style="padding-right: 5px; text-align: right;"><?php p($l->t('Download directory:')) ?></td>
                <td><input id="aletsch_downloadDir" name="downloadDir" style="width:300px;" value="<?php p($_['downloadDir']) ?>" /></td>
            </tr>
        </table>
                
        <div>
            <input type="checkbox" id="aletsch_storeFullPath" name="storeFullPath" <?php p($_['storeFullPath']) ?> />
            <label for="aletsch_storeFullPath"><?php p($l->t('Store (and recover!) full path in archive description')) ?></label>
            <p style="font-size: 0.9em;"><?php p($l->t('If this checkbox is disabled Aletsch will use the stored path on it\'s database if it\'s available.')) ?></p>
            <p style="font-size: 0.9em;">
                <?php
                    p($l->t('If the stored path is not available, files will be stored on "Download directory" with a random file name'));
                    p($l->t(' (if the description is empty) or with a truncated description.'));
                ?>
            </p>
        </div>
</fieldset>

<?php
    if($_SESSION['OC_Version'][0] === 7) {
        print '</div>';
    }
