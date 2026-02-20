<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:header>
            <flux:heading size="xl">Master Varian</flux:heading>
            <flux:subheading>Kelola jenis varian (seperti rasa, level pedas) yang dapat digunakan berulang kali.</flux:subheading>
        </flux:header>
        
        {{-- TOMBOL CREATE MURNI FLUX --}}
        <flux:button wire:click="openCreate" x-on:click="$flux.modal('variant-modal').show()" icon="plus" variant="primary">
            Tambah Master Varian
        </flux:button>
    </div>

    <flux:main>
        {{ $this->table }}
    </flux:main>

    {{-- MODAL CUSTOM MURNI FLUX --}}
    <flux:modal name="variant-modal" class="md:w-[600px]" x-on:close-variant-modal.window="$flux.modal('variant-modal').close()">
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