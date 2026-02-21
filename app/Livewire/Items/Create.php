<?php

declare(strict_types=1);

namespace App\Livewire\Items;

use App\Enums\ItemStatus;
use App\Models\Item;
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

                                Select::make('variantGroups')
                                    ->relationship('variantGroups', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->label('Grup Varian Terkait')
                                    ->helperText('Pilih varian yang berlaku untuk item ini (Cth: Rasa, Level Pedas).')
                                    ->columnSpanFull(),

                                TextInput::make('price')
                                    ->label('Price')
                                    ->placeholder('0.00')
                                    ->required()
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->columnSpan(1),

                                Select::make('status')
                                    ->label('Status')
                                    ->options(ItemStatus::class)
                                    ->required()
                                    ->native(false)
                                    ->columnSpan(1),
                            ]),
                    ]),
            ])
            // DEKLARASI MODEL & STATEPATH BERADA DI LUAR COMPONENTS
            ->statePath('data')
            ->model(Item::class);
    }

    public function create(): void
    {
        $data = $this->form->getState();

        $record = Item::create($data);

        // Kunci utamanya ada di baris ini: Menyimpan relasi varian
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
