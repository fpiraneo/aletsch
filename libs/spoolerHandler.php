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

class spoolerHandler {
    private $OCUserName = NULL;
    private $operations = array();
    private $jobtype = array('fileUpload', 'fileDownload', 'newArchive');
    private $jobStatus = array('hold', 'waiting', 'running', 'completed', 'error');

    function __construct($OCUserName) {
        $this->OCUserName = $OCUserName;
        $this->load();
    }
    
    /**
     * Get allowed jobs type
     * @return Array
     */
    function getJobValidTypes() {
        return $this->jobtype;
    }
    
    /**
     * Return jobs in spoolers
     * @return array
     */
    function getOperations() {
        return $this->operations;
    }
        
    /**
     * Loads all operations currently spooled on DB for indicated user
     */
    function load() {
        // Reset actual content of spooler data
        $this->operations = array();
        
        // Get spooled operations
        if(is_null($this->OCUserName)) {
            $sql = 'SELECT * FROM *PREFIX*aletsch_spool';
            $args = array();            
        } else {
            $sql = 'SELECT * FROM *PREFIX*aletsch_spool WHERE ocusername=?';
            $args = array(
                $this->OCUserName
            );
        }

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);
        
        while($row = $resRsrc->fetchRow()) {
            $spoolEntry = array(
                'jobid' => $row['jobid'],
                'ocusername' => $row['ocusername'],
                'vaultarn' => $row['vaultarn'],
                'jobtype' => $row['jobtype'],
                'jobstatus' => $row['jobstatus'],
                'jobstarted' => $row['jobstarted'],
                'jobdata' => $row['jobdata'],
                'jobpid' => $row['jobpid'],
                'jobdiagnostic' => $row['jobdiagnostic']
            );
            
            $this->operations[$row['jobid']] = $spoolEntry;
        }
    }
    
    /**
     * Return the number of jobs with given status
     * @param String $status Status to count the job, 'running' for default
     * @return int Counted jobs
     */
    function countJobsWithStatus($status='running') {
        $total = 0;
        
        foreach($this->operations as $op) {
            if($op['jobstatus'] === $status) {
                $total++;
            }
        }
        
        return $total;
    }
    
    /**
     * Get the jobs with given status
     * @param String $status Status to count the job, 'running' for default
     * @return array Jobs with the indicated status
     */
    function getJobsWithStatus($status='running') {
        $jobs = array();
        
        foreach($this->operations as $op) {
            if($op['jobstatus'] === $status) {
                $jobs[] = $op;
            }
        }
        
        return $jobs;
    }
    
    /**
     * Return first running job data, FALSE if not found
     * @return Array Array with all job's data, FALSE if no running job found
     */
    function getRunningJob() {
        foreach ($this->operations as $op) {
            if($op['jobstatus'] === 'running') {
                return $op;
            }
        }
        
        return FALSE;
    }

    /**
     * Enter new job in spool
     * @param string $jobtype
     * @return Integer New job id, FALSE if no job has been created
     */
    function newJob($jobtype) {
        // Check for correct job type
        if(array_search($jobtype, $this->jobtype) === FALSE) {
            return FALSE;
        }
        
        // Enter new job in spool
        $sql = 'INSERT INTO *PREFIX*aletsch_spool (ocusername, jobtype, jobstatus) VALUES(?,?,?)';
        $args = array(
            $this->OCUserName,
            $jobtype,
            'hold'
        );

        $query = \OCP\DB::prepare($sql);
        $query->execute($args);
        
        // Get last inserted id
        $newID = \OCP\DB::insertid();
        
        // Reload data from DB
        $this->load();
        
        // Return last insterted ID
        return $newID;
    }
    
    function removeJob($jobid) {
        // Remove job from spool
        $sql = 'DELETE FROM *PREFIX*aletsch_spool WHERE jobid=?';
        $args = array(
            $jobid
        );

        $query = \OCP\DB::prepare($sql);
        $query->execute($args);
        
        // Update local structure
        unset($this->operations[$jobid]);
        
        // Return last insterted ID
        return TRUE;
    }

    /**
     * Set vault ARN for given job ID
     * @param Integer $jobid
     * @param String $vaultARN
     * @return boolean TRUE if job id is valid, false otherwise
     */
    function setVaultARN($jobid, $vaultARN) {
        // Check if job with given ID is set
        if(!isset($this->operations[$jobid])) {
            return FALSE;
        }
        
        // Set ARN
        $this->updateFieldData($jobid, 'vaultarn', $vaultARN);
        
        return TRUE;
    }
    
    /**
     * Get vault ARN for given job ID
     * @param Integer $jobid
     * @return String Job's vaultarn if job id is valid, FALSE otherwise
     */
    function getVaultARN($jobid) {
        // Check if job with given ID is set
        if(!isset($this->operations[$jobid])) {
            return FALSE;
        }
        
        // Get ARN
        return $this->operations[$jobid]['vaultarn'];
    }
    
    /**
     * Return job type for given jobid
     * @param Integer $jobid
     * @return String Job type, FALSE if jobid is not set
     */
    function getJobType($jobid) {
        // Check if job with given ID is set
        if(!isset($this->operations[$jobid])) {
            return FALSE;
        }
        
        // Get job type
        return $this->operations[$jobid['jobtype']];
    }
    
    /**
     * Return job status for given jobid
     * @param Integer $jobid
     * @return String Job type, FALSE if jobid is not set
     */
    function getJobStatus($jobid) {
        // Check if job with given ID is set
        if(!isset($this->operations[$jobid])) {
            return FALSE;
        }
        
        // Get job status
        return $this->operations[$jobid]['jobstatus'];
    }

    /**
     * Set job status for given jobid
     * @param Integer $jobid
     * @param String $jobstatus One of these: 'hold', 'waiting', 'running', 'completed', 'error'
     * @return String Job type, FALSE if jobid is not set
     */
    function setJobStatus($jobid, $jobstatus) {
        // Check if job with given ID is set
        if(!isset($this->operations[$jobid])) {
            return FALSE;
        }
        
        // Check for correct job status
        if(array_search($jobstatus, $this->jobStatus) === FALSE) {
            return FALSE;
        }
        
        // Set job status
        $this->updateFieldData($jobid, 'jobstatus', $jobstatus);
        
        return TRUE;
    }

    /**
     * Return job diagnostic for given jobid
     * @param Integer $jobid
     * @return String Job type, FALSE if jobid is not set
     */
    function getJobDiagnostic($jobid) {
        // Check if job with given ID is set
        if(!isset($this->operations[$jobid])) {
            return FALSE;
        }
        
        // Get job type
        return $this->operations[$jobid]['jobdiagnostic'];
    }

    /**
     * Set job diagnostic for given jobid
     * @param Integer $jobid
     * @return String Job diagnostic, FALSE if jobid is not set
     */
    function setJobDiagnostic($jobid, $jobdiagnostic) {
        // Check if job with given ID is set
        if(!isset($this->operations[$jobid])) {
            return FALSE;
        }
        
        // Set job diagnostic
        $this->updateFieldData($jobid, 'jobdiagnostic', $jobdiagnostic);
        
        return TRUE;
    }

    /**
     * Return job data for given jobid
     * @param Integer $jobid
     * @return String Job data, FALSE if job data is not set
     */
    function getJobData($jobid) {
        // Check if job with given ID is set
        if(!isset($this->operations[$jobid])) {
            return FALSE;
        }
        
        // Get job type
        return $this->operations[$jobid]['jobdata'];
    }

    /**
     * Set job data for given jobid
     * @param Integer $jobid
     * @param String $jobdata Job data to set
     * @return Boolean TRUE if ok, FALSE if $jobid is not set
     */
    function setJobData($jobid, $jobdata) {
        // Check if job with given ID is set
        if(!isset($this->operations[$jobid])) {
            return FALSE;
        }
        
        // Set job data
        $this->updateFieldData($jobid, 'jobdata', $jobdata);
        
        return TRUE;
    }
    
    /**
     * Return job start date for given jobid
     * @param Integer $jobid
     * @return String Job data, FALSE if job data is not set
     */
    function getJobStartDate($jobid) {
        // Check if job with given ID is set
        if(!isset($this->operations[$jobid])) {
            return FALSE;
        }
        
        // Get job type
        return $this->operations[$jobid]['jobstarted'];
    }

    /**
     * Set job start date for given jobid
     * @param Integer $jobid
     * @param String $jobStartDate Job start timestamp
     * @return Start date and time, FALSE if $jobid is not set
     */
    function setJobStartDate($jobid, $jobStartDate) {
        // Check if job with given ID is set
        if(!isset($this->operations[$jobid])) {
            return FALSE;
        }
        
        // Set job data
        $this->updateFieldData($jobid, 'jobstarted', $jobStartDate);
        
        return TRUE;
    }
    
    /**
     * Return job PID for given jobid
     * @param Integer $jobid
     * @return String Job data, FALSE if job data is not set
     */
    function getJobPID($jobid) {
        // Check if job with given ID is set
        if(!isset($this->operations[$jobid])) {
            return FALSE;
        }
        
        // Get job type
        return $this->operations[$jobid]['jobpid'];
    }

    /**
     * Set job PID for given jobid
     * @param Integer $jobid
     * @return Boolean TRUE if ok, FALSE if $jobid is not set
     */
    function setJobPID($jobid, $jobPID) {
        // Check if job with given ID is set
        if(!isset($this->operations[$jobid])) {
            return FALSE;
        }
        
        // Set job data
        $this->updateFieldData($jobid, 'jobpid', $jobPID);
        
        return TRUE;
    }
    
    /**
     * Return extended job data for given jobid
     * @param Integer $jobid
     * @return String Job data, FALSE if job data is not set
     */
    function getJobExtData($jobid) {
        // Check if job with given ID is set
        if(!isset($this->operations[$jobid])) {
            return FALSE;
        }
        
        // Get job type
        return $this->operations[$jobid]['jobextdata'];
    }

    /**
     * Set extended job data for given jobid
     * @param Integer $jobid
     * @return Boolean TRUE if ok, FALSE if $jobid is not set
     */
    function setJobExtData($jobid, $jobextdata) {
        // Check if job with given ID is set
        if(!isset($this->operations[$jobid])) {
            return FALSE;
        }
        
        // Set job data
        $this->updateFieldData($jobid, 'jobextdata', $jobextdata);
        
        return TRUE;
    }
    
    /**
     * Update stored data on local and on DB
     * @param Integer $jobID
     * @param String $fieldName
     * @param String $data
     */
    private function updateFieldData($jobID, $fieldName, $data) {
        // Update locally
        $this->operations[$jobID][$fieldName] = $data;
        
        // Update on DB
        $sql = 'UPDATE *PREFIX*aletsch_spool SET ' . $fieldName . '=? WHERE jobid = ?';
        $args = array(
            $data,
            $jobID
        );

        $query = \OCP\DB::prepare($sql);
        $query->execute($args);
    }
}
