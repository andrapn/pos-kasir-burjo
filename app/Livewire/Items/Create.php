<?php

declare(strict_types=1);

namespace App\Livewire\Items;

use App\Enums\ItemStatus;
use App\Models\Item;
use Filament\Forms\Components\Repeater;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Create extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'status' => ItemStatus::ACTIVE->value,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Item Information')
                    ->description('Update the item details below.')
                    ->icon('heroicon-o-cube')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Item Name')
                                    ->placeholder('Enter item name')
                                    ->required()
                                    ->maxLength(255)
                                    ->autofocus()
                                    ->unique()
                                    ->columnSpan(1),

                                Select::make('category')
                                    ->label('Kategori')
                                    ->options([
                                        'Makanan' => 'Makanan',
                                        'Snack' => 'Snack',
                                        'Minuman' => 'Minuman',
                                    ])
                                    ->required()
                                    ->default('Makanan'),

                                Repeater::make('variants')
                                    ->relationship('variants')
                                    ->label('Varian Produk (Opsional)')
                                    ->schema([
                                        TextInput::make('group_name')
                                            ->label('Grup Varian (Cth: Rasa)')
                                            ->required(),
                                        TextInput::make('name')
                                            ->label('Pilihan (Cth: Semangka)')
                                            ->required(),
                                        TextInput::make('stock')
                                            ->label('Stok Tambahan')
                                            ->numeric()
                                            ->nullable()
                                            ->helperText('Kosongkan jika tidak perlu dilacak'),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull()
                                    ->addActionLabel('Tambah Varian'),

                                TextInput::make('price')
                                    ->label('Price')
                                    ->placeholder('0.00')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->columnSpan(1),
                                Select::make('status')
                                    ->label('Status')
                                    ->options(ItemStatus::class)
                                    ->required()
                                    ->native(false)
                                    ->columnSpan(1),
                            ])
                            ->model(Item::class)
                            ->statePath('data'),
                    ]),
            ]);
    }

    public function create(): void
    {
        $data = $this->form->getState()['data'];

        $record = Item::create($data);

        $this->form->model($record)->saveRelationships();

        Notification::make()
            ->title('Product created')
            ->body("The {$record->name} has been created successfully.")
            ->success()
            ->send();

        $this->redirectRoute('items.index');
    }

    public function render(): View
    {
        return view('livewire.items.create');
    }
}
