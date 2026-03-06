<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use Livewire\Component;
use Mary\Traits\Toast; // 使用 Mary UI 的通知功能

class SystemSettings extends Component
{
    use Toast;

    // 使用 Form Object (Laravel 11+ 推薦) 來進一步瘦身 Component
    public array $payload = [];

    public function mount()
    {
        // 初始載入
        $this->payload = Setting::pluck('value', 'key')->toArray();
    }

    public function save()
    {
        foreach ($this->payload as $key => $value) {
            Setting::updateValue($key, $value);
        }
        $this->success('系統參數已儲存');
    }

    public function render()
    {
        return view('livewire.settings.system-settings');
    }
}