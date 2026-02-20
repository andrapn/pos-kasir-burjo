<?php

declare(strict_types=1);

namespace App\Livewire\Inventory;

use App\Models\VariantGroup;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
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
            ]);
        // Semua Action & Modal Form AKU HAPUS TOTAL di sini untuk ngetes.
    }

    public function render(): View
    {
        return view('livewire.inventory.variant-groups');
    }
}
