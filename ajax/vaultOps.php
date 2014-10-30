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

$op = filter_input(INPUT_POST, 'op', FILTER_SANITIZE_STRING);
$vaultARN = filter_input(INPUT_POST, 'vault', FILTER_SANITIZE_URL);

// Prepare result structure
$result = array(
    'opResult' => 'KO',
    'opData' => array(),
    'errData' => array(
        'exCode' => '',
        'exMessage' => ''
    )
);

// If vault is not set, forfait
if(!isset($vaultARN)) {
    $result['opResult'] = 'KO';
    $result['errData']['exCode'] = 'AletschParamError';
    $result['errData']['exMessage'] = 'Vault is not set';
    
    die(json_encode($result));
}

$vaultName = \OCA\aletsch\aletsch::explodeARN($vaultARN, TRUE);

// Open glacier's instance and select right operation
// Retrieve accounts data
$OCUserName = \OCP\User::getUser();
$userAccount = new \OCA\aletsch\credentialsHandler($OCUserName);

$serverLocation = $userAccount->getServerLocation();
$username = $userAccount->getUsername();
$password = $userAccount->getPassword();

$serverAvailableLocations = \OCA\aletsch\aletsch::getServersLocation();

// Create instance to glacier
$glacier = new \OCA\aletsch\aletsch($serverLocation, $username, $password);

switch($op) {
    // Refresh inventory
    case 'refreshInventory': {
        try {
            $inventoryOpRes = $glacier->getInventory($vaultName);
        }
        catch(Aws\Glacier\Exception\GlacierException $ex) {
            $result['opResult'] = 'KO';
            $result['errData']['exCode'] = $ex->getExceptionCode();
            $result['errData']['exMessage'] = $ex->getMessage();

            die(json_encode($result));
        }
        
        $result['opResult'] = 'OK';
        $result['opData'] = $inventoryOpRes;

        die(json_encode($result));
        
        break;
    }
    
    case 'getJobsList': {
        try {
            $jobs = $glacier->listJobs($vaultName);
        }
        catch(Aws\Glacier\Exception\GlacierException $ex) {
            $result['opResult'] = 'KO';
            $result['errData']['exCode'] = $ex->getExceptionCode();
            $result['errData']['exMessage'] = $ex->getMessage();

            die(json_encode($result));
        }
        
        $jobsTable = \OCA\aletsch\utilities::prepareJobList($jobs);
        
        $result['opResult'] = 'OK';
        $result['opData'] = $jobsTable;

        die(json_encode($result));
        break;
    }
    
    case 'getInventory': {
        $jobID = filter_input(INPUT_POST, 'jobid', FILTER_SANITIZE_STRING);

        // If vault is not set, forfait
        if(!isset($jobID)) {
            $result['opResult'] = 'KO';
            $result['errData']['exCode'] = 'AletschParamError';
            $result['errData']['exMessage'] = 'Job ID is not set';

            die(json_encode($result));
        }        
        
        try {
            $tmpFilePath = tempnam(sys_get_temp_dir(), uniqid("aletsch_"));
            $inventoryData = $glacier->getInventoryResult($vaultName, $jobID, $tmpFilePath);
        }
        catch (Aws\Glacier\Exception\GlacierException $ex) {
            $result['opResult'] = 'KO';
            $result['errData']['exCode'] = $ex->getExceptionCode();
            $result['errData']['exMessage'] = $ex->getMessage();

            die(json_encode($result));
        }
        
        // Get credentials ID of this user
        $OCUserName = \OCP\User::getUser();
        $credentials = new \OCA\aletsch\credentialsHandler($OCUserName);
        $credentials->load();
        $credID = $credentials->getCredID();
        
        // Save this inventory on DB
        $inventory = new \OCA\aletsch\inventoryHandler($inventoryData);
        $inventory->saveOnDB($credID);
        
        $inventoryDetails = array(
            'date' => $inventory->getInventoryDate(),
            'archives' => $inventory->getArchives()
        );

        $result['opResult'] = 'OK';
        $result['opData'] = json_encode($inventoryDetails);

        die(json_encode($result));
    }
    
    // Unrecognised operation fallback
    default: {
        $result['opResult'] = 'KO';
        $result['errData']['exCode'] = 'AletschParamError';
        $result['errData']['exMessage'] = 'Unrecognized operation';

        die(json_encode($result));

        break;
    }
}

