<?php

declare(strict_types=1);

namespace App\Livewire\Inventory;

use App\Models\VariantGroup;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table; // <- Ini kunci biar nggak dicoret lagi
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class VariantGroups extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

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
                    ->badge()
                    ->listLimit(3), // <- Ini yang benar untuk Filament v3
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Master Varian')
                    ->icon('heroicon-o-plus')
                    ->form($this->getFormSchema())
                    ->modalWidth(MaxWidth::Large), // <- Menggunakan Enum v3
            ])
            ->actions([
                EditAction::make()
                    ->form($this->getFormSchema())
                    ->modalWidth(MaxWidth::Large), // <- Menggunakan Enum v3
                DeleteAction::make(),
            ]);
    }

    public function render(): View
    {
        return view('livewire.inventory.variant-groups');
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
                ->relationship('options')
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
}
