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
$userAccounts = OCA\aletsch\accountHandler::getAccountsTable($OCUserName);

if(count($userAccounts) === 0) {
	// No accounts found
	$tmpl->assign('accountID', '');
	$tmpl->assign('serverID', '');
	$tmpl->assign('credID', '');

	$tmpl->assign('serverLocation', '');
	$tmpl->assign('username', '');
	$tmpl->assign('password', '');
} else {
	// One or more accounts found
	// NOTE: Just one account supported on this version
	list($accountID) = array_keys($userAccounts);
	$accountData = $userAccounts[$accountID];

	$tmpl->assign('accountID', $accountID);
	$tmpl->assign('serverID', $accountData['serverLocation']['id']);
	$tmpl->assign('credID', $accountData['username']['id']);
	
	$tmpl->assign('serverLocation', $accountData['serverLocation']['value']);
	$tmpl->assign('username', $accountData['username']['value']);
	$tmpl->assign('password', $accountData['password']['value']);
}

return $tmpl->fetchPage();