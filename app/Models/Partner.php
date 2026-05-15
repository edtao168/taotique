<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
		'photo_path',
        'phone',
        'role',
        'contacts',
        'joined_at',
        'is_active',
    ];

    /**
     * 自動將資料庫的 JSON 轉為 PHP 陣列，方便操作
     */
    protected $casts = [
        'contacts' => 'array',
        'joined_at' => 'date',
        'is_active' => 'boolean',
		'deleted_at' => 'datetime',
    ];

    /**
     * 關聯到登入帳號 (User)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
	
	// 業務邏輯方法
    public function deactivate()
    {
        return $this->update([
            'is_active' => false,
            'deleted_at' => now(),
        ]);
    }

    public function activate()
    {
        return $this->update([
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }

    // Scope 查詢
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->whereNull('deleted_at');
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false)
                    ->orWhereNotNull('deleted_at');
    }
	
	// 自訂存取器
    public function getStatusAttribute()
    {
        if ($this->trashed()) {
            return 'deleted';
        }
        
        return $this->is_active ? 'active' : 'inactive';
    }
}