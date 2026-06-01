<?php

namespace App\Livewire\Accountings;

use App\Models\Journal;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;

class JournalIndex extends Component
{
    use Toast;

    public string $search = '';
    public string $status = '';
    public $date_range = [];   
    public ?int $selectedJournalId = null;
    public ?Journal $selectedJournal = null;
    public bool $showDrawer = false;
    public bool $showRejectModal = false;
    public string $rejectReason = '';
    protected $paginationTheme = 'bootstrap';

    // 開啟 Drawer
    public function openDrawer(int $journalId): void
    {
        $this->selectedJournalId = $journalId;
        $this->selectedJournal = Journal::with(['items.account', 'shop'])
            ->findOrFail($journalId);
			
		$this->showDrawer = true;
    }

    // 關閉 Drawer
    public function closeDrawer(): void
    {
        $this->selectedJournalId = null;
        $this->selectedJournal = null;
		$this->showDrawer = false;
        $this->reset('showRejectModal', 'rejectReason');
    }

    // 送出審核
    public function submitForApproval(int $journalId): void
    {
        try {
            DB::transaction(function () use ($journalId) {
                $journal = Journal::lockForUpdate()->findOrFail($journalId);
                
                // 防呆：只有 draft 可送審
                if ($journal->status !== 'draft') {
                    throw new \RuntimeException('僅草稿可提交審核');
                }

                $journal->update([
                    'status' => 'posted'
                ]);
            }, 3);

            $this->success('✅ 已提交審核');
            $this->refreshSelected();
        } catch (\Throwable $e) {
            $this->error('提交失敗：' . $e->getMessage());
        }
    }

    // 審核通過
    public function approve(int $journalId): void
    {
        try {
            DB::transaction(function () use ($journalId) {
                $journal = Journal::lockForUpdate()->findOrFail($journalId);
                
                // 防呆：只有待審核可通過
                if (!in_array($journal->status, ['posted', 'draft'])) {
                    throw new \RuntimeException('僅待審核/草稿可執行審核');
                }

                $journal->update([
                    'status' => 'closed',
                    'approved_by' => auth()->user()?->name ?? 'Admin',
                    'approved_at' => now()
                ]);
            }, 3);

            $this->success('✅ 憑證審核通過 · 已過帳');
            $this->refreshSelected();
        } catch (\Throwable $e) {
            $this->error('審核失敗：' . $e->getMessage());
        }
    }

    // 駁回審核
    public function rejectApproval(int $journalId): void
    {
        $this->validate([
            'rejectReason' => 'required|max:200'
        ]);

        try {
            DB::transaction(function () use ($journalId) {
                $journal = Journal::lockForUpdate()->findOrFail($journalId);
                
                // 防呆：只有待審核可駁回
                if ($journal->status !== 'posted') {
                    throw new \RuntimeException('僅待審核狀態可駁回');
                }

                $journal->update([
                    'status' => 'draft',
                    'rejected_by' => auth()->user()?->name ?? 'Admin',
                    'rejected_at' => now(),
                    'reject_reason' => $this->rejectReason
                ]);
            }, 3);

            $this->info('❌ 已駁回為草稿');
            $this->showRejectModal = false;
            $this->reset('rejectReason');
            $this->refreshSelected();
        } catch (\Throwable $e) {
            $this->error('駁回失敗：' . $e->getMessage());
        }
    }

    // 刪除（僅草稿）
    public function delete(int $journalId): void
    {
        try {
            DB::transaction(function () use ($journalId) {
                $journal = Journal::lockForUpdate()->findOrFail($journalId);
                
                // 防呆：只有 draft 可刪
                if ($journal->status !== 'draft') {
                    throw new \RuntimeException('僅草稿可刪除');
                }

                $journal->items()->delete();
                $journal->delete();
            }, 3);

            $this->success('🗑️ 已刪除草稿');
            $this->closeDrawer();
        } catch (\Throwable $e) {
            $this->error('刪除失敗：' . $e->getMessage());
        }
    }

    /**
     * 🎯 刷新選中資料 (語法徹底校正嚴謹版)
     */
    protected function refreshSelected(): void
    {
        if ($this->selectedJournalId) {
            $this->selectedJournal = Journal::with(['items.account'])
                ->findOrFail($this->selectedJournalId);
        }
    }
	
	/**
     * 🎯 渲染日記帳主列表 (排除多型貪婪加載，保持最高效能)
     */
    public function render()
    {
        $query = Journal::query()
            ->with(['items.account', 'shop']) 
            ->when($this->search, fn($q) => $q->where('description', 'like', "%{$this->search}%"))
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc');

        return view('livewire.accountings.journal-index', [
            'journals' => $query->paginate(15),
            'statuses' => ['draft', 'posted', 'cancelled', 'closed']
        ]);
    }

}