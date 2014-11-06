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

include __DIR__ . '/../libs/dataHandler.php';

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
        $command = "sleep 300";
        $pid = exec(sprintf('%s > /dev/null 2>&1 & echo $!', $command));

        $spooler->setJobStatus($jobData['jobid'], 'running');
        $spooler->setJobDiagnostic($jobData['jobid'], 'Started at ' . date('c'));
        $spooler->setJobPID($jobData['jobid'], $pid);

        \OCP\Util::writeLog('aletsch', 'Job ' . $jobData['jobid'] . ' started at ' . date('c') . ' - PID:' . $pid, 0);
    }
    
    public static function refreshJobStatus(\OCA\aletsch\spoolerHandler $spooler) {
        $runningJob = $spooler->getRunningJob();
        $pid = $spooler->getJobPID($runningJob['jobid']);
        
        if(file_exists('/proc/' . $pid)){
            $spooler->setJobDiagnostic($runningJob['jobid'], 'Last refresh at ' . date('c'));
        } else {
            // Close old job
            $spooler->setJobStatus($runningJob['jobid'], 'completed');
            $spooler->setJobPID($runningJob['jobid'], 0);
            $spooler->setJobDiagnostic($runningJob['jobid'], 'Job ended at ' . date('c'));
            
            // Check for next operation
            \OCA\aletsch\cron\spooler::checkForNextOp($spooler);
        }
    }
}


