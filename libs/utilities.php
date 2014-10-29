<?php
/*
 * Copyright 2014 by Francesco PIRANEO G. (fpiraneo@gmail.com)
 * 
 * This file is part of oclife.
 * 
 * oclife is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * oclife is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with oclife.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\aletsch;
class utilities {
    /**
     * Format a file size in human readable form
     * @param integer $bytes File size in bytes
     * @param integer $precision Decimal digits (default: 2)
     * @return string
     */
    public static function formatBytes($bytes, $precision = 2, $addOriginal = FALSE) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $dimension = max($bytes, 0);
        $pow = floor(($dimension ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $dimension /= pow(1024, $pow);

        $result = round($dimension, $precision) . ' ' . $units[$pow];

        if($addOriginal === TRUE) {
            $result .= sprintf(" (%s bytes)", number_format($bytes));
        }

        return $result;
    }
    
    /**
     * Prepare the job list for a vault
     * @param Array $jobList
     * @return string
     */
    public static function prepareJobList($jobList = array()) {
        // Handle translations
        $l = new \OC_L10N('aletsch');

        if(count($jobList) === 0) {
            $result = '<div id="aletsch_emptylist">' . $l->t('No running or completed jobs on this vault.') . '</div>';
        } else {
            $result = '<table class=\'aletsch_resultTable\'>';
            $result .= '<tr>';
            $result .= '<th>' . $l->t('Action') . '</th>';
            $result .= '<th>' . $l->t('Creation date') . '</th>';
            $result .= '<th>' . $l->t('Completed?') . '</th>';
            $result .= '<th>' . $l->t('Completion date') . '</th>';
            $result .= '<th>' . $l->t('Status code') . '</th>';
            $result .= '<th>' . $l->t('Status message') . '</th>';
            $result .= '</tr>';

            foreach($jobList as $job) {
                /*
                    [Action] => InventoryRetrieval
                    [Completed] =>
                    [CompletionDate] =>
                    [CreationDate] => 2014-10-29T13:46:07.973Z
                    [JobId] => C8Vvy4HseP2KBGZwCCajikQSbwUXZ-B2p7M5CydnX9DtThdyEffSY3YWf641ZXHDw5UduZSm2cUvgvGJezxHmrHvnrK1
                    [StatusCode] => InProgress
                    [StatusMessage] =>
                    [VaultARN] => arn:aws:glacier:eu-west-1:000000000000:vaults/VaultName
                 */

                $creationDate = trim($job['CreationDate']) === '' ? 'N.A.' : $job['CreationDate'];
                $completed = trim($job['Completed']) === '' ? 'NO' : $job['Completed'];
                $completionDate = trim($job['CompletionDate']) === '' ? 'N.A.' : $job['CompletionDate'];
                
                $result .= sprintf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>", $job['Action'], $creationDate, $completed, $completionDate, $job['StatusCode'], $job['StatusMessage']);
            }
            
            $result .= '</table>';
        }        

        return $result;
    }
}