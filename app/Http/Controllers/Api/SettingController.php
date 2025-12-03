<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\Group;
use App\Models\User;

class SettingController extends BaseController
{
    public static $server_version = 12;
    /**
     * Get s3 setting
     *
     * @return string
     */
    public function getS3Setting(Request $request)
    {
        try {
            $apiKey = env('S3_KEY');
            $apiSecret = env('S3_PASSWORD');
            $apiBucket = env('S3_BUCKET');
            $apiRegion = env('S3_REGION');

            $reps = ['s3_api_key' => $apiKey, 's3_api_secret' => $apiSecret, 's3_api_bucket' => $apiBucket, 's3_api_region' => $apiRegion];
            return $this->getJsonResponse(true, 'OK', $reps);
        } catch (\Exception $ex) {
            return $this->getJsonResponse(false, 'Chưa cài đặt đủ thông tin S3 API', null);
        }
    }

    // Set or apply setting
    private function setSetting($key, $value){
        $setting = Setting::where('name', $key)->first();

        if ($setting == null){
            $setting = new Setting();
            $setting->name = $key;
            $setting->value = $value;
            $setting->save();
            return;
        }

        $setting->value = $value;
        $setting->save();
    }

    public function getStorageTypeSetting()
    {
        $setting = Setting::where('name', 'storage_type')->first();

        // Tạo setting nếu chưa có dựa trên thông tin trong file .env
        if ($setting == null) {
            $setting = new Setting();
            $setting->name = 'storage_type';

            $apiKey = env('S3_KEY');
            $apiSecret = env('S3_PASSWORD');
            $apiBucket = env('S3_BUCKET');
            $apiRegion = env('S3_REGION');

            if($apiKey != null && $apiSecret != null && $apiBucket != null && $apiRegion != null) {
                $setting->value = 's3';
            } else {
                $setting->value = 'hosting';
            }
            $setting->save();
        }

        return $this->getJsonResponse(true, 'OK', $setting->value);
    }

    // 23.7.2024 check version of private server
    public function getPrivateServerVersion()
    {
        $version = SettingController::$server_version;
        $response = [];
        // check trash group
        if($version >= 11) {
            $groupTrashId = 0;
            $groupTrashName = 'Trash auto create (update private server version 11)';

            if (!Group::where('id', $groupTrashId)->exists()) {
                try {
                    $userAdmin = User::where('role', 2)->first()->id;

                    $group = new Group();
                    $group->name = $groupTrashName;
                    $group->sort = 2147483647; // int max
                    $group->created_by = $userAdmin;
                    $group->save();

                } catch (\Exception $e){
                    $version -= 1;
                    $response['message'] = 'Can not create Trash group';
                }
            }

            $group = Group::where('name', $groupTrashName)->first();

            if ($group == null) {
                $version -= 1;
            }
            else if($group->id != $groupTrashId) {
                try {
                    $group->id = $groupTrashId;
                    $group->save();
                } catch (\Exception $e){
                    $response['message'] = 'Can not update id group Trash';
                    $version -= 1;
                }
            }
        }

        $response['version'] = $version;
        return $this->getJsonResponse(true, 'OK', $response);
    }

    // 24.9.2024
    public function getAllSetting(){
        $version = SettingController::$server_version;
        $response = [];
        // check trash group
        if($version >= 11) {
            $groupTrashId = 0;
            $groupTrashName = 'Trash auto create (update private server version 11)';

            if (!Group::where('id', $groupTrashId)->exists()) {
                try {
                    $userAdmin = User::where('role', 2)->first()->id;

                    $group = new Group();
                    $group->name = $groupTrashName;
                    $group->sort = 2147483647; // int max
                    $group->created_by = $userAdmin;
                    $group->save();

                } catch (\Exception $e){
                    $version -= 1;
                    $response['message'] = 'Can not create Trash group';
                }
            }

            $group = Group::where('name', $groupTrashName)->first();

            if ($group == null) {
                $version -= 1;
            }
            else if($group->id != $groupTrashId) {
                try {
                    $group->id = $groupTrashId;
                    $group->save();
                } catch (\Exception $e){
                    $response['message'] = 'Can not update id group Trash';
                    $version -= 1;
                }
            }
        }

        $storage_type = Setting::where('name', 'storage_type')->first();
        $cache_extension = Setting::where('name', 'cache_extension')->first();
        if ($storage_type == null) {
            $storage_type = new Setting();
            $storage_type->name = 'storage_type';

            $apiKey = env('S3_KEY');
            $apiSecret = env('S3_PASSWORD');
            $apiBucket = env('S3_BUCKET');
            $apiRegion = env('S3_REGION');

            if($apiKey != null && $apiSecret != null && $apiBucket != null && $apiRegion != null) {
                $storage_type->value = 's3';
            } else {
                $storage_type->value = 'hosting';
            }
            $storage_type->save();
        }
        $response['version'] = $version;
        $response['storage_type'] = $storage_type->value ?? 'hosting';
        $response['cache_extension'] = $cache_extension->value ?? 'off';


        return $this->getJsonResponse(true, 'OK', $response);
    }

}
