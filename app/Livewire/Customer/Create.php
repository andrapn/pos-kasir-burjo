<?php

declare(strict_types=1);

namespace App\Livewire\Customer;

use App\Models\Customer;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
            'is_active' => true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pelanggan')
                    ->description('Masukkan nama untuk antrean. Nomor HP opsional.')
                    ->icon('heroicon-o-users')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Pelanggan')
                            ->required()
                            ->minLength(2)
                            ->maxLength(255)
                            ->placeholder('Cth: Mas Budi')
                            ->autofocus(),

                        TextInput::make('phone')
                            ->label('Nomor HP (Opsional)')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('Cth: 081234567890')
                            ->prefixIcon('heroicon-o-phone'),
                    ])
                    ->columns(1), // UI lebih clean dengan 1 kolom
            ])
            ->statePath('data')
            ->model(Customer::class);
    }

    public function create(): void
    {
        $data = $this->form->getState();

        $record = Customer::create($data);

        $this->form->model($record)->saveRelationships();

        Notification::make()
            ->title('Customer created')
            ->body("Customer {$record->name} has been created successfully.")
            ->success()
            ->send();

        $this->redirect(route('customers.index'), navigate: true);
    }

    public function cancel(): void
    {
        $this->redirect(route('customers.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.customer.create');
    }
}
