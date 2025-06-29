<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class UploadService
{
    protected SettingService $settingService;
    protected S3UploadService $s3UploadService;

    public function __construct(SettingService $settingService, S3UploadService $s3UploadService)
    {
        $this->settingService = $settingService;
        $this->s3UploadService = $s3UploadService;
    }
    /**
     * Store uploaded file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $fileName
     * @return array
     */
    public function storeFile($file, string $fileName)
    {
        try {
            if ($file->getSize() > 0) {
                // Initialize settings if needed
                $this->settingService->initializeDefaultSettings();

                // Get storage type from database
                $storageType = $this->settingService->getSetting('storage_type')->value ?? 'local';

                if ($storageType === 's3') {
                    //return $this->storeFileToS3($file, $fileName);
                    return [
                        'success' => false,
                        'message' => 'use_endpoint_upload_s3',
                        'data' => ['message' => 's3_upload_not_supported']
                    ];
                } else {
                    return $this->storeFileLocally($file, $fileName);
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'upload_failed',
                    'data' => ['message' => 'file_empty']
                ];
            }
        } catch (\Exception $ex) {
            return [
                'success' => false,
                'message' => 'upload_failed',
                'data' => $ex
            ];
        }
    }

    /**
     * Store file locally
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $fileName
     * @return array
     */
    private function storeFileLocally($file, string $fileName)
    {
        $storedFile = $file->storeAs('public/profiles', $fileName);
        $fileName = str_replace("public/profiles/", "", $storedFile);

        return [
            'success' => true,
            'message' => 'ok',
            'data' => [
                'path' => 'storage/profiles',
                'file_name' => $fileName,
                'file_key' => 'storage/profiles/' . $fileName,
                'storage_path' => 'storage/profiles/' . $fileName
            ]
        ];
    }

    /**
     * Configure S3 from database settings
     *
     * @return void
     */
    private function configureS3FromDatabase()
    {
        $s3UploadService = new S3UploadService($this->settingService);
        $s3Key = $this->settingService->getSetting('s3_key')->value ?? '';
        $s3Secret = $this->settingService->getSetting('s3_secret')->value ?? '';
        $s3Bucket = $this->settingService->getSetting('s3_bucket')->value ?? '';
        $s3Region = $s3UploadService->getS3RegionCode($this->settingService->getSetting('s3_region')->value ?? '');

        config(['filesystems.disks.s3.key' => $s3Key]);
        config(['filesystems.disks.s3.secret' => $s3Secret]);
        config(['filesystems.disks.s3.bucket' => $s3Bucket]);
        config(['filesystems.disks.s3.region' => $s3Region]);
        // config(['filesystems.disks.s3.url' => $s3Url]);
    }

    /**
     * Delete file from storage
     *
     * @param string $fileName
     * @return array
     */
    public function deleteFile(string $storage_path)
    {
        try {
            // Initialize settings if needed
            // $this->settingService->initializeDefaultSettings();

            // Get storage type from database
            $storageType = $this->settingService->getSetting('storage_type')->value ?? 'local';

            if ($storageType === 's3') {
                $this->configureS3FromDatabase();
                $s3Bucket = $this->settingService->getSetting('s3_bucket')->value ?? '';
                if (strpos($storage_path, $s3Bucket) === 0) {
                    $storage_path = substr($storage_path, strlen($s3Bucket) + 1);
                }
                Storage::disk('s3')->delete($storage_path);
            } else {
                $relativePath = ltrim(preg_replace('/^storage\//', '', $storage_path));
                Storage::disk('public')->delete($relativePath);
            }

            return [
                'success' => true,
                'message' => 'ok',
                'data' => []
            ];
        } catch (\Exception $ex) {
            return [
                'success' => false,
                'message' => 'Thất bại',
                'data' => $ex->getMessage()
            ];
        }
    }

    public function createDownloadUrl(string $storage_path, $checkFileExists)
    {
        try {
            // Initialize settings if needed
            // $this->settingService->initializeDefaultSettings();

            // Get storage type from database
            $storageType = $this->settingService->getSetting('storage_type')?->value ?? 'local';

            if ($storageType === 's3') {
                $s3Bucket = $this->settingService->getSetting('s3_bucket')->value ?? '';
                if (strpos($storage_path, $s3Bucket) === 0) {
                    $storage_path = substr($storage_path, strlen($s3Bucket) + 1);
                }
                $this->configureS3FromDatabase();
                if ($checkFileExists == true) {
                    if (!Storage::disk('s3')->exists($storage_path)) {
                        return [
                            'success' => false,
                            'message' => 'file_not_found',
                            'data' => null
                        ];
                    }
                }
                $result = $this->s3UploadService->generateDownloadPresignedUrl($storage_path);
            } else {
                $relativePath = ltrim(preg_replace('/^storage\//', '', $storage_path));
                if (!Storage::disk('public')->exists($relativePath)) {
                    return [
                        'success' => false,
                        'message' => 'file_not_found',
                        'data' => null
                    ];
                }
                $result = url($storage_path);
            }

            return [
                'success' => true,
                'message' => 'ok',
                'data' => [
                    'download_url' => $result,
                    'expires_in' => 50 * 60
                ]
            ];
        } catch (\Exception $ex) {
            return [
                'success' => false,
                'message' => 'error',
                'data' => $ex->getMessage()
            ];
        }
    }
}