<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = ['key', 'value', 'group', 'type', 'description'];
	
	protected $casts = [
        'value' => 'json',
    ];

    /**
     * 通用取得設定值
     */
    public static function get(string $key, $default = null)
    {
        $cacheKey = "setting_{$key}";
        
        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = self::find($key);
            
            if (!$setting) {
                return $default;
            }
            
            // Laravel cast 已經自動還原型別（包含字串、陣列、數字、布林值）
            $value = $setting->value;
            $type = $setting->type ?? 'string';
            
            return match($type) {
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $value,
                'float'   => (float) $value,
                default   => $value, // json 陣列與 string 字串由 cast 處理即可
            };
        });
    }
    
    /**
     * 解碼儲存的值
     */
    public function getDecodedValue($default = null)
    {
        $rawValue = $this->attributes['value'] ?? null;
        
        if (is_null($rawValue)) {
            return $default;
        }
        
        // 解碼 JSON 字串
        $decoded = json_decode($rawValue, true);
        
        // 如果解碼失敗，返回原始值
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $rawValue;
        }
        
        $value = $decoded;
        $type = $this->type ?? 'string';
        
        // 根據類型轉換
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'json':
                return $value;
            case 'string':
            default:
                return (string) $value;
        }
    }
    
    /**
     * 更新設定值
     */
    public static function updateValue(string $key, $value)
    {
        return \DB::transaction(function () use ($key, $value) {
            $setting = self::where('key', $key)->lockForUpdate()->first();
            
            // 自動判斷 type
            $type = self::detectType($value);
            
            if ($setting) {
                $setting->value = $value;
                $setting->type = $type;
                $setting->save();
            } else {
                $setting = self::create([
                    'key' => $key,
                    'value' => $value,
                    'type' => $type,
                    'group' => 'core'
                ]);
            }
            
            // 清除快取
            Cache::forget("setting_{$key}");
            if ($setting->group) {
                Cache::forget("setting.{$setting->group}.{$key}");
            }
            
            return $setting;
        });
    }
    
    /**
     * 編碼為 JSON 字串
     */
    private static function encodeToJsonString($value): string
    {
        // 布林值轉為 JSON 布林
        if (is_bool($value)) {
            return json_encode($value);  // 輸出 "true" 或 "false" 字串
        }
        
        // 數字
        if (is_numeric($value)) {
            return json_encode($value);  // 輸出 "5" 或 "5.5"
        }
        
        // 字串
        if (is_string($value)) {
            return json_encode($value);  // 輸出 "PO-"
        }
        
        // 陣列
        if (is_array($value)) {
            return json_encode($value);
        }
        
        // NULL
        if (is_null($value)) {
            return json_encode(null);
        }
        
        return json_encode((string) $value);
    }
    
    /**
     * 自動偵測值的類型
     */
    private static function detectType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_array($value)) {
            return 'json';
        }
        return 'string';
    }
    
    /**
     * 取得布林值
     */
    public static function getBool(string $key, bool $default = false): bool
    {        
        return filter_var(self::get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * 檢查功能是否啟用
     */
    public static function isEnabled(string $key): bool
    {
        return self::getBool($key, false);
    }    
}