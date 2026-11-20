@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Tabungan'], ['label' => 'Transaksi Baru']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Transaksi Tabungan</h1>
            <p class="text-[13px] text-[#64748b] mt-1">Catat setoran, penarikan, atau pembayaran tagihan langsung dari saldo tabungan siswa.</p>
        </div>
    </div>

    <div class="card px-4 py-3">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-ui.select
                    wire:model.live="student_id"
                    name="student_id"
                    label="Siswa"
                    placeholder="Pilih siswa..."
                    :options="$students"
                    required
                />

                <x-ui.select
                    wire:model.live="type"
                    name="type"
                    label="Jenis Transaksi"
                    :options="['deposit' => 'Setoran', 'withdrawal' => 'Penarikan', 'bill_payment' => 'Bayar Tagihan dari Saldo']"
                    required
                />

                @if($type !== 'bill_payment')
                    <x-ui.input
                        wire:model.live="amount"
                        name="amount"
                        label="Jumlah"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        required
                    />
                @endif

                <x-ui.textarea
                    wire:model.live="description"
                    name="description"
                    label="Keterangan"
                    placeholder="Opsional..."
                    rows="2"
                />
            </div>

            @if($type === 'bill_payment')
                <div class="rounded-[6px] border border-[#e5eaf0] bg-[#f8fafc] px-4 py-3 flex items-center justify-between">
                    <span class="text-[13px] font-medium text-[#334155]">Saldo tabungan tersedia</span>
                    <span class="tabular text-sm font-bold text-[#2872c8]">{{ \App\Support\Formatter::rupiah($availableBalance) }}</span>
                </div>

                @if($selectedStudent === null)
                    <p class="text-[13px] text-[#8994a3]">Pilih siswa terlebih dahulu untuk melihat tagihan yang belum lunas.</p>
                @elseif($unpaidBills->isEmpty())
                    <p class="text-[13px] text-[#8994a3]">Tidak ada tagihan tertunggak untuk siswa ini.</p>
                @else
                    @error('billAmounts')
                        <p class="form-error">{{ $message }}</p>
                    @enderror

                    <div class="card !p-0 overflow-hidden">
                        <x-ui.table :empty="false">
                            <x-slot:head>
                                <tr>
                                    <th>Tagihan</th>
                                    <th class="w-28">Jatuh Tempo</th>
                                    <th class="w-36 text-right">Sisa Tagihan</th>
                                    <th class="w-44 text-right">Nominal Bayar</th>
                                </tr>
                            </x-slot:head>

                            @foreach($unpaidBills as $bill)
                                <tr wire:key="unpaid-bill-{{ $bill->id }}">
                                    <td>
                                        <div class="font-medium text-[#111827]">{{ $bill->bill?->title ?? $bill->bill_number }}</div>
                                        <div class="text-[12px] text-[#8994a3]">
                                            {{ $bill->bill?->category?->name ?? 'Tagihan' }} · {{ $bill->bill_number }}
                                        </div>
                                    </td>
                                    <td class="text-xs text-[#475569]">{{ $bill->due_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="text-right tabular text-xs font-medium text-[#c0392b]">
                                        {{ \App\Support\Formatter::rupiah((float) $bill->remaining_amount) }}
                                    </td>
                                    <td class="text-right">
                                        <x-ui.input
                                            wire:model="billAmounts.{{ $bill->id }}"
                                            name="billAmounts.{{ $bill->id }}"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="{{ (float) $bill->remaining_amount }}"
                                            placeholder="0"
                                            class="text-right"
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    </div>
                @endif
            @endif

            <div class="flex items-center justify-end gap-2 pt-1">
                <a href="{{ route('savings.index') }}" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Transaksi</button>
            </div>
        </form>
    </div>
</div>
