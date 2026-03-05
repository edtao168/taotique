<?php // app/Livewire/Sales/ShowSale.php

namespace App\Livewire\Sales;

use App\Models\Sale;
use Livewire\Component;

class ShowSale extends Component
{
    public Sale $sale;

    public function mount(Sale $sale)
    {
        // 預加載所有需要的關聯，避免 N+1 問題
        $this->sale = $sale->load(['customer', 'items.product', 'user']);
    }

    public function render()
    {
        return view('livewire.sales.show-sale');
    }
}