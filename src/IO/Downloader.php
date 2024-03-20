<?php

namespace MercadoPago\Woocommerce\IO;

use MercadoPago\Woocommerce\IO\LogFile;
use MercadoPago\Woocommerce\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

class Downloader
{
    /**
     * @var Logs
     */
    private $logs;

    /**
     * @var array
     */

    public $pluginLogs;


    public function __construct(Logs $logs)
    {
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
        } catch (\Exception $e) {
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
     */
    public function downloadLog(): void
    {
        if (isset($_GET['files'])) {
            $selectedFiles = array_map('sanitize_text_field', (array)$_GET['files']);
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
     * @return void
     */
    private function singleFileDownload(array $selectedFile): void
    {
        $filename = reset($selectedFile);
        $file_path = WP_CONTENT_DIR . '/uploads/wc-logs/' . $filename;
        if (file_exists($file_path) && is_readable($file_path)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        }
    }

    /**
     * downloads multiple files
     *
     * @return void
     */
    private function multipleFileDownload(array $selectedFiles): void
    {
        $zip = new \ZipArchive();
        $temp_file = tempnam(sys_get_temp_dir(), 'logs_');
        if ($zip->open($temp_file, \ZipArchive::CREATE) === true) {
            foreach ($selectedFiles as $filename) {
                $file_path = WP_CONTENT_DIR . '/uploads/wc-logs/' . $filename;
                if (file_exists($file_path) && is_readable($file_path)) {
                    $zip->addFile($file_path, $filename);
                }
            }
            $zip->close();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="mercado-pago-logs.zip"');
            header('Content-Length: ' . filesize($temp_file));
            readfile($temp_file);
            unlink($temp_file);
            exit;
        }
    }
}
