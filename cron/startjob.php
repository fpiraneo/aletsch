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

    require __DIR__ . '/../libs/aletsch.php';
    require __DIR__ . '/../libs/utilities.php';

    // Set arguments names
    $argNames = array(
        'cmdName',
        'username',
        'password',
        'vaultarn',
        'jobtype'
    );

    // Check for right parameter number
    if($argc < count($argNames)) {
        die('Not enough parameters.');
    }
    
    $clp = array_combine($argNames, $argv);
    
    // Instance to aletsch
    $vaultData = \OCA\aletsch\aletsch::explodeARN($clp['vaultarn']);
    $glacier = new \OCA\aletsch\aletsch($vaultData['serverLocation'], $clp['username'], $clp['password']);
    
    switch($clp['jobtype']) {
        // Upload a file
        case 'fileUpload': {
            // Set arguments names
            $argNames = array(
                'cmdName',
                'username',
                'password',
                'vaultarn',
                'jobtype',
                'localPath',
                'statusPath'
            );

            // Check for right parameter number
            if($argc != count($argNames)) {
                die('Not enough parameters.');
            }

            $clp = array_combine($argNames, $argv);

            // Check if the file can be accessed
            if(!is_file($clp['localPath'])) {
                die('Unable to access file.');
            }

            $success = $glacier->uploadArchive($vaultData['vaultName'], $clp['localPath'], NULL, $clp['statusPath']);
            
            break;
        }
        
        // Download a file
        case 'fileDownload': {
            // Set arguments names
            $argNames = array(
                'cmdName',
                'username',
                'password',
                'vaultarn',
                'jobtype',
                'jobid',
                'destPath',
                'statusPath'
            );

            // Check for right parameter number
            if($argc != count($argNames)) {
                die('Not enough parameters.');
            }

            $clp = array_combine($argNames, $argv);

            $tempOutFile = sys_get_temp_dir() . uniqid('/aletsch_out_');
            $success = $glacier->getJobData($vaultData['vaultName'], $clp['jobid'], $clp['destPath'], $clp['statusPath']);
            
            if($success) {
                copy($tempOutFile, $clp['destPath']);
            }
            
            unlink($tempOutFile);
        }
    }
