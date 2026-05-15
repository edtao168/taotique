<?php

namespace App\Livewire\Settings\Partners;

use App\Models\Partner;
use App\Models\User;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithFileUploads;

    // 搜尋與狀態
    public $search = '';
    public bool $partnerModal = false;
    public bool $viewModal = false;

    // 表單欄位
    public ?Partner $editingPartner = null;
    public ?Partner $viewingPartner = null;
    public $name, $phone, $role, $user_id, $joined_at, $line_id, $wechat_id;
	public $photo; // 用於儲存上傳的暫存檔
    public $photo_path; // 用於顯示現有的照片路徑
    public bool $is_active = true;

    // 生命週期鉤子
    protected $listeners = ['refreshPartners' => '$refresh'];

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'name', 'label' => '夥伴姓名'],
            ['key' => 'user.email', 'label' => '登入帳號'],
            ['key' => 'contacts', 'label' => '通訊工具'],
            ['key' => 'role', 'label' => '職稱'],
            ['key' => 'status', 'label' => '狀態'], // 改用自定義狀態
            ['key' => 'actions', 'label' => '操作', 'sortable' => false],
        ];

        // 使用 Model 的 scope 查詢
        $partners = Partner::with('user')
            ->withTrashed()
			->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('created_at', 'desc')
            ->get();

        $users = User::all();

        return view('livewire.settings.partners.index', compact('partners', 'headers', 'users'));
    }

    // 檢視詳細資料
    public function view(Partner $partner)
    {
        $this->viewingPartner = $partner->load('user');
        $this->viewModal = true;
    }

    // 標記為離職（使用 Model 的方法）
    public function deactivate(Partner $partner)
    {
        try {
            $partner->deactivate();
            $this->success('夥伴已標記為離職');
            $this->dispatch('refreshPartners');
        } catch (\Exception $e) {
            $this->error('操作失敗：' . $e->getMessage());
        }
    }

    // 恢復夥伴（使用 Model 的方法）
    public function restore(Partner $partner)
    {
        try {
            $partner->activate();
            $this->success('夥伴已恢復');
            $this->dispatch('refreshPartners');
        } catch (\Exception $e) {
            $this->error('操作失敗：' . $e->getMessage());
        }
    }

    // 編輯功能
    public function edit(Partner $partner)
	{
		$this->editingPartner = $partner;
		$this->name = $partner->name;
		$this->phone = $partner->phone;
		$this->role = $partner->role;
		$this->user_id = $partner->user_id;
		$this->joined_at = $partner->joined_at ? $partner->joined_at->format('Y-m-d') : null;
		$this->is_active = $partner->is_active;
		
		// 這裡最重要：讀取資料庫存的照片路徑
		$this->photo_path = $partner->photo_path; 

		if (isset($partner->contacts)) {
			$this->line_id = $partner->contacts['line'] ?? null;
			$this->wechat_id = $partner->contacts['wechat'] ?? null;
		}

		$this->partnerModal = true;
	}

    // 儲存
    public function save()
	{
		$this->validate([
			'name' => 'required',
			'photo' => 'nullable|image|max:2048', // 驗證上傳的檔案
		]);

		// 1. 先準備基礎資料
		$data = [
			'name' => $this->name,
			'user_id' => $this->user_id,
			'phone' => $this->phone,
			'role' => $this->role,
			'joined_at' => $this->joined_at,
			'is_active' => $this->is_active,
			'contacts' => [
				'line' => $this->line_id,
				'wechat' => $this->wechat_id,
			],
		];

		// 2. 處理照片：必須把路徑存回 $data 陣列！
		if ($this->photo) {
			// 這行會將檔案存入 storage/app/public/partners 並返回路徑字串
			$data['photo_path'] = $this->photo->store('partners', 'public');
		}

		try {
			if ($this->editingPartner) {				
				$this->editingPartner->update($data);
				$this->success('更新完成');
			} else {
				Partner::create($data);
				$this->success('新增完成');
			}
			
			$this->cancel();
			$this->dispatch('refreshPartners');
		} catch (\Exception $e) {
			$this->error('儲存失敗：' . $e->getMessage());
		}
	}

    // 取消
    public function cancel()
    {
        $this->partnerModal = false;
        $this->viewModal = false;
        $this->resetForm();
        $this->editingPartner = null;
        $this->viewingPartner = null;
		$this->photo = null;
    }

    // 重置表單
    private function resetForm()
    {
        $this->reset(['name', 'user_id', 'line_id', 'wechat_id', 'phone', 'role', 'joined_at', 'is_active', 'photo', 'photo_path']);
    }
}