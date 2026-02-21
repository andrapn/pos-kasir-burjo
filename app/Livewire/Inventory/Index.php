<?php

declare(strict_types=1);

namespace App\Livewire\Inventory;
use App\Models\Item;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Enums\ItemStatus;
use App\Models\Inventory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Component;
use App\Models\VariantOption;
final class Index extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => Inventory::query()->with('item'))
            ->columns([
                TextColumn::make('item.name')
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-cube')
                    // 1. TAMPILKAN NAMA VARIAN JIKA ADA
                    ->formatStateUsing(function ($record) {
                        $name = ucfirst((string) $record->item->name);
                        if ($record->variant_option_id) {
                            $variant = \App\Models\VariantOption::find($record->variant_option_id);
                            if ($variant) {
                                $name .= ' (' . $variant->name . ')';
                            }
                        }
                        return $name;
                    })
                    // 2. GANTI SKU JADI CATEGORY BIAR GAK ERROR
                    ->description(fn($record): string => "Kategori: " . ($record->item->category ?? 'Umum'))
                    ->searchable(),

                TextColumn::make('item.price')
                    ->label('Unit Price')
                    ->money()
                    ->color('info')
                    ->sortable()
                    ->alignLeft(),

                TextColumn::make('quantity')
                    ->label('Stock')
                    ->sortable()
                    ->badge()
                    ->color(fn($state): string => match (true) {
                        $state <= 0 => 'danger',
                        $state <= 10 => 'warning',
                        default => 'success',
                    })
                    ->alignCenter(),

                TextColumn::make('stock_value')
                    ->label('Stock Value')
                    ->getStateUsing(fn($record): float => $record->quantity * $record->item->price)
                    ->money('IDR')
                    ->color('success')
                    ->weight('bold')
                    ->alignCenter(),

                TextColumn::make('item.status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn(ItemStatus $state): string => ucfirst($state->getLabel()))
                    ->color(fn(ItemStatus $state): string => match ($state) {
                        ItemStatus::ACTIVE => 'success',
                        ItemStatus::INACTIVE => 'danger',
                    })
                    ->icon(fn(ItemStatus $state): string => match ($state) {
                        ItemStatus::ACTIVE => 'heroicon-o-check-circle',
                        ItemStatus::INACTIVE => 'heroicon-o-x-circle',
                    }),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Item Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->query(
                        fn(Builder $query, array $data): Builder => $data['value']
                            ? $query->whereHas('item', fn($q) => $q->where('status', $data['value']))
                            : $query,
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Create Item')
                    ->model(Inventory::class)
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Add New Inventory')
                    ->modalDescription('Add stock for a product or its variants.')
                    ->modalIcon('heroicon-o-archive-box')
                    ->schema([
                        Select::make('item_id')
                            ->label('Product')
                            ->formatStateUsing(fn($state): string => Str::ucfirst($state))
                            ->preload()
                            ->relationship('item', 'name')
                            ->searchable()
                            ->required()
                            ->native(false)
                            ->prefixIcon('heroicon-o-cube')
                            ->placeholder('Select a product')
                            ->live() // BIKIN LIVE BIAR BISA TRIGGER VARIAN
                            ->afterStateUpdated(fn (Set $set) => $set('variant_option_id', null)),

                        // TAMBAHAN DROPDOWN VARIAN DINAMIS
                        Select::make('variant_option_id')
                            ->label('Pilih Varian (Wajib)')
                            ->prefixIcon('heroicon-o-tag')
                            ->options(function (Get $get) {
                                $itemId = $get('item_id');
                                if (!$itemId) return [];

                                $item = Item::with('variantGroups.options')->find($itemId);
                                if (!$item) return [];

                                $options = [];
                                foreach ($item->variantGroups as $group) {
                                    if ($group->track_stock) {
                                        foreach ($group->options as $opt) {
                                            $options[$opt->id] = $group->name . ' - ' . $opt->name;
                                        }
                                    }
                                }
                                return $options;
                            })
                            ->visible(function (Get $get) {
                                $itemId = $get('item_id');
                                if (!$itemId) return false;
                                $item = Item::with('variantGroups')->find($itemId);
                                return $item && $item->variantGroups->where('track_stock', true)->isNotEmpty();
                            })
                            ->required(function (Get $get) {
                                $itemId = $get('item_id');
                                if (!$itemId) return false;
                                $item = Item::with('variantGroups')->find($itemId);
                                return $item && $item->variantGroups->where('track_stock', true)->isNotEmpty();
                            })
                            ->searchable()
                            ->preload(),

                        TextInput::make('quantity')
                            ->label('Initial Quantity')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required()
                            ->prefixIcon('heroicon-o-hashtag')
                            ->hint('Enter the initial stock quantity'),
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([

                ]),
            ])
            ->emptyStateHeading('No inventory records')
            ->emptyStateDescription('Inventory will appear here when items are added.')
            ->emptyStateIcon('heroicon-o-archive-box')
            ->defaultSort('quantity', 'asc')
            ->striped();
    }

    public function render(): View
    {
        return view('livewire.items.list-inventories');
    }
}
