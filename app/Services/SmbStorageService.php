<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log; // Keep Log for errors/warnings

class SmbStorageService
{
    protected string $host;
    protected string $defaultShare; // Store the original share from env
    protected string $currentShare; // The share currently being used
    protected string $username;
    protected string $password;
    protected string $domain;

    public function __construct()
    {
        $this->host = env('NAS_HOST');
        $this->defaultShare = trim(env('NAS_SHARE'), "'\""); // Trim quotes from .env
        $this->currentShare = $this->defaultShare; // Initially use the default share
        $this->username = env('NAS_USERNAME');
        $this->password = env('NAS_PASSWORD');
        $this->domain = env('NAS_DOMAIN');
    }

    /**
     * Temporarily set the SMB share path for the next operation.
     *
     * @param string $sharePath The full share path (e.g., 'puims\ENIMS-MNT')
     * @return $this
     */
    public function setShare(string $sharePath): self
    {
        $this->currentShare = trim($sharePath, "'\"");
        // Log::debug("SmbStorageService: Temporarily setting share to '{$this->currentShare}'"); // REMOVED DEBUG LOG
        return $this; // Allow chaining
    }

    /**
     * Reset the share path back to the default one from the .env file.
     *
     * @return $this
     */
    public function resetShare(): self
    {
        if ($this->currentShare !== $this->defaultShare) {
            $this->currentShare = $this->defaultShare;
            // Log::debug("SmbStorageService: Resetting share back to default '{$this->defaultShare}'"); // REMOVED DEBUG LOG
        }
        return $this;
    }

    /**
     * Helper to parse the full share path into the actual share and the subdirectory path.
     * Uses the $currentShare property.
     */
    private function parseSharePath(): array
    {
        $normalizedPath = str_replace('\\', '/', $this->currentShare);
        $firstSlashPos = strpos($normalizedPath, '/');

        if ($firstSlashPos === false) {
            return [$normalizedPath, '']; // Share name only
        }

        $shareName = substr($normalizedPath, 0, $firstSlashPos);
        // Ensure pathPrefix does not end with a slash for consistency
        $pathPrefix = rtrim(substr($normalizedPath, $firstSlashPos + 1), '/');

        return [$shareName, $pathPrefix];
    }

    /**
     * Build the remote path including the prefix.
     */
    private function buildRemotePath(string $pathPrefix, string $filename): string
    {
        // Ensure filename doesn't start with a slash and prefix doesn't end with one
        $filename = ltrim($filename, '/');
        $pathPrefix = rtrim($pathPrefix, '/');

        if (empty($pathPrefix)) {
            return $filename;
        } else {
            // Use forward slashes for smbclient compatibility within the command
            return $pathPrefix . '/' . $filename;
        }
    }


    public function put(UploadedFile $file, string $remoteFilename): void
    {
        [$shareName, $pathPrefix] = $this->parseSharePath();
        $fullRemotePath = $this->buildRemotePath($pathPrefix, $remoteFilename);
        $localTempPath = $file->getRealPath();
        $fullShareUrl = "//{$this->host}/{$shareName}";
        $fullAuth = "{$this->domain}/{$this->username}%{$this->password}";

        // Use a single 'put' command with the full path
        $smbCommand = "put \"{$localTempPath}\" \"{$fullRemotePath}\"";
        $command = ['smbclient', $fullShareUrl, '-U', $fullAuth, '-c', $smbCommand];

        // Log::debug('SMB PUT Command: ' . implode(' ', $command)); // REMOVED DEBUG LOG

        $process = new Process($command);
        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            Log::error('SMB PUT Failed:', [ // Keep error log
                'command' => $command,
                'error' => $e->getProcess()->getErrorOutput(),
                'output' => $e->getProcess()->getOutput()
            ]);
            throw $e; // Re-throw the exception
        } finally {
            $this->resetShare(); // Ensure share is reset even on failure
        }
    }

    /**
     * Get file contents and return as a file response.
     *
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     * @throws ProcessFailedException
     */
    public function get(string $filename)
    {
        [$shareName, $pathPrefix] = $this->parseSharePath();
        $fullRemotePath = $this->buildRemotePath($pathPrefix, $filename);
        $localTempPath = tempnam(sys_get_temp_dir(), 'smb_'); // Create temp file path
        $fullShareUrl = "//{$this->host}/{$shareName}";
        $fullAuth = "{$this->domain}/{$this->username}%{$this->password}";

        // Use a single 'get' command
        $smbCommand = "get \"{$fullRemotePath}\" \"{$localTempPath}\"";
        $command = ['smbclient', $fullShareUrl, '-U', $fullAuth, '-c', $smbCommand];

        // Log::debug('SMB GET Command: ' . implode(' ', $command)); // REMOVED DEBUG LOG

        $process = new Process($command);
        try {
            $process->mustRun();
            // Important: Return the response before resetting the share
            return response()->file($localTempPath)->deleteFileAfterSend(true);
        } catch (ProcessFailedException $e) {
             Log::warning('SMB GET Failed:', [ // Keep warning log
                'command' => $command,
                'error' => $e->getProcess()->getErrorOutput(),
                'output' => $e->getProcess()->getOutput()
            ]);
            @unlink($localTempPath); // Clean up temp file on failure
            throw $e; // Re-throw the exception
        } finally {
             $this->resetShare(); // Ensure share is reset
        }
    }

    public function delete(string $filename): void
    {
        [$shareName, $pathPrefix] = $this->parseSharePath();
        $fullRemotePath = $this->buildRemotePath($pathPrefix, $filename);
        $fullShareUrl = "//{$this->host}/{$shareName}";
        $fullAuth = "{$this->domain}/{$this->username}%{$this->password}";

        // Use a single 'rm' command
        $smbCommand = "rm \"{$fullRemotePath}\"";
        $command = ['smbclient', $fullShareUrl, '-U', $fullAuth, '-c', $smbCommand];

        // Log::debug('SMB DELETE Command: ' . implode(' ', $command)); // REMOVED DEBUG LOG

        $process = new Process($command);
        try {
            $process->run(); // Use run() instead of mustRun() for delete

            if (!$process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                // Ignore "file not found" errors during delete
                if (strpos($errorOutput, 'NT_STATUS_NO_SUCH_FILE') === false &&
                    strpos($errorOutput, 'NT_STATUS_OBJECT_NAME_NOT_FOUND') === false) {
                     Log::warning('SMB DELETE Failed (Non "Not Found" Error):', [ // Keep warning log
                        'command' => $command,
                        'error' => $errorOutput,
                        'output' => $process->getOutput()
                    ]);
                    // Optionally re-throw if it's a critical error other than not found
                    // throw new ProcessFailedException($process);
                }
            }
        } catch (\Exception $e) {
             // Catch potential exceptions from Process execution itself
             Log::error('SMB DELETE Exception:', [ // Keep error log
                 'command' => $command,
                 'message' => $e->getMessage()
             ]);
             // Optionally re-throw
             // throw $e;
        } finally {
            $this->resetShare(); // Ensure share is reset
        }
    }
}

