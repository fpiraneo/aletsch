<?php
namespace OCA\aletsch;

require __DIR__ . '/aws/aws-autoloader.php';
use Aws\Glacier\GlacierClient;
use Aws\Glacier\Model\MultipartUpload\UploadPartGenerator;

/**
 * Code partly inspired to http://blogs.aws.amazon.com/php/post/Tx7PFHT4OJRJ42/Uploading-Archives-to-Amazon-Glacier-from-PHP
 */
class aletsch {
    // Constant value of 1Mbytes in bytes
    // this is a Megabyte - Better a Mebibyte
    private $megaByte = 1048576;
    private $maxReelSize;
    private $fileCopyBlockSize = 16;

    // Block size is 4Mb
    private $blockSize;
    private $uploadPartSize;

    private $glacierClient;

    private $archiver;
    private $lastCatalog;

    private $offline = TRUE;

    /**
     * Class constructor
     */
    function __construct($region, $key, $secret) {
        $this->glacierClient = GlacierClient::factory(array(
            'region' => $region,
            'key'    => $key,
            'secret' => $secret
        ));

        $this->maxReelSize = (1024 * $this->megaByte) * 2;
        $this->blockSize = $this->megaByte * 4;
        $this->uploadPartSize = 4 * $this->megaByte;
    }

    /**
     * Set the reel maximum size
     * Provide Mb or Gb as measure unit
     * Minimum allowed 4Mb, maximum allowed: 4Gb
     */
    function setReelSize($reelSize) {
        $minAccept = $this->megaByte * 4;		// 4Mb
        $maxAccept = ($this->megaByte * 1024) * 4;	// 4Gb

        $ucReelSize = trim(strtoupper($reelSize));
        $umMB = strpos($ucReelSize, 'MB');
        $umGB = strpos($ucReelSize, 'GB');

        // Accepts only megabyte or gigabyte
        if(!$umMB && !$umGB) {
            return false;
        }

        // Sets measure unit
        $value = trim(substr($ucReelSize, 0, strlen($ucReelSize) - 2));

        if($umMB) {
            $multiplier = $this->megaByte;
        } else if($umGB) {
            $multiplier = $this->megaByte * 1024;
        } else {
            $multiplier = 0;
        }

        $setValue = $multiplier * $value;

        if($setValue >= $minAccept && $setValue <= $maxAccept) {
            $this->maxReelSize = $setValue;
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Get the reel maximum size
     * Results in bytes - Always
     */
    function getReelSize() {
        return $this->maxReelSize;
    }

    /**
     * Set catalog from json
     */
    function setCatalogFromJson($catalog) {
        $this->lastCatalog = json_decode($catalog, TRUE);
    }

    /**
     * Get catalog entries number
     */
    function getCatalogEntries() {
        return count($this->lastCatalog);
    }

    /**
     * Get catalog item
     */
    function getCatalogItem($itemNum) {
        $keys = array_keys($this->lastCatalog);
        return $this->lastCatalog[$keys[$itemNum]];
    }

    /**
     * Get all possible servers locations
     * @return array All server id and locations on an array
     */
    public static function getServersLocation() {
        $serverAvailableLocations = array(
            'us-east-1' => 'US East (N. Virginia)',
            'us-west-1' => 'US West (Northern California)',
            'us-west-2' => 'US West (Oregon)',
            'eu-west-1' => 'Europa (Ireland)',
            'cn-north-1' => 'China (Beijing)',
            'ap-northeast-1' => 'Asia Pacific (Tokyo)',
            'ap-southeast-2' => 'Asia Pacific (Sydney)'
        );

        return $serverAvailableLocations;
    }
    
    /**
     * Explode ARN on it's component
     * Associative array contains:
     * - stringType
     * - provider
     * - serviceName
     * - serverLocation
     * - code
     * - vaultPath
     * - vaultName
     * @param string $arn ARN to be exploded
     * @param boolean $onlyVaultName If TRUE return only the vault name
     * @return array The exploded ARN on array
     */
    public static function explodeARN($arn, $onlyVaultName = FALSE) {
        $explodedArn = explode(':', $arn);
        
        if(count($explodedArn) !== 6) {
            return FALSE;
        }
        
        // Just the vault name
        $vaultName = substr($explodedArn[5], 7);
        
        if($onlyVaultName) {
            return $vaultName;
        } else {
            $vaultData = array(
                'stringType' => $explodedArn[0],
                'provider' => $explodedArn[1],
                'serviceName' => $explodedArn[2],
                'serverLocation' => $explodedArn[3],
                'code' => $explodedArn[4],
                'vaultPath' => $explodedArn[5],
                'vaultName' => $vaultName,
            );
            
            return $vaultData;
        }
    }

    /**
     * Get the vaults list
     */
    function vaultList() {
        $answer = $this->glacierClient->listVaults(array(
            'accountId' => '-'
        ));

        $data = $answer->getAll();

        $vaultList = $data['VaultList'];

        return $vaultList;
    }

    /**
     * Begin getting of the inventory of the indicated vault
     */
    function getInventory($vaultName) {
        $answer = $this->glacierClient->initiateJob(array(
            'accountId' => '-',
            'vaultName' => $vaultName,
            'Format'    => 'JSON',
            'Type' => 'inventory-retrieval',
            'Description' => 'Retrieve inventory'
        ));

        $data = $answer->getAll();
        return $data;
    }
    
    /**
     * Start an archive retrieval job
     * $retrBegin and $retrEnd are optional: if not set the whole archive will be
     * retrieved; if not null these data will be automatically megabyte aligned.
     * @param String $vaultName Name of the vault to retrieve the archive from
     * @param String $archiveID The ID of the archive to retrieve
     * @param Integer $retrBegin Start byte to retrieve the archive from
     * @param Integer $retrEnd End byte to retrieve the archive to
     * @return Array Job queuing result
     */
    function retrieveArchive($vaultName, $archiveID, $retrBegin=NULL, $retrEnd=NULL) {        
        $retrivealParameters = array(
            'accountId' => '-',
            'vaultName' => $vaultName,
            'Type' => 'archive-retrieval',
            'ArchiveId' => $archiveID,
            'Description' => 'Retrieve archive'
        );

        if(!is_null($retrBegin) && !is_null($retrEnd)) {
            // Compute range to be retrieved - megabyte aligned
            $archiveBegin = $retrBegin - ($retrBegin % $this->megaByte);

            $remainder = ($retrEnd + 1) % $this->megaByte;
            $endOffset = ($remainder == 0) ? 0 : ($this->megaByte - $remainder);
            $archiveEnd = $retrEnd + $endOffset + 1;

            $retrivealParameters['RetrievalByteRange'] = sprintf("%d-%d", $archiveBegin, $archiveEnd);
        }

        $result = $this->glacierClient->initiateJob($retrivealParameters);

        $data = $result->getAll();
        
        return $data;
    }
	
    /**
     * Get the list of all the jobs for the indicated vault
     */
    function listJobs($vaultName) {
        $answer = $this->glacierClient->listJobs(array(
                'accountId' => '-',
                'vaultName' => $vaultName
        ));

        $data = $answer->getAll();
        return $data['JobList'];
    }
	
    /**
     * Get the details of the indicated job
     */
    function describeJob($vaultName, $jobID) {
        $answer = $this->glacierClient->describeJob(array(
                'accountId' => '-',
                'vaultName' => $vaultName,
                'jobId' => $jobID
        ));

        $data = $answer->getAll();
        return $data;	
    }
    
    /**
     * Get the job's results and store on the indicated path
     * @param String $vaultName Vault name to retrieve the data from
     * @param String $jobID Job ID of the data retrieve job
     * @param String $destFilePath Destination path to put the contents
     * @param String $progressFile Path for status file
     * @return Array Operation's result
     */
    function getJobData($vaultName, $jobID, $destFilePath, $progressFile) {
        $progress = array(
            'pid'		=> getmypid(),
            'offline'   	=> $this->offline,
            'totalRead' 	=> 0,
            'totalWritten'	=> 0,
            'processedFiles'    => 0,
            'fileRead'          => 0,
            'totalFiles'	=> 1,
            'thisFilePath'	=> '',
            'thisFilePerc'	=> '',
            'status'            => '',
            'extStatus'         => ''
        );

        try {
            $answer = $this->glacierClient->getJobOutput(array(
                'accountId' => '-',
                'vaultName' => $vaultName,
                'jobId' => $jobID,
                'saveAs' => $destFilePath
            ));
        }
        catch (Aws\Glacier\Exception\GlacierException $ex) {
            $progress['status'] = 'error';
            $progress['extStatus'] = $ex->getExceptionCode() . ' - ' . $ex->getMessage();

            file_put_contents($progressFile, json_encode($progress));
            
            return FALSE;
        }

        $progress['status'] = 'completed';
        $progress['extStatus'] = 'HTTP status: ' . $answer['status'];
        file_put_contents($progressFile, json_encode($progress));
        
        $data = $answer->getAll();

        return $data;
    }

    /**
     * Get the result of an inventory
     */
    function getInventoryResult($vaultName, $jobID, $tmpFilePath) {
        $this->getJobData($vaultName, $jobID, $tmpFilePath);

        // Read JSON data
        $inventoryJSON = file_get_contents($tmpFilePath);
        $inventory = json_decode($inventoryJSON, TRUE);
        unlink($tmpFilePath);

        return $inventory;
    }

    /**
     * Create a new vault
     */
    function createVault($vaultName) {
        $answer = $this->glacierClient->createVault(array(
            'accountId' => '-',
            'vaultName' => $vaultName
        ));

        $result = $answer->getAll();

        return $result['location'];
    }

    /**
     * Delete a vault
     */
    function deleteVault($vaultName) {
        $answer = $this->glacierClient->deleteVault(array(
            'accountId' => '-',
            'vaultName' => $vaultName
        ));

        $result = $answer->getAll();

        return $result;
    }

    /**
     * Delete an archive
     */
    function deleteArchive($vaultName, $archiveID) {
        $answer = $this->glacierClient->deleteArchive(array(
            'accountId' => '-',
            'vaultName' => $vaultName,
            'archiveId' => $archiveID
        ));

        return $answer->getAll();
    }

    /**
     * Check for SHA-256 algorithm registered
     * Return TRUE if present, an array with registered algorithm otherwise
     */
    function checkAlgos() {
        $algos = hash_algos();
        if(array_search('sha256', $algos) === FALSE) {
            return $algos;
        } else {
            return TRUE;
        }
    }

    /**
     * Upload an archive - choose the best method based on size
     * Less than 100Mb will be single request, multiple request otherwise
     * @param String $vaultName Name of the vault to upload the archive into
     * @param String $filePath Path of the file to upload
     * @param String $description Description of the archive - If NULL the filename will be assigned
     * @param String $progressFile Path of the progress file to write
     */
    function uploadArchive($vaultName, $filePath, $description = NULL, $progressFile = NULL) {
        // Get archive description if passed is null
        if(is_null($description)) {
            $pathParts = pathinfo($filePath);
            $description = $pathParts['basename'];
        }

        // Initialize status array
        $progress = array(
            'pid'		=> getmypid(),
            'offline'           => $this->offline,
            'totalRead' 	=> 0,
            'totalWritten'	=> 0,
            'processedFiles'    => 0,
            'fileRead'          => 0,
            'totalFiles'	=> 1,
            'thisFilePath'	=> '',
            'thisFilePerc'	=> '',
            'status'            => '',
            'extStatus'         => ''
        );

        // Get file size; if FALSE the file is not accessible, forfait
        $size = filesize($filePath);

        if($size === FALSE) {
            // Record on progress file
            if(!is_null($progressFile)) {
                $progress['status'] = 'error';
                $progress['extStatus'] = 'Unable to read file';
                file_put_contents($progressFile, json_encode($progress));
            }

            return FALSE;
        }

        // Choose the right upload way and run for it
        if($size < (10 * $this->megaByte)) {
            // Record on progress file
            if(!is_null($progressFile)) {
                $progress['status'] = 'running';
                $progress['extStatus'] = 'Uploading single file ' . \OCA\aletsch\utilities::formatBytes($size);
                file_put_contents($progressFile, json_encode($progress));
            }

            // Do upload
            try {
                $archiveID = $this->uploadArchiveSingle($vaultName, $filePath, $description);
            }
            catch (Aws\Glacier\Exception\GlacierException $ex) {
                $progress['status'] = 'error';
                $progress['extStatus'] = $ex->getExceptionCode() . ' - ' . $ex->getMessage();

                file_put_contents($progressFile, json_encode($progress));

                return FALSE;
            }
        } else {
            // Record on progress file
            if(!is_null($progressFile)) {
                $progress['status'] = 'running';
                $progress['extStatus'] = 'Uploading multipart ' . \OCA\aletsch\utilities::formatBytes($size);
                file_put_contents($progressFile, json_encode($progress));
            }

            // Do upload
            try {
                $archiveID = $this->uploadArchiveMultipart($vaultName, $filePath, $description);
            }
            catch (Aws\Glacier\Exception\GlacierException $ex) {
                $progress['status'] = 'error';
                $progress['extStatus'] = $ex->getExceptionCode() . ' - ' . $ex->getMessage();

                file_put_contents($progressFile, json_encode($progress));

                return FALSE;
            }
        }

        // Record on progress file
        if(!is_null($progressFile)) {
            $progress['processedFiles'] = 1;
            $progress['status'] = 'completed';
            $progress['extStatus'] = $archiveID;
            file_put_contents($progressFile, json_encode($progress));
        }
        return $archiveID;
    }

    /**
     * Upload an archive with a single request
     * $vaultName - Name of the vault to upload the archive into
     * $filePath - Path of the file to upload
     */
    function uploadArchiveSingle($vaultName, $filePath, $description) {
        if($this->offline) {
            $archiveID = uniqid('OFFLINE_');
        } else {
            $archiveData = fopen($filePath, 'rb');

            if($archiveData === FALSE) {
                return FALSE;
            }

            $result = $this->glacierClient->uploadArchive(array(
                'vaultName' => $vaultName,
                'archiveDescription' => $description,
                'body' => $archiveData
            ));

            $archiveID = $result->get('archiveId');
        }

        return $archiveID;
    }

    /**
     * Upload an archive with a multiple request
     * $vaultName - Name of the vault to upload the archive into
     * $filePath - Path of the file to upload
     */
    function uploadArchiveMultipart($vaultName, $filePath, $description) {
        if($this->offline) {
            $archiveID = uniqid('OFFLINE_');
        } else {
            $archiveRSRC = fopen($filePath, 'rb');
            $multiParts = UploadPartGenerator::factory($archiveRSRC, $this->uploadPartSize);

            // Initiate multipart upload
            $result = $this->glacierClient->initiateMultipartUpload(array(
                'vaultName' => $vaultName,
                'archiveDescription' => $description,
                'partSize'  => $this->uploadPartSize,
            ));

            $multipartUploadId = $result->get('uploadId');

            // Upload each part individually using data from the part generator
            $archiveData = fopen($filePath, 'rb');
            foreach ($multiParts as $part) {
                fseek($archiveData, $part->getOffset());

                $this->glacierClient->uploadMultipartPart(array(
                    'vaultName'     => $vaultName,
                    'uploadId'      => $multipartUploadId,
                    'body'          => fread($archiveData, $part->getSize()),
                    'range'         => $part->getFormattedRange(),
                    'checksum'      => $part->getChecksum(),
                    'ContentSHA256' => $part->getContentHash()
                ));
            }
            fclose($archiveData);

            // Complete the upload
            $result = $this->glacierClient->completeMultipartUpload(array(
                'vaultName'   => $vaultName,
                'uploadId'    => $multipartUploadId,
                'archiveSize' => $multiParts->getArchiveSize(),
                'checksum'    => $multiParts->getRootChecksum()
            ));

            unset($multipartUploadId);
            unset($multiParts);

            $archiveID = $result->get('archiveId');
        }

        return $archiveID;
    }

    /**
     * Cleanup and init the archiver
     */
    function cleanupArchiver() {
        $this->archiver = array();
    }
	
    /**
     * Get file's id from it's path
     */
    function getIDFromPath($filePath) {
        return hash('sha256', $filePath);
    }

    /**
     * Add a file path to the current archiver
     */
    function addFileToArchiver($filePath) {
        // If file not accessible, forfait and return false
        if(!is_file($filePath)) {
            return FALSE;
        }

        // Add file to the file's list to compress
        $fileID = $this->getIDFromPath($filePath);

        $this->archiver[$fileID] = array(
            'filePath'      => $filePath,
            'owner'         => fileowner($filePath),
            'group'         => filegroup($filePath),
            'permissions'   => fileperms($filePath),
            'fileSize'      => filesize($filePath)
        );

        return $fileID;
    }

    /**
     * Remove a file from the list given it's ID
     */
    function removeFileFromArchiver($fileID) {
        if(array_search($fileID, $this->archiver)) {
            unset($this->archiver[$fileID]);
            return $fileID;
        } else {
            return FALSE;
        }
    }
	
    /**
     * List current archiver
     */
    function listArchiver() {
        $totLen = 0;

        foreach($this->archiver as $inFileData) {
            $totLen += $inFileData['fileSize'];
        }

        $summary = array(
            'totLen'	=> $totLen,
            'totFiles'	=> count($this->archiver)
        );

        $result = $this->archiver;
        $result['summary'] = $summary;

        return $result;
    }

    /**
     * Execute a new archiver operation
     * @param String $vaultName Vault name to archive the generated files
     * @param String $archiveName Name of the archive - Used as prefix of the files
     * @param Strong $progressFile Path to progress file
     */
    function archive($vaultName, $archiveName, $progressFile = NULL) {
        // Keeps track of working parameters
        $progress = array(
            'pid'		=> getmypid(),
            'offline'           => $this->offline,
            'totalRead' 	=> 0,
            'totalWritten'	=> 0,
            'processedFiles'    => 0,
            'fileRead'          => 0,
            'totalFiles'	=> count($this->archiver),
            'thisFilePath'	=> '',
            'thisFilePerc'	=> ''
        );

        // Check if we have files on archiver
        if(count($this->archiver) < 1) {
            $progress['status'] = 'error';
            $progress['extStatus'] = 'No files on archiver';
            if(!is_null($progressFile)) {
                file_put_contents($progressFile, json_encode($progress));
            }
            
            return FALSE;
        }

        // Begin operations
        $tempDir = sys_get_temp_dir();
        $tempFileName = $tempDir . uniqid('/aletsch_tmp_');
        $catalog = array();
        $inThisReel = array();
        $reelNo = 0;
        $blockNo = 0;
        $reelWritten = 0;
        $timeBegin = time();

        // Actual chunk output
        $tempFile = fopen($tempFileName, 'wb');
        if(!$tempFile) {
            $progress['status'] = 'error';
            $progress['extStatus'] = 'Unable to open temporary file for output';
            if(!is_null($progressFile)) {
                file_put_contents($progressFile, json_encode($progress));
            }
            
            return FALSE;
        }

        // Loop through files to be stored
        $progress['status'] = 'running';

        foreach($this->archiver as $inFileID => $inFileData) {
            $progress['thisFilePath'] = $inFileData['filePath'];
            $progress['fileRead'] = 0;

            $inrsrc = fopen($inFileData['filePath'], 'rb');
            $fileLen = 0;		// Length of compressed file in bytes
            $blocksLen = 0;		// Lenght of compressed file in blocks

            $catalog[$inFileID] = array(
                'filePos'	=> $reelWritten,	// The beginning of this file!
                'fileData'	=> $inFileData
            );

            // Update file list on current reel
            $inThisReel[] = $inFileID;

            // Read file content and proceed for compression
            while($buff = fread($inrsrc, $this->blockSize)) {
                $bufout = gzcompress($buff);
                $bufOutSize = strlen($bufout);

                $ctrlData = sprintf("reel=%d,block=%d,len=%d\n", $reelNo, $blockNo++, $bufOutSize);
                $newDataLen = $bufOutSize + strlen($ctrlData);

                // Here evaluate if continue to actual reel or open a new one
                // If a new reel has to be opened, put a %NEXTREEL mark
                if($reelWritten + $newDataLen >= $this->maxReelSize) {
                    // Put next reel mark and close the file
                    fwrite($tempFile, '%NEXTREEL');
                    fclose($tempFile);

                    // Upload last reel
                    $outFileName = sprintf("%s.reel-%d", $archiveName, $reelNo);
                    if($this->offline) {
                        $reelID = $tempDir . '/' . $outFileName;
                        rename($tempFileName, $reelID);
                    } else {
                        $reelID = $this->uploadArchive($vaultName, $tempFileName, $outFileName);
                    }

                    // Assign reel ID to all file contained on last reel
                    foreach($inThisReel as $fileID) {
                        $catalog[$fileID]['reelID'][] = $reelID;
                    }

                    // Cleanup list of file on current reel - Leave just the current file ID
                    unset($inThisReel);
                    $inThisReel = array($inFileID);

                    // Begin a new reel
                    $tempFile = fopen($tempFileName, 'wb');
                    if(!$tempFile) {
                        $progress['status'] = 'error';
                        $progress['extStatus'] = 'Unable to open temporary file for output';
                        if(!is_null($progressFile)) {
                            file_put_contents($progressFile, json_encode($progress));
                        }
            
                        return FALSE;
                    }

                    // Increment counters
                    $reelNo++;

                    // Reset position pointers
                    $reelWritten = 0;
                    $blockNo = 0;

                    // New control data
                    $ctrlData = sprintf("reel=%d,block=%d,len=%d\n", $reelNo, $blockNo, $bufOutSize);
                }

                fwrite($tempFile, $ctrlData);
                fwrite($tempFile, $bufout);

                // Increment read data counters
                $blockRead = strlen($buff);
                $progress['totalRead'] += $blockRead;
                $progress['fileRead'] += $blockRead;

                // Increment written data counters
                $fileLen += $newDataLen;
                $reelWritten = ftell($tempFile);
                $progress['totalWritten'] += $newDataLen;
                $blocksLen++;

                // output progress data if requested
                $progress['thisFilePerc'] = sprintf("%6.2f", ($progress['fileRead'] / $inFileData['fileSize']) * 100);
                if(!is_null($progressFile)) {
                    file_put_contents($progressFile, json_encode($progress));
                }
            }

            // Store file data
            $catalog[$inFileID]['fileLen'] = $fileLen;
            $catalog[$inFileID]['blocksLen'] = $blocksLen;
            $catalog[$inFileID]['endPos'] = $reelWritten;

            // Close actual infile
            fclose($inrsrc);

            // Increment number of processed files
            $progress['processedFiles']++;
        }

        // Close and upload current reel
        fclose($tempFile);
        $outFileName = sprintf("%s.reel-%d", $archiveName, $reelNo);

        if($this->offline) {
            $reelID = $tempDir . '/' . $outFileName;
            rename($tempFileName, $reelID);
        } else {
            $reelID = $this->uploadArchive($vaultName, $tempFileName, $outFileName);
            unlink($tempFileName);
        }

        // Assign reel ID to last processed files
        foreach($inThisReel as $fileID) {
            $catalog[$fileID]['reelID'][] = $reelID;
        }
        unset($inThisReel);

        // Save current catalog
        $this->lastCatalog = $catalog;

        // Save on glacier the full catalog
        $catalog2upload = gzcompress(json_encode($catalog));
        if($this->offline) {
            $catalogID = $tempDir . '/' . $archiveName . '.catalog';
            file_put_contents($catalogID, $catalog2upload);
        } else {
            file_put_contents($tempFileName, $catalog2upload);
            $catalogID = $this->uploadArchive($vaultName, $tempFileName, $archiveName . '.catalog');
            unlink($tempFileName);
        }

        // Return operation's data
        $summary = array(
            'reels'         => ($reelNo + 1),
            'files'         => $progress['processedFiles'],
            'totTime'       => time() - $timeBegin,
            'totalRead'     => $progress['totalRead'],
            'totalWritten'  => $progress['totalWritten']
        );

        $result = array(
            'catalogID' => $catalogID,
            'summary'   => $summary,
            'catalog'   => $catalog
        );

        if($this->offline) {
            $summaryID = $tempDir . '/' . $archiveName . '.summary';
            file_put_contents($summaryID, json_encode($result));
        }

        // Save a copy of the catalog on progress file
        $progress['catalog'] = $result;
        
        // Send "done" to progress file
        $progress['status'] = 'completed';
        if(!is_null($progressFile)) {
            file_put_contents($progressFile, json_encode($progress));
        }

        return $result;
    }

    /**
     * Retrieve catalog from glacier
     */
    function startCatalogRetrieve($vaultName, $fileID) {
        if($this->offline) {
            $retrJobID = $fileID;
        } else {
            $result = $this->glacierClient->initiateJob(array(
                'accountId' => '-',
                'vaultName' => $vaultName,
                'Format' => 'JSON',
                'Type' => 'archive-retrieval',
                'ArchiveId' => $fileID,
                'Description' => "Retrieve catalog from vault '$vaultName'"
            ));

            $retrJobID = $result->getAll();
        }

        return $retrJobID;
    }
	
	/**
	 * Execute a file retrieval operation
	 * Returns the job's ID
	 */
	function startUnarchive($vaultName, $fileID) {
            // Get file data from catalog
            $fileInfo = $this->lastCatalog[$fileID];
            if(!is_array($fileInfo)) {
                return false;	// File data not found
            }

            /* At this point the $fileInfo should be:
                $fileInfo = array(
                    'filePos'	=> $reelWritten,	// The beginning of the file!
                    'endPos'	=> $reelWritten - 1;	// End of the file
                    'fileData'	=> array(
                                            'filePath'      => $filePath,
                                            'owner'         => fileowner($filePath),
                                            'group'         => filegroup($filePath),
                                            'permissions'   => fileperms($filePath),
                                            'fileSize'      => filesize($filePath)
                                        ),
                    'reelID'	=> array(reelIDs),
                    'fileLen' 	=> $fileLen,
                    'blocksLen' => ($blocksLen - 1)
                );
            */

            // Compute reel 
            $reelBegin = $fileInfo['reelID'][0];
            $lastReelIndex = count($fileInfo['reelID']) - 1;
            $reelEnd = $fileInfo['reelID'][$lastReelIndex];

            // Compute range to be retrieved - megabyte aligned
            $fileBegin = $fileInfo['filePos'] - ($fileInfo['filePos'] % $this->megaByte);

            $remainder = ($fileInfo['endPos'] + 1) % $this->megaByte;
            $endOffset = ($remainder == 0) ? 0 : ($this->megaByte - $remainder);
            $fileEnd = $fileInfo['endPos'] + $endOffset + 1;

            // Compute new start / end position 
            $fileInfo['filePos'] = $fileInfo['filePos'] - $fileBegin;
            if(count($fileInfo['reelID']) == 1) {
                $fileInfo['endPos'] = $fileInfo['endPos'] - $fileBegin;
            }
		
            // Start retrieval jobs
            $retrJobID = array();

            for($reelIndex = 0; $reelIndex <= $lastReelIndex; $reelIndex++) {
                if($reelIndex == 0) {
                    // First reel
                    $retrieveAll = FALSE;
                    $retrBegin = $fileBegin;
                    $retrEnd = $this->maxReelSize;
                } else if($reelIndex == $lastReelIndex) {
                    // Last reel
                    $retrieveAll = FALSE;
                    $retrBegin = 0;
                    $retrEnd = $fileEnd;
                } else {
                    // Intermediate reel(s)
                    $retrieveAll = TRUE;
                    $retrBegin = 0;
                    $retrEnd = $this->maxReelSize;
                }

                if($this->offline) {
                    // Prepare some "chunk file" that conform to what glacier will return
                    $tempDir = sys_get_temp_dir();
                    $chunkFileName = $tempDir . uniqid('/aletsch_chunk_');

                    $retrJobID[] =
                        array(
                            'vaultName' => $vaultName,
                            'ArchiveId' => $chunkFileName,
                            'Description' => 'Retrieve reel #' . $reelIndex . " from vault '$vaultName'",
                            'RetrievalByteRange' => sprintf("%d-%d %s", $retrBegin, $retrEnd, (($retrieveAll) ? '(ALL)':''))
                    );

                    $chunkRsrc = fopen($chunkFileName, 'wb');
                    $inRsrc = fopen($fileInfo['reelID'][$reelIndex], 'rb');
                    fseek($inRsrc, $retrBegin);

                    if($reelIndex == 0) {
                        // First reel
                        $toRead = $this->maxReelSize - $retrBegin;
                    } else if($reelIndex == $lastReelIndex) {
                        // Last reel
                        $toRead = $fileEnd;
                    } else {
                        // Intermediate reel(s)
                        $toRead = $this->maxReelSize;
                    }

                    do {
                        $buffer = fread($inRsrc, ($this->megaByte * $this->fileCopyBlockSize));
                        fwrite($chunkRsrc, $buffer);

                        $toRead -= strlen($buffer);
                    } while(!feof($inRsrc) && $toRead > 0 );

                    fclose($chunkRsrc);
                    fclose($inRsrc);
                } else {
                    $retrivealParameters = array(
                        'accountId' => '-',
                        'vaultName' => $vaultName,
                        'Format' => 'JSON',
                        'Type' => 'archive-retrieval',
                        'ArchiveId' => $fileInfo['reelID'][$reelIndex],
                        'Description' => 'Retrieve reel #' . $reelIndex . " from vault '$vaultName'"
                    );

                    if(!$retrieveAll) {
                        $retrivealParameters['RetrievalByteRange'] = sprintf("%d-%d", $retrBegin, $retrEnd);
                    }

                    $result = $this->glacierClient->initiateJob($retrivealParameters);

                    $retrJobID[] = $result->getAll();
                }
            }

            $retrieveData = array(
                'fileInfo' => $fileInfo,
                'jobIDs' => $retrJobID
            );

            return $retrieveData;
	}
	
	/**
	 * Execute a file retrieval operation
	 * Returns the uncompressed catalog in JSON
	 */
	function retrieveCatalogContent($vaultName, $jobID) {
            if($this->offline) {
                $tempFileName = $jobID;
            } else {
                $tempDir = sys_get_temp_dir();
                $tempFileName = $tempDir . uniqid('/aletsch_tmp_');

                $result = $this->glacierClient->getJobOutput(array(
                    'accountId' => '-',
                    'vaultName' => $vaultName,
                    'jobId' => $jobID,
                    'saveAs' => $tempFileName
                ));

                $data = $result->getAll();
            }

            $result = file_get_contents($tempFileName);
            $catalog = gzuncompress($result);
            unlink($tempFileName);

            return $catalog;
	}

	/**
	 * Execute an archive download and join operation
	 * Returns the temp filename with the file content
	 */
	function retrieveArchiveContent($vaultName, $retrieveData, $outFileName = NULL, $progressFile = NULL) {
            // Prepare destination pathname
            if(is_null($outFileName)) {
                $outPath = $retrieveData['fileInfo']['fileData']['filePath'];
            } else if($outFileName == '.') {
                $outPath = '.' . $retrieveData['fileInfo']['fileData']['filePath'];
            } else {
                $outPath = $outFileName;
            }

            // Keeps track of working parameters
            $progress = array(
                    'pid'		=> getmypid(),
                    'offline'		=> $this->offline,
                    'totalBlocks'	=> $retrieveData['fileInfo']['blocksLen'],
                    'thisFilePath'	=> $outPath,
                    'step'		=> 'NOTHING',
                    'descr1'		=> '',
                    'descr2'		=> '',
                    'totalRead' 	=> 0,
                    'totalWritten'	=> 0,
                    'blocksProcessed'	=> 0,
                    'thisFilePerc'	=> ''
            );

            if(!is_null($progressFile)) {
                file_put_contents($progressFile, json_encode($progress));					
            }

            // Start file processing
            $tempDir = sys_get_temp_dir();
            $retrieveFileName = $tempDir . uniqid('/aletsch_retrieve_');
            $destRsrc = fopen($retrieveFileName, 'wb');

            $nbOfReels = count($retrieveData['jobIDs']);
		
            for($index = 0; $index < $nbOfReels; $index++) {
                $partRetrieveFileName = $tempDir . uniqid('/aletsch_part_');
                $retrieveJobID = $retrieveData['jobIDs'][$index]['ArchiveId'];

                if($this->offline) {
                    $partRetrieveFileName = $retrieveJobID;
                } else {
                    $progress['step'] = 'DOWNLOADING';
                    $progress['descr1'] = $retrieveJobID;

                    if(!is_null($progressFile)) {
                            file_put_contents($progressFile, json_encode($progress));					
                    }

                    $result = $this->glacierClient->getJobOutput(array(
                            'accountId' => '-',
                            'vaultName' => $vaultName,
                            'jobId' => $retrieveJobID,
                            'range' => sprintf("%d-%d", $retrieveData['fileInfo']['filePos'], $retrieveData['fileInfo']['endPos']),
                            'saveAs' => $partRetrieveFileName
                    ));

                    $data = $result->getAll();
                }

                $resID = fopen($partRetrieveFileName, 'rb');
                if(!$resID) {
                    printf("Unable to open %s.", $partRetrieveFileName);
                    return FALSE;
                }

                // Seek on first reel
                if($index == 0) {
                    fseek($resID, $retrieveData['fileInfo']['filePos']);
                    /*printf("\nSeek to: %d\n", $retrieveData['fileInfo']['filePos']); */
                }

                // Compute how many data to read
                // - Intermediate reel -> until EOF
                // - Last reel -> until endPos
                if($index == ($nbOfReels - 1)) {
                    $toRead = $retrieveData['fileInfo']['endPos'];
                    /*printf("\nProcessing final reel %s: %d\n", $retrieveJobID, $toRead); */
                } else {
                    $toRead = filesize($retrieveJobID);
                    /*printf("\nProcessing intermediate reel %s: %d bytes\n", $retrieveJobID, $toRead); */
                }

                $progress['totalRead'] = $toRead;
                $progress['totalWritten'] = 0;
                $progress['step'] = 'COPYING';

                do {
                    $buffer = fread($resID, ($this->megaByte * $this->fileCopyBlockSize));
                    fwrite($destRsrc, $buffer);

                    $toRead -= strlen($buffer);

                    $progress['totalWritten'] += strlen($buffer);
                    $progress['thisFilePerc'] = sprintf("%6.2f", ($progress['totalWritten'] / $progress['totalRead']) * 100);

                    if(!is_null($progressFile)) {
                        file_put_contents($progressFile, json_encode($progress));					
                    }
                } while(!feof($resID) && $toRead > 0 );

                fwrite($destRsrc, "\n");

                fclose($resID);

                // Delete part retrieve file
                unlink($partRetrieveFileName);
            }
		
		// Close output file
		fclose($destRsrc);

		// Create destination directory if not exists
		if(is_null($outFileName) || $outFileName == '.') {
			$parent = dirname($outPath);
			
			if(!is_dir($parent)) {
				mkdir($parent, 0777, TRUE);
			}
		}

		// Uncompress it
		$outrsrc = fopen($outFileName, 'wb');
		$inrsrc = fopen($retrieveFileName, 'rb');

		$progress['step'] = 'UNCOMPRESSING';
		$progress['totalBlocks'] = $retrieveData['fileInfo']['blocksLen'];
		
		if(!is_null($progressFile)) {
			file_put_contents($progressFile, json_encode($progress));					
		}				

		for($block = 0; $block < $retrieveData['fileInfo']['blocksLen']; $block++) {
			$ctrlDataLineNum = fscanf($inrsrc, "%s\n", $ctrlDataLine);
			/*printf("%s\n", $ctrlDataLine);*/
			
			if($ctrlDataLine == '%NEXTREEL') {
				$ctrlDataLineNum = fscanf($inrsrc, "%s\n", $ctrlDataLine);
			}
		
			$ctrlDataNum = sscanf($ctrlDataLine, "reel=%d,block=%d,len=%d", $reelNo, $blockNo, $blockLen);

			// Unable to find block begin data
			if($ctrlDataNum == 0) {
				/*printf("Not enough parameters!\n%s\n", $ctrlDataLine);*/
				fclose($inrsrc);
				fclose($outrsrc);

				return FALSE;
			}
			
			$buff = fread($inrsrc, $blockLen);
			$wresult = fwrite($outrsrc, gzuncompress($buff));
			
			// Keep track of process data
			$progress['blocksProcessed'] = $block;
			$progress['thisFilePerc'] = sprintf("%6.2f", ($block / $retrieveData['fileInfo']['blocksLen']) * 100);
			
			if(!is_null($progressFile)) {
				file_put_contents($progressFile, json_encode($progress));					
			}				
		}

		fclose($inrsrc);
		fclose($outrsrc);
		
		unlink($retrieveFileName);
		
		// Send job complete status
		$progress = array(
			'pid'			=> getmypid(),
			'offline'		=> $this->offline,
			'totalBlocks'		=> $retrieveData['fileInfo']['blocksLen'],
			'thisFilePath'		=> $outPath,
			'step'			=> 'DONE',
			'descr1'		=> '',
			'descr2'		=> '',
			'totalRead' 		=> 0,
			'totalWritten'		=> 0,
			'blocksProcessed'	=> 0,
			'thisFilePerc'		=> ''
		);

		if(!is_null($progressFile)) {
			file_put_contents($progressFile, json_encode($progress));					
		}

		return TRUE;		
	}
}
