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
$op = filter_input(INPUT_POST, 'op', FILTER_SANITIZE_STRING);

// Prepare result structure
$result = array(
    'opResult' => 'KO',
    'opData' => array(),
    'errData' => array(
        'exCode' => '',
        'exMessage' => ''
    )
);

// Check if operation has been set
if(!isset($op)) {
    $result = array(
        'opResult' => 'KO',
        'opData' => array(),
        'errData' => array(
            'exCode' => 'AletschParamError',
            'exMessage' => 'Operation code not set'
        )
    );

    \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);
    
    die(json_encode($result));
}

//
// Action switcher
//
switch($op) {
    // Get spool contents for given user
    case 'getOps': {
        $asHtml = filter_input(INPUT_POST, 'ashtml', FILTER_SANITIZE_NUMBER_INT);
        
        $spoolerHandler = new \OCA\aletsch\spoolerHandler($OCUserName);
        $spool = $spoolerHandler->getOperations();
        
        $result['opResult'] = 'OK';
        $result['opData'] = ($asHtml) ? \OCA\aletsch\utilities::prepareSpoolerList($spool, TRUE) : $spool;

        die(json_encode($result));
        
        break;
    }
    
    // Add upload operation to spool
    case 'addUploadOp': {
        $filePath = filter_input(INPUT_POST, 'filePath', FILTER_SANITIZE_URL);
        $localPath = \OC\Files\Filesystem::getLocalFolder($filePath);
        
        if(!isset($filePath)) {
            $result = array(
                'opResult' => 'KO',
                'opData' => array(),
                'errData' => array(
                    'exCode' => 'AletschParamError',
                    'exMessage' => 'File path not set'
                )
            );

            \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);

            die(json_encode($result));
        }
        
        $spoolerHandler = new \OCA\aletsch\spoolerHandler($OCUserName);
        $jobID = $spoolerHandler->newJob('fileUpload');
        $jobData = array(
            'filePath' => $filePath,
            'localPath' => $localPath,
            'statusPath' => sys_get_temp_dir() . uniqid('/aletsch_sts_')
        );
        $spoolerHandler->setJobData($jobID, json_encode($jobData));
        
        $result['opResult'] = 'OK';
        $result['opData'] = '';

        die(json_encode($result));
        
        break;
    }
    
    // Add download operation to spool
    case 'addDownloadOp': {
        $archiveID = filter_input(INPUT_POST, 'archiveID', FILTER_SANITIZE_URL);
        $glacierJobID = filter_input(INPUT_POST, 'glacierJobID', FILTER_SANITIZE_URL);
        $vaultARN = filter_input(INPUT_POST, 'vaultARN', FILTER_SANITIZE_STRING);
        
        if(!isset($archiveID) || !isset($glacierJobID)) {
            $result = array(
                'opResult' => 'KO',
                'opData' => array(),
                'errData' => array(
                    'exCode' => 'AletschParamError',
                    'exMessage' => 'Either archiveID or glacier job is are not set'
                )
            );

            \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);

            die(json_encode($result));
        }
        
        // Recover archive's description and try to use it as destination file name
        // Obviously the file name should be plausible
        $inventory = new \OCA\aletsch\inventoryHandler();
        $inventory->loadFromDB($vaultARN);
        $archives = $inventory->getArchives();
        $fileName = NULL;
        
        foreach($archivesList as $entry) {
            if($entry['ArchiveId'] === $archiveID) {
                $fileName = $entry['ArchiveDescription'];
                break;
            }
        }

        // Archive description not found - Assign a random name
        if(is_null($fileName)) {
            $fileName = uniqid('archive_');
        } else {
            // Check if description can be used as file name - Assign a truncated file name otherwise
            // We use maximum 255 characters to maintain a compatibility on all file systems that can be used
            $fileName = substr($fileName, 0, 255);
        }
        
        $destPath = \OC_User::getHome($user) . '/files/' . $fileName;
        
        $spoolerHandler = new \OCA\aletsch\spoolerHandler($OCUserName);
        $jobID = $spoolerHandler->newJob('fileDownload');
        $jobData = array(
            'filePath' => $fileName,
            'destPath' => $destPath,
            'statusPath' => sys_get_temp_dir() . uniqid('/aletsch_sts_'),
            'jobID' => $glacierJobID
        );
        $spoolerHandler->setJobData($jobID, json_encode($jobData));
        
        $result['opResult'] = 'OK';
        $result['opData'] = '';

        die(json_encode($result));
        
        break;
    }
    
    // Change job attribute
    case 'changeJobAttribute': {
        $attr = filter_input(INPUT_POST, 'attr', FILTER_SANITIZE_STRING);
        $vaultARN = filter_input(INPUT_POST, 'vaultARN', FILTER_SANITIZE_STRING);
        $jobID = filter_input(INPUT_POST, 'jobid', FILTER_SANITIZE_STRING);
        
        if(!isset($attr) || !isset($vaultARN) || !isset($jobID)) {
            $result = array(
                'opResult' => 'KO',
                'opData' => array(),
                'errData' => array(
                    'exCode' => 'AletschParamError',
                    'exMessage' => 'Essential parameter not set'
                )
            );

            \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);

            die(json_encode($result));
        }
        
        $spoolerHandler = new \OCA\aletsch\spoolerHandler($OCUserName);
        
        switch($attr) {
            case 'vaultarn': {
                $spoolerHandler->setVaultARN($jobID, $vaultARN);
                break;
            }
            
            default: {
                $result = array(
                    'opResult' => 'KO',
                    'opData' => array(),
                    'errData' => array(
                        'exCode' => 'AletschParamError',
                        'exMessage' => 'Parameter to set not handled'
                    )
                );

                \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);

                die(json_encode($result));
                
                break;
            }
        }
        
        $result['opResult'] = 'OK';
        $result['opData'] = '';

        die(json_encode($result));
        
        break;
    }
    
    case 'changeJobStatus': {
        $jobIDsJSON = filter_input(INPUT_POST, 'jobid', FILTER_SANITIZE_STRING);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

        $jobIDs = json_decode($jobIDsJSON);
        
        // Check for right format of jobid
        if(!is_array($jobIDs)) {
            $result = array(
                'opResult' => 'KO',
                'opData' => array(),
                'errData' => array(
                    'exCode' => 'AletschParamError',
                    'exMessage' => 'Parameter jobid in wrong format - Must be an array'
                )
            );

            \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);

            die(json_encode($result));
        }
        
        // Change job status
        $spoolerHandler = new \OCA\aletsch\spoolerHandler($OCUserName);
        
        foreach($jobIDs as $jobID) {
            $actualStatus = $spoolerHandler->getJobStatus($jobID);
            $statusChangeResult = FALSE;
            
            if($actualStatus === 'hold') {
                $currentARN = $spoolerHandler->getVaultARN($jobID);
                $ARNPrefix = substr($currentARN, 0, 16);
                if($ARNPrefix !== 'arn:aws:glacier:') {
                    $statusChangeResult = $spoolerHandler->setJobStatus($jobID, 'error');
                    $spoolerHandler->setJobDiagnostic($jobID, 'Vault name is not set.');
                } else {
                    $statusChangeResult = $spoolerHandler->setJobStatus($jobID, $status);
                }

                if(!$statusChangeResult) {
                    $result = array(
                        'opResult' => 'KO',
                        'opData' => array(),
                        'errData' => array(
                            'exCode' => 'AletschParamError',
                            'exMessage' => 'Parameter job id or jobstatus not valid'
                        )
                    );

                    \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);

                    die(json_encode($result));
                }
            } else {
                if(!$statusChangeResult) {
                    $result = array(
                        'opResult' => 'KO',
                        'opData' => array(),
                        'errData' => array(
                            'exCode' => 'AletschWrongJobState',
                            'exMessage' => 'Job status must be \'hold\' to be released'
                        )
                    );

                    \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);

                    die(json_encode($result));
                }
            }
        }

        $result['opResult'] = 'OK';
        $result['opData'] = '';

        die(json_encode($result));
        
        break;
    }
    
    case 'resetJobStatus': {
        $jobIDsJSON = filter_input(INPUT_POST, 'jobid', FILTER_SANITIZE_STRING);

        $jobIDs = json_decode($jobIDsJSON);
        
        // Check for right format of jobid
        if(!is_array($jobIDs)) {
            $result = array(
                'opResult' => 'KO',
                'opData' => array(),
                'errData' => array(
                    'exCode' => 'AletschParamError',
                    'exMessage' => 'Parameter jobid in wrong format - Must be an array'
                )
            );

            \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);

            die(json_encode($result));
        }
        
        // Reset job status
        $spoolerHandler = new \OCA\aletsch\spoolerHandler($OCUserName);
        
        foreach($jobIDs as $jobID) {
            $actualStatus = $spoolerHandler->getJobStatus($jobID);
            if($actualStatus === 'error') {
                $statusChangeResult = $spoolerHandler->setJobStatus($jobID, 'hold');
                $spoolerHandler->setJobDiagnostic($jobID, '');
            } else {
                $result = array(
                    'opResult' => 'KO',
                    'opData' => array(),
                    'errData' => array(
                        'exCode' => 'AletschWrongJobState',
                        'exMessage' => 'Job status must be \'error\' to be reset.'
                    )
                );

                \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);

                die(json_encode($result));
            }
            
            if(!$statusChangeResult) {
                $result = array(
                    'opResult' => 'KO',
                    'opData' => array(),
                    'errData' => array(
                        'exCode' => 'AletschParamError',
                        'exMessage' => 'Parameter job id or jobstatus not valid'
                    )
                );

                \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);

                die(json_encode($result));
            }
        }

        $result['opResult'] = 'OK';
        $result['opData'] = '';

        die(json_encode($result));
        
        break;
    }
    
    case 'removeJob': {
        $jobIDsJSON = filter_input(INPUT_POST, 'jobid', FILTER_SANITIZE_STRING);

        $jobIDs = json_decode($jobIDsJSON);
        
        // Check for right format of jobid
        if(!is_array($jobIDs)) {
            $result = array(
                'opResult' => 'KO',
                'opData' => array(),
                'errData' => array(
                    'exCode' => 'AletschParamError',
                    'exMessage' => 'Parameter jobid in wrong format - Must be an array'
                )
            );

            \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);

            die(json_encode($result));
        }
        
        // Remove the job
        $spoolerHandler = new \OCA\aletsch\spoolerHandler($OCUserName);
        
        foreach($jobIDs as $jobID) {
            $actualStatus = $spoolerHandler->getJobStatus($jobID);
            if($actualStatus !== 'running') {
                $spoolerHandler->removeJob($jobID);
            } else {
                $result = array(
                    'opResult' => 'KO',
                    'opData' => array(),
                    'errData' => array(
                        'exCode' => 'AletschWrongJobState',
                        'exMessage' => 'Job status must not be \'running\' to be removed.'
                    )
                );

                \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);

                die(json_encode($result));
            }            
        }

        $result['opResult'] = 'OK';
        $result['opData'] = '';

        die(json_encode($result));
        
        break;
    }
    
    // Unrecognised operation fallback
    default: {
        $result['opResult'] = 'KO';
        $result['errData']['exCode'] = 'AletschParamError';
        $result['errData']['exMessage'] = 'Unrecognized operation';

        \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'] . ': ' . $op, 0);
    
        die(json_encode($result));

        break;
    }
}

