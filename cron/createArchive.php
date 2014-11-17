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
$newVaultName = filter_input(INPUT_POST, 'newVaultName', FILTER_SANITIZE_STRING);

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
if(!isset($vaultARN) && !isset($newVaultName)) {
    $result['opResult'] = 'KO';
    $result['errData']['exCode'] = 'AletschParamError';
    $result['errData']['exMessage'] = 'Vault is not set';
    \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);
    
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

// Retrieve vaults list
try {
    $vaults = $glacier->vaultList();
}
catch(Aws\Glacier\Exception\GlacierException $ex) {
    $result['opResult'] = 'KO';
    $result['errData']['exCode'] = $ex->getExceptionCode();
    $result['errData']['exMessage'] = $ex->getMessage();
    
    \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);

    die(json_encode($result));
}

// Update stored vault data
$userCredID = $userAccount->getCredID();
$vaultHandler = new \OCA\aletsch\vaultHandler($userCredID);
$vaultHandler->update($vaults);

//
// Action switcher
//
switch($op) {
    // Get vaults list
    case 'vaultList': {
        // Retrieve vaults list
        try {
            $vaults = $glacier->vaultList();
        }
        catch(Aws\Glacier\Exception\GlacierException $ex) {
            $result['opResult'] = 'KO';
            $result['errData']['exCode'] = $ex->getExceptionCode();
            $result['errData']['exMessage'] = $ex->getMessage();

            \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);
    
            die(json_encode($result));
        }
        
        $result['opResult'] = 'OK';
        $result['opData'] = $vaults;

        die(json_encode($result));
        
        break;
    }
    
    // Create new vault
    case 'createVault': {
        try {
            $createNew = $glacier->createVault($newVaultName);
        }
        catch(Aws\Glacier\Exception\GlacierException $ex) {
            $result['opResult'] = 'KO';
            $result['errData']['exCode'] = $ex->getExceptionCode();
            $result['errData']['exMessage'] = $ex->getMessage();

            \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);
    
            die(json_encode($result));
        }
        
        $result['opResult'] = 'OK';
        $result['opData'] = $createNew;

        die(json_encode($result));
        
        break;
    }
    
    // Create new vault
    case 'deleteVault': {
        try {
            $deleteResult = $glacier->deleteVault($vaultName);
        }
        catch(Aws\Glacier\Exception\GlacierException $ex) {
            $result['opResult'] = 'KO';
            $result['errData']['exCode'] = $ex->getExceptionCode();
            $result['errData']['exMessage'] = $ex->getMessage();

            \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);
    
            die(json_encode($result));
        }
        
        $result['opResult'] = 'OK';
        $result['opData'] = $deleteResult;

        die(json_encode($result));
        
        break;
    }
    
    // Refresh inventory
    case 'refreshInventory': {
        try {
            $inventoryOpRes = $glacier->getInventory($vaultName);
        }
        catch(Aws\Glacier\Exception\GlacierException $ex) {
            $result['opResult'] = 'KO';
            $result['errData']['exCode'] = $ex->getExceptionCode();
            $result['errData']['exMessage'] = $ex->getMessage();

            \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);
    
            die(json_encode($result));
        }
        
        $result['opResult'] = 'OK';
        $result['opData'] = $inventoryOpRes;

        die(json_encode($result));
        
        break;
    }
    
    // Get actual job list
    case 'getJobsList': {
        try {
            $jobs = $glacier->listJobs($vaultName);
        }
        catch(Aws\Glacier\Exception\GlacierException $ex) {
            $result['opResult'] = 'KO';
            $result['errData']['exCode'] = $ex->getExceptionCode();
            $result['errData']['exMessage'] = $ex->getMessage();

            \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);
    
            die(json_encode($result));
        }
        
        $jobsTable = \OCA\aletsch\utilities::prepareJobList($jobs);
        
        $result['opResult'] = 'OK';
        $result['opData'] = $jobsTable;

        die(json_encode($result));
        break;
    }
    
    // Get inventory from DB
    case 'getInventory': {
        $inventory = new \OCA\aletsch\inventoryHandler();
        $inventoryID = $inventory->loadFromDB($vaultARN);
        $lastGlacierInventoryDate = $vaultHandler->getLastInventory($vaultARN);
        $lastDBInventoryDate = $inventory->getInventoryDate();
        $inventoryDetails = array(
            'date' => $inventory->getInventoryDate(),
            'outdated' => ($lastGlacierInventoryDate !== $lastDBInventoryDate) && $lastDBInventoryDate !== NULL,
            'archiveList' => \OCA\aletsch\utilities::prepareArchivesList($inventory->getArchives(), TRUE)
        );            

        $result['opResult'] = 'OK';
        $result['opData'] = $inventoryDetails;

        die(json_encode($result));

        break;
    }
    
    // Get inventory from glacier
    case 'getInventoryResult': {
        $jobID = filter_input(INPUT_POST, 'jobid', FILTER_SANITIZE_STRING);

        // If jobid is not set, forfait
        if(!isset($jobID)) {
            $result['opResult'] = 'KO';
            $result['errData']['exCode'] = 'AletschParamError';
            $result['errData']['exMessage'] = 'Job ID is not set';

            die(json_encode($result));
        }        
        
        try {
            $tmpFilePath = tempnam(sys_get_temp_dir(), uniqid('/aletsch_'));
            $inventoryData = $glacier->getInventoryResult($vaultName, $jobID, $tmpFilePath);
        }
        catch (Aws\Glacier\Exception\GlacierException $ex) {
            $result['opResult'] = 'KO';
            $result['errData']['exCode'] = $ex->getExceptionCode();
            $result['errData']['exMessage'] = $ex->getMessage();

            \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);
    
            die(json_encode($result));
        }
        
        // Get credentials ID of this user
        $OCUserName = \OCP\User::getUser();
        $credentials = new \OCA\aletsch\credentialsHandler($OCUserName);
        $credentials->load();
        $credID = $credentials->getCredID();
        
        // Save this inventory on DB
        $inventory = new \OCA\aletsch\inventoryHandler();
        $inventory->setDataFromInventory($inventoryData);
        $inventory->saveOnDB($credID);
        
        $inventoryDetails = array(
            'date' => $inventory->getInventoryDate(),
            'archives' => \OCA\aletsch\utilities::prepareArchivesList($inventory->getArchives(), TRUE)
        );

        $result['opResult'] = 'OK';
        $result['opData'] = $inventoryDetails;

        die(json_encode($result));
    }
    
    // Delete archives
    case 'deleteArchives': {
        $archives = json_decode(filter_input(INPUT_POST, 'archives', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES), TRUE);
        
        foreach($archives as $archiveID) {
            $result = $glacier->deleteArchive($vaultName, $archiveID);
        }
        
        $result['opResult'] = 'OK';
        $result['opData'] = '';

        die(json_encode($result));

        break;
    }
    
    // Retrieve archives
    case 'retrieveArchives': {
        $archives = json_decode(filter_input(INPUT_POST, 'archives', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES), TRUE);

        try {
            foreach($archives as $archiveID) {
                $retrieveOpRes = $glacier->retrieveArchive($vaultName, $archiveID);
            }
        }
        catch(Aws\Glacier\Exception\GlacierException $ex) {
            $result['opResult'] = 'KO';
            $result['errData']['exCode'] = $ex->getExceptionCode();
            $result['errData']['exMessage'] = $ex->getMessage();

            \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);
    
            die(json_encode($result));
        }
        
        $result['opResult'] = 'OK';
        $result['opData'] = $retrieveOpRes;

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

