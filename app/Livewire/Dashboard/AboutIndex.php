<?php

namespace App\Livewire\Dashboard;

use Illuminate\Support\Facades\File;
use Livewire\Component;

class AboutIndex extends Component
{
    public function getVersion(): string
    {
        $packageJson = base_path('package.json');
        
        if (File::exists($packageJson)) {
            $content = File::get($packageJson);
            $data = json_decode($content, true);
            return $data['version'] ?? '未設定版本';
        }
        
        return '無法讀取版本';
    }

    public function render()
    {
        return view('livewire.dashboard.about-index');
    }
}