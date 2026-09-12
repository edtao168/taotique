<?php
// app/Traits/HasProductFilter.php

namespace App\Traits;

trait HasProductFilter
{
    public string $search = '';
    public ?int $selectedShop = null;
    public ?int $selectedWarehouse = null;
    public bool $showLowStockOnly = false;

    /**
     * Livewire 會自動呼叫這些 updated{Property} 掛鉤
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedShop(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedWarehouse(): void
    {
        $this->resetPage();
    }

    public function updatedShowLowStockOnly(): void
    {
        $this->resetPage();
    }
}