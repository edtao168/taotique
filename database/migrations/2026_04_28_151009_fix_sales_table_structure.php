<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // 1. 拆除舊外鍵
            $table->dropForeign('sales_channel_foreign');

            // 2. 增加隔離欄位與更名
            $table->unsignedBigInteger('shop_id')->default(1)->after('id')->index();
            $table->renameColumn('channel', 'channel_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            // 3. 建立新外鍵
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->foreign('channel_id')
                  ->references('id')
                  ->on('channels')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropForeign(['channel_id']);
            $table->dropColumn('shop_id');
            $table->renameColumn('channel_id', 'channel');
            $table->foreign('channel')
                  ->references('id')
                  ->on('shops')
                  ->onDelete('restrict');
        });
    }
};