<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proxies', function (Blueprint $table) {
            // Xóa cột status
            $table->dropColumn('status');

            // Thêm cột meta_data dạng JSON (nullable nếu cần)
            $table->longText('meta_data')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('proxies', function (Blueprint $table) {
            // Rollback: thêm lại cột status (giả sử ban đầu là string, bạn chỉnh đúng kiểu ban đầu)
            $table->string('status')->nullable();

            // Xóa cột meta_data
            $table->dropColumn('meta_data');
        });
    }
};
