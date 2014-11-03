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
 
namespace OCA\aletsch;

class credentialsHandler {
	private $OCUserName = NULL;	
	private $credID = NULL;
	private $serverLocation = NULL;
	private $username = NULL;
	private $password = NULL;

    /**
     * Class constructor
     */
    function __construct($OCUserName) {
		$this->OCUserName = $OCUserName;
		$this->load();
    }
	
    function setUsername($username) {
            $this->username = $username;
            $this->save();
    }

    function setPassword($password) {
            $this->password = $password;
            $this->save();
    }

    function setServerLocation($serverLocation) {
            $this->serverLocation = $serverLocation;
            $this->save();
    }

    function getCredID() {
            return $this->credID;
    }

    function getServerLocation() {
            return $this->serverLocation;
    }

    function getUsername() {
            return $this->username;
    }

    function getPassword() {
            return $this->password;
    }

    function load() {
        $this->credID = NULL;
        $this->serverLocation = NULL;
        $this->username = NULL;
        $this->password = NULL;

        $sql = "SELECT credid, serverLocation, username, password FROM *PREFIX*aletsch_credentials WHERE ocusername=?";
        $args = array($this->OCUserName);

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);

        $row = $resRsrc->fetchRow();
        if($row !== FALSE) {
            $this->credID = $row['credid'];
            $this->serverLocation = $row['serverLocation'];
            $this->username = $row['username'];
            $this->password = $row['password'];
        }

        return $this->credID;
    }

    private function save() {
        if($this->serverLocation !== NULL && $this->username !== NULL && $this->password !== NULL) {
            if($this->credID === NULL) {
                // Insert new credentials
                $sql = "INSERT INTO *PREFIX*aletsch_credentials (ocusername, serverLocation, username, password) VALUES (?,?,?,?)";
                $args = array($this->OCUserName, $this->serverLocation, $this->username, $this->password);
                $query = \OCP\DB::prepare($sql);
                $resRsrc = $query->execute($args);

                // Get inserted index
                $this->credID = \OCP\DB::insertid();
            } else {
                $sql = 'UPDATE *PREFIX*aletsch_credentials SET serverLocation=?, username=?, password=? WHERE credid=?';
                $args = array($this->serverLocation, $this->username, $this->password, $this->credID);
                $query = \OCP\DB::prepare($sql);
                $resRsrc = $query->execute($args);
            }
        }
    }
}

class vaultHandler {
    private $credID = NULL;
    private $vaults = array();
    private $allVaultsSize = 0;
    
    function __construct($credID) {
            $this->credID = $credID;
            $this->load();
    }

    /**
     * Get list of stored vaults on DB
     * @return Array
     */
    function getVaults() {
        return $this->vaults;
    }
    
    /**
     * Return vault full size
     * @return Int Vault size in bytes
     */
    function getAllVaultSize() {
        return $this->allVaultsSize;
    }
    
    /**
     * Get last inventory date on provided vault
     * @param String $vaultARN Vault ARN to get data to
     * @return String Last inventory date or FALSE if not ARN provided
     */
    function getLastInventory($vaultARN) {
        // No valid data provided
        if(trim($vaultARN) === '') {
            return FALSE;
        }
        
        // Return vault last updated (if found)
        return $this->vaults[$vaultARN]['lastinventory'];
    }
    
    /**
     * Load vaults stored on DB
     */
    function load() {
        $this->vaults = array();
        
        $sql = "SELECT vaultid, vaultarn, creationdate, lastinventory, numberofarchives, sizeinbytes FROM *PREFIX*aletsch_vaults WHERE credid=?";
        $args = array($this->credID);

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);

        $this->allVaultsSize = 0;
        
        while($row = $resRsrc->fetchRow()) {
            $vaultData = array(
                'vaultid' => $row['vaultid'],
                'creationdate' => $row['creationdate'],
                'lastinventory' => $row['lastinventory'],
                'numberofarchives' => $row['numberofarchives'],
                'sizeinbytes' => $row['sizeinbytes']
            );
            
            $this->vaults[$row['vaultarn']] = $vaultData;
            $this->allVaultsSize += $row['sizeinbytes'];
        }        
    }
    
    /**
     * Update stored vault on DB
     * @param Array $vaults Vaults description as returned by Amazon class
     */
    function update($vaults) {
        // If not array provided - Forfait
        if(!is_array($vaults)) {
            return FALSE;
        }
        
        // Proceed with update
        foreach($vaults as $vault) {
            // Check if vaultARN already exists
            if(isset($this->vaults[$vault['VaultARN']])) {
                // Update just `lastinventory`, `numberofarchives` and `sizeinbytes`
                $sql = 'UPDATE *PREFIX*aletsch_vaults SET lastinventory=?, numberofarchives=?, sizeinbytes=? WHERE vaultid=?';
                $args = array(
                    isset($vault['LastInventoryDate']) ? $vault['LastInventoryDate'] : NULL,
                    $vault['NumberOfArchives'],
                    $vault['SizeInBytes'],
                    $this->vaults[$vault['VaultARN']]['vaultid']
                );
                $query = \OCP\DB::prepare($sql);
                $resRsrc = $query->execute($args);                
            } else {
                // New vault - Proceed to insert
                $sql = "INSERT INTO *PREFIX*aletsch_vaults (credid, vaultarn, creationdate, lastinventory, numberofarchives, sizeinbytes) VALUES (?,?,?,?,?,?)";
                $args = array(
                    $this->credID,
                    $vault['VaultARN'],
                    $vault['CreationDate'],
                    $vault['LastInventoryDate'],
                    $vault['NumberOfArchives'],
                    $vault['SizeInBytes']
                );
                $query = \OCP\DB::prepare($sql);
                $resRsrc = $query->execute($args);
            }      
        }
        
        // Remove from DB vaults that no longer exists
        $remoteARN = array_column($vaults, 'VaultARN');
        foreach($this->vaults as $arn => $vault) {
            if(in_array($arn, $remoteARN) === FALSE) {
                // Delete entry on vaults table
                $sql = "DELETE FROM *PREFIX*aletsch_vaults WHERE vaultid=?";
                $args = array($vault['vaultid']);

                $query = \OCP\DB::prepare($sql);
                $resRsrc = $query->execute($args);
                
                // Delete all stored inventories
                \OCA\aletsch\inventoryHandler::removeInventories($arn);
            }
        }
        
        // Refresh local structure
        $this->load();
        
        // End
        return TRUE;
    }
}

class inventoryHandler {
    private $inventoryDate = NULL;
    private $vaultArn = NULL;
    private $archives = array();
    
    function setDataFromInventory($inventoryData) {
        // Set the date
        $this->inventoryDate = $inventoryData->InventoryDate;
        
        // Save vault's ARN
        $this->vaultArn = $inventoryData->VaultARN;
        
        // Set archives data
        $this->archives = $inventoryData->ArchiveList;
    }

    /**
     * Get actual inventory date
     * @return String
     */
    function getInventoryDate() {
        return $this->inventoryDate;
    }

    /**
     * Get actual inventory
     * @return Array
     */
    function getArchives() {
        return $this->archives;
    }
    
    /**
     * Get actual vault's ARN
     * @return String
     */
    function getVaultArn() {
        return $this->vaultArn;
    }
    
    /**
     * Save actual inventory on DB
     * @param Integer $credID Credentials under to store the actual inventory
     */
    function saveOnDB($credID=NULL) {
        // If credential is not provided, forfait
        if(is_null($credID)) {
            return FALSE;
        }
        
        // Build and compress archives data
        $archives = json_encode($this->archives);
        $comprArchives = gzcompress($archives);
        
        // Remove old data
        $sql = "DELETE FROM *PREFIX*aletsch_inventories WHERE credid=? AND vaultarn=?";
        $args = array($credID, $this->vaultArn);

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);                
        
        // Insert new inventory
        $sql = "INSERT INTO *PREFIX*aletsch_inventories (credid, vaultarn, inventorydate, inventorydata) VALUES (?,?,?,?)";
        $args = array(
            $credID,
            $this->vaultArn,
            $this->inventoryDate,
            $comprArchives
        );
        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);
        
        // Return last inserted inventory ID
        return \OCP\DB::insertid();
    }
    
    /**
     * Load last saved inventory from DB
     * @param String $vaultARN
     */
    function loadFromDB($vaultARN) {
        // If provided vault ARN not valid forfait
        if(trim($vaultARN) === '' || is_null($vaultARN)) {
            return FALSE;
        }
        
        // Clear old data
        $this->inventoryDate = NULL;
        $this->vaultArn = NULL;
        $this->archives = NULL;
        $inventoryID = NULL;
        
        // Get stored data
        $sql = "SELECT inventoryid, inventorydate, inventorydata FROM *PREFIX*aletsch_inventories WHERE vaultarn=?";
        $args = array(
            $vaultARN
        );

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);
        
        while($row = $resRsrc->fetchRow()) {
            $this->inventoryDate = $row['inventorydate'];
            $this->vaultArn = $vaultARN;

            $jsonArchives = gzuncompress($row['inventorydata']);
            $this->archives = json_decode($jsonArchives);
            
            $inventoryID = $row['inventoryid'];
        }
        
        // Return reverted inventory ID
        return $inventoryID;
    }
    
    /**
     * Delete all inventories belonging to a vault
     * @param String $VaultARN Vault ARN to remove the inventories
     * @return boolean TRUE on success
     */
    public static function removeInventories($VaultARN) {
        $query = 'DELETE FROM oc_aletsch_inventories WHERE vaultarn=?';
        $args = array($credID, $this->vaultArn);

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);                
        
        return TRUE;
    }
}