<?php
namespace OCA\aletsch;

include __DIR__ . '/aws.phar';
use Aws\Glacier\GlacierClient;
use Aws\Glacier\Model\MultipartUpload\UploadBuilder;
use Aws\Common\Hash\TreeHash;
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

    private $multipartUploadId;
    private $multiParts;

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
		$minAccept = $this->megaByte * 4;			// 4Mb
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
	function getCatalogEntries($catalog) {
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
     * Get all files ID of the indicated user
     * NOTE: TO BE PATCHED FOR OC7!!!
     * @param string $user Username
     * @param string $path Path to get the content
     * @param boolean $onlyID Get only the ID of files
     * @param boolean $indexed Output result as dictionary array with fileID as index
     * @return array ID of all the files
     */
    public static function getOCFileList($user, $path = '', $onlyID = FALSE, $indexed = FALSE) {
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

                $itemRes = \OCA\aletsch\aletsch::getFileList($user, $itemPath, $onlyID);
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
                                    $result = array_merge($result, \OCA\aletsch\aletsch::getFSFileList($workPath));
                            } else {
                                    $result[] = $workPath;
                            }
                    }
            }

            $currentDir->close();

            return $result;
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
			'Type' => 'inventory-retrieval'
		));

		$data = $answer->getAll();
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
	 */
	function getJobData($vaultName, $jobID, $destFilePath) {
        $answer = $this->glacierClient->getJobOutput(array(
            'accountId' => '-',
            'vaultName' => $vaultName,
            'jobId' => $jobID,
            'saveAs' => $destFilePath
        ));

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
		$inventory = json_decode($inventoryJSON);
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
		
		return 'done';
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
	 * $vaultName - Name of the vault to upload the archive into
	 * $filePath - Path of the file to upload
	 */
	function uploadArchive($vaultName, $filePath, $description = NULL) {
		if(is_null($description)) {
			$pathParts = pathinfo($filePath);
			$description = $pathParts['basename'];
		}
			
		$size = filesize($filePath);
		
		if($size === FALSE) {
			return FALSE;
		}
		
		if($size < (10 * $this->megaByte)) {
			$archiveID = $this->uploadArchiveSingle($vaultName, $filePath, $description);
		} else {
			$archiveID = $this->uploadArchiveMultipart($vaultName, $filePath, $description);
		}
	
		return $archiveID;
	}
	
	/**
	 * Upload an archive with a single request
	 * $vaultName - Name of the vault to upload the archive into
	 * $filePath - Path of the file to upload
	 */
	function uploadArchiveSingle($vaultName, $filePath, $description) {
		$archiveData = fopen($filePath, 'rb');

		if($archiveData === FALSE) {
			return FALSE;
		}
		
		$result = $this->glacierClient->uploadArchive(array(
			'vaultName' => $vaultName,
			'archiveDescription' => $description,
			'body'      => $archiveData
		));
		
		$archiveID = $result->get('archiveId');
		
		return $archiveID;
	}
	
	/**
	 * Upload an archive with a multiple request
	 * $vaultName - Name of the vault to upload the archive into
	 * $filePath - Path of the file to upload
	 */
	function uploadArchiveMultipart($vaultName, $filePath, $description) {
		$this->prepareMultipartUpload($filePath);
		
		// Initiate multipart upload
		$this->initiateMultipartUpload($vaultName);
		
		// Upload each part individually using data from the part generator
		$archiveData = fopen($filePath, 'rb');
		foreach ($parts as $part) {
			fseek($archiveData, $part->getOffset());

			$this->glacierClient->uploadMultipartPart(array(
					'vaultName'     => $vaultName,
					'uploadId'      => $this->multipartUploadId,
					'body'          => fread($archiveData, $part->getSize()),
					'range'         => $this->multiParts->getFormattedRange(),
					'checksum'      => $this->multiParts->getChecksum(),
					'ContentSHA256' => $this->multiParts->getContentHash()
				));
		}
		fclose($archiveData);
 
		// Complete the upload
		$archiveId = $this->closeMultipartUpload($vaultName, $this->multipartUploadId);
		
		return $archiveId;
	}
	
	/**
	 * Initiate a multipart upload - Upload ID stored as property
	 */
	function initiateMultipartUpload($vaultName) {
		$result = $this->glacierClient->initiateMultipartUpload(array(
			'vaultName' => $vaultName,
			'partSize'  => $this->uploadPartSize,
		));
		
		$this->multipartUploadId = $result->get('uploadId');
	}
	
	/**
	 * Close a multipart upload - Returns an upload ID
	 */
	function closeMultipartUpload($vaultName) {
		$result = $this->glacierClient->completeMultipartUpload(array(
			'vaultName'   => $vaultName,
			'uploadId'    => $this->multipartUploadId,
			'archiveSize' => $this->multiParts->getArchiveSize(),
			'checksum'    => $this->multiParts->getRootChecksum()
		));
		
		unset($this->multipartUploadId);
		unset($this->multiParts);
		
		return $result->get('archiveId');
	}

	/**
	 * Prepare a multipart upload and store all the data as object property
	 */
	function prepareMultipartUpload($filePath) {
		$archiveRSRC = fopen($filePath, 'rb');
		$this->multiParts = UploadPartGenerator::factory($archiveRSRC, $this->uploadPartSize);
	}
	
	/**
	 * Cleanup and init the archiver
	 */
	function cleanupArchiver() {
		$this->archiver = array();
		unset($this->archiver);
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
			'filePath'		=> $filePath,
			'owner'			=> fileowner($filePath),
			'group'			=> filegroup($filePath),
			'permissions'	=> fileperms($filePath),
			'fileSize'		=> filesize($filePath)
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
		
		foreach($this->archiver as $inFileID => $inFileData) {
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
	 */
	function archive($vaultName, $archiveName, $progressFile = NULL) {
		// Check if we have files on archiver
		if(count($this->archiver) < 1) {
			return FALSE;
		}
		
		// Begin operations
		$tempDir = sys_get_temp_dir();
		$tempFileName = $tempDir . '/aletsch_tmp_' . uniqid();
		$catalog = array();
		$inThisReel = array();
		$reelNo = 0;
		$blockNo = 0;
		$reelWritten = 0;
		$timeBegin = time();
		
		// Keeps track of working parameters
		$progress = array(
			'pid'				=> getmypid(),
			'offline'			=> $this->offline,
			'totalRead' 		=> 0,
			'totalWritten'		=> 0,
			'processedFiles'	=> 0,
			'fileRead'			=> 0,
			'totalFiles'		=> count($this->archiver),
			'thisFilePath'		=> '',
			'thisFilePerc'		=> ''
		);
		
		// Actual chunk output
		$tempFile = fopen($tempFileName, 'wb');
		if(!$tempFile) {
			return FALSE;
		}
		
		// Loop through files to be stored
		foreach($this->archiver as $inFileID => $inFileData) {
			$progress['thisFilePath'] = $inFileData['filePath'];
			$progress['fileRead'] = 0;
			
			$inrsrc = fopen($inFileData['filePath'], 'rb');
			$fileLen = 0;		// Length of compressed file in bytes
			$blocksLen = 0;		// Lenght of compressed file in blocks

			$catalog[$inFileID] = array(
				'filePos'		=> $reelWritten,	// The beginning of this file!
				'fileData'		=> $inFileData
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
				
				$wresult1 = fwrite($tempFile, $ctrlData);
				$wresult2 = fwrite($tempFile, $bufout);
				
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
		}

		// Cleanup temp file
		unlink($tempFileName);
		
		// Return operation's data
		$summary = array(
			'reels'			=> ($reelNo + 1),
			'files'			=> $progress['processedFiles'],
			'totTime'		=> time() - $timeBegin,
			'totalRead'		=> $progress['totalRead'],
			'totalWritten'	=> $progress['totalWritten']
		);
		
		$result = array(
			'catalogID' => $catalogID,
			'summary'	=> $summary,
			'catalog'	=> $catalog
		);
		
		if($this->offline) {
			$summaryID = $tempDir . '/' . $archiveName . '.summary';
			file_put_contents($summaryID, json_encode($result));
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
			
			$retrJobID = $answer->getAll();
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
				'filePos'		=> $reelWritten,		// The beginning of the file!
				'endPos'		=> $reelWritten - 1;	// End of the file
				'fileData'		=> array(
										'filePath'		=> $filePath,
										'owner'			=> fileowner($filePath),
										'group'			=> filegroup($filePath),
										'permissions'	=> fileperms($filePath),
										'fileSize'		=> filesize($filePath)
									),
				'reelID'		=> array(reelIDs),
				'fileLen' 		=> $fileLen,
				'blocksLen' 	=> ($blocksLen - 1)
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
				$chunkFileName = $tempDir . '/aletsch_chunk_' . uniqid();

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
				
				$retrJobID[] = $answer->getAll();
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
			$tempFileName = $tempDir . '/aletsch_tmp_' . uniqid();
		
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
			'pid'				=> getmypid(),
			'offline'			=> $this->offline,
			'totalBlocks'		=> $retrieveData['fileInfo']['blocksLen'],
			'thisFilePath'		=> $outPath,
			'step'				=> 'NOTHING',
			'descr1'			=> '',
			'descr2'			=> '',
			'totalRead' 		=> 0,
			'totalWritten'		=> 0,
			'blocksProcessed'	=> 0,
			'thisFilePerc'		=> ''
		);

		if(!is_null($progressFile)) {
			file_put_contents($progressFile, json_encode($progress));					
		}

		// Start file processing
		$tempDir = sys_get_temp_dir();
		$retrieveFileName = $tempDir . '/aletsch_retrieve_' . uniqid();
		$destRsrc = fopen($retrieveFileName, 'wb');

		$nbOfReels = count($retrieveData['jobIDs']);
		
		for($index = 0; $index < $nbOfReels; $index++) {
			$partRetrieveFileName = $tempDir . '/aletsch_part_' . uniqid();
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
			'pid'				=> getmypid(),
			'offline'			=> $this->offline,
			'totalBlocks'		=> $retrieveData['fileInfo']['blocksLen'],
			'thisFilePath'		=> $outPath,
			'step'				=> 'DONE',
			'descr1'			=> '',
			'descr2'			=> '',
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
