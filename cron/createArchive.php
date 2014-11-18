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

    $progress = array(
        'pid'		=> getmypid(),
        'offline'   	=> NULL,
        'totalRead' 	=> 0,
        'totalWritten'	=> 0,
        'processedFiles'    => 0,
        'fileRead'      => 0,
        'totalFiles'	=> 1,
        'thisFilePath'	=> '',
        'thisFilePerc'	=> ''
    );


    // Right parameter combine
    switch($argv[1]) {
        // Upload a file
        case 'newArchive': {
            // Set arguments names
            $argNames = array(
                'cmdName',
                'jobtype',
                'username',
                'password',
                'vaultarn',
                'instructionsFilePath',
                'statusPath',
                'immediateUpload'
            );

            // Check for right parameter number
            if($argc != count($argNames)) {
                $progress = array(
                    'status'    => 'error',
                    'extStatus' => 'newArchive: Not enough parameters'
                );

                file_put_contents($clp['statusPath'], json_encode($progress));

                error_log('newArchive: Not enough parameters - Passed:' . $argc . ', required: ' . count($argNames) . ' - List: ' . implode(',', $argv));
                die();
            }

            break;
        }

        default: {
            $progress = array(
                'status'    => 'error',
                'extStatus' => 'Invalid option for jobType: ' . $argv[1]
            );

            file_put_contents($clp['statusPath'], json_encode($progress));

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
        case 'newArchive': {
            // Check if the file can be accessed
            if(!is_file($clp['instructionsFilePath'])) {
                $progress = array(
                    'status'    => 'error',
                    'extStatus' => 'newArchive: Unable to access file: ' . $clp['instructionsFilePath']
                );
                
                file_put_contents($clp['statusPath'], json_encode($progress));
                
                error_log('newArchive: Unable to access file: ' . $clp['instructionsFilePath']);
                die();
            }

            // Reverts parameters from instruction file
            $instructionsJSON = file_get_contents($clp['instructionsFilePath']);
            $instructions = json_decode($instructionsJSON, TRUE);
            
            // Create a new archiver and add all files
            $vaultData = \OCA\aletsch\aletsch::explodeARN($clp['vaultarn']);
            $glacier = new \OCA\aletsch\aletsch($vaultData['serverLocation'], $clp['username'], $clp['password']);
            
            $glacier->cleanupArchiver();
            
            foreach($instructions['files'] as $pathToAdd) {
                $fileID = $glacier->addFileToArchiver($pathToAdd);
                
                if($fileID === FALSE) {
                    $progress = array(
                        'status'            => 'error',
                        'extStatus'         => 'Unable to access file ' . $pathToAdd . ' while addFileToArchiver.'
                    );

                    file_put_contents($clp['statusPath'], json_encode($progress));

                    // We are compelled to log to php log because we don't have the ownCloud context
                    error_log('aletsch - ' . $progress['extStatus']);
                    
                    die();
                }
            }
            
            // Create the archive
            try {
                $summary = $glacier->archive($vaultData['vaultName'], $instructions['archiveName'], $clp['statusPath']);
            }
            catch(Aws\Glacier\Exception\GlacierException $ex) {
                $progress = array(
                    'status'            => 'error',
                    'extStatus'         => $ex->getExceptionCode() . ' - ' . $ex->getMessage()
                );
                
                file_put_contents($clp['statusPath'], json_encode($progress));

                // We are compelled to log to php log because we don't have the ownCloud context
                error_log('aletsch - ' . $exCode . ' - ' . $exMessage);

                die();
            }
            
            // Check for good result
            if($summary === FALSE) {
                error_log('aletsch - No summary, something went wrong while archive.');
                die();
            }

            // Unlink instruction file
            unlink($clp['instructionsFilePath']);
            
            break;
        }
    }
