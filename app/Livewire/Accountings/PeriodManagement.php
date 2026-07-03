<?php // app/Livewire/Accountings/PeriodManagement.php

namespace App\Livewire\Accountings;

use App\Models\AccountingPeriod;
use App\Models\Sale;
use App\Enums\WorkflowStatus;
use Carbon\Carbon;
use Livewire\Component;
use Mary\Traits\Toast;

class PeriodManagement extends Component
{
    use Toast;

    public $periods = [];
    public $yearMonth;
    public $note;

    public function mount()
    {
        $this->loadPeriods();
        $this->yearMonth = now()->subMonth()->format('Y-m');
    }

    public function loadPeriods()
    {
        $this->periods = collect(range(0, 11))->map(function($i) {
            $month = now()->subMonths($i)->format('Y-m');
            $record = AccountingPeriod::where('period', $month)->first();
            
            return [
                'period' => $month,
                'is_closed' => $record && !is_null($record->closed_at),
                'closed_at' => $record?->closed_at,
                'closed_by' => $record?->closer?->name,
                'reopened_at' => $record?->reopened_at,
                'reopened_by' => $record?->reoper?->name,
                'reopen_count' => $record?->reopen_count ?? 0,
                'note' => $record?->note,
            ];
        })->toArray();
    }

    public function close()
    {
        $this->validate([
            'yearMonth' => 'required|date_format:Y-m',
            'note' => 'nullable|string|max:255',
        ]);

        try {
            // 驗證是否為有效月份（不能關未來月份）
			$inputDate = Carbon::createFromFormat('Y-m', $this->yearMonth);
			if ($inputDate->isFuture()) {
				throw new \Exception('不能關閉未來的會計期間。');
			}

			// 可選：限制只能關閉上個月或更早
			$lastMonth = Carbon::now()->subMonth()->format('Y-m');
			if ($this->yearMonth > $lastMonth) {
				throw new \Exception('只能關閉上個月或更早的會計期間。');
			}
		
			$year = substr($this->yearMonth, 0, 4);
            $month = substr($this->yearMonth, 5, 2);

            // 檢查未完成的銷售單
            $pendingSales = Sale::whereMonth('sold_at', $month)
                ->whereYear('sold_at', $year)
                ->whereNotIn('status', [
                    WorkflowStatus::COMPLETED->value, 
                    WorkflowStatus::CANCELLED->value,
                    WorkflowStatus::SETTLED->value,
                ])
                ->count();

            if ($pendingSales > 0) {
                throw new \Exception("該月份尚有 {$pendingSales} 筆未完成的銷售單，請先處理。");
            }

            // 檢查未結算的非現金訂單
            $unsettledSales = Sale::whereMonth('sold_at', $month)
                ->whereYear('sold_at', $year)
                ->needsSettlement()
                ->count();

            if ($unsettledSales > 0) {
                throw new \Exception("該月份尚有 {$unsettledSales} 筆未結算的訂單，請先結算。");
            }

            AccountingPeriod::close($this->yearMonth, $this->note);
            $this->loadPeriods();
            $this->success("✅ {$this->yearMonth} 已關帳完成！");
            $this->reset(['note']);

        } catch (\Exception $e) {
            $this->error('關帳失敗：' . $e->getMessage());
        }
    }

    public function reopen($period)
    {
        try {
            if (!auth()->user()?->hasRole('admin')) {
                throw new \Exception('只有管理員可以執行反關帳。');
            }

            AccountingPeriod::reopen($period);
            $this->loadPeriods();
            $this->success("✅ {$period} 已重新開啟。");

        } catch (\Exception $e) {
            $this->error('反關帳失敗：' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.accountings.period-management');
    }
}