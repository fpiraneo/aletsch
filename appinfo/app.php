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

\OCP\App::addNavigationEntry(array(

    // the string under which your app will be referenced in owncloud
    'id' => 'aletsch',

    // sorting weight for the navigation. The higher the number, the higher
    // will it be listed in the navigation
    'order' => 10,

    // the route that will be shown on startup
    'href' => \OCP\Util::linkToRoute('aletsch_index'),

    // the icon that will be shown in the navigation
    // this file needs to exist in img/example.svg
    'icon' => \OCP\Util::imagePath('aletsch', 'TK_aletsch_icon.svg'),

    // the title of your application. This will be used in the
    // navigation or on the settings page of your app
    'name' => 'Aletsch'
));

\OCP\App::registerPersonal('aletsch', 'personal-settings');
\OCP\Util::addScript('aletsch', 'sendCloudFile');
\OCP\Util::addStyle('aletsch','upload');
\OCP\Backgroundjob::addRegularTask('\OCA\aletsch\cron\spooler', 'run');