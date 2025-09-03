<?php

namespace App\Services;

use Aws\S3\S3Client;
use Aws\S3\PostObjectV4;
use Exception;
use Illuminate\Support\Facades\Storage;
use Aws\Exception\AwsException;

class S3UploadService
{
    protected $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function getDORegion($s3Region) {
        $host = preg_replace('#^https?://#', '', $s3Region);
        $parts = explode('.', $host);
        if (is_array($parts) && count($parts) > 2) {
            return $parts[0];
        }
        return null;
    }

    public function getS3RegionCode($s3Region)
    {
        // DO
        $checkDO = $this->getDORegion($s3Region);
        if($checkDO != null) {
            return $checkDO;
        }

        // AWS
        if ($s3Region == 'AFSouth1')
            return 'af-south-1';
        if ($s3Region == 'APEast1')
            return 'ap-east-1';
        if ($s3Region == 'APNortheast1')
            return 'ap-northeast-1';
        if ($s3Region == 'APNortheast2')
            return 'ap-northeast-2';
        if ($s3Region == 'APNortheast3')
            return 'ap-northeast-3';
        if ($s3Region == 'APSouth1')
            return 'ap-south-1';
        if ($s3Region == 'APSoutheast1')
            return 'ap-southeast-1';
        if ($s3Region == 'APSoutheast2')
            return 'ap-southeast-2';
        if ($s3Region == 'CACentral1')
            return 'ca-central-1';
        if ($s3Region == 'CNNorth1')
            return 'cn-north-1';
        if ($s3Region == 'CNNorthWest1')
            return 'cn-northwest-1';
        if ($s3Region == 'EUCentral1')
            return 'eu-central-1';
        if ($s3Region == 'EUNorth1')
            return 'eu-north-1';
        if ($s3Region == 'EUSouth1')
            return 'eu-south-1';
        if ($s3Region == 'EUWest1')
            return 'eu-west-1';
        if ($s3Region == 'EUWest2')
            return 'eu-west-2';
        if ($s3Region == 'EUWest3')
            return 'eu-west-3';
        if ($s3Region == 'MESouth1')
            return 'me-south-1';
        if ($s3Region == 'SAEast1')
            return 'sa-east-1';
        if ($s3Region == 'USEast1')
            return 'us-east-1';
        if ($s3Region == 'USEast2')
            return 'us-east-2';
        if ($s3Region == 'USGovCloudEast1')
            return 'us-gov-east-1';
        if ($s3Region == 'USGovCloudWest1')
            return 'us-gov-west-1';
        if ($s3Region == 'USIsobEast1')
            return 'us-isob-east-1';
        if ($s3Region == 'USIsoEast1')
            return 'us-iso-east-1';
        if ($s3Region == 'USWest1')
            return 'us-west-1';
        if ($s3Region == 'USWest2')
            return 'us-west-2';
        return 'us-east-1';
    }

    public function generateUploadPresignedUrl($fileName, $expires = '+10 minutes', $mimeType = 'application/octet-stream')
    {
        // $mimeType = 'application/octet-stream';

        // Key S3
        $key = 'profiles/' . $fileName;

        // Initialize settings if needed
        $this->settingService->initializeDefaultSettings();

        // Get S3 settings from database
        $s3Settings = $this->settingService->getS3Settings();

        if (!$s3Settings['success']) {
            return [
                'success' => false,
                'message' => 'S3 configuration not found or incomplete',
                'data' => null
            ];
        }

        $s3Data = $s3Settings['data'];

        // Validate S3 configuration
        if (
            empty($s3Data['s3_api_key']) || empty($s3Data['s3_api_secret']) ||
            empty($s3Data['s3_api_bucket']) || empty($s3Data['s3_api_region'])
        ) {
            return [
                'success' => false,
                'message' => 'S3 configuration is incomplete. Please check your S3 settings.',
                'data' => null
            ];
        }

        // Create S3 client
        $regionCode = $this->getS3RegionCode($s3Data['s3_api_region']);
        $isDO = $this->getDORegion($s3Data['s3_api_region']) != null;
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => $regionCode,
            'credentials' => [
                'key' => $s3Data['s3_api_key'],
                'secret' => $s3Data['s3_api_secret'],
            ],
        ]);
        if($isDO) { 
            $s3 = new S3Client([
                'version' => 'latest',
                'region' => $regionCode,
                'endpoint' => $s3Data['s3_api_region'],
                'credentials' => [
                    'key' => $s3Data['s3_api_key'],
                    'secret' => $s3Data['s3_api_secret'],
                ],
            ]);
        }

        $bucket = $s3Data['s3_api_bucket'];

        $options = [
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $mimeType,
            // 'ACL' => 'public-read' // use presigned url to download file
        ];

        // presigned PUT
        $command = $s3->getCommand('PutObject', $options);

        // $expires = '+10 minutes';

        $request = $s3->createPresignedRequest($command, $expires);

        $presignedUrl = (string) $request->getUri();

        return [
            'success' => true,
            'message' => 'Presigned URL generated successfully',
            'data' => [
                'upload_url' => $presignedUrl,
                // 'public_url' => "https://{$bucket}.s3.amazonaws.com/{$key}",
                // 'key' => $key,
                'storage_path' => $s3Data['s3_api_bucket'] . '/' . $key,
                // 'expires_in' => 600,                         // 10 minutes
                'mime_type' => $mimeType,
                'method' => 'PUT'
             ]
        ];
    }

    /**
     * Generate S3 presigned URL for file upload
     *
     * @param string|null $fileName
     * @param int $maxFileSize Maximum file size in bytes (default: 10MB)
     * @param string $expires Expiration time (default: +10 minutes)
     * @return array
     */
    public function generatePresignedUploadUrl($fileName = null, $expires = '+50 minutes', $mimeType = 'application/octet-stream')
    {
        try {
            return $this->generateUploadPresignedUrl($fileName, $expires, $mimeType);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate presigned URL: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function generateDownloadPresignedUrl($filePath, $expires = '+50 minutes')
    {
        // Initialize settings if needed
        // $this->settingService->initializeDefaultSettings();

        // Get S3 settings
        $s3Settings = $this->settingService->getS3Settings();

        if (!$s3Settings['success']) {
            return [
                'success' => false,
                'message' => 'S3 configuration not found or incomplete',
                'data' => null
            ];
        }

        $s3Data = $s3Settings['data'];

        if (
            empty($s3Data['s3_api_key']) || empty($s3Data['s3_api_secret']) ||
            empty($s3Data['s3_api_bucket']) || empty($s3Data['s3_api_region'])
        ) {
            return [
                'success' => false,
                'message' => 'S3 configuration is incomplete. Please check your S3 settings.',
                'data' => null
            ];
        }

        try {
            $regionCode = $this->getS3RegionCode($s3Data['s3_api_region']);
            $isDO = $this->getDORegion($s3Data['s3_api_region']) != null;

            $s3 = new S3Client([
                'version' => 'latest',
                'region' => $regionCode,
                'credentials' => [
                    'key' => $s3Data['s3_api_key'],
                    'secret' => $s3Data['s3_api_secret'],
                ],
            ]);

            if($isDO) { 
                $s3 = new S3Client([
                    'version' => 'latest',
                    'region' => $regionCode,
                    'endpoint' => $s3Data['s3_api_region'],
                    'credentials' => [
                        'key' => $s3Data['s3_api_key'],
                        'secret' => $s3Data['s3_api_secret'],
                    ],
                ]);
            }
            // $bucket = $s3Data['s3_api_bucket'];
            $parts = explode('/', $filePath);
            $bucket = $parts[0];

            $filePathWithoutBucket = substr($filePath, strlen($bucket) + 1);

            $options = [
                'Bucket' => $bucket,
                'Key' => $filePathWithoutBucket
            ];

            // Tạo presigned GET request
            $command = $s3->getCommand('GetObject', $options);
            $request = $s3->createPresignedRequest($command, $expires);
            $presignedUrl = (string) $request->getUri();

            return $presignedUrl;
            // return [
            //     'success' => true,
            //     'message' => 'Presigned download URL generated successfully',
            //     'data' => [
            //         'download_url' => $presignedUrl,
            //         'key' => $fileKey,
            //         'method' => 'GET',
            //         'expires_in' => strtotime($expires) - time() // seconds left
            //     ]
            // ];
        } catch (AwsException $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate download URL: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
}