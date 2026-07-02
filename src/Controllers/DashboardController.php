<?php

declare(strict_types=1);

namespace App\Controllers;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;
use DirectoryIterator;
use App\Core\Response;
use App\Core\Config;
use App\Core\Version;

class DashboardController
{
    public function read(): void
    {
        $baseDir = dirname(__DIR__, 2);
		$src = $baseDir . '\src';
		$userStorage = $baseDir . '\storage\Users';
		$itemStorage = $baseDir . '\storage\Items';
		$logStorage = $baseDir . '\storage\Logs';

		$systemSize = $this->getDirectorySizeFlat($baseDir);
		$systemSize = $systemSize + $this->getDirectorySizeRecursively($src);

		$storageSize = $this->getDirectorySizeRecursively($userStorage);
		$storageSize = $storageSize + $this->getDirectorySizeRecursively($itemStorage);

		$logSize = $this->getDirectorySizeRecursively($logStorage);

		Response::success([
			"systemSizeBytes" => $systemSize,
			"storageSizeBytes" => $storageSize,
			"logSizeBytes" => $logSize,
			"totalQuotaBytes" => Config::get('storage_quota_bytes'),
			"version" => Version::VERSION
		]);
    }

    private function getDirectorySizeRecursively(string $directory): int
    {
        $size = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $size += $item->getSize();
            }
        }

        return $size;
    }

    private function getDirectorySizeFlat(string $directory): int
    {
        $size = 0;

        $iterator = new DirectoryIterator($directory);

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $size += $item->getSize();
            }
        }

        return $size;
    }
}
