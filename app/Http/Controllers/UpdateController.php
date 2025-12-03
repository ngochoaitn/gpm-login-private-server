<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PclZip;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class UpdateController extends Controller
{
    /**
     * Download and update source code from a remote ZIP file.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateFromRemoteZip(Request $request)
    {
        // URL của file ZIP cần tải
        $zipUrl = 'https://github.com/ngochoaitn/gpm-login-private-server/releases/download/latest/latest-update.zip';

        // Tạo một tên file tạm thời để lưu file ZIP
        $zipFileName = 'update.zip';
        $zipFilePath = storage_path('app/' . $zipFileName);

        try {
            if (!$this->download_file_from_url($zipUrl, $zipFilePath))
                return redirect()->back()->with('msg', 'Cannot download ZIP file');

            $archive = new PclZip($zipFilePath);

            $destination = base_path();

            if ($archive->extract(PCLZIP_OPT_PATH, $destination) == 0) {
                return redirect()->back()->with('msg', 'Failed to extract the ZIP file');
            }

            Storage::delete($zipFileName);

            try {
                // Artisan::call('migrate');
                UpdateController::migrationDatabase();
            } catch (\Exception $e) {
                return redirect()->back()->with('msg', 'Migration failed: '.$e->getMessage());
            }
            return redirect()->back()->with('msg', 'Update completed successfully: version ' . \App\Http\Controllers\Api\SettingController::$server_version);

        } catch (\Exception $e) {
            return redirect()->back()->with('msg', 'error ' . $e->getMessage());
        }
    }

    function download_file_from_url($url, $file_name){
        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) coc_coc_browser/87.0.152 Chrome/81.0.4044.152 Safari/537.36\r\n" .
                    "Accept: */*\r\n"
                    ."Accept: */*\r\n"
                    ."Accept-Encoding: gzip, deflate, br\r\n"
            ],
            "https" => [
                "method" => "GET",
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) coc_coc_browser/87.0.152 Chrome/81.0.4044.152 Safari/537.36\r\n" .
                    "Accept: */*\r\n"
                    ."Accept: */*\r\n"
                    ."Accept-Encoding: gzip, deflate, br\r\n"
            ],
            'ssl' => [
                // set some SSL/TLS specific options
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
    
        $context = stream_context_create($opts);
    
        $content = @file_get_contents($url, false, $context);
        if($content != false) {
            file_put_contents($file_name, $content);
            return true;
        }else{
            return false;
        }
    }

    public static function migrationDatabase(){
        try {
            $sql = "
            CREATE TABLE IF NOT EXISTS group_roles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                group_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                role INT COMMENT '1 - read only, 2 - full control',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES `users`(id) ON DELETE CASCADE
            );";

            DB::statement($sql);
        } catch (\Exception $e) {
            return redirect()->back()->with('msg', 'Migration failed: '.$e->getMessage());
        }
    }
}
