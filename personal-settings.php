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

// Retrieve accounts
$OCUserName = \OCP\User::getUser();
$userAccounts = OCA\aletsch\accountHandler::getAccountsTree($OCUserName);









$serverLocation = OCP\Config::getAppValue('aletsch', 'serverLocation');
$username = OCP\Config::getAppValue('aletsch', 'username');
$password = OCP\Config::getAppValue('aletsch', 'password');

$tmpl = new \OCP\Template('aletsch', 'personal-settings');

$tmpl->assign('serverLocation', $serverLocation);
$tmpl->assign('username', $username);
$tmpl->assign('password', $password);

return $tmpl->fetchPage();