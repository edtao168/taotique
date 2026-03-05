<?php

namespace App\Models;

use App\Models\Shop;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
	
	public function partner()
	{
		return $this->hasOne(Partner::class);
	}
	
	// 對應 config/acl.php
    public function canViewCost(): bool
    {
        if (!$this->is_active) return false;

        $permissions = config("acl.roles.{$this->role}", []);
        return in_array('view_cost', $permissions);
    }

    // 厚 Model 擴充：獲取使用者當前所屬的「業務範圍」
    public function getWorkContext()
    {
        return [
            'shop' => $this->shop_id,
            'warehouse' => $this->warehouse_id
        ];
    }
	
	/**
     * 核心權限判斷
     */
    public function hasAbility(string $ability): bool
    {
        if (!$this->is_active) return false;

        $permissions = config("acl.roles.{$this->role}", []);
        return in_array($ability, $permissions);
    }

    /**
     * 封裝常用的敏感判斷：成本查看
     */
    public function canAccessFinancialData(): bool
    {
        return $this->hasAbility('view_cost');
    }
	
	/**
	 * 帳號所屬的營業點
	 */
	public function shop(): BelongsTo
	{
		return $this->belongsTo(Shop::class);
	}

	/**
	 * 帳號預設的出貨倉庫
	 */
	public function warehouse(): BelongsTo
	{
		return $this->belongsTo(Warehouse::class);
	}
}
