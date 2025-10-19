<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Models\Group;
use Carbon\Carbon;

class SetupService
{
    /**
     * Check if database is set up
     *
     * @return bool
     */
    public function isDatabaseSetup()
    {
        try {
            $query = "select * from users";
            DB::select($query);
            return true;
        } catch (\Exception $ex) {
            return false;
        }
    }

    /**
     * Create database and initial setup
     *
     * @param string $host
     * @param string $port
     * @param string $username
     * @param string $password
     * @param string $dbname
     * @return array
     */
    public function createDatabase(string $host, string $port, string $username, string $password, string $dbname)
    {
        // Set config to cache
        config(['database.connections.mysql.host' => $host]);
        config(['database.connections.mysql.port' => $port]);
        config(['database.connections.mysql.username' => $username]);
        config(['database.connections.mysql.password' => $password]);
        config(['database.connections.mysql.database' => $dbname]);

        // Test connection
        $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'";

        try {
            DB::select($query);
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
            $group->name = 'Default group';
            $group->id = '0000000-0000-0000-0000-000000000000';
            $group->sort = 1;
            $group->created_by = $firstUser->id;
            $group->save();

            // Seed default settings
            Artisan::call('db:seed', ['--class' => 'SettingsSeeder']);

            // If connection is ok, write to .env file
            $this->setEnvironmentValue('DB_HOST', $host);
            $this->setEnvironmentValue('DB_PORT', $port);
            $this->setEnvironmentValue('DB_DATABASE', $dbname);
            $this->setEnvironmentValue('DB_USERNAME', $username);
            $this->setEnvironmentValue('DB_PASSWORD', $password);

            return [
                'success' => true,
                'message' => 'ok',
                'data' => [
                    'admin_username' => $firstUser->user_name,
                    'admin_password' => $firstUser->password
                ]
            ];
        } catch (\Exception $ex) {
            $msg = $ex->getMessage();
            return [
                'success' => false,
                'message' => 'database_connection_failed',
                'data' => ['details' => $msg]
            ];
        }
    }

    /**
     * Get current system time
     *
     * @return array
     */
    public function getSystemTime()
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s');
        return ['time' => $now];
    }

    /**
     * Write environment value to .env file
     *
     * @param string $envKey
     * @param string $envValue
     */
    private function setEnvironmentValue($envKey, $envValue)
    {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);

        $oldValue = env($envKey);
        $str = str_replace("{$envKey}={$oldValue}", "{$envKey}={$envValue}", $str);
        $fp = fopen($envFile, 'w');
        fwrite($fp, $str);
        fclose($fp);
    }
}
