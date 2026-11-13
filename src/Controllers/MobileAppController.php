<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Storage\Storage;
use finfo;

class MobileAppController
{
    private string $apkDirectory;
    private string $apkPath;

    public function __construct()
    {
		$this->apkDirectory = dirname(__DIR__, 2)
			. DIRECTORY_SEPARATOR
			. 'Storage'
			. DIRECTORY_SEPARATOR
			. 'MobileApp';

        $this->apkPath = $this->apkDirectory . '/RestForgeMobile.apk';
    }
	
    public function upload()
    {
        if (!isset($_FILES['apk'])) {
			Response::error(
				'APK file is required.',
				400
			);
        }

        $file = $_FILES['apk'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
			Response::error(
				'APK upload failed.',
				400
			);
        }

        if (!is_uploaded_file($file['tmp_name'])) {
			Response::error(
				'Invalid uploaded file.',
				400
			);
        }

        $version = $_POST['MobileAppVersion'] ?? null;
        $build = $_POST['MobileAppBuild'] ?? null;

        if (!$version || !filter_var($build, FILTER_VALIDATE_INT)
            || (int)$build < 1) {
			Response::error(
				'MobileAppVersion and a positive MobileAppBuild are required.',
				400
			);
        }

        // Validate APK extension and MIME type.
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'apk') {
			Response::error(
				'Only APK files are accepted.',
				400
			);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!in_array($mime, [
            'application/vnd.android.package-archive',
            'application/octet-stream',
			'application/zip'
        ], true)) {
			Response::error(
				'Invalid APK file type.',
				400
			);
        }

        if (!is_dir($this->apkDirectory)) {
            if (!mkdir($this->apkDirectory, 0750, true)) {
				Response::error(
					'Could not create APK directory.',
					500
				);
            }
        }

        // Move to a temporary file first, then replace the previous APK.
        $temporaryPath = $this->apkPath . '.tmp';

        if (!move_uploaded_file($file['tmp_name'], $temporaryPath)) {
			Response::error(
				'Could not save APK.',
				500
			);
        }

        if (!rename($temporaryPath, $this->apkPath)) {
            @unlink($temporaryPath);
			Response::error(
				'Could not replace existing APK.',
				500
			);
        }

        $this->update(
            $version,
            (int)$build,
            filesize($this->apkPath)
        );
    }

    public function download()
    {
        if (!is_file($this->apkPath)) {
			Response::error(
				'No mobile app release is available.',
				404
			);
        }

        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="RestForgeMobile.apk"');
        header('Content-Length: ' . filesize($this->apkPath));
        header('Cache-Control: no-store');

        readfile($this->apkPath);
        exit;
    }

	private function update(string $mobileAppVersion, int $mobileAppBuild, int $apkFileSize): void
	{
		$records = Storage::list(
			'config');

		$updated = Storage::update(
			'config',
			$records[0]['id'],
			[
				'MobileAppVersion' => $mobileAppVersion,
				'MobileAppBuild' => $mobileAppBuild,
				'ApkFileSize' => $apkFileSize
			]
		);
		
		if (!$updated) {
			Response::error(
				'Config not updated',
				404
			);
		}
		
		Response::success([
            'message' => 'APK uploaded successfully.',
            'MobileAppVersion' => $mobileAppVersion,
            'MobileAppBuild' => $mobileAppBuild
        ], 204);
	}
}
