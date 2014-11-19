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

    require __DIR__ . '/../libs/aletsch.php';
    require __DIR__ . '/../libs/utilities.php';

    function gzcompressfile($source, $dest, $level = FALSE) {
        $mode = 'wb' . $level;
        $error = FALSE;

        $fp_out = gzopen64($dest, $mode);
        if($fp_out) {
            $fp_in = fopen($source,'rb');

            if($fp_in) {
                while(!feof($fp_in)) {
                    gzwrite($fp_out, fread($fp_in, 1024 * 512));
                }

                fclose($fp_in);
            } else {
                $error = TRUE;
            }

            gzclose($fp_out);
        } else {
            $error = TRUE;
        }

        if($error) {
            return FALSE;
        } else {
            return $dest;
        }
    }
    
    function gzuncompressfile($source, $dest) {
        $error = FALSE;

        $fp_out = fopen($dest,'wb');
        if($fp_out) {
            $fp_in = gzopen64($source, 'rb');

            if($fp_in) {
                while(!gzeof($fp_in)) {
                    fwrite($fp_out, gzread($fp_in, 1024 * 512));
                }

                gzclose($fp_in);
            } else {
                $error = TRUE;
            }

            fclose($fp_out);
        } else {
            $error = TRUE;
        }

        if($error) {
            return FALSE;
        } else {
            return $dest;
        }
    }
    
    // Right parameter combine
    switch($argv[1]) {
        // Upload a file
        case 'fileUpload': {
            // Set arguments names
            $argNames = array(
                'cmdName',
                'jobtype',
                'username',
                'password',
                'vaultarn',
                'localPath',
                'statusPath',
                'description',
                'compressFile'
            );

            // Check for right parameter number
            if($argc != count($argNames)) {
                error_log('fileUpload: Not enough parameters - Passed:' . $argc . ', required: ' . count($argNames) . ' - List: ' . implode(',', $argv));
                die();
            }

            break;
        }
        // Download a file
        case 'fileDownload': {
            // Set arguments names
            $argNames = array(
                'cmdName',
                'jobtype',
                'username',
                'password',
                'vaultarn',
                'jobid',
                'destPath',
                'statusPath'
            );

            // Check for right parameter number
            if($argc != count($argNames)) {
                error_log('fileDownload: Not enough parameters - Passed:' . $argc . ', required: ' . count($argNames) . ' - List: ' . implode(',', $argv));
                die();
            }

            break;
        }
        
        default: {
            error_log('Invalid option for jobType: ' . $argv[1]);
            die();
            break;
        }
    }

    $clp = array_combine($argNames, $argv);

    // Instance to aletsch
    $vaultData = \OCA\aletsch\aletsch::explodeARN($clp['vaultarn']);
    $glacier = new \OCA\aletsch\aletsch($vaultData['serverLocation'], $clp['username'], $clp['password']);
    
    switch($clp['jobtype']) {
        // Upload a file
        case 'fileUpload': {
            // Check if the file can be accessed
            if(!is_file($clp['localPath'])) {
                $progress = array(
                    'status'    => 'error',
                    'extStatus' => 'fileUpload: Unable to access file'
                );

                file_put_contents($clp['statusPath'], json_encode($progress));

                error_log('fileUpload: Unable to access file: ' . $clp['localPath']);
                die();
            }

            error_log(sprintf("fileUpload: [DEBUG] localPath: %s, Description: %s", $clp['localPath'], $clp['description']));
            
            if($clp['compressFile']) {
                $sourceFileName = pathinfo($clp['localPath'], PATHINFO_BASENAME);
                $destFileName = $sourceFileName . '.gz';
                $zippedFilePath = sys_get_temp_dir() . '/' . $destFileName;
                
                $compressionResult = gzcompressfile($clp['localPath'], $zippedFilePath);
                if($compressionResult === FALSE) {
                    $progress = array(
                        'status'    => 'error',
                        'extStatus' => 'fileUpload: Unable to compress file'
                    );

                    file_put_contents($clp['statusPath'], json_encode($progress));

                    error_log('fileUpload: Unable to compress file: ' . $clp['localPath']);
                    die();
                }
                
                $success = $glacier->uploadArchive($vaultData['vaultName'], $zippedFilePath, '[gz]' . $clp['description'], $clp['statusPath']);
            } else {
                $success = $glacier->uploadArchive($vaultData['vaultName'], $clp['localPath'], $clp['description'], $clp['statusPath']);
            }

            break;
        }
        
        // Download a file
        case 'fileDownload': {
            $tempOutFile = sys_get_temp_dir() . uniqid('/aletsch_out_');
            $success = $glacier->getJobData($vaultData['vaultName'], $clp['jobid'], $tempOutFile, $clp['statusPath']);
            
            if($success) {
                mkdir(dirname($clp['destPath']), 0755, TRUE);
                
                if(substr($clp['destPath'], 0, 4) === '[gz]') {
                    $tempUnzippedFile = sys_get_temp_dir() . uniqid('/aletsch_out_');
                    gzuncompressfile($tempOutFile, $tempUnzippedFile);
                    $destFilePath = substr($clp['destPath'], 4);
                    copy($tempUnzippedFile, $destFilePath);
                } else {
                    copy($tempOutFile, $clp['destPath']);                    
                }
            } else {
                $progress = array(
                    'status'    => 'error',
                    'extStatus' => 'fileDownload: Unable to download file'
                );

                file_put_contents($clp['statusPath'], json_encode($progress));                
            }
            
            unlink($tempOutFile);
            
            break;
        }
    }
