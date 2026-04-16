<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false; // 主鍵是字串，需關閉遞增
	public $timestamps = true;
    protected $fillable = ['key', 'value', 'group', 'description'];

    // Laravel 11/12 推薦的 Attribute Casting 語法
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * 取得設定值（自動處理陣列包裝和類型轉換）
     */
    public function getNormalizedValue($default = null)
    {
        // 直接訪問 attributes 陣列，避免觸發 accessor
        $value = $this->attributes['value'] ?? $this->value;
        
        // 如果已經是解碼後的陣列
        if (is_array($value)) {
            // 處理陣列包裝的情況：["SO-"] -> "SO-"
            if (count($value) === 1) {
                $firstValue = reset($value);
                return $this->normalizeSingleValue($firstValue, $default);
            }
            return $value;
        }
        
        // 如果是 JSON 字串，手動解碼
        if (is_string($value) && $this->isJson($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && count($decoded) === 1) {
                return $this->normalizeSingleValue(reset($decoded), $default);
            }
            return $decoded;
        }
        
        // 處理單一值
        return $this->normalizeSingleValue($value, $default);
    }
	
    /**
     * 檢查字串是否為有效的 JSON
     */
    private function isJson($string): bool
    {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * 標準化單一值（處理布林、數字、字串引號）
     */
    private function normalizeSingleValue($value, $default = null)
    {
        if (is_null($value)) {
            return $default;
        }
        
        // 布林值
        if ($value === true || $value === false) {
            return $value;
        }
        
        // 字串形式的布林值
        if ($value === 'true') return true;
        if ($value === 'false') return false;
        
        // 數字
        if (is_numeric($value)) {
            // 判斷是否為整數
            if (floor($value) == $value) {
                return (int) $value;
            }
            return (float) $value;
        }
        
        // 字串：去除可能的外層引號
        if (is_string($value)) {
            $value = $this->stripQuotes($value);
        }
        
        return $value;
    }
    
    /**
     * 去除字串外層的引號
     */
    private function stripQuotes($value)
    {
        if (!is_string($value)) {
            return $value;
        }
        
        // 去除外層雙引號
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return substr($value, 1, -1);
        }
        
        // 去除外層單引號
        if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            return substr($value, 1, -1);
        }
        
        return $value;
    }

    /**
     * 取得系統參數（帶快取保護）- 改良版
     */
    public static function getValue(string $group, string $key, $default = null) 
    {
        return Cache::remember("setting.{$group}.{$key}", 86400, function () use ($group, $key, $default) {
            $setting = self::where('group', $group)->where('key', $key)->first();
            if (!$setting) {
                return $default;
            }
            return $setting->getNormalizedValue($default);
        });
    }
    
    /**
     * 通用取得設定值（不限 group）
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
			$setting = self::find($key);
			if (!$setting) return $default;

			$val = $setting->value;

			// 如果 type 是 json，確保回傳的是 array 方便 bcmath 使用
			if ($setting->type === 'json') {
				return is_array($val) ? $val : json_decode($val, true);
			}

			return match ($setting->type) {
				'boolean' => filter_var($val, FILTER_VALIDATE_BOOLEAN),
				'number', 'float' => (string) $val, // 財務運算建議保持 string 給 bcmath
				default => $val,
			};
		});
    }
    
    /**
     * 批量取得設定值
     */
    public static function getMany(array $keys, $default = null)
    {
        $settings = self::whereIn('key', $keys)->get();
        $result = [];
        
        foreach ($keys as $key) {
            $setting = $settings->firstWhere('key', $key);
            $result[$key] = $setting ? $setting->getNormalizedValue($default) : $default;
        }
        
        return $result;
    }

    /**
     * 更新設定值（自動處理編碼）
     */
    public static function updateValue(string $key, $value)
	{
		return \DB::transaction(function () use ($key, $value) {
			// 使用 lockForUpdate 確保更新時的數據一致性
			$setting = self::where('key', $key)->lockForUpdate()->first();
			
			if ($setting) {
				$setting->value = $value; // 觸發 Mutator
				$setting->save();
			} else {
				$setting = self::create(['key' => $key, 'value' => $value]);
			}

			Cache::forget("sys_setting_{$key}");
			if ($setting->group) {
				Cache::forget("setting.{$setting->group}.{$key}");
			}

			return $setting;
		});
	}
    
    /**
     * 準備儲存的值（確保 JSON 編碼正確）
     */
    private static function normalizeForStorage($value)
    {
        // 如果是布林值，轉為字串形式（與 output.txt 一致）
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        // 如果是數字，轉為字串
        if (is_numeric($value)) {
            return (string) $value;
        }
        
        // 如果是字串且不是 JSON 格式，直接返回（Laravel JSON cast 會處理）
        if (is_string($value)) {
            // 檢查是否已經是 JSON
            json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // 不是 JSON，直接返回字串
                return $value;
            }
        }
        
        return $value;
    }
    
    /**
     * 檢查功能是否啟用
     */
    public static function isEnabled(string $key): bool
    {
        return (bool) self::get($key, false);
    }
    
    /**
     * Accessor: 取得標準化後的 value
     */
    public function getValueAttribute()
    {
        return $this->getNormalizedValue();
    }
	
	 /**
     * Mutator: 設定 value 時自動處理
     */
    public function setValueAttribute($value)
    {
        // 如果是布林值，轉為字串形式
        if (is_bool($value)) {
			$data = $value ? 'true' : 'false';
		} elseif (is_numeric($value)) {
			$data = (string) $value;
		} elseif (is_array($value)) {
			$data = $value;
		} else {
			$data = $value;
		}

		// 關鍵：將處理後的結果轉換為 JSON 字串存入		
		$this->attributes['value'] = json_encode($data);
    }
}