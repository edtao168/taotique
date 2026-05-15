<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Component;

class Show extends Component
{
    public Product $product;

    public function mount(Product $product)
    {
        // 預載入關聯：庫存、庫別、以及所屬店鋪
        $this->product = $product->load(['inventories.warehouse.shop', 'images']);
    }

    public function render()
    {
        return view('livewire.products.show');
    }
}