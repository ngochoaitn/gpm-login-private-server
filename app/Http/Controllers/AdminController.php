<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Profile;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use stdClass;

// Simple, so not use middleware
class AdminController extends Controller
{
    public function index(){
        $loginUser = Auth::user();
        if ($loginUser == null || $loginUser->role != 2)
            return redirect('/admin/auth');

        $users = User::where('id', '<>', $loginUser->id)->orderBy('role', 'desc')->get();

        $storageType = 's3';
        $setting = Setting::where('name', 'storage_type')->first();
        
        // Tạo setting nếu chưa có dựa trên thông tin trong file .env
        if($setting == null) {
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

        $storageType = $setting->value;

        $s3Config = new stdClass();
        $s3Config->S3_KEY = env('S3_KEY');
        $s3Config->S3_PASSWORD = env('S3_PASSWORD');
        $s3Config->S3_BUCKET = env('S3_BUCKET');
        $s3Config->S3_REGION = env('S3_REGION');

        $cache_extension_setting = Setting::where('name', 'cache_extension')->first()->value ?? "off";
        return view('index', compact('users', 'storageType', 's3Config', 'cache_extension_setting'));
    }

    public function toogleActiveUser($id) {
        $user = User::find($id);
        if ($user == null)
            return;

        if ($user->active == 0) $user->active = 1;
        else if ($user->active == 1) $user->active = 0;

        $user->save();
        return redirect()->back();
    }

    public function saveSetting(Request $request){
        $setting = Setting::where('name', 'storage_type')->first();
        if ($setting == null)
            $setting = new Setting();

        $setting->name = 'storage_type';
        $setting->value = $request->type;
        $setting->save();

        if ($setting->value == 'hosting'){
            Artisan::call('storage:link');
        } else if ($setting->value == 's3'){
            $this->setEnvironmentValue('S3_KEY', $request->S3_KEY);
            $this->setEnvironmentValue('S3_PASSWORD', $request->S3_PASSWORD);
            $this->setEnvironmentValue('S3_BUCKET', $request->S3_BUCKET);
            $this->setEnvironmentValue('S3_REGION', $request->S3_REGION);
        }

        $cache_extension_setting = Setting::where('name', 'cache_extension')->first();
        if ($cache_extension_setting == null)
            $cache_extension_setting = new Setting();
        $cache_extension_setting->name = 'cache_extension';
        $cache_extension_setting->value = $request->cache_extension ?? "off";
        $cache_extension_setting->save();

        return redirect()->back()->with('msg', 'Storge type is changed to: '.$setting->value);
    }

    public function resetProfileStatus(){
        Profile::query()->update(['status' => 1]);
        // Forearch exception: Allowed memory size of ... bytes exhausted (tried to allocate ... bytes) 
        // $profiles = Profile::get();
        // foreach ($profiles as $profile){
        //     $profile->status = 1;
        //     $profile->save();
        // }
        return redirect()->back()->with('msg', 'Reset profile status successfully');
    }

    public function runMigrations() {
        try {
            // Chạy lệnh migrate qua Artisan
            // Artisan::call('migrate');
            UpdateController::migrationDatabase();
            
            // Trả về thông báo thành công
            return redirect()->back()->with('msg', 'Migration successfully');
        } catch (\Exception $e) {
            // Trả về lỗi nếu quá trình migrate thất bại
            return redirect()->back()->with('msg', 'Migration failed: '.$e->getMessage());
        }
    }

    // Write .env
    private function setEnvironmentValue($envKey, $envValue) {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);

        $oldValue = env($envKey);
        $str = str_replace("{$envKey}={$oldValue}", "{$envKey}={$envValue}", $str);
        $fp = fopen($envFile, 'w');
        fwrite($fp, $str);
        fclose($fp);
    }
}
