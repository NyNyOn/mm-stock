<?php

namespace App\Http\Controllers;

use App\Services\SmbStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // Keep Log for errors/warnings
use Illuminate\Support\Facades\Config; // Needed to get department config

class ImageController extends Controller
{
    protected SmbStorageService $smbService;
    protected string $defaultDeptKey;
    protected array $departments;

    public function __construct(SmbStorageService $smbService)
    {
        $this->smbService = $smbService;
        $this->departments = Config::get('department_stocks.departments', []);
        $this->defaultDeptKey = Config::get('department_stocks.default_key', 'mm');
    }

    /**
     * Fetch the image file from the correct SMB share based on deptKey.
     * Includes fallback to the default department share.
     *
     * @param string $deptKey The department key (e.g., 'it', 'en')
     * @param string $filename The name of the image file
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\Response
     */
    public function show(string $deptKey, string $filename)
    {
        $targetShare = $this->departments[$deptKey]['nas_share'] ?? null;
        $defaultShare = $this->departments[$this->defaultDeptKey]['nas_share'] ?? null;

        if (!$targetShare) {
            Log::error("NAS Image: Invalid department key '{$deptKey}' provided.");
            abort(404, 'Invalid department specified.');
        }

        try {
            // Attempt to get from the specified department's share
            return $this->smbService->setShare($targetShare)->get($filename);

        } catch (\Symfony\Component\Process\Exception\ProcessFailedException $e) {
            $errorOutput = $e->getProcess()->getErrorOutput();
            $isNotFound = strpos($errorOutput, 'NT_STATUS_NO_SUCH_FILE') !== false ||
                          strpos($errorOutput, 'NT_STATUS_OBJECT_NAME_NOT_FOUND') !== false;

            // If the error IS "Not Found" AND we are not already checking the default share AND default share exists
            if ($isNotFound && $deptKey !== $this->defaultDeptKey && $defaultShare) {
                Log::warning("NAS Image: File '{$filename}' not found in '{$targetShare}'. Attempting default share."); // Keep warning
                try {
                    // Attempt to get from the default department's share
                    return $this->smbService->setShare($defaultShare)->get($filename);

                } catch (\Symfony\Component\Process\Exception\ProcessFailedException $e2) {
                    // Log the error from the second attempt (default share)
                     Log::error("NAS Image: File '{$filename}' also not found in default share.", ['error' => $e2->getMessage()]); // Keep error
                     abort(404, 'Image not found in specified or default location.');
                } catch (\Exception $e2) {
                    // Catch other potential exceptions during the second attempt
                    Log::error("NAS Image: Unexpected error fetching '{$filename}' from default share.", ['error' => $e2->getMessage()]); // Keep error
                    abort(500, 'Server error fetching image.');
                }
            } else {
                // If it's another error (not "Not Found"), or if it failed on the default share already, log and abort
                Log::error("NAS Image: Failed to get file '{$filename}' from '{$targetShare}'.", ['error' => $e->getMessage()]); // Keep error
                abort(404, 'Image not found or inaccessible.'); // Keep it 404 for security, even if it's potentially a 500
            }
        } catch (\Exception $e) {
            // Catch other potential exceptions (e.g., config error in SmbService)
            Log::error("NAS Image: Unexpected error fetching '{$filename}' from '{$targetShare}'.", ['error' => $e->getMessage()]); // Keep error
            abort(500, 'Server error fetching image.');
        } finally {
            // Ensure the SmbService share is reset regardless of success or failure
             // $this->smbService->resetShare(); // resetShare is now called within get/put/delete
        }
    }
}

