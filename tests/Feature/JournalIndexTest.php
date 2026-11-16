<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\JournalStatus;
use App\Livewire\Reports\JournalIndex;
use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JournalIndexTest extends TestCase
{
    use RefreshDatabase;

    private Account $cashAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(\Database\Seeders\AccountSeeder::class);

        $this->cashAccount = Account::query()->where('code', '1101')->firstOrFail();
        $this->expenseAccount = Account::query()->where('code', '5101')->firstOrFail();
    }

    private function superadmin(): User
    {
        return User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    private function journal(string $number = 'JU-TEST-1', array $overrides = []): Journal
    {
        $journal = Journal::query()->create(array_merge([
            'user_id' => $this->superadmin()->id,
            'journal_number' => $number,
            'journal_date' => now()->toDateString(),
            'description' => 'Pembelian ATK',
            'reference_number' => 'REF-1',
            'status' => JournalStatus::Posted->value,
        ], $overrides));

        JournalDetail::query()->create([
            'journal_id' => $journal->id,
            'account_id' => $this->expenseAccount->id,
            'debit' => 50000,
            'credit' => 0,
        ]);

        JournalDetail::query()->create([
            'journal_id' => $journal->id,
            'account_id' => $this->cashAccount->id,
            'debit' => 0,
            'credit' => 50000,
        ]);

        return $journal;
    }

    public function test_page_lists_journals_for_authorized_user(): void
    {
        $this->journal();

        Livewire::actingAs($this->superadmin())
            ->test(JournalIndex::class)
            ->assertSee('JU-TEST-1')
            ->assertSee('Pembelian ATK')
            ->assertSee('Seimbang');
    }

    public function test_page_is_forbidden_without_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.journals'))
            ->assertForbidden();
    }

    public function test_toggle_expands_details(): void
    {
        $journal = $this->journal();

        Livewire::actingAs($this->superadmin())
            ->test(JournalIndex::class)
            ->assertSet('expandedId', null)
            ->call('toggle', $journal->id)
            ->assertSet('expandedId', $journal->id)
            ->assertSee('Beban Operasional')
            ->call('toggle', $journal->id)
            ->assertSet('expandedId', null);
    }

    public function test_search_filters_by_description(): void
    {
        $this->journal('JU-ATK-1');
        $this->journal('JU-LISTRIK-1', ['description' => 'Pembayaran listrik']);

        Livewire::actingAs($this->superadmin())
            ->test(JournalIndex::class)
            ->set('search', 'listrik')
            ->assertSee('JU-LISTRIK-1')
            ->assertDontSee('JU-ATK-1');
    }

    public function test_voided_journal_is_labelled(): void
    {
        $this->journal('JU-VOID-1', ['status' => JournalStatus::Voided->value]);

        Livewire::actingAs($this->superadmin())
            ->test(JournalIndex::class)
            ->assertSee('Void');
    }
}
