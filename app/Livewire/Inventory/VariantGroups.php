<?php

namespace App\Livewire\Inventory;

use App\Models\VariantGroup;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class VariantGroups extends Component implements HasForms, HasTable, HasActions
{
    use InteractsWithForms;
    use InteractsWithTable;
    use InteractsWithActions;

    public ?array $data = [];
    public $editingId = null;

    public function mount()
    {
        $this->form->fill();
    }

    public function table(Table $table): Table
    {
        return $table
            // 👇 FIX LEMOT: Tambahkan with('options') agar database cuma ditarik 1 kali
            ->query(VariantGroup::query()->with('options'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Grup (Cth: Rasa)')
                    ->searchable(),
                IconColumn::make('track_stock')
                    ->label('Pakai Stok?')
                    ->boolean(),
                TextColumn::make('options_count')
                    ->label('Jumlah Pilihan')
                    ->counts('options'),
                TextColumn::make('options.name')
                    ->label('Daftar Opsi')
                    ->badge()
                    // 👇 FIX OFFSIDE: Lipat opsi yang kepanjangan
                    ->limitList(3) // Maksimal tampilkan 3 badge
                    ->expandableLimitedList() // Sisanya disembunyikan jadi tombol
                    ->wrap(), // Jaga-jaga paksa turun ke baris baru kalau layar sempit
                
                // Kolom aksi custom kamu
                TextColumn::make('id')
                    ->label('Aksi')
                    ->view('livewire.inventory.table-actions'),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([ // <-- Ubah dari ->schema(...) jadi ->components(...)
                TextInput::make('name')
                    ->label('Judul Varian')
                    ->placeholder('Cth: Rasa Nutrisari, Level Pedas')
                    ->required(),
                Toggle::make('track_stock')
                    ->label('Aktifkan Manajemen Stok?')
                    ->default(false),
                Repeater::make('options')
                    ->label('Isi Pilihan Varian')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Pilihan')
                            ->required(),
                    ])
                    ->columns(1)
                    ->addActionLabel('Tambah Pilihan'),
            ])
            ->statePath('data'); // Wajib ada untuk form custom
    }

    public function openCreate(): void
    {
        $this->form->fill();
        $this->editingId = null;
        
        // 👇 TAMBAHKAN BARIS INI
        $this->dispatch('open-variant-modal'); 
    }

    public function openEdit($id): void
    {
        $this->editingId = $id;
        $group = VariantGroup::with('options')->find($id);

        if ($group) {
            $this->form->fill([
                'name' => $group->name,
                'track_stock' => $group->track_stock,
                'options' => $group->options->toArray(),
            ]);
        }
        
        // 👇 TAMBAHKAN BARIS INI JUGA
        $this->dispatch('open-variant-modal'); 
    }

    public function delete($id)
    {
        VariantGroup::find($id)?->delete();
    }

    public function save()
    {
        $data = $this->form->getState();

        if ($this->editingId) {
            $group = VariantGroup::find($this->editingId);
            $group->update([
                'name' => $data['name'],
                'track_stock' => $data['track_stock'],
            ]);
            $group->options()->delete();
            if (!empty($data['options'])) {
                $group->options()->createMany($data['options']);
            }
        } else {
            $group = VariantGroup::create([
                'name' => $data['name'],
                'track_stock' => $data['track_stock'],
            ]);
            if (!empty($data['options'])) {
                $group->options()->createMany($data['options']);
            }
        }

        $this->form->fill();
        $this->editingId = null;
        $this->dispatch('close-variant-modal'); // Memicu penutupan modal Flux
    }

    public function render(): View
    {
        return view('livewire.inventory.variant-groups');
    }
}