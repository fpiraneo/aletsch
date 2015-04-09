<?php

/*
 * Copyright 2015 by Francesco PIRANEO G. (fpiraneo@gmail.com)
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

$this->create('aletsch_index', '/')->action(
    function($params){
        require __DIR__ . '/../index.php';
    }
);

// Following routes for ajax 
$this->create('get_cloud_files', 'ajax/getCloudFiles.php')->actionInclude('aletsch/ajax/getCloudFiles.php');
$this->create('save_personal_settings', 'ajax/savePersonalSettings.php')->actionInclude('aletsch/ajax/savePersonalSettings.php');
$this->create('spool_operations', 'ajax/spoolOps.php')->actionInclude('aletsch/ajax/spoolOps.php');
$this->create('vault_operations', 'ajax/vaultOps.php')->actionInclude('aletsch/ajax/vaultOps.php');
