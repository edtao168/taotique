<?php
// app/Enums/JournalStatus.php

namespace App\Enums;

enum JournalStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case CLOSED = 'closed';
    case REVERSED = 'reversed';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => '草稿',
            self::POSTED => '已過帳',
            self::CLOSED => '已結帳',
            self::REVERSED => '已沖銷',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'badge-ghost',
            self::POSTED => 'badge-success',
            self::CLOSED => 'badge-info',
            self::REVERSED => 'badge-warning',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    public function isDeletable(): bool
    {
        return $this === self::DRAFT;
    }

    public function isFinalized(): bool
    {
        return in_array($this, [self::CLOSED, self::REVERSED]);
    }

    public function canPost(): bool
    {
        return $this === self::DRAFT;
    }

    public function canClose(): bool
    {
        return $this === self::POSTED;
    }

    public function canReverse(): bool
    {
        return in_array($this, [self::POSTED, self::CLOSED]);
    }

    public function nextActions(): array
    {
        return match($this) {
            self::DRAFT => ['post' => '過帳'],
            self::POSTED => ['close' => '結帳', 'reverse' => '沖銷'],
            self::CLOSED => ['reverse' => '沖銷'],
            default => [],
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
            ->toArray();
    }
}