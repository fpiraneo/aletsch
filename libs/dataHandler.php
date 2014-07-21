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

class accountsHandler {
	private $accountID = NULL;
	private $OCUserName = NULL;

    /**
     * Class constructor
     */
    function __construct() {
    }
	
	function setOCUserName($OCUserName) {
		$this->OCUserName = $OCUserName;
		
		$loadResult = $this->load();
		if(!$loadResult) {
			$this->save();
		}
	}
	
	function getAccountID() {
		return $this->accountID;
	}
	
	function getOCUserName() {
		return $this->OCUserName;
	}
	
	/**
	 *  Get account tree for actual user
	 *  Format:
	 *  array(
	 *		serverID => array(
	 *  		'serverLocation' => serverLocation,
	 *  		'credentials' => array(
	 *  			'credID' => array(
	 *  				'username' => userName,
	 *  				'password' => password
	 *  			)
	 *  		)
	 *  	)
	 *  )
	 */
	static function getAccountsTree($OCUserName) {
		// Get basic account data
		$accountData = new OCA\aletsch\accountsHandler();
		$accountData->setOCUserName($OCUserName);
		$accountID = $accountData->getAccountID();
		
		$accountTree = array();
		
		// Get all servers for the account
		$servers = OCA\aletsch\serverHandler::getServersForAccount($accountID);
		foreach($servers as $serverID) {
			// Get server data
			$serverData = new OCA\aletsch\serverHandler();
			$serverData->setServerID($serverID);
			$serverLocation = $serverData->getServerName();
			$accountTree[$serverID]['serverLocation'] = $serverLocation;
			
			// Get all credentials for the server
			$accountTree[$serverID]['credentials'] = array();
			
			$credentials = OCA\aletsch\credentialsHandler::getCredentialsForServerID($serverID);
			foreach($credentials as $credID) {
				$credData = new OCA\aletsch\credentialsHandler();
				$credData->setCredID($credID);
				$credArray = array(
					'username' => $credData->getUsername(),
					'password' => $credData->getPassword()
				);
				
				$accountTree[$serverID]['credentials'][$credid] = $credArray;
				
				unset($credData);
			}
			
			unset($serverData);
		}
		
		return $accountTree;
	}
	
	/**
	 *  Get account table for actual user
	 *  Format:
	 *  array(
	 *  	[accountID] => array(
	 *  		'serverLocation' => array('id' => serverID, 'value' => serverLocation),
	 *  		'username' => array('id' => credID, 'value' => username),
	 *  		'password' => array('id' => credID, 'value' => password)
	 *  		)
	 *  	)
	 */
	static function getAccountsTable($OCUserName) {
		// Get basic account data
		$accountData = new OCA\aletsch\accountsHandler();
		$accountData->setOCUserName($OCUserName);
		$accountID = $accountData->getAccountID();
		
		$accountTable = array();
		
		// Get all servers for the account
		$servers = OCA\aletsch\serverHandler::getServersForAccount($accountID);
		foreach($servers as $serverID) {
			// Get server data
			$serverData = new OCA\aletsch\serverHandler();
			$serverData->setServerID($serverID);
			$serverLocation = $serverData->getServerName();
			
			// Get all credentials for the server
			$credentials = OCA\aletsch\credentialsHandler::getCredentialsForServerID($serverID);
			foreach($credentials as $credID) {
				$credData = new OCA\aletsch\credentialsHandler();
				$credData->setCredID($credID);
				
				$accountTable[$accountID] = array(
					'serverLocation' => array('id' => $serverID, 'value' => $serverLocation),
					'username' => array('id' => $credID, 'value' => $credData->getUsername()),
					'password' => array('id' => $credID, 'value' => $credData->getPassword())
				);
				
				unset($credData);
			}
			
			unset($serverData);
		}
		
		return $accountTable;
	}

	private function save() {
		if($this->accountID === NULL) {
			// Insert new server data
			$sql = "INSERT INTO *PREFIX*aletsch_accounts (ocusername) VALUES (?)";
			$args = array($this->OCUserName);
			$query = \OCP\DB::prepare($sql);
			$resRsrc = $query->execute($args);

			// Get inserted index
			$this->accountID = \OCP\DB::insertid();		
		} else {
			$sql = 'UPDATE *PREFIX*aletsch_accounts SET ocusername=? WHERE accountid=?';
			$args = array($this->OCUserName, $this->accountID);
			$query = \OCP\DB::prepare($sql);
			$resRsrc = $query->execute($args);
		}
	}
	
	private function load() {
		// Get server's basic data
        $sql = "SELECT accountid FROM *PREFIX*aletsch_accounts WHERE ocusername=?";
        $args = array($this->OCUserName);

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);
		
        $row = $resRsrc->fetchRow();
		
		if($row === FALSE) {
			$this->accountID = NULL;
			return FALSE;
		} else {
			$this->accountID = $row['accountid'];
			return TRUE;
		}
	}
}

class serverHandler {
	private $serverID = NULL;
	private $accountID = NULL;
	private $serverName = NULL;
	
    /**
     * Class constructor
     */
    function __construct() {
    }

	function setServerID($serverID) {
		$this->serverID = $serverID;
		$this->load();
	}
	
	function setAccountID($accountID) {
		$this->accountID = $accountID;
		$this->save();
	}
	
	function setServerName($serverName) {
		$this->serverName = $serverName;
		$this->save();
	}
	
	function getServerID() {
		return $this->serverID;
	}
	
	function getAccountID() {
		return $this->accountID;
	}
	
	function getServerName() {
		return $this->serverName;
	}

	static function getServersForAccount($accountID) {
		$serverIDs = array();
		
		$sql = "SELECT serverid FROM *PREFIX*aletsch_servers WHERE accountid=?";
        $args = array($accountID);

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);
		
        while($row = $resRsrc->fetchRow()) {
			$serverIDs[] = $row['serverid'];
		}

		return $serverIDs;
	}

	private function load() {
		// Get server's basic data
        $sql = "SELECT accountid, servername FROM *PREFIX*aletsch_servers WHERE serverid=?";
        $args = array($this->serverID);

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);
		
        $row = $resRsrc->fetchRow();
		$this->serverName = $row['servername'];
	}
	
	private function save() {
		if($this->accountID !== NULL && $this->serverName !== NULL) {
			if($this->serverID === NULL) {
				// Insert new server data
				$sql = "INSERT INTO *PREFIX*aletsch_servers (accountid, servername) VALUES (?,?)";
				$args = array($this->accountID, $this->serverName);
				$query = \OCP\DB::prepare($sql);
				$resRsrc = $query->execute($args);

				// Get inserted index
				$this->serverID = \OCP\DB::insertid();		
			} else {
				$sql = 'UPDATE *PREFIX*aletsch_servers SET accountid=?, servername=? WHERE serverid=?';
				$args = array($this->accountID, $this->serverName, $this->serverID);
				$query = \OCP\DB::prepare($sql);
				$resRsrc = $query->execute($args);
			}
		}
	}
}

class credentialsHandler {
	private $credID = NULL;
	private $serverID = NULL;
	private $username = NULL;
	private $password = NULL;
	
    /**
     * Class constructor
     */
    function __construct() {
    }
	
    function setCredID($credID) {
		$this->credID = $credID;
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
	
	function setServerID($serverID) {
		$this->serverID = $serverID;
		$this->save();
	}

	function getCredID() {
		return $this->credID;
	}
	
	function getServerID() {
		return $this->serverID;
	}
	
	function getUsername() {
		return $this->username;
	}
	
	function getPassword() {
		return $this->password;
	}
	
	static function getCredentialsForServerID($serverID) {
		$credIDs = array();
		
		$sql = "SELECT credid FROM *PREFIX*aletsch_credentials WHERE serverid=?";
        $args = array($serverID);

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);
		
        while($row = $resRsrc->fetchRow()) {
			$credIDs[] = $row['credid'];
		}

		return $credIDs;
	}

	private function load() {
		$this->serverID = NULL;
		$this->username = NULL;
		$this->password = NULL;
		
        $sql = "SELECT serverid, username, password FROM *PREFIX*aletsch_credentials WHERE credid=?";
        $args = array($this->credID);

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);
		
        $row = $resRsrc->fetchRow();
		$this->serverID = $row['serverid'];
		$this->username = $row['username'];
		$this->password = $row['password'];
	}
	
	private function save() {
		if($this->username !== NULL && $this->password !== NULL) {
			if($this->credID === NULL) {
				// Insert new credentials
				$sql = "INSERT INTO *PREFIX*aletsch_credentials (serverid, username, password) VALUES (?,?,?)";
				$args = array($this->serverID, $this->username, $this->password);
				$query = \OCP\DB::prepare($sql);
				$resRsrc = $query->execute($args);

				// Get inserted index
				$this->credID = \OCP\DB::insertid();		
			} else {
				$sql = 'UPDATE *PREFIX*aletsch_credentials SET serverid=?, username=?, password=? WHERE credid=?';
				$args = array($this->serverID, $this->username, $this->password, $this->credID);
				$query = \OCP\DB::prepare($sql);
				$resRsrc = $query->execute($args);
			}
		}
	}
}