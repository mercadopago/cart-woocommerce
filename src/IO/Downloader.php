<?php

namespace MercadoPago\Woocommerce\IO;

use Exception;
use MercadoPago\Woocommerce\Entities\Files\Log as LogFile;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use ZipArchive;

if (!defined('ABSPATH')) {
    exit;
}

class Downloader
{
    private Logs $logs;

    public array $pluginLogs;

    private CurrentUser $currentUser;

    public function __construct(Logs $logs, CurrentUser $currentUser)
    {
        $this->currentUser  = $currentUser;
        $this->logs     = $logs;
        $this->pluginLogs = $this->getNameOfFileLogs();
    }


    /**
     * Get log files names order by date
     *
     * @return array of logs from plugin
     */
    private function getNameOfFileLogs(): array
    {
        $selectedLogFiles = array();
        try {
            $logDirectory = WP_CONTENT_DIR . '/uploads/wc-logs/';
            if (file_exists($logDirectory)) {
                $logFiles = scandir($logDirectory);

                foreach ($logFiles as $file) {
                    if ($file == '.' || $file == '..') {
                        continue;
                    }

                    if (strpos($file, 'mercadopago') !== false || strpos($file, 'MercadoPago') !== false || strpos($file, 'fatal-errors') !== false) {
                        preg_match('/^(.*?)-(\d{4}-\d{2}-\d{2})/', $file, $matches);
                        $logFile = new LogFile();
                        $logFile->fileFullName = $file;
                        $logFile->fileName = $matches[1];
                        $logFile->fileDate = $matches[2];
                        $selectedLogFiles[] = $logFile;
                    }
                }
            }

            usort($selectedLogFiles, function ($a, $b) {
                return strtotime($b->fileDate) - strtotime($a->fileDate);
            });
        } catch (Exception $e) {
            $this->logs->file->warning(
                "Mercado pago gave error to get log files names: {$e->getMessage()}",
                __CLASS__
            );
        }
        return $selectedLogFiles;
    }

    /**
     * Handles log downloads.
     *
     * @return void
     * @throws Exception
     */
    public function downloadLog(): void
    {
        if (isset($_GET['files'])) {
            $selectedFiles = Form::sanitizedGetData('files');
            $numFiles = count($selectedFiles);
            if ($numFiles === 1) {
                $this->singleFileDownload($selectedFiles);
            } elseif ($numFiles > 1) {
                $this->multipleFileDownload($selectedFiles);
            }
        }
    }

    /**
     * downloads a single file
     *
     * @param array $selectedFile
     *
     * @return void
     * @throws Exception
     */
    private function singleFileDownload(array $selectedFile): void
    {
        $filename = reset($selectedFile);

        if (!$this->validatesDownloadSecurity($filename)) {
            throw new Exception('attempt to download the file ' . $filename . 'on ' .  __METHOD__);
        }

        $file_path = WP_CONTENT_DIR . '/uploads/wc-logs/' . $filename;

        if (file_exists($file_path) && is_readable($file_path)) {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Type: application/octet-stream');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            throw new Exception('error to download log file ' . __METHOD__);
        }
    }

    /**
     * downloads multiple files
     *
     * @param array $selectedFiles
     *
     * @return void
     * @throws Exception
     */
    private function multipleFileDownload(array $selectedFiles): void
    {
        $zip = new ZipArchive();
        $temp_file = tempnam(sys_get_temp_dir(), 'logs_');

        if ($zip->open($temp_file, ZipArchive::CREATE) === true) {
            foreach ($selectedFiles as $filename) {
                if (!$this->validatesDownloadSecurity($filename)) {
                    continue;
                }

                $file_path = WP_CONTENT_DIR . '/uploads/wc-logs/' . $filename;

                if (file_exists($file_path) && is_readable($file_path)) {
                    $zip->addFile($file_path, $filename);
                }
            }
            $zip->close();

            header('Content-Disposition: attachment; filename="mercado-pago-logs.zip"');
            header('Content-Type: application/zip');
            header('Content-Length: ' . filesize($temp_file));
            readfile($temp_file);
            unlink($temp_file);
            exit;
        } else {
            throw new Exception('error to download log files ' . __METHOD__);
        }
    }

    /**
     * Validates a filename and user permissions to prevent path traversal attempts and ensure expected format.
     *
     * @param string $filename The filename to be validated
     *
     * @return bool True if the filename is valid, false otherwise
     */
    private function validatesDownloadSecurity(string $filename): bool
    {
        $this->currentUser->validateUserNeededPermissions();

        return $this->hasAllowedExtension($filename) &&
            $this->hasNoDisallowedCharacters($filename) &&
            $this->containsExpectedTerms($filename);
    }

    private function hasAllowedExtension(string $filename): bool
    {
        $allowed_pattern = '/\.log$/';
        return  (bool)preg_match($allowed_pattern, $filename);
    }

    private function hasNoDisallowedCharacters(string $filename): bool
    {
        $disallowed = array('..', '/', '\\', '.php', '.ini', '.exe', '.bat', '.sh', '.js', '.py', '.pl', '.sql', '.mdb', '.sqlite', '.zip', '.tar', '.gz', '.htaccess');
        return empty(array_intersect($disallowed, array($filename)));
    }

    private function containsExpectedTerms(string $filename): bool
    {
        $allowed_pattern = '/mercadopago|MercadoPago|fatal-errors/';
        return (bool)preg_match($allowed_pattern, $filename);
    }
}
