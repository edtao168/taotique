<?php
// database/migrations/2026_07_20_145410_add_tenant_support.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. tenants 表：如果不存在才建立
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->id();
                $table->string('name')->comment('公司/品牌名稱');
                $table->string('subdomain', 50)->nullable()->unique();
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });

            // 只有新建時才插入預設租戶
            DB::table('tenants')->insert([
                'name' => '我的公司',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. 取得租戶 ID（不管是新建還是已存在）
        $tenantId = DB::table('tenants')->value('id');

        // 3. 需要加 tenant_id 的表（如果還沒有該欄位才加）
        $tables = [
            'users', 'products', 'customers', 'suppliers',
            'accounts', 'accounting_rules', 'channels', 'shops',
            'material_definitions', 'accounting_periods'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->unsignedBigInteger('tenant_id')
                        ->after('id')
                        ->nullable()
                        ->comment('所屬租戶');
                    $blueprint->index('tenant_id');
                });

                DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);

                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->unsignedBigInteger('tenant_id')->nullable(false)->change();
                });
            }
        }

        // 4. category_definitions（沒有 id，特殊處理）
        if (Schema::hasTable('category_definitions') && !Schema::hasColumn('category_definitions', 'tenant_id')) {
            Schema::table('category_definitions', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->index('tenant_id');
            });
            DB::table('category_definitions')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            Schema::table('category_definitions', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        }

        // 5. partners：tenant_id + shop_id
        if (Schema::hasTable('partners')) {
            if (!Schema::hasColumn('partners', 'tenant_id')) {
                Schema::table('partners', function (Blueprint $table) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                    $table->index('tenant_id');
                });
                DB::table('partners')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
                Schema::table('partners', function (Blueprint $table) {
                    $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
                });
            }

            if (!Schema::hasColumn('partners', 'shop_id')) {
                Schema::table('partners', function (Blueprint $table) {
                    $table->unsignedBigInteger('shop_id')->nullable()->after('tenant_id');
                    $table->index('shop_id');
                });
            }
        }

        // 6. settings：多層級
        if (Schema::hasTable('settings')) {
            if (!Schema::hasColumn('settings', 'tenant_id')) {
                Schema::table('settings', function (Blueprint $table) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->after('key');
                });
            }
            if (!Schema::hasColumn('settings', 'shop_id')) {
                Schema::table('settings', function (Blueprint $table) {
                    $table->unsignedBigInteger('shop_id')->nullable()->after('tenant_id');
                });
            }
            if (!Schema::hasColumn('settings', 'user_id')) {
                Schema::table('settings', function (Blueprint $table) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('shop_id');
                });
            }
            Schema::table('settings', function (Blueprint $table) {
                $table->index(['tenant_id', 'shop_id', 'user_id']);
            });

            DB::table('settings')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        }

        // 7. users：加 current_shop_id
        if (Schema::hasColumn('users', 'tenant_id') && !Schema::hasColumn('users', 'current_shop_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('current_shop_id')->nullable()->after('tenant_id');
            });
        }
    }

    public function down(): void
    {
        // ... 保持不變
    }
};