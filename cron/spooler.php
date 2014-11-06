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
        
        // Check for running and waiting jobs
        $running = $spooler->countJobsWithStatus();
        $waiting = $spooler->countJobsWithStatus('waiting');
        
        if($running > 0) {
            // Check for running jobs status
            \OCP\Util::writeLog('aletsch', 'We already have running jobs! - Refreshing status!', 0);
            $runningJob = $spooler->getRunningJob();
            \OCA\aletsch\cron\spooler::refreshJobStatus($spooler, $runningJob);
        } else if ($waiting > 0) {
            // Start first waiting job
            $waitingJob = $spooler->getJobsWithStatus('waiting');
            \OCA\aletsch\cron\spooler::runJob($spooler, $waitingJob[0]);
        } else {
            // Nothing to do!
            \OCP\Util::writeLog('aletsch', 'No jobs running or to start - Exiting now!', 0);
        }
    }
    
    public static function runJob(\OCA\aletsch\spoolerHandler $spooler, $jobData) {
        $spooler->setJobStatus($jobData['jobid'], 'running');
        \OCP\Util::writeLog('aletsch', 'Job ' . $jobData['jobid'] . ' started - PID:' . getmypid(), 0);
        
        $command = "sleep 300";
        $pid = exec(sprintf('%s > /dev/null 2>&1 & echo $!', $command));
        
        $spooler->setJobDiagnostic($jobData['jobid'], 'PID:' . $pid);
    }
    
    public static function refreshJobStatus(\OCA\aletsch\spoolerHandler $spooler, $runningJob) {
        $diagnostic = $spooler->getJobDiagnostic($runningJob['jobid']);
        
        sscanf("PID:%d", $pid);
        
        $spooler->setJobDiagnostic($runningJob['jobid'], 'PID:' . $pid . ' Last refresh at ' . date('c'));
        $jobData = json_encode($runningJob);
        \OCP\Util::writeLog('aletsch', 'Running job data:' . $jobData, 0);
    }
}


