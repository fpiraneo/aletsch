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

class storedArchives {
    private $storedArchives = array();
    
    /**
     * Load all archives for indicated ownCloud user
     * @param String $OCUserName ownCloud user to get the archives
     */
    function getArchivesForUser($OCUserName) {
        // Reset actually stored archives;
        $this->storedArchives = array();
        
        // Get stored data
        $sql = "SELECT * FROM *PREFIX*aletsch_archives WHERE ocuserid=?";
        $args = array(
            $OCUserName
        );

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);
        
        while($row = $resRsrc->fetchRow()) {
            
            
            
            $archiveData = array(
                'vaultarn' => $row['vaultarn'],
                'archivedate' => $row['archivedate'],
                'archivedescr' => $row['archivedescr']
            );
            
            $this->storedArchives[$row['archiveid']] = $archiveData;
        }
        
    }
}




class archivesHandler {
    private $inventoryDate = NULL;
    private $vaultArn = NULL;
    private $archives = array();
    
    function setDataFromInventory($inventoryData) {
        // Set the date
        $this->inventoryDate = $inventoryData['InventoryDate'];
        
        // Save vault's ARN
        $this->vaultArn = $inventoryData['VaultARN'];
        
        // Set archives data
        $this->archives = $inventoryData['ArchiveList'];
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
        $sql = "DELETE FROM *PREFIX*aletsch_archives WHERE credid=? AND vaultarn=?";
        $args = array($credID, $this->vaultArn);

        $query = \OCP\DB::prepare($sql);
        $query->execute($args);                
        
        // Insert new inventory
        $sql = "INSERT INTO *PREFIX*aletsch_inventories (credid, vaultarn, inventorydate, inventorydata) VALUES (?,?,?,?)";
        $args = array(
            $credID,
            $this->vaultArn,
            $this->inventoryDate,
            $comprArchives
        );
        $query = \OCP\DB::prepare($sql);
        $query->execute($args);
        
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
            $this->archives = json_decode($jsonArchives, TRUE);
            
            $inventoryID = $row['inventoryid'];
        }
        
        // Return reverted inventory ID
        return $inventoryID;
    }
    
    /**
     * Delete all inventories belonging to a vault
     * @param Integer $credID Credentials ID
     * @param String $VaultARN Vault ARN to remove the inventories
     * @return boolean TRUE on success
     */
    public static function removeInventories($VaultARN) {
        $sql = 'DELETE FROM oc_aletsch_inventories WHERE vaultarn=?';
        $args = array($VaultARN);

        $query = \OCP\DB::prepare($sql);
        $query->execute($args);                
        
        return TRUE;
    }
}
