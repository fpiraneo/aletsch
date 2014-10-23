<?php
    if($_SESSION['OC_Version'][0] === 7) {
        print '<div class="section">';
    }
?>
    <fieldset class="personalblock">
        <h2>Aletsch</h2>
        <form id="aletsch_settings">
            <div style="margin-bottom: 5px;">
                <label for="aletsch_serverLocation"><?php p($l->t('Server location')) ?></label>
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
            </div>

            <div style="margin-bottom: 5px;">
                <label for="aletsch_username"><?php p($l->t('Username')) ?></label>
                <input id="aletsch_username" name="username" style="width:500px;" data-credid="<?php p($_['credID']) ?>" value="<?php p($_['username']) ?>" />
            </div>

            <div style="margin-bottom: 5px;">
                <label for="aletsch_password"><?php p($l->t('Password')) ?></label>
                <input id="aletsch_password" name="password" style="width:500px;" data-credid="<?php p($_['credID']) ?>" value="<?php p($_['password']) ?>" />
            </div>
            
            <button id="aletsch_saveCredentials"><?php p($l->t('Save credentials')) ?></button>
        </form>
    </fieldset>

<?php
    if($_SESSION['OC_Version'][0] === 7) {
        print '</div>';
    }
