<?php

namespace App\Services;

use App\Models\User;
use App\Models\Profile;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use stdClass;

class AdminService
{
    protected $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }
    /**
     * Get admin dashboard data
     *
     * @param User $loginUser
     * @return array
     */
    public function getDashboardData(User $loginUser)
    {
        // Initialize default settings if they don't exist
        $this->settingService->initializeDefaultSettings();

        $users = User::where('id', '<>', $loginUser->id)->get();

        // Get storage type from database
        $storageType = $this->settingService->getSetting('storage_type')->value ?? 'local';

        // Get S3 config from database
        $s3Config = $this->settingService->getS3Config();

        $cache_extension_setting = $this->settingService->getSetting('cache_extension')->value ?? "off";

        return [
            'users' => $users,
            'storageType' => $storageType,
            's3Config' => $s3Config,
            'cache_extension_setting' => $cache_extension_setting
        ];
    }

    /**
     * Toggle user active status
     *
     * @param string $userId
     * @return bool
     */
    public function toggleUserActiveStatus(string $userId)
    {
        $user = User::find($userId);
        if ($user == null) {
            return false;
        }

        // Toggle the is_active status
        $user->is_active = !$user->is_active;
        $user->save();

        if(!$user->is_active) {
            $user->tokens()->delete();
        }

        return true;
    }

    /**
     * Reset user password
     *
     * @param string $userId
     * @return array
     */
    public function resetUserPassword(string $userId)
    {
        $user = User::find($userId);
        if ($user == null) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // Generate a random password (8 characters)
        $newPassword = $this->generateRandomPassword();

        // Update user password (Laravel automatically hashes it via User model mutator)
        $user['password'] = Hash::make($newPassword);
        $user->save();

        return [
            'success' => true,
            'message' => "Password reset successfully for user: {$user->email}",
            'newPassword' => $newPassword
        ];
    }

    /**
     * Generate a random password
     *
     * @param int $length
     * @return string
     */
    private function generateRandomPassword(int $length = 8)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $password;
    }

    /**
     * Save system settings
     *
     * @param string $type
     * @param string|null $s3Key
     * @param string|null $s3Password
     * @param string|null $s3Bucket
     * @param string|null $s3Region
     * @param string $cacheExtension
     * @return string
     */
    public function saveSettings(string $type, ?string $s3Key = null, ?string $s3Password = null, ?string $s3Bucket = null, ?string $s3Region = null, string $cacheExtension = 'off')
    {
        // Save storage type setting
        $this->settingService->setSetting('storage_type', $type);

        // If storage type is local, create storage link
        if ($type == 'local') {
            Artisan::call('storage:link');
        }

        // Save S3 settings to database
        if ($type == 's3') {
            $s3Data = [
                'S3_KEY' => $s3Key ?? '',
                'S3_PASSWORD' => $s3Password ?? '',
                'S3_BUCKET' => $s3Bucket ?? '',
                'S3_REGION' => $s3Region ?? ''
            ];
            $this->settingService->updateS3Settings($s3Data);
        }

        // Save cache extension setting
        $this->settingService->setSetting('cache_extension', $cacheExtension);

        return 'Storage type is changed to: ' . $type;
    }

    /**
     * Reset all profile statuses to ready (not in use)
     *
     * @return bool
     */
    public function resetProfileStatuses()
    {
        // Reset all profiles to ready status and clear usage
        Profile::query()->update([
            'status' => Profile::STATUS_READY,
            'using_by' => null
        ]);
        return true;
    }

    public function runMigrations()
    {
        try {
            \App\Http\Controllers\UpdateController::migrationDatabase();
            return ['success' => true, 'message' => 'ok'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Migration failed: ' . $e->getMessage()];
        }
    }
}
