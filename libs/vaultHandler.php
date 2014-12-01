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
                \OCA\aletsch\inventoryHandler::removeInventories($this->credID, $arn);
            }
        }
        
        // Refresh local structure
        $this->load();
        
        // End
        return TRUE;
    }
}
