<?php // app/Models/AccountingPeriod.php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriod extends Model
{
    use App\Models\Traits\TenantScoped;
	
	protected $fillable = [
        'period',
        'closed_at',
        'closed_by',
        'reopened_at',
        'reopened_by',
        'reopen_count',
        'note',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'reopen_count' => 'integer',
    ];

    // =========================================================================
    // SECTION: 關聯
    // =========================================================================

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reoper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    // =========================================================================
    // SECTION: 動態屬性（不需要儲存 start_date / end_date）
    // =========================================================================

    public function getStartDateAttribute(): string
    {
        return Carbon::createFromFormat('Y-m', $this->period)
            ->startOfMonth()
            ->toDateString();
    }

    public function getEndDateAttribute(): string
    {
        return Carbon::createFromFormat('Y-m', $this->period)
            ->endOfMonth()
            ->toDateString();
    }

    public function getYearAttribute(): string
    {
        return substr($this->period, 0, 4);
    }

    public function getMonthAttribute(): string
    {
        return substr($this->period, 5, 2);
    }

    public function getMonthNameAttribute(): string
    {
        return Carbon::createFromFormat('Y-m', $this->period)
            ->format('F');
    }

    public function getIsClosedAttribute(): bool
    {
        return !is_null($this->closed_at);
    }

    public function getIsReopenedAttribute(): bool
    {
        return !is_null($this->reopened_at);
    }

    // =========================================================================
    // SECTION: 靜態方法
    // =========================================================================

    public static function isClosed(string $yearMonth): bool
    {
        return self::where('period', $yearMonth)
            ->whereNotNull('closed_at')
            ->exists();
    }

    public static function close(string $yearMonth, ?string $note = null, ?int $userId = null): self
    {
        if (self::isClosed($yearMonth)) {
            throw new \Exception("{$yearMonth} 已經關帳了");
        }

        return self::create([
            'period' => $yearMonth,
            'closed_at' => now(),
            'closed_by' => $userId ?? auth()->id(),
            'note' => $note,
            'reopen_count' => 0,
        ]);
    }

    public static function reopen(string $yearMonth, ?int $userId = null): self
    {
        $period = self::where('period', $yearMonth)->firstOrFail();

        if (!$period->is_closed) {
            throw new \Exception("{$yearMonth} 尚未關帳，不需要重新開啟");
        }

        $period->update([
            'reopened_at' => now(),
            'reopened_by' => $userId ?? auth()->id(),
            'reopen_count' => $period->reopen_count + 1,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return $period;
    }

    public static function getClosedPeriods(): array
    {
        return self::whereNotNull('closed_at')
            ->orderBy('period', 'desc')
            ->pluck('period')
            ->toArray();
    }

    public static function getReopenedPeriods(): array
    {
        return self::whereNotNull('reopened_at')
            ->orderBy('period', 'desc')
            ->pluck('period')
            ->toArray();
    }
}