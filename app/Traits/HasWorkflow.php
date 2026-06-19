<?php // app/Traits/HasWorkflow.php

namespace App\Traits;

use App\Enums\WorkflowStatus;

trait HasWorkflow
{
    /**
     * 取得狀態顯示名稱
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status?->label() ?? '-';
    }

    /**
     * 取得狀態顏色
     */
    public function getStatusColorAttribute(): string
    {
        return $this->status?->color() ?? 'badge-ghost';
    }

    /**
     * 判斷是否可編輯
     */
    public function isEditable(): bool
    {
        return $this->status?->isEditable() ?? false;
    }

    /**
     * 判斷是否可刪除
     */
    public function isDeletable(): bool
    {
        return $this->status?->isDeletable() ?? false;
    }

    /**
     * 判斷是否可審核通過
     */
    public function canApprove(): bool
    {
        return $this->status?->canApprove() ?? false;
    }

    /**
     * 判斷是否為最終狀態
     */
    public function isFinalized(): bool
    {
        return $this->status?->isFinalized() ?? false;
    }
	
	/**
     * 改變狀態
     */
    public function setStatus(string $newStatus, ?string $event = null): void
    {
        $oldStatus = $this->status;
        
        $this->status = $newStatus;
        $this->save();

        // 如果需要記錄歷史，可以在這裡加
        // 如果不需要，這行刪掉也沒關係
        Log::info('狀態已變更', [
            'model' => static::class,
            'id' => $this->id,
            'from' => $oldStatus,
            'to' => $newStatus,
            'event' => $event,
        ]);
    }
}