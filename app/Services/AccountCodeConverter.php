<?php // app/Services/AccountCodeConverter.php

namespace App\Services;

class AccountCodeConverter
{
    /**
     * 大陸《小企業會計準則》 → 台灣會計科目對照表
     */
    private const MAPPING = [
        // 資產類
        '1001' => '1111',    // 庫存現金 → 庫存現金
        '1002' => '1112',    // 銀行存款 → 銀行存款
        '1122' => '1121',    // 應收帳款 → 應收帳款
        '1405' => '1210',    // 庫存商品 → 商品存貨
        '140501' => '1211',  // 庫存商品-成品 → 製成品
        '140502' => '1212',  // 庫存商品-進口關稅 → 商品存貨-關稅
        '140503' => '1213',  // 庫存商品-運費 → 商品存貨-運費
        
        // 負債類
        '2202' => '2121',    // 應付帳款 → 應付帳款
        '2221' => '2171',    // 應交稅費 → 應付稅捐
        '222101' => '217101', // 應交增值稅(進項) → 進項稅額
        '222103' => '217103', // 應交增值稅(銷項) → 銷項稅額
        
        // 權益類
        '4001' => '3111',    // 實收資本 → 資本
        '4101' => '3121',    // 盈餘公積 → 法定盈餘公積
        
        // 損益類
        '5001' => '4111',    // 主營業務收入 → 銷貨收入
        '500101' => '411101', // 門市零售收入 → 銷貨收入-零售
        '500105' => '411102', // 買家運費收入 → 其他收入-運費
        '500110' => '4191',    // 銷售折扣與折讓 → 銷貨折讓
        '5401' => '5111',    // 主營業務成本 → 銷貨成本
        '5601' => '6111',    // 銷售費用 → 推銷費用
        '560105' => '611105', // 支付手續費 → 推銷費用-手續費
        '560106' => '611106', // 平台運費支出 → 推銷費用-運費
        '560108' => '611108', // 帳款調整 → 推銷費用-調整
        '560111' => '611111', // 佣金 → 推銷費用-佣金
        '5602' => '6211',    // 管理費用 → 管理費用
    ];
    
    /**
     * 將大陸科目代碼轉換為台灣科目代碼
     */
    public static function convert(string $chinaCode, ?int $shopId = null): string
    {
        // 直購電商可選擇保留大陸準則（跨境財報）
        $keepChinaStandard = $shopId && in_array($shopId, config('business.china_standard_shops', []));
        
        if ($keepChinaStandard) {
            return $chinaCode;
        }
        
        return self::MAPPING[$chinaCode] ?? $chinaCode;
    }
    
    /**
     * 批量轉換規則線的科目代碼
     */
    public static function convertRuleLines(array $lines, ?int $shopId = null): array
    {
        foreach ($lines as &$line) {
            if (isset($line['account_code']) && !str_starts_with($line['account_code'], 'DYNAMIC:')) {
                $line['account_code'] = self::convert($line['account_code'], $shopId);
            }
        }
        return $lines;
    }
}