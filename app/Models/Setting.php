<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false; // 主鍵是字串，需關閉遞增
    protected $fillable = ['key', 'value', 'group', 'description'];

    // Laravel 11/12 推薦的 Attribute Casting 語法
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    // 厚 Model 邏輯：封裝讀取與快取
    public static function getValue(string $key, $default = null)
    {
        return Cache::rememberForever("sys_setting_{$key}", function () use ($key, $default) {
            return self::where('key', $key)->value('value') ?? $default;
        });
    }

    // 厚 Model 邏輯：封裝更新與清除快取
    public static function updateValue(string $key, $value)
    {
        $setting = self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("sys_setting_{$key}");
        return $setting;
    }
	
	// 在 Setting Model 內
	public static function isEnabled(string $key): bool
	{
		return (bool) self::get($key, false);
	}

}