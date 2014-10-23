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

OCP\JSON::checkAppEnabled('aletsch');
OCP\User::checkLoggedIn();

$OCUserName = \OCP\User::getUser();

$credID = filter_input(INPUT_POST, 'credid', FILTER_SANITIZE_NUMBER_INT);
$serverLocation = filter_input(INPUT_POST, 'serverLocation', FILTER_SANITIZE_STRING);
$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
$password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

$userAccount = new OCA\aletsch\credentialsHandler($OCUserName);
$userAccount->setServerLocation($serverLocation);
$userAccount->setUsername($username);
$userAccount->setPassword($password);

print 'OK';
