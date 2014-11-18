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

    // Right parameter combine
    switch($argv[1]) {
        // Upload a file
        case 'fileUpload': {
            // Set arguments names
            $argNames = array(
                'cmdName',
                'jobtype',
                'username',
                'password',
                'vaultarn',
                'localPath',
                'statusPath',
                'description'
            );

            // Check for right parameter number
            if($argc != count($argNames)) {
                error_log('fileUpload: Not enough parameters - Passed:' . $argc . ', required: ' . count($argNames) . ' - List: ' . implode(',', $argv));
                die();
            }

            break;
        }
        // Download a file
        case 'fileDownload': {
            // Set arguments names
            $argNames = array(
                'cmdName',
                'jobtype',
                'username',
                'password',
                'vaultarn',
                'jobid',
                'destPath',
                'statusPath'
            );

            // Check for right parameter number
            if($argc != count($argNames)) {
                error_log('fileDownload: Not enough parameters - Passed:' . $argc . ', required: ' . count($argNames) . ' - List: ' . implode(',', $argv));
                die();
            }

            break;
        }
        
        default: {
            error_log('Invalid option for jobType: ' . $argv[1]);
            die();
            break;
        }
    }

    $clp = array_combine($argNames, $argv);

    // Instance to aletsch
    $vaultData = \OCA\aletsch\aletsch::explodeARN($clp['vaultarn']);
    $glacier = new \OCA\aletsch\aletsch($vaultData['serverLocation'], $clp['username'], $clp['password']);
    
    switch($clp['jobtype']) {
        // Upload a file
        case 'fileUpload': {
            // Check if the file can be accessed
            if(!is_file($clp['localPath'])) {
                $progress = array(
                    'status'    => 'error',
                    'extStatus' => 'fileUpload: Unable to access file'
                );

                file_put_contents($clp['statusPath'], json_encode($progress));

                error_log('fileUpload: Unable to access file: ' . $clp['localPath']);
                die();
            }

            error_log(sprintf("fileUpload: [DEBUG ] localPath: %s, Description: %s", $clp['localPath'], $clp['description']));
            $success = $glacier->uploadArchive($vaultData['vaultName'], $clp['localPath'], $clp['description'], $clp['statusPath']);
            
            break;
        }
        
        // Download a file
        case 'fileDownload': {
            $tempOutFile = sys_get_temp_dir() . uniqid('/aletsch_out_');
            $success = $glacier->getJobData($vaultData['vaultName'], $clp['jobid'], $tempOutFile, $clp['statusPath']);
            
            if($success) {
                mkdir(dirname($clp['destPath']), 0755, TRUE);
                copy($tempOutFile, $clp['destPath']);
            } else {
                $progress = array(
                    'status'    => 'error',
                    'extStatus' => 'fileDownload: Unable to download file'
                );

                file_put_contents($clp['statusPath'], json_encode($progress));                
            }
            
            unlink($tempOutFile);
            
            break;
        }
    }
