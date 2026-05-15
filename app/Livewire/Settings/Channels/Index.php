<<<<<<< HEAD
<?php

namespace App\Livewire\Settings\Channels;

use App\Models\Channel;
use Livewire\Component;
use Mary\Traits\Toast;
use Illuminate\Validation\Rule;

class Index extends Component
{
    use Toast;

    public $search = '';
    public bool $channelModal = false;

    public ?Channel $editingChannel = null;
    
    // Form fields
    public $name = '';
    public $type = 'offline';
    public $platform_fee_rate = 0;
    public $is_active = true;

    protected function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('channels')->ignore($this->editingChannel->id ?? null)],
            'type' => 'required|string|max:255',
            'platform_fee_rate' => 'required|numeric|min:0|max:1',
            'is_active' => 'boolean',
        ];
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'name', 'label' => '通路名稱'],
            ['key' => 'type', 'label' => '類型'],
            ['key' => 'platform_fee_rate', 'label' => '平台手續費率'],
            ['key' => 'is_active', 'label' => '狀態'],
            ['key' => 'actions', 'label' => '', 'sortable' => false, 'class' => 'w-20'],
        ];

        $channels = Channel::query()
            ->when($this->search, function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('type', 'like', "%{$this->search}%");
            })
            ->get();

        return view('livewire.settings.channels.index', [
            'channels' => $channels,
            'headers' => $headers,
        ]);
    }

    public function create()
    {
        $this->reset(['editingChannel', 'name', 'type', 'platform_fee_rate', 'is_active']);
        $this->type = 'offline';
        $this->platform_fee_rate = 0;
        $this->is_active = true;
        $this->channelModal = true;
    }

    public function edit(Channel $channel)
    {
        $this->editingChannel = $channel;
        $this->name = $channel->name;
        $this->type = $channel->type;
        // 確保顯示為數字格式
        $this->platform_fee_rate = (float) $channel->platform_fee_rate;
        $this->is_active = (bool) $channel->is_active;
        $this->channelModal = true;
    }

    public function save()
    {
        $data = $this->validate();

        if ($this->editingChannel) {
            $this->editingChannel->update($data);
            $this->success('通路資訊已更新');
        } else {
            Channel::create($data);
            $this->success('新通路已建立');
        }

        $this->reset(['editingChannel', 'name', 'type', 'platform_fee_rate', 'is_active', 'channelModal']);
    }

    public function delete(Channel $channel)
    {
        try {
            $channel->delete();
            $this->success('通路已刪除');
        } catch (\Exception $e) {
            $this->error('刪除失敗，該通路可能已有相關交易紀錄。');
        }
    }
=======
<?php

namespace App\Livewire\Settings\Channels;

use App\Models\Channel;
use Livewire\Component;
use Mary\Traits\Toast;
use Illuminate\Validation\Rule;

class Index extends Component
{
    use Toast;

    public $search = '';
    public bool $channelModal = false;

    public ?Channel $editingChannel = null;
    
    // Form fields
    public $name = '';
    public $type = 'offline';
    public $platform_fee_rate = 0;
    public $is_active = true;

    protected function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('channels')->ignore($this->editingChannel->id ?? null)],
            'type' => 'required|string|max:255',
            'platform_fee_rate' => 'required|numeric|min:0|max:1',
            'is_active' => 'boolean',
        ];
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'name', 'label' => '通路名稱'],
            ['key' => 'type', 'label' => '類型'],
            ['key' => 'platform_fee_rate', 'label' => '平台手續費率'],
            ['key' => 'is_active', 'label' => '狀態'],
            ['key' => 'actions', 'label' => '', 'sortable' => false, 'class' => 'w-20'],
        ];

        $channels = Channel::query()
            ->when($this->search, function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('type', 'like', "%{$this->search}%");
            })
            ->get();

        return view('livewire.settings.channels.index', [
            'channels' => $channels,
            'headers' => $headers,
        ]);
    }

    public function create()
    {
        $this->reset(['editingChannel', 'name', 'type', 'platform_fee_rate', 'is_active']);
        $this->type = 'offline';
        $this->platform_fee_rate = 0;
        $this->is_active = true;
        $this->channelModal = true;
    }

    public function edit(Channel $channel)
    {
        $this->editingChannel = $channel;
        $this->name = $channel->name;
        $this->type = $channel->type;
        // 確保顯示為數字格式
        $this->platform_fee_rate = (float) $channel->platform_fee_rate;
        $this->is_active = (bool) $channel->is_active;
        $this->channelModal = true;
    }

    public function save()
    {
        $data = $this->validate();

        if ($this->editingChannel) {
            $this->editingChannel->update($data);
            $this->success('通路資訊已更新');
        } else {
            Channel::create($data);
            $this->success('新通路已建立');
        }

        $this->reset(['editingChannel', 'name', 'type', 'platform_fee_rate', 'is_active', 'channelModal']);
    }

    public function delete(Channel $channel)
    {
        try {
            $channel->delete();
            $this->success('通路已刪除');
        } catch (\Exception $e) {
            $this->error('刪除失敗，該通路可能已有相關交易紀錄。');
        }
    }
>>>>>>> b29039cfb5a4a2683aedba9883af961633089c73
}