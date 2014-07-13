<fieldset class="personalblock">
    <h2>Aletsch</h2>
    <form id="aletsch_settings">
        <div style="margin-bottom: 5px;">
            <label for="aletsch_serverLocation"><?php p($l->t('Server location')) ?></label>
            <select id="aletsch_serverLocation" name="serverLocation">
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
            <input id="aletsch_username" name="username" style="width:500px;" value="<?php p($_['username']) ?>" />
        </div>
        
        <div style="margin-bottom: 5px;">
            <label for="aletsch_password"><?php p($l->t('Password')) ?></label>
            <input id="aletsch_password" name="password" style="width:500px;" value="<?php p($_['password']) ?>" />
        </div>
    </form>
</fieldset>