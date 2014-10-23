<?php
/*
 * Copyright 2014 by Francesco PIRANEO G. (fpiraneo@gmail.com)
 * 
 * This file is part of aletsch.
 * 
 * aletsch is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * aletsch is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with aletsch.  If not, see <http://www.gnu.org/licenses/>.
 */

// Highlight current menu item
OCP\App::setActiveNavigationEntry('aletsch');

// Look up other security checks in the docs!
\OCP\User::checkLoggedIn();
\OCP\App::checkAppEnabled('aletsch');

// Following is needed by layout manager
\OCP\Util::addScript('aletsch', 'layout/jquery.sizes');
\OCP\Util::addScript('aletsch', 'layout/jlayout.border');
\OCP\Util::addScript('aletsch', 'layout/jquery.jlayout');
\OCP\Util::addScript('aletsch', 'layout/layout');

\OCP\Util::addStyle('aletsch', 'layout');

// Load main script
\OCP\Util::addScript('aletsch', 'mainAletsch');

// Retrieve accounts data
$OCUserName = \OCP\User::getUser();
$userAccount = new \OCA\aletsch\credentialsHandler($OCUserName);

$serverLocation = $userAccount->getServerLocation();
$username = $userAccount->getUsername();
$password = $userAccount->getPassword();

$serverAvailableLocations = \OCA\aletsch\aletsch::getServersLocation();

// Create instance to glacier
$glacier = new \OCA\aletsch\aletsch($serverLocation, $username, $password);

// Retrieve vaults list
try {
    $vaults = $glacier->vaultList();
}
catch(Aws\Glacier\Exception\GlacierException $ex) {
    $exCode = $ex->getExceptionCode();
    $exMessage = $ex->getMessage();
    $errStatus = TRUE;
}


// In case of error, assign the message to the template's variables
if($errStatus) {
    $tpl = new OCP\Template("aletsch", "svcerr", "user");

    $tpl->assign('errCode', $exCode);
    $tpl->assign('errMessage', $exMessage);
} else {
    $tpl = new OCP\Template("aletsch", "main", "user");

    $tpl->assign('credID', $userAccount->getCredID());
    $tpl->assign('serverLocation', $serverLocation);
    $tpl->assign('serverTextLocation', $serverAvailableLocations[$serverLocation]);
}

$tpl->printPage();
