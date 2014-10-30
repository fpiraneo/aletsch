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
     * Prepare the html job list for a vault
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
            $result .= '<th>&nbsp;</th>';
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
                
                if(trim($job['Completed']) === '') {
                    $completed = $l->t('No');
                } else if (trim($job['Completed']) === '1') {
                    $completed = $l->t('Yes');
                } else {
                    $completed = trim($job['Completed']);
                }
                
                $completionDate = trim($job['CompletionDate']) === '' ? 'N.A.' : $job['CompletionDate'];
                
                // Action button for the job
                if(trim($job['Completed']) === '1' && trim($job['StatusCode']) === 'Succeeded') {
                    switch(trim($job['Action'])) {
                        case 'InventoryRetrieval': {
                            $action = sprintf("<button id='%s' class='getInventory' data-jobid='%s'>%s</button>", uniqid('aletsh_'), trim($job['JobId']), $l->t('Get inventory'));
                            break;
                        }
                        
                        default: {
                            $action = $l->t('Unsupported action');
                            break;
                        }
                    }
                } else {
                    $action = '&nbsp;';
                }
                
                $result .= sprintf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>", $job['Action'], $creationDate, $completed, $completionDate, $job['StatusCode'], $job['StatusMessage'], $action);
            }
            
            $result .= '</table>';
        }

        return $result;
    }
    
    /**
     * Prepare the archives list for a vault
     * @param Array $archivesList List of archives as array of objects
     * @param Boolean $insertCheckBoxes Insert archive's checkbox to select an archive
     * @return string html code
     */
    public static function prepareArchivesList($archivesList = array(), $insertCheckBoxes = FALSE) {
        // Handle translations
        $l = new \OC_L10N('aletsch');

        if(is_null($archivesList)) {
            $result = '<div id="aletsch_emptylist">' . $l->t('No inventory - Click on "Refresh inventory" to refresh.') . '</div>'; 
        } else if(count($archivesList) === 0) {
            $result = '<div id="aletsch_emptylist">' . $l->t('No archives on this vault.') . '</div>';
        } else {
            $result = '<table class=\'aletsch_resultTable\'>';
            $result .= '<tr>';
            if($insertCheckBoxes) {
                $result .= '<th><input type=\'checkbox\' id=\'aletsch_selectAllArchives\' /></th>';
            }
            $result .= '<th>' . $l->t('Description') . '</th>';
            $result .= '<th>' . $l->t('Creation date') . '</th>';
            $result .= '<th>' . $l->t('Size') . '</th>';
            $result .= '</tr>';

            foreach($archivesList as $entry) {
                /*
                    [ArchiveId]
                    [ArchiveDescription]
                    [CreationDate] => 2014-10-29T13:46:07.973Z
                    [Size]
                    [SHA256TreeHash]
                 */

                if($insertCheckBoxes) {
                    $action = sprintf("<td><input type='checkbox' id='%s' class='archiveSelection' data-archiveid='%s' /></td>", uniqid("aletsch_"), $entry->ArchiveId);
                } else {
                    $action = '';
                }
                $size = \OCA\aletsch\utilities::formatBytes($entry->Size);
                                
                $result .= sprintf("<tr>%s<td>%s</td><td>%s</td><td>%s</td></tr>", $action, $entry->ArchiveDescription, $entry->CreationDate, $size);
            }
            
            $result .= '</table>';
        }
        
        return $result;
    }
}