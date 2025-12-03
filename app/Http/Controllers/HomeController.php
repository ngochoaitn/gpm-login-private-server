<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Models\Group;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function index(){
        try {
            // Check database exists
            $query = "select * from users";
            $checkResult = DB::select($query);
            
            return view("ready");
        } catch (\Exception $ex) {
            return redirect('/setup'); // TODO: tạo view setup cho nhập các thông tin
        }
    }

    public function setup(){
        try {
            // Check database exists
            $query = "select * from users";
            $checkResult = DB::select($query);
            return redirect('/');
        } catch (\Exception $ex) {
            return view('setup');
        }
    }

    /*
    * Create database
    */
    public function createDb(Request $request){
        $host = $request->host;
        $port = $request->port;
        $username = $request->username;
        $password = $request->password;
        $dbname = $request->dbname;

        // Set config to cache
        config(['database.connections.mysql.host' => $host]);
        config(['database.connections.mysql.port' => $port]);
        config(['database.connections.mysql.username' => $username]);
        config(['database.connections.mysql.password' => $password]);
        config(['database.connections.mysql.database' => $dbname]);

        // Test connection
        $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME =  '$dbname'";

        try {
            $db = DB::select($query);
            Artisan::call('migrate');

            // Create first user
            $firstUser = new User();
            $firstUser->user_name = 'administrator';
            $firstUser->password = 'administrator';
            $firstUser->display_name = 'Administrator';
            $firstUser->role = 2;
            $firstUser->save();

            // Create first group
            $group = new Group();
            $group->name = 'All';
            $group->sort = 1;
            $group->created_by = $firstUser->id;
            $group->save();

            // If connection is ok, write to .env file
            $this->setEnvironmentValue('DB_HOST', $host);
            $this->setEnvironmentValue('DB_PORT', $port);
            $this->setEnvironmentValue('DB_DATABASE', $dbname);
            $this->setEnvironmentValue('DB_USERNAME', $username);
            $this->setEnvironmentValue('DB_PASSWORD', $password);

            return "<b>Tạo thành công</b>
                    <br><br>
                    Tài khoản admin mặc định: <b>$firstUser->user_name</b> / <b>$firstUser->password</b>
                    Bạn có thể đổi mật khẩu trên giao diện GPM-Login
                    <br><br>
                    <br><br><a href='/'>Về trang chủ</a>";
        } catch (Exception $ex) {
            $msg = $ex->getMessage();
            $error = "Không kết nối được đến database<br>$msg";
            return view('setup')->withErrors($error);
        }
    }

    /**
     * Get system time
     */
    public function getSystemTime(){
        $now = Carbon::now()->format('Y-m-d H:i:s');
        return ['time' => $now];
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

    public function test(){
        return 'ok';
    }
}
