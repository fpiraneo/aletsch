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

OCP\User::checkAdminUser();

// Handle translations
$l = new \OC_L10N('aletsch');

$var1 = OCP\Config::getAppValue('aletsch', 'settingName');

/*
 * To save the value on DB, call via Ajax the following
 * $result = OCP\Config::setAppValue('aletsch', 'settingName', 'Value to save');
 */

$tmpl = new \OCP\Template('aletsch', 'admin-settings');

return $tmpl->fetchPage();