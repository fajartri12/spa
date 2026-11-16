@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Tabungan'], ['label' => 'Transaksi Baru']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Transaksi Tabungan</h1>
            <p class="text-[13px] text-[#64748b] mt-1">Catat setoran atau penarikan tabungan siswa.</p>
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
                    :options="['deposit' => 'Setoran', 'withdrawal' => 'Penarikan']"
                    required
                />

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

                <x-ui.textarea
                    wire:model.live="description"
                    name="description"
                    label="Keterangan"
                    placeholder="Opsional..."
                    rows="2"
                />
            </div>

            <div class="flex items-center justify-end gap-2 pt-1">
                <a href="{{ route('savings.index') }}" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Transaksi</button>
            </div>
        </form>
    </div>
</div>
