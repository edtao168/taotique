<?php // app/Livewire/Settings/Users/UserManagement.php

namespace App\Livewire\Settings\Users;

use App\Models\Shop;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class UserManagement extends Component
{
    use Toast, WithPagination;
	
    public bool $userDrawer = false;
    public ?User $selectedUser = null;
    
    // 表單暫存變數
    public string $name = '';
    public string $email = '';
    public string $password = ''; // 新增密碼欄位
    public ?string $role = 'staff';
    public ?int $shop_id = null;
    public ?int $warehouse_id = null;
	public int $perPage = 10;

    public array $roleOptions = [
        ['id' => 'owner', 'name' => '店主 (Owner)'],
        ['id' => 'manager', 'name' => '經理 (Manager)'],
        ['id' => 'staff', 'name' => '店員 (Staff)'],
		['id' => 'guest', 'name' => '來賓 (Guest)'],
    ];

    public function render()
    {	
        // 進入頁面檢查：非 Owner 不能進來（或依據你的 ACL 定義）
        if (auth()->user()->role !== 'owner') {
            abort(403, '只有店主可以管理帳號');
        }
		
        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'name', 'label' => '使用者名稱'],
            ['key' => 'role', 'label' => '權限角色', 'class' => 'w-32'],
            ['key' => 'shop.name', 'label' => '所屬營業點'], // 顯示關聯店鋪
            ['key' => 'is_active', 'label' => '啟用', 'class' => 'w-20'],
        ];

        return view('livewire.settings.users.user-management', [
            'users' => User::with(['shop', 'warehouse'])->paginate(10),
            'headers' => $headers,
            'shops' => Shop::all(), // 供 Select 使用
            'warehouses' => Warehouse::all(), // 供 Select 使用
        ]);
    }
	
	// 當搜尋關鍵字改變時，重置每頁數量與分頁狀態
    public function updatedSearch()
    {
        $this->perPage = 10;
        $this->resetPage();
    }
	
	/**
     * 手機端專用：加載更多商品
     */
    public function loadMore()
    {
        $this->perPage += 10;
    }
	

    public function create()
    {
        $this->reset(['selectedUser', 'name', 'email', 'password', 'role', 'shop_id', 'warehouse_id']);
        $this->userDrawer = true;
    }

    public function edit(User $user)
    {
        $this->selectedUser = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role;
        $this->shop_id = $user->shop_id;
        $this->warehouse_id = $user->warehouse_id;
        $this->password = ''; // 編輯時密碼預設留空
        $this->userDrawer = true;
    }

    public function save()
    {
        // 再次確保操作者是 Owner
        if (auth()->user()->role !== 'owner') return;

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . ($this->selectedUser->id ?? 'NULL'),
            'role' => 'required',
            'shop_id' => 'nullable|exists:shops,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'password' => $this->selectedUser ? 'nullable|min:8' : 'required|min:8',
        ];

        $data = $this->validate($rules);

        // 處理密碼加密
        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        } else {
            unset($data['password']);
        }

        if ($this->selectedUser) {
            $this->selectedUser->update($data);
            $this->success("帳號資料已更新");
        } else {
            User::create($data);
            $this->success("新帳號建立成功");
        }

        $this->userDrawer = false;
    }

    public function toggleActive($userId)
    {
        if (auth()->user()->role !== 'owner') return;

        $user = User::findOrFail($userId);
        if ($user->id === auth()->id()) {
            $this->error("不能停用自己的帳號");
            return;
        }
        $user->is_active = !$user->is_active;
        $user->save();
        $this->info("帳號狀態已切換");
    }
}