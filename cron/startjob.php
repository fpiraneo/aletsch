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

    require __DIR__ . '/../libs/dataHandler.php';
    require __DIR__ . '/../libs/aletsch.php';

    // Check for right parameter number
    if($argc != 3) {
        die();
    }
    
    // Check for right job id
    $jobid = intval($argv[1]);
    if($jobid <= 0) {
        die();
    }
    
    // Reverts job informations
    $spooler = new \OCA\aletsch\spoolerHandler();
    $jobData = $spooler->getJobData($jobid);
    
    // Reverts credentials of the job's owner
    $credentials = new \OCA\aletsch\credentialsHandler($jobData['ocusername']);
    $username = $credentials->getUsername();
    $password = $credentials->getPassword();

    // Instance to aletsch
    $vaultData = \OCA\aletsch\aletsch::explodeARN($jobData['vaultarn']);
    $glacier = new \OCA\aletsch\aletsch($vaultData['serverLocation'], $username, $password);
    
    switch($jobData['jobtype']) {
        case 'fileUpload': {
            // Upload a file
            // Check if the file can be accessed
            $filePaths = json_decode($jobData['jobdata']);
            if(!is_file($filePath)) {
                die();
            }

            $description = pathinfo($filePath, PATHINFO_BASENAME);
            $message = date('c') . ' - ' . 'Uploading ' . $filePaths['localPath'] . ' on ' . $vaultData['vaultName'] . ' Description: ' . $description;
            file_put_contents(__DIR__ . 'fpglog.txt', $message, FILE_APPEND);
            
            //$glacier->uploadArchive($vaultData['vaultName'], $filePaths['localPath'], $description);            
        }
    }
