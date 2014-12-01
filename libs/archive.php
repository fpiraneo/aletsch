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

class archive {
    // Standard glacier properties
    private $ArchiveId;
    private $ArchiveDescription;
    private $CreationDate;
    private $Size;
    private $SHA256TreeHash;
    
    // Aletsch added properties
    private $fileid;
    private $inventoryID;
    private $localPath;
    private $attributes;
    
    // Constants
    private $validAttributes = array('gz', 'deleted', 'uploaded');
    
    public function __construct($ArchiveId) {
        $this->ArchiveId = $ArchiveId;
        $this->loadArchiveData();
    }
    
    /**
     * Set standard Glacier's data starting from an array with the following:
     * ArchiveDescription, CreationDate, Size, SHA256TreeHash
     * @param Array $stdData
     */
    function setStandardProp($stdData) {
        $this->ArchiveDescription = $stdData['ArchiveDescription'];
        $this->CreationDate = $stdData['CreationDate'];
        $this->Size = $stdData['Size'];
        $this->SHA256TreeHash = $stdData['SHA256TreeHash'];
        
        $this->saveArchiveData();
    }
    
    /**
     * Get all properties into an associative array
     * @return Array 
     */
    function getPropArray() {
        $result = array(
            'ArchiveId' => $this->ArchiveId,
            'ArchiveDescription' => $this->ArchiveDescription,
            'CreationDate' => $this->CreationDate,
            'Size' => $this->Size,
            'SHA256TreeHash' => $this->SHA256TreeHash,
            'localPath' => $this->localPath,
            'attributes' => $this->attributes
        );
        
        return $result;
    }
    
    function getFileid() {
        return $this->fileid;
    }

    function getInventoryID() {
        return $this->inventoryID;
    }

    function getArchiveId() {
        return $this->ArchiveId;
    }

    function getArchiveDescription() {
        return $this->ArchiveDescription;
    }

    function getCreationDate() {
        return $this->CreationDate;
    }

    function getSize() {
        return $this->Size;
    }

    function getSHA256TreeHash() {
        return $this->SHA256TreeHash;
    }

    function getLocalPath() {
        return $this->localPath;
    }

    function setInventoryID($inventoryID) {
        $this->inventoryID = $inventoryID;
        $this->saveArchiveData();
    }

    function setArchiveDescription($ArchiveDescription) {
        $this->ArchiveDescription = $ArchiveDescription;
        $this->saveArchiveData();
    }

    function setCreationDate($CreationDate = NULL) {
        if(is_null($CreationDate)) {
            $this->CreationDate = date('c');
        } else {
            $this->CreationDate = $CreationDate;
        }
        $this->saveArchiveData();
    }

    function setSize($Size) {
        $this->Size = $Size;
        $this->saveArchiveData();
    }

    function setSHA256TreeHash($SHA256TreeHash) {
        $this->SHA256TreeHash = $SHA256TreeHash;
        $this->saveArchiveData();
    }

    function setLocalPath($localPath) {
        $this->localPath = $localPath;
        $this->saveArchiveData();
    }
    
    /**
     * Get attributes set and their values for this archive
     * @return Array
     */
    function getAttributes() {
        return $this->attributes;
    }

    /**
     * Set attribute(s) for the archive; handled attributes are:
     * - gz : File is gzipped => BOOLEAN
     * @param String $attrName Attribute name
     * @param Any $attrValue Attribute value; NULL clears the attribute
     * @return boolean
     */
    function setAttribute($attrName = NULL, $attrValue = NULL) {
        if(is_null($attrName) || trim($attrName === '') || array_search($attrName, $this->validAttributes) === FALSE) {
            return FALSE;
        }
        
        // If $attrValue is null clear attribute from array
        if(is_null($attrValue)) {
            unset($this->attributes[$attrName]);
        }
        
        // Set it otherwise
        $this->attributes[$attrName] = $attrValue;
        
        // Attribute set
        return TRUE;
    }
    
    /**
     * Load archives data staring from stored inventoryID
     */
    private function loadArchiveData() {
        // Query for archives data
        $sql = 'SELECT * FROM `*PREFIX*aletsch_inventoryData` WHERE `ArchiveId`=?';
        $args = array(
            $this->ArchiveId
        );

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);

        while($archive = $resRsrc->fetchRow()) {
            $this->ArchiveDescription = $archive['ArchiveDescription'];
            $this->CreationDate = $archive['CreationDate'];
            $this->Size = $archive['Size'];
            $this->SHA256TreeHash = $archive['SHA256TreeHash'];
            $this->fileid = $archive['fileid'];
            $this->inventoryID = $archive['inventoryID'];
            $this->localPath = $archive['localPath'];
            $this->attributes = json_decode($archive['attributes'], TRUE);
        }
    }
    
    /**
     * Update / create an archive's entry on local DB
     */
    private function saveArchiveData() {
        if(is_null($this->fileid)) {
            $sql = 'INSERT INTO `*PREFIX*aletsch_inventoryData` (`inventoryid`, `ArchiveId`, `ArchiveDescription`, `CreationDate`, `Size`, `SHA256TreeHash`, `localPath`, `attributes`) VALUES (?,?,?,?,?,?,?,?)';
            $args = array(
                $this->inventoryID,
                $this->ArchiveID,
                $this->ArchiveDescription,
                $this->CreationDate,
                $this->Size,
                $this->SHA256TreeHash,
                $this->localPath,
                json_encode($this->attributes)
            );
            
            $query = \OCP\DB::prepare($sql);
            $query->execute($args);
            
            $this->fileid = \OCP\DB::insertid();
        } else {
            $sql = 'UPDATE `*PREFIX*aletsch_inventoryData` SET `inventoryid`=?, `ArchiveId`=?, `ArchiveDescription`=?, `CreationDate`=?, `Size`=?, `SHA256TreeHash`=?, `localPath`=?, `attributes`=? WHERE `fileid`=?';
            $args = array(
                $this->inventoryID,
                $this->ArchiveID,
                $this->ArchiveDescription,
                $this->CreationDate,
                $this->Size,
                $this->SHA256TreeHash,
                $this->localPath,
                json_encode($this->attributes),
                $this->fileid
            );

            $query = \OCP\DB::prepare($sql);
            $query->execute($args);
        }
    }
    
    /**
     * Remove all archives data from local DB - This may be useful for archives reconciliation
     * @param Integer $inventoryID
     */
    public static function removeAllArchivesData($inventoryID) {
        $sql = 'DELETE FROM `*PREFIX*aletsch_inventoryData` WHERE `inventoryid`=?';
        $args = array(
            $inventoryID
        );
        $query = \OCP\DB::prepare($sql);
        $query->execute($args);
    }
    
    /**
     * Remove a single archive from local DB
     * @param String $archiveID ID of the archive to be removed
     */
    public static function removeArchiveData($archiveID) {
        $sql = 'DELETE FROM `*PREFIX*aletsch_inventoryData` WHERE `ArchiveId`=?';
        $args = array(
            $archiveID
        );
        $query = \OCP\DB::prepare($sql);
        $query->execute($args);
    }

    /**
     * Load archives data staring from stored inventoryID
     * @param Integer $inventoryID Inventory ID where the archives belongs from
     * @return Array Archives data
     */
    public static function loadArchivesData($inventoryID) {
        // Query for archives data
        $sql = 'SELECT ArchiveId FROM `*PREFIX*aletsch_inventoryData` WHERE `inventoryid`=?';
        $args = array(
            $inventoryID
        );

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);

        $archives = array();

        while($id = $resRsrc->fetchRow()) {
            $archives[$id['ArchiveId']] = new \OCA\aletsch\archive($id['ArchiveId']);
        }
        
        return $archives;
    }

    /**
     * Reconcile stored archives data on DB with provided Glacier inventory
     * @param String $JSONInventoryData JSON inventory data coming from Glacier
     * @param Integer $inventoryID ID of inventory
     */
    public static function archivesReconcile($JSONInventoryData, $inventoryID) {
        $onlineInventoryData = json_decode($JSONInventoryData, TRUE);
        $onlineInventoryIDs = array_column($onlineInventoryData, 'ArchiveId');
        
        $storedInventory = \OCA\aletsch\archive::loadArchivesData($inventoryID);
        $storedInventoryIDs = array_keys($storedInventory);
        
        // Contains the keys of the items not presents on our DB that should be added!
        $newItems = array_diff($onlineInventoryIDs, $storedInventoryIDs);
        
        // Contains already presents items on our DB
        $presItems = array_intersect($onlineInventoryIDs, $storedInventoryIDs);
        
        // Contains the keys of the items removed from the vault that should also be removed from our DB!
        $removedItems = array_diff($storedInventoryIDs, $onlineInventoryIDs);
        
        // Prepare an indexed array containing the online inventory
        // Each item will be indexed with it's own ArchiveID
        $onlineInventory = array();
        foreach($onlineInventoryData as $onlineItem) {
            $onlineInventory[$onlineItem['ArchiveId']] = $onlineItem;
        }
        
        // Insert new items
        foreach($newItems as $itemIdToCreate) {
            $archive = new \OCA\aletsch\archive($itemIdToCreate);
            $archive->setStandardProp($onlineInventory[$itemIdToCreate]);            
        }
        
        // Update already presents items
        foreach($presItems as $itemToUpdate) {
            $archive = new \OCA\aletsch\archive($itemToUpdate);
            $archive->setAttribute('uploaded', NULL);
            $archive->setSHA256TreeHash($onlineInventory[$itemToUpdate]['SHA256TreeHash']);
            $archive->setSize($onlineInventory[$itemToUpdate]['Size']);
            $archive->setCreationDate($onlineInventory[$itemToUpdate]['CreationDate']);
        }
        
        // Remove items from local DB
        foreach ($removedItems as $itemIdToRemove) {
            \OCA\aletsch\archive::removeArchiveData($itemIdToRemove);
        }
    }
}