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
	private $OCUserName = '';	
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
