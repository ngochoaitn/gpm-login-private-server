<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\UploadService;
use App\Services\S3UploadService;
use App\Services\SettingService;
use App\Services\ProfileService;

class UploadController extends BaseController
{
    protected UploadService $uploadService;
    protected S3UploadService $s3UploadService;
    protected SettingService $settingService;
    protected ProfileService $profileService;

    public function __construct(UploadService $uploadService, S3UploadService $s3UploadService, SettingService $settingService, ProfileService $profileService)
    {
        $this->uploadService = $uploadService;
        $this->s3UploadService = $s3UploadService;
        $this->settingService = $settingService;
        $this->profileService = $profileService;
    }

    public function store(Request $request)
    {
        $fileName = $request->file_name ?? $request->query('file_name', null);
        if ($fileName == null) {
            return $this->getJsonResponse(false, 'Failed', ['message' => 'file_name_is_required']);
        }
        $content = $request->file('file') ?? $request->getContent();
        $result = $this->uploadService->storeFile($content, $fileName);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function delete(Request $request)
    {
        $result = $this->uploadService->deleteFile($request->storage_path);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    /**
     * Generate S3 presigned URL for file upload
     *
     * @param Request $request
     * @return string JSON response
     */
    public function createUploadUrl(Request $request)
    {
        // Get optional parameters from request
        $fileName = $request->get('file_name');
        // $profileId = $request->get('profile_id');
        // if ($profileId) $fileName = $profileId . '.zip';
        $expires = $request->get('expires', '+50 minutes'); // Default 10 minutes
        $mimeType = $request->get('mime_type', 'application/octet-stream');

        $storageType = $this->settingService->getSetting('storage_type')?->value ?? 'local';
        if ($storageType == 's3') {
            $result = $this->s3UploadService->generatePresignedUploadUrl($fileName, $expires, $mimeType);
        } else {
            // local upload
            $result = [
                'success' => true,
                'message' => 'Upload URL created successfully',
                'data' => [
                    'upload_url' => url('/api/file/local-upload?file_name=' . $fileName),
                    'method' => 'POST',
                    'mime_type' => $mimeType,
                    'storage_path' => 'storage/profiles/' . $fileName,
                ]
            ];
        }
        // Generate presigned URL

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function createDownloadUrl(Request $request)
    {
        $storagePath = $request->storage_path ?? $request->file_key;
        // $profile_id = $request->profile_id;
        // if ($profile_id) {
        //     $result = $this->profileService->getProfile($profile_id);
        //     if ($result['success']) {
        //         $profile = $result['data'];
        //         $storagePath = $profile->storage_path;
        //     } else {
        //         return $this->getJsonResponse(false, $result['message'], $result['data']);
        //     }
        // }
        $checkFileExists = filter_var($request->query('check_file_exists', false), FILTER_VALIDATE_BOOLEAN);
        $result = $this->uploadService->createDownloadUrl($storagePath, $checkFileExists);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function checkFileExists(Request $request)
    {
        $storagePath = $request->storage_path ?? $request->file_key;
        $result = $this->uploadService->checkFileExists($storagePath);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }
}
