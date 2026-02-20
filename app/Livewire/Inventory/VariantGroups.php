<?php

namespace App\Livewire\Inventory;

use App\Models\VariantGroup;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

// Surat Izin WAJIB agar modal Form tidak error
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

// 👇 KITA HANYA IMPORT SATU ACTION MURNI INI, BUANG YANG LAIN 👇
use Filament\Tables\Actions\Action; 

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class VariantGroups extends Component implements HasForms, HasTable, HasActions
{
    use InteractsWithForms;
    use InteractsWithTable;
    use InteractsWithActions;

    public function table(Table $table): Table
    {
        return $table
            ->query(VariantGroup::query())
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
                    ->badge(),
            ])
            ->headerActions([
                // 👇 CREATE MANUAL - BUKAN PAKAI CREATEACTION 👇
                Action::make('create')
                    ->label('Tambah Master Varian')
                    ->icon('heroicon-o-plus')
                    ->form($this->getFormSchema())
                    ->action(function (array $data) {
                        $group = VariantGroup::create([
                            'name' => $data['name'],
                            'track_stock' => $data['track_stock'],
                        ]);
                        
                        if (!empty($data['options'])) {
                            $group->options()->createMany($data['options']);
                        }
                    }),
            ])
            ->actions([
                // 👇 EDIT MANUAL 👇
                Action::make('edit')
                    ->icon('heroicon-o-pencil-square')
                    ->form($this->getFormSchema())
                    ->fillForm(fn (VariantGroup $record): array => [
                        'name' => $record->name,
                        'track_stock' => $record->track_stock,
                        'options' => $record->options->toArray(),
                    ])
                    ->action(function (array $data, VariantGroup $record) {
                        $record->update([
                            'name' => $data['name'],
                            'track_stock' => $data['track_stock'],
                        ]);
                        
                        $record->options()->delete(); // Hapus opsi lama
                        if (!empty($data['options'])) {
                            $record->options()->createMany($data['options']); // Masukkan opsi baru
                        }
                    }),

                // 👇 DELETE MANUAL 👇
                Action::make('delete')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->action(fn (VariantGroup $record) => $record->delete()),
            ]);
    }

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Judul Varian')
                ->placeholder('Cth: Rasa Nutrisari, Level Pedas')
                ->required(),
            
            Toggle::make('track_stock')
                ->label('Aktifkan Manajemen Stok?')
                ->helperText('Nyalakan jika varian ini memotong stok. Matikan jika hanya pelengkap.')
                ->default(false),
            
            Repeater::make('options')
                // ->relationship() DIHAPUS karena kita save manual di atas
                ->label('Isi Pilihan Varian')
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Pilihan')
                        ->placeholder('Cth: Semangka, Sedang, Panas')
                        ->required(),
                ])
                ->columns(1)
                ->addActionLabel('Tambah Pilihan')
                ->required(),
        ];
    }

    public function render(): View
    {
        return view('livewire.inventory.variant-groups');
    }
}