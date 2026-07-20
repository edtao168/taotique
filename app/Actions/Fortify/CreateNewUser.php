<?php

namespace App\Actions\Fortify;

use App\Models\Tenant;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'company_name' => ['nullable', 'string', 'max:255'],
        ])->validate();

        // 1. 建立租戶
        $tenant = Tenant::create([
            'name' => $input['company_name'] ?? $input['name'] . '的公司',
            'status' => 'active',
        ]);

        // 2. 建立預設店鋪
        $shop = Shop::create([
            'tenant_id' => $tenant->id,
            'name' => '總店',
            'is_active' => true,
        ]);

        // 3. 建立使用者（admin）
        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'tenant_id' => $tenant->id,
            'current_shop_id' => $shop->id,
            'role' => 'owner',  // ✅ 第一個使用者是管理員
            'is_active' => true,
        ]);
    }
}