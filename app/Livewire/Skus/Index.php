<?php

// App\Livewire\Skus\Index.php

public $category_id;
public $material_id;
public $serial_number; // 最後的流水號

// 自動組成完整編碼
public function updated($propertyName)
{
    if (in_array($propertyName, ['category_id', 'material_id', 'serial_number'])) {
        $cat = CategoryDefinition::find($this->category_id)?->code ?? '?';
        $mat = MaterialDefinition::find($this->material_id)?->code ?? '???';
        $this->code = $cat . $mat . $this->serial_number;
    }
}