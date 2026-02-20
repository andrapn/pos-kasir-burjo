<div class="space-y-6">
    <flux:header>
        <flux:heading size="xl">Master Varian</flux:heading>
        <flux:subheading>Kelola jenis varian (seperti rasa, level pedas) yang dapat digunakan berulang kali pada berbagai item produk.</flux:subheading>
    </flux:header>

    <flux:main>
        {{ $this->table }}
        
        {{-- INI WAJIB ADA AGAR POP-UP FORM BISA MUNCUL --}}
        <x-filament-actions::modals />
    </flux:main>
</div>