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
    * Get all files ID of the indicated user
    * TODO: Check if this function gives back only the files the user can access.
    * @param string $user Username
    * @param string $path Path to get the content
    * @param boolean $onlyID Get only the ID of files
    * @param boolean $indexed Output result as dictionary array with fileID as index
    * @return array Files list
    */
    public static function getFileList($user, $path = '', $onlyID = FALSE, $indexed = FALSE) {
        $oc_version = $_SESSION['OC_Version'][0];
        
        if($oc_version === 7) {
            $myres = \OCA\aletsch\utilities::getOC7FileList($user, $path, $onlyID, $indexed);
            return $myres;
        } else {
            return \OCA\aletsch\utilities::getOC6FileList($user, $path, $onlyID, $indexed);            
        }
    }
    
    private static function getOC6FileList($user, $path, $onlyID, $indexed) {
        $result = array();

        $dirView = new \OC\Files\View('/' . $user);
        $dirContent = $dirView->getDirectoryContent($path);
        
        foreach($dirContent as $item) {
            $itemRes = array();
            
            if(strpos($item['mimetype'], 'directory') === FALSE) {
                $fileData = array('fileid'=>$item['fileid'], 'name'=>$item['name'], 'mimetype'=>$item['mimetype']);
                $fileData['path'] = isset($item['usersPath']) ? $item['usersPath'] : $item['path'];
                        
                $itemRes[] = ($onlyID) ? $item['fileid'] : $fileData;
            } else {
                // Case by case build appropriate path
                if(isset($item['usersPath'])) {
                    // - this condition when usersPath is set - i.e. Shared files
                    $itemPath = $item['usersPath'];
                } elseif(isset($item['path'])) {
                    // - Standard case - Normal user's folder
                    $itemPath = $item['path'];
                } else {
                    // - Special folders - i.e. sharings
                    $itemPath = 'files/' . $item['name'];
                }

                $itemRes = \OCA\aletsch\utilities::getOC6FileList($user, $itemPath, $onlyID, $indexed);
            }            
            
            foreach($itemRes as $item) {
                if($onlyID) {
                    $result[] = intval($item);
                } else {
                    if($indexed) {
                        $result[intval($item['fileid'])] = $item;
                    } else {
                        $result[] = $item;
                    }
                }
            }
        }

        return $result;        
    }

    private static function getOC7FileList($user, $path, $onlyID, $indexed) {
        $result = array();

        $dirView = new \OC\Files\View('/' . $user);
        $dirContent = $dirView->getDirectoryContent($path);
        
        foreach($dirContent as $item) {
            $fileID = $item->getId();
            $fileMime = $item->getMimetype();
            $fileName = $item->getName();
            $filePath = substr($item->getPath(), strlen($user) + 2);
            
            $itemRes = array();
            
            if(strpos($fileMime, 'directory') === FALSE) {
                $fileData = array(
                    'fileid' => $fileID,
                    'name' => $fileName,
                    'mimetype' => $fileMime,
                    'path' => $filePath
                );
                        
                $itemRes[] = ($onlyID) ? $fileID : $fileData;
            } else {
                $itemRes = \OCA\aletsch\utilities::getOC7FileList($user, $filePath, $onlyID, $indexed);
            }            
            
            foreach($itemRes as $item) {
                if($onlyID) {
                    $result[] = intval($item);
                } else {
                    if($indexed) {
                        $result[intval($item['fileid'])] = $item;
                    } else {
                        $result[] = $item;
                    }
                }
            }
        }

        return $result;        
    }
    
    /**
    * Get all files ID of the indicated user
    * TODO: Check if this function gives back only the files the user can access.
    * @param string $user Username
    * @param string $path Path to get the content
    * @param boolean $onlyID Get only the ID of files
    * @param boolean $indexed Output result as dictionary array with fileID as index
    * @return array Files list
    */
    public static function getFileTree($user, $path = '') {
        $oc_version = $_SESSION['OC_Version'][0];
        
        if($oc_version === 7) {
            $myres = \OCA\aletsch\utilities::getOC7FileTree($user, $path);
            return $myres;
        } else {
            return \OCA\aletsch\utilities::getOC6FileTree($user, $path);            
        }
    }
    
    private static function getOC6FileTree($user, $path) {
        $result = array();

        $dirView = new \OC\Files\View('/' . $user);
        $dirContent = $dirView->getDirectoryContent($path);
        
        foreach($dirContent as $item) {
            $itemRes = array();
            
            if(strpos($item['mimetype'], 'directory') === FALSE) {
                $fileData = array('fileid'=>$item['fileid'], 'name'=>$item['name'], 'mimetype'=>$item['mimetype']);
                $fileData['path'] = isset($item['usersPath']) ? $item['usersPath'] : $item['path'];
                        
                $itemRes[] = ($onlyID) ? $item['fileid'] : $fileData;
            } else {
                // Case by case build appropriate path
                if(isset($item['usersPath'])) {
                    // - this condition when usersPath is set - i.e. Shared files
                    $itemPath = $item['usersPath'];
                } elseif(isset($item['path'])) {
                    // - Standard case - Normal user's folder
                    $itemPath = $item['path'];
                } else {
                    // - Special folders - i.e. sharings
                    $itemPath = 'files/' . $item['name'];
                }

                $itemRes = \OCA\aletsch\utilities::getOC6FileList($user, $itemPath, $onlyID, $indexed);
            }            
            
            foreach($itemRes as $item) {
                if($onlyID) {
                    $result[] = intval($item);
                } else {
                    if($indexed) {
                        $result[intval($item['fileid'])] = $item;
                    } else {
                        $result[] = $item;
                    }
                }
            }
        }

        return $result;        
    }

    private static function getOC7FileTree($user, $path) {
        $result = array();

        $dirView = new \OC\Files\View('/' . $user);
        $dirContent = $dirView->getDirectoryContent($path);
        
        foreach($dirContent as $item) {
            $fileMime = $item->getMimetype();
            $fileName = $item->getName();
            $fileSize = $item->getSize();
            $filePath = substr($item->getPath(), strlen($user) + 2);
            
            if(strpos($fileMime, 'directory') === FALSE) {
                $fileData = array(
                    'key' => $filePath,
                    'title' => $fileName,
                    'expanded' => TRUE,
                    'folder' => FALSE,
                    'mime' => $fileMime,
                    'size' => \OCA\aletsch\utilities::formatBytes($fileSize),
                    'children' => array()
                );                        
            } else {
                $fileData = array(
                    'key' => $filePath,
                    'title' => $fileName,
                    'expanded' => TRUE,
                    'folder' => TRUE,
                    'mime' => $fileMime,
                    'size' => \OCA\aletsch\utilities::formatBytes($fileSize),
                    'children' => \OCA\aletsch\utilities::getOC7FileTree($user, $filePath)
                );
            }

            $result[] = $fileData;
        }

        return $result;        
    }
    
    /**
     * Get all files starting from indicated directory
     */
    public static function getFSFileList($path = '/', $includeHidden = FALSE) {
        if(substr($path, -1, 1) == '/' && strlen($path) > 1) {
            $path = substr($path, 0, strlen($path) - 1);
        }

        $result = array();

        $currentDir = dir($path);

        while($entry = $currentDir->read()) {
            if($entry != '.' && $entry!= '..' && ($includeHidden || substr($entry, 0, 1) != '.')) {
                $workPath = $path . '/' . $entry;

                if(is_dir($workPath)) {
                    $result = array_merge($result, \OCA\aletsch\utilities::getFSFileList($workPath));
                } else {
                    $result[] = $workPath;
                }
            }
        }

        $currentDir->close();

        return $result;
    }
    
    /**
     * Prepare the vault accordion list
     * @param Array $vaultData Vaults data
     */
    public static function prepareVaultsList($vaultData = array()) {
        // Handle translations
        $l = new \OC_L10N('aletsch');

        if(count($vaultData) === 0) {
            $result = '<div class="aletsch_emptylist">' . $l->t('No vaults.') . '</div>';
        } else {
            $result = '';
            
            foreach($vaultData as $vaultarn => $vault) {
                $vaultName = \OCA\aletsch\aletsch::explodeARN($vaultarn, TRUE);
                $lastInventory = trim($vault['lastinventory']) === '' ? $l->t('Never') : $vault['lastinventory'] . ' UTC';

                $result .= '<h3 data-vaultarn="' . $vaultarn . '">' . $vaultName . '</h3>';
                $result .= '<div>';
                $result .= '<p><strong>ARN:</strong>' . $vaultarn . '</p>';
                $result .= '<p><strong>' . $l->t('Creation date') . ':</strong> ' . $vault['creationdate'] . ' UTC</p>';
                $result .= '<p><strong>' . $l->t('Last inventory') . ':</strong> ' . $lastInventory . '</p>';
                $result .= '<p><strong>' . $l->t('Number of archives') . ':</strong> ' . $vault['numberofarchives'] . '</p>';
                $result .= '<p><strong>' . $l->t('Size') . ':</strong> ' . \OCA\aletsch\utilities::formatBytes($vault['sizeinbytes'], 2, FALSE) . '</p>';
                $result .= '</div>';
            }
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
            $result = '<div class="aletsch_emptylist">' . $l->t('No running or completed jobs on this vault.') . '</div>';
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
                    [Action] => InventoryRetrieval | ArchiveRetrieval
                    [Completed] =>
                    [CompletionDate] =>
                    [CreationDate] => 2014-10-29T13:46:07.973Z
                    [JobId] => C8Vvy4HseP2KBGZwCCajikQSbwUXZ-B2p7M5CydnX9DtThdyEffSY3YWf641ZXHDw5UduZSm2cUvgvGJezxHmrHvnrK1
                    [StatusCode] => InProgress
                    [StatusMessage] =>
                    [VaultARN] => arn:aws:glacier:eu-west-1:000000000000:vaults/VaultName
                    [JobDescription] => The job description you provided when you initiated the job.
                    For archive retriveal:
                    [ArchiveId] (String) => For an ArchiveRetrieval job, this is the archive ID requested for download. Otherwise, this field is null.
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
                            $action = sprintf("<button id='%s' class='getInventory' data-jobid='%s'>%s</button>", uniqid('aletsch_'), trim($job['JobId']), $l->t('Get inventory'));
                            break;
                        }
                        
                        case 'ArchiveRetrieval': {
                            $action = sprintf("<button id='%s' class='getArchive' data-jobid='%s' data-archiveid='%s'>%s</button>", uniqid('aletsch_'), trim($job['JobId']), trim($job['ArchiveId']), $l->t('Get archive'));
                            break;
                        }
                        
                        default: {
                            $action = $l->t('Unsupported action');
                            break;
                        }
                    }
                } else {
                    $action = $l->t('No actions');
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
            $result = '<div class="aletsch_emptylist">' . $l->t('No inventory - Click on "Inventory" to refresh.') . '</div>'; 
        } else if(count($archivesList) === 0) {
            $result = '<div class="aletsch_emptylist">' . $l->t('No archives on this vault.') . '</div>';
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
    
    /**
     * Prepare the spooler list
     * @param Array $spooler List of jobs on spooler
     * @param Boolean $insertCheckBoxes Insert operation's checkbox to select a job
     * @return string html code
     */
    public static function prepareSpoolerList($spooler = array(), $insertCheckBoxes = FALSE) {
        // Handle translations
        $l = new \OC_L10N('aletsch');

        if(count($spooler) === 0) {
            $result = '<div id="aletsch_emptylist">' . $l->t('No jobs on your spooler.') . '</div>';
        } else {
            // Prepare the vault list
            $OCUserName = \OCP\User::getUser();
            $userAccount = new \OCA\aletsch\credentialsHandler($OCUserName);
            
            $vaultsHandler = new \OCA\aletsch\vaultHandler($userAccount->getCredID());
            $vaults = array_keys($vaultsHandler->getVaults());
            
            // Prepare table
            $result = '<table class=\'aletsch_resultTable\'>';
            $result .= '<tr>';
            if($insertCheckBoxes) {
                $result .= '<th><input type=\'checkbox\' id=\'aletsch_selectAllSpoolJobs\' /></th>';
            }
            $result .= '<th>' . $l->t('Vault') . '</th>';
            $result .= '<th>' . $l->t('Type') . '</th>';
            $result .= '<th>' . $l->t('Status') . '</th>';
            $result .= '<th>' . $l->t('Data') . '</th>';
            $result .= '<th>' . $l->t('Diagnostic') . '</th>';
            $result .= '</tr>';

            foreach($spooler as $jobid => $jobData) {
                /*
                    [jobid]
                    [vaultarn]
                    [jobtype]
                    [jobstatus]
                    [jobdata]
                    [jobdiagnostic]
                 */

                $action = ($insertCheckBoxes) ? sprintf("<td><input type='checkbox' id='%s' class='spoolJobSelection' data-spooljobid='%s' /></td>", uniqid("aletsch_"), $jobid) : '';
                
                // Prepare vaults select
                $vaultAction = '<select class="vaultSelect" data-jobid="' . $jobid . '">';
                $thisSelected = ($jobData['vaultarn'] === '') ? ' selected="selected"' : '';
                $vaultAction .= '<option value="EMPTY"' . $thisSelected . '>' . $l->t('Not selected') . "</option>";
                
                foreach($vaults as $vault) {
                    $thisSelected = ($jobData['vaultarn'] === $vault) ? ' selected="selected"' : '';
                    $vaultAction .= '<option value="' . $vault . '"' . $thisSelected . '>' . \OCA\aletsch\aletsch::explodeARN($vault, TRUE) . '</option>';
                }
                $vaultAction .= '</select>';
                
                $filePaths = json_decode($jobData['jobdata'], TRUE);
                $status = ($jobData['jobstatus'] === 'running') ? 'Running ' . $jobData['jobstarted'] : $jobData['jobstatus'];
                $result .= sprintf("<tr>%s<td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>", $action, $vaultAction, $jobData['jobtype'], $status, $filePaths['filePath'], $jobData['jobdiagnostic']);
            }
            
            $result .= '</table>';
        }
        
        return $result;
    }
}