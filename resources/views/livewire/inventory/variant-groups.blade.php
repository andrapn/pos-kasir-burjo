<div class="space-y-6"> {{-- Container utama biar ada jarak antar elemen --}}
    
    {{-- Header Section: Dibikin sejajar kiri-kanan --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                Master Varian
            </h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Kelola grup variasi (seperti rasa, level pedas) yang bisa dipasang ke banyak menu sekaligus.
            </p>
        </div>

        {{-- Button Tambah: Otomatis kedorong ke kanan di layar PC --}}
        <div class="shrink-0">
            {{-- Sesuaikan trigger modalnya dengan cara kamu buka modal --}}
            <flux:button wire:click="openCreate" variant="primary" icon="plus" class="bg-indigo-600 hover:bg-indigo-700">
                Tambah Varian
            </flux:button>
        </div>
    </div>

    {{-- Table Section --}}
    <div class="bg-white dark:bg-zinc-900 shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-800 rounded-xl overflow-hidden">
        {{ $this->table }}
    </div>

    {{-- MODAL CUSTOM MURNI FLUX --}}
    <flux:modal name="variant-modal" class="md:w-[600px]" 
        x-on:close-variant-modal.window="$flux.modal('variant-modal').close()"
        x-on:open-variant-modal.window="$flux.modal('variant-modal').show()"
    >
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? 'Edit Varian' : 'Tambah Varian' }}</flux:heading>
            
            {{-- Panggil form Filament di sini --}}
            {{ $this->form }}
            
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>