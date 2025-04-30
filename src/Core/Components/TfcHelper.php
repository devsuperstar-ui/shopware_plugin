<?php

namespace TfcSwOzi\Core\Components;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TfcHelper
{
    private $pluginPath;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        // Set the plugin path dynamically using the container parameter
        $this->pluginPath = $parameterBag->get('kernel.project_dir') . '/custom/plugins/TfcSwOzi';
    }

    public function writeInfo(string $fileName, string $comment, bool $create = false, bool $doTs = false): void
    {
        if (empty($fileName)) {
            $fileName = 'debug';
        }

        $mode = $create ? 'w' : 'a';
        $timestamp = $doTs ? date('d.m.Y H:i:s') . "\n" : '';

        $logDirectory = $this->pluginPath . '/Logfiles';
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0777, true);
        }

        $filePath = $logDirectory . '/' . $fileName . '.txt';

        $handle = fopen($filePath, $mode);
        if ($handle) {
            fwrite($handle, $timestamp . $comment . "\n");
            fclose($handle);
        } else {
            throw new \RuntimeException("Unable to open log file at $filePath");
        }
    }
}
