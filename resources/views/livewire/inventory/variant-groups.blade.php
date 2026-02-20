<div class="space-y-6">
    <flux:header>
        <flux:heading size="xl">Master Varian</flux:heading>
        <flux:subheading>Kelola jenis varian (seperti rasa, level pedas) yang dapat digunakan berulang kali pada berbagai item produk.</flux:subheading>
    </flux:header>

    <flux:main>
        {{-- Ini akan memanggil tabel Filament yang kita buat di Logic tadi --}}
        {{ $this->table }}
    </flux:main>
</div>