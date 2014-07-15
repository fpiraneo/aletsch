<?php
namespace OCA\aletsch;

class accountsHandler {
	private $accountID;
	private $OCUserName;

    /**
     * Class constructor
     */
    function __construct() {
		$this->accountID = NULL;
		$this->OCUserName = NULL;
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
	
	static function getAccountsTree($OCUserName) {
		// Get basic account data
		$accountData = new OCA\aletsch\accountsHandler();
		$accountData->setOCUserName($OCUserName);
		$accountID = $accountData->getAccountID();
		
		$accountTree = array(
			$accountID => array(
				'userName' => $OCUserName,
				'server' => array()
			)
		);
		
		// Get all servers for the account
		$servers = OCA\aletsch\serverHandler::getServersForAccount($accountID);
		foreach($servers as $serverID) {
			// Get server data
			$serverData = new OCA\aletsch\serverHandler();
			$serverData->setServerID($serverID);
			$serverName = $serverData->getServerName();
			$accountTree[$accountID]['server'][$serverID]['serverName'] = $servername;
			
			// Get all credentials for the server
			$accountTree[$accountID]['server'][$serverID]['credentials'] = array();
			
			$credentials = OCA\aletsch\credentialsHandler::getCredentialsForServerID($serverID);
			foreach($credentials as $credID) {
				$credData = new OCA\aletsch\credentialsHandler();
				$credData->setCredID($credID);
				$credArray = array(
					'username' => $credData->getUsername(),
					'password' => $credData->getPassword()
				);
				
				$accountTree[$accountID]['server'][$serverID]['credentials'][$credid] = $credArray;
				
				unset($credData);
			}
			
			unset($serverData);
		}
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
	private $serverID;
	private $accountID;
	private $serverName;
	
    /**
     * Class constructor
     */
    function __construct() {
		$this->serverID = NULL;
		$this->accountID = NULL;
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
	private $credID;
	private $serverID;
	private $username;
	private $password;
	
    /**
     * Class constructor
     */
    function __construct() {
		$this->credID = NULL;
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