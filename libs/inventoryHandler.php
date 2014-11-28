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


// TODO - Da rivedere!!!

namespace OCA\aletsch;

class inventoryHandler {
    /**
     * @var Integer User's credentials id where the inventory belongs to
     */
    private $credentialsID = NULL;
    
    /**
     * @var Integer Actually loaded inventory ID; NULL means new inventory
     */
    private $inventoryID = NULL;

    /**
     * @var datetime Date and time of the actual inventory
     */
    private $inventoryDate = NULL;
    
    /**
     * @var String Vault ARN the inventory belongs to
     */    
    private $vaultArn = NULL;
    
    /**
     * @var Array Archives objects with the following fields: ArchiveId, ArchiveDescription, CreationDate, Size, SHA256TreeHash
     */
    private $archives = array();
    
    /**
     * Class constructor - Load an inventory or create a new one if not exists
     * @param Integer $credID User's credentials ID
     * @param String $vaultARN Vault ARN the inventory belongs to
     */
    public function __construct($credID, $vaultARN) {
        $this->credentialsID = $credID;
        
        if(is_null($this->loadFromDB($vaultARN))) {
            $this->vaultArn = $vaultARN;
            $this->saveOnDB();
        }
    }
    
    /**
     * This is a reconciliation function; take a JSON inventory coming from Glacier
     * and reconcile data on DB
     * @param Array $inventoryData Array with inventory data
     */
    function setDataFromInventory($inventoryData) {
        // Set the date
        $this->inventoryDate = $inventoryData['InventoryDate'];
        
        // Save vault's ARN
        $this->vaultArn = $inventoryData['VaultARN'];
        
        // Save modifications on DB
        $this->saveOnDB();
        
        // Reconcile stored data with actual inventory data
        \OCA\aletsch\archive::archivesReconcile($inventoryData['ArchiveList'], $this->inventoryID);        
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
        $archives = array();
        foreach($this->archives as $archiveData) {
            $archives[] = $archiveData->getPropArray();
        }
        return $archives;
    }
    
    /**
     * Get actual vault's ARN
     * @return String
     */
    function getVaultArn() {
        return $this->vaultArn;
    }
    
    /**
     * Set an attribute for an archive
     * @param String $archiveID Glacier's archive ID
     * @param String $attributeName Attribute name - See "archive->setAttribute()" function for more
     * @param Any $attributeValue Attribute value
     */
    function setArchiveAttribute($archiveID, $attributeName, $attributeValue) {
        // Check if archive id is valid
        if(!isset($this->archives[$archiveID])) {
            return FALSE;
        }
        
        // Set attribute value
        $this->archives[$archiveID]->setAttribute($attributeName, $attributeValue);
    }
    
    /**
     * Get an attribute value for an archive
     * @param String $archiveID Glacier's archive ID
     * @param String $attributeName Attribute name - See "archive->setAttribute()" function for more
     */
    function getArchiveAttribute($archiveID, $attributeName) {
        // Check if archive id is valid
        if(!isset($this->archives[$archiveID])) {
            return FALSE;
        }

        // Get all attributes values
        $allAttrs = $this->getArchiveAttributes($archiveID);
        
        if(!isset($allAttrs[$attributeName])) {
            return FALSE;
        } else {
            return $allAttrs[$attributeName];
        }
    }
    
    /**
     * Get all attributes for an archive
     * @param String $archiveID Glacier's archive ID
     */
    function getArchiveAttributes($archiveID) {
        // Check if archive id is valid
        if(!isset($this->archives[$archiveID])) {
            return FALSE;
        }

        // Get attributes value
        return $this->archives[$archiveID]->getAttributes();
    }
    
    /**
     * Save actual inventory on DB
     */
    private function saveOnDB() {
        // If credential is not provided, forfait
        if(is_null($this->credID)) {
            return FALSE;
        }
        
        // If this inventory already has an ID, perform an update; perform an insert otherwise
        if($this->inventoryID) {
            // Insert new inventory
            $sql = 'UPDATE `*PREFIX*aletsch_inventories` SET `credid`=?, `vaultarn`=?, `inventorydate`=? WHERE `inventoryid`=?';
            $args = array(
                $this->credID,
                $this->vaultArn,
                $this->inventoryDate,
                $this->inventoryID
            );
            $query = \OCP\DB::prepare($sql);
            $query->execute($args);            
        } else {
            // Insert new inventory
            $sql = 'INSERT INTO `*PREFIX*aletsch_inventories` (`credid`, `vaultarn`, `inventorydate`) VALUES (?,?,?)';
            $args = array(
                $this->credID,
                $this->vaultArn,
                $this->inventoryDate
            );
            $query = \OCP\DB::prepare($sql);
            $query->execute($args);
            
            $this->inventoryID = \OCP\DB::insertid();
        }

        // Return last inserted inventory ID
        return $this->inventoryID;
    }
    
    /**
     * Load last saved inventory from DB
     * @param String $vaultARN
     */
    private function loadFromDB($vaultARN) {
        // If provided vault ARN not valid forfait
        if(trim($vaultARN) === '' || is_null($vaultARN)) {
            return FALSE;
        }
        
        // Clear old data
        $this->inventoryDate = NULL;
        $this->vaultArn = NULL;
        $this->inventoryID = NULL;
        
        // Get stored data
        $sql = 'SELECT `inventoryid`, `inventorydate` FROM `*PREFIX*aletsch_inventories` WHERE `vaultarn`=?';
        $args = array(
            $vaultARN
        );

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);
        
        while($row = $resRsrc->fetchRow()) {
            $this->inventoryDate = $row['inventorydate'];
            $this->vaultArn = $vaultARN;
            $this->inventoryID = $row['inventoryid'];
            $this->archives = \OCA\aletsch\archive::loadArchivesData($this->inventoryID);
        }
        
        // Return reverted inventory ID
        return $this->inventoryID;
    }

    /**
     * Delete all inventories belonging to a vault
     * @param Integer $credID Credentials ID
     * @param String $vaultARN Vault ARN to remove the inventories
     * @return boolean TRUE on success
     */
    public static function removeInventories($vaultARN) {
        // Get stored data
        $sql = "SELECT `inventoryid` FROM `*PREFIX*aletsch_inventories` WHERE `vaultarn`=?";
        $args = array(
            $vaultARN
        );

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);
        
        while($row = $resRsrc->fetchRow()) {
            // Remove vault entry
            $sql = 'DELETE FROM `*PREFIX*oc_aletsch_inventories` WHERE `vaultarn`=?';
            $args = array($vaultARN);

            $query = \OCP\DB::prepare($sql);
            $query->execute($args);                

            // Remove archives entry
            \OCA\aletsch\archive::removeAllArchivesData($row['inventoryid']);
        }
        
        return TRUE;
    }    
}
