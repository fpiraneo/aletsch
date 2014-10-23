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

return $tmpl->fetchPage();