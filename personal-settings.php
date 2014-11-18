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

OCP\User::checkLoggedIn();

OCP\Util::addScript('aletsch', 'savePersonalSettings');

// Handle translations
$l = new \OC_L10N('aletsch');

// Create template object
$tmpl = new \OCP\Template('aletsch', 'personal-settings');

// Retrieve accounts
$OCUserName = \OCP\User::getUser();
$userAccount = new \OCA\aletsch\credentialsHandler($OCUserName);

if(is_null($userAccount->getCredID())) {
    // No accounts found
    $tmpl->assign('credID', '');
    $tmpl->assign('serverLocation', '');
    $tmpl->assign('username', '');
    $tmpl->assign('password', '');
} else {
    $tmpl->assign('credID', $userAccount->getCredID());	
    $tmpl->assign('serverLocation', $userAccount->getServerLocation());
    $tmpl->assign('username', $userAccount->getUsername());
    $tmpl->assign('password', $userAccount->getPassword());
}

// Default download directory - Where the files will be downloaded
$downloadDir = OCP\Config::getAppValue('aletsch', 'downloadDir');
if(trim($downloadDir) === '') {
    $downloadDir = 'aletsch downloads';
    OCP\Config::setAppValue('aletsch', 'downloadDir', $downloadDir);
}
$tmpl->assign('downloadDir', $downloadDir);

// Store full path on archive's description
$storeFullPath = intval(OCP\Config::getAppValue('aletsch', 'storeFullPath'));
$tmpl->assign('storeFullPath', ($storeFullPath === 1) ? 'checked="checked"' : '');

return $tmpl->fetchPage();