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
use App\Controllers\UserController;
use App\Controllers\SessionController;
use App\Controllers\ItemController;
use App\Controllers\LogController;
use App\Controllers\ConfigController;
use App\Security\LoginThrottle;

class DashboardController
{
    public function read(): void
    {
        $baseDir = dirname(__DIR__, 2);
		$src = $baseDir . DIRECTORY_SEPARATOR . 'src';

		$storageDir = $baseDir . DIRECTORY_SEPARATOR . 'Storage';
		$userStorage = $storageDir . DIRECTORY_SEPARATOR . UserController::COLLECTION;
		$sessionStorage = $storageDir . DIRECTORY_SEPARATOR . SessionController::COLLECTION;
		$itemStorage = $storageDir . DIRECTORY_SEPARATOR . ItemController::COLLECTION;
		$logStorage = $storageDir . DIRECTORY_SEPARATOR . LogController::COLLECTION;
		$mobileAppStorage = $storageDir . DIRECTORY_SEPARATOR . 'MobileApp';
		$throttleStorage  = $storageDir . DIRECTORY_SEPARATOR . LoginThrottle::COLLECTION;
		$configStorage    = $storageDir . DIRECTORY_SEPARATOR . ConfigController::COLLECTION;

		$systemSize = $this->getDirectorySizeFlat($baseDir);
		$systemSize = $systemSize + $this->getDirectorySizeRecursively($src);
		$systemSize = $systemSize + $this->getDirectorySizeRecursively($mobileAppStorage);

		$storageSize = $this->getDirectorySizeRecursively($userStorage);
		$storageSize = $storageSize + $this->getDirectorySizeRecursively($sessionStorage);
		$storageSize = $storageSize + $this->getDirectorySizeRecursively($itemStorage);
		$storageSize = $storageSize + $this->getDirectorySizeRecursively($throttleStorage);
		$storageSize = $storageSize + $this->getDirectorySizeRecursively($configStorage);

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

		if (!is_dir($directory)) {
			return $size;
		}

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

		if (!is_dir($directory)) {
			return $size;
		}

        $iterator = new DirectoryIterator($directory);

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $size += $item->getSize();
            }
        }

        return $size;
    }
}
