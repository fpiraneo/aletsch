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

namespace OCA\aletsch\cron;

include __DIR__ . '/../libs/spoolerHandler.php';
include __DIR__ . '/../libs/credentialsHandler.php';

class spooler {
    public static function run() {
        // Take note of the cron job started
        \OCP\Util::writeLog('aletsch', 'Spooler cron job started', 0);
        
        // Get the spooler
        $spooler = new \OCA\aletsch\spoolerHandler(NULL);
        \OCA\aletsch\cron\spooler::checkForNextOp($spooler);
    }
    
    public static function checkForNextOp($spooler) {
        // Check for running and waiting jobs
        if(!\OCA\aletsch\cron\spooler::checkForRunningJobs($spooler)) {
            if(!\OCA\aletsch\cron\spooler::checkForWaitingJobs($spooler)) {
                // Nothing to do!
                \OCP\Util::writeLog('aletsch', 'No jobs running or to start - Exiting now!', 0);
            }
        }        
    }
    
    public static function checkForRunningJobs($spooler) {
        $running = $spooler->countJobsWithStatus();

        // Check for running jobs status
        if($running) {
            \OCP\Util::writeLog('aletsch', 'We already have running jobs! - Refreshing status!', 0);
            \OCA\aletsch\cron\spooler::refreshJobStatus($spooler);
        }

        return ($running === 0) ? FALSE : TRUE;
    }

    public static function checkForWaitingJobs($spooler) {
        $waiting = $spooler->countJobsWithStatus('waiting');
        
        if($waiting) {
            // Start first waiting job
            $waitingJob = $spooler->getJobsWithStatus('waiting');
            \OCA\aletsch\cron\spooler::runJob($spooler, $waitingJob[0]);
        }
        
        return ($waiting === 0) ? FALSE : TRUE;
    }

    public static function runJob(\OCA\aletsch\spoolerHandler $spooler, $jobData) {
        $credentials = new \OCA\aletsch\credentialsHandler($jobData['ocusername']);
        
        $jobDataDetails = json_decode($jobData['jobdata'], TRUE);
        
        switch($jobData['jobtype']) {
            case 'fileUpload': {
                $command = 'fileuploaddownload';
                $parameters = array(
                    'jobtype' => $jobData['jobtype'],
                    'username' => escapeshellarg($credentials->getUsername()),
                    'password' => escapeshellarg($credentials->getPassword()),
                    'vaultarn' => escapeshellarg($jobData['vaultarn']),
                    'localPath' => escapeshellarg($jobDataDetails['localPath']),
                    'statusPath' => escapeshellarg($jobDataDetails['statusPath']),
                    'description' => escapeshellarg($jobDataDetails['description']),
                    'compressFile' => $jobDataDetails['compressFile']
                );
                break;
            }
            
            case 'fileDownload': {
                $command = 'fileuploaddownload';
                $parameters = array(
                    'jobtype' => $jobData['jobtype'],
                    'username' => escapeshellarg($credentials->getUsername()),
                    'password' => escapeshellarg($credentials->getPassword()),
                    'vaultarn' => escapeshellarg($jobData['vaultarn']),
                    'jobid' => $jobDataDetails['jobID'],
                    'destPath' => escapeshellarg($jobDataDetails['destPath']),
                    'statusPath' => escapeshellarg($jobDataDetails['statusPath'])
                );
                break;
            }
        }
        
        $commandLineArgs = implode(' ', $parameters);
        
        $shellCmd = "php -f " . __DIR__ . sprintf("/%s.php %s", $command, $commandLineArgs);
        $pid = exec(sprintf('%s > /dev/null 2>&1 & echo $!', $shellCmd));
        
        $spooler->setJobStatus($jobData['jobid'], 'running');
        $spooler->setJobDiagnostic($jobData['jobid'], 'Started');
        $spooler->setJobPID($jobData['jobid'], $pid);
        $spooler->setJobStartDate($jobData['jobid'], date('c'));

        \OCP\Util::writeLog('aletsch', 'Job ' . $jobData['jobid'] . ' started at ' . date('c') . ' - PID:' . $pid, 0);
    }
    
    public static function refreshJobStatus(\OCA\aletsch\spoolerHandler $spooler) {
        $runningJob = $spooler->getRunningJob();
        $jobDetails = json_decode($runningJob['jobdata'], TRUE);
        
        if(file_exists($jobDetails['statusPath'])) {
            $status = file_get_contents($jobDetails['statusPath']);
            $progress = json_decode($status, TRUE);

            $spooler->setJobStatus($runningJob['jobid'], $progress['status']);
            $spooler->setJobDiagnostic($runningJob['jobid'], date('c') . ' - ' . $progress['extStatus']);

            switch($progress['status']) {
                case 'running': {
                    break;
                }
                
                case 'error': {
                    // Close old job
                    $spooler->setJobPID($runningJob['jobid'], 0);
                    
                    // Remove status file
                    unlink($jobDetails['statusPath']);

                    // Check for next operation
                    \OCA\aletsch\cron\spooler::checkForNextOp($spooler);

                    break;
                }
                case 'completed': {
                    // If uploading a file, save the uploaded file ID as returned by Glacier
                    if($runningJob['jobtype'] === 'fileUpload') {
                        $credentials = new \OCA\aletsch\credentialsHandler($runningJob['ocusername']);
                        $inventory = new \OCA\aletsch\inventoryHandler($credentials->getCredID(), $runningJob['vaultarn']);
                        $archiveAttrs = array(
                            'uploaded' => TRUE,
                            'gz' => ($jobDetails['compressFile']) ? TRUE : NULL
                        );

                        $inventory->addArchive($progress['extStatus'], NULL, $jobDetails['description'], filesize($jobDetails['localPath']), NULL, $jobDetails['filePath'], $archiveAttrs);
                    }
                    
                    // Close old job
                    $spooler->setJobPID($runningJob['jobid'], 0);
                    
                    // Remove status file
                    unlink($jobDetails['statusPath']);

                    // Check for next operation
                    \OCA\aletsch\cron\spooler::checkForNextOp($spooler);

                    break;
                }
            }
        } else {
            $pid = $spooler->getJobPID($runningJob['jobid']);

            if(file_exists('/proc/' . $pid)){
                $spooler->setJobStatus($runningJob['jobid'], 'running');
                $spooler->setJobDiagnostic($runningJob['jobid'], 'NO STATUS FILE! Last refresh at ' . date('c'));
            } else {
                // Close old job
                $spooler->setJobStatus($runningJob['jobid'], 'completed');
                $spooler->setJobPID($runningJob['jobid'], 0);
                $spooler->setJobDiagnostic($runningJob['jobid'], 'NO STATUS FILE! Job ended at ' . date('c'));

                // Check for next operation
                \OCA\aletsch\cron\spooler::checkForNextOp($spooler);
            }
        }
    }    
}


