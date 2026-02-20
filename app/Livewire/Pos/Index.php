<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\PaymentMethod;
use App\Models\Sale;
use Exception;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class Index extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public array $items = [];

    /** @var Collection<int, Customer> */
    public $customers;

    /** @var Collection<int, PaymentMethod> */
    public $paymentMethods;

    public ?string $search = null;

    public ?string $customerSearch = null;

    public array $cart = [];

    public ?array $data = [];

    public $customerId;

    public $paymentMethodId;

    public int $paidAmount = 0;

    public float $discountAmount = 0;

    public function mount(): void
    {
        $this->items = Item::with('inventory')
            ->withWhereHas(relation: 'inventory', callback: function ($query): void {
                $query->where('quantity', '>', 0);
            })
            ->active()
            ->latest()
            ->get()
            ->map(fn(Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'price' => $item->price,
                'stock' => $item->inventory->quantity,
            ])
            ->toArray();

        $this->customers = Customer::all();
        $this->paymentMethods = PaymentMethod::select(['id', 'name'])->get();
    }

    #[Computed]
    public function filteredItems(): array
    {
        if (in_array($this->search, [null, '', '0'], true)) {
            return $this->items;
        }

        $search = mb_strtolower($this->search);

        return array_filter(
            $this->items,
            fn(array $item): bool => str_contains(mb_strtolower((string) $item['name']), $search)
                || str_contains(mb_strtolower((string) $item['sku']), $search),
        );
    }

    #[Computed]
    public function subtotal(): float|int
    {
        return collect($this->cart)
            ->sum(fn($item): int|float => $item['price'] * $item['quantity']);
    }

    #[Computed]
    public function tax(): float
    {
        return 0; // Set menjadi 0 agar tidak ada nilai pajak
    }

    #[Computed]
    public function totalBeforeDiscount(): int|float
    {
        // Langsung return subtotal saja tanpa ditambah tax
        return $this->subtotal; 
    }

    #[Computed]
    public function total(): float
    {
        return $this->totalBeforeDiscount - $this->discountAmount;
    }

    #[Computed]
    public function change(): int|float
    {
        return max(0, $this->paidAmount - $this->total);
    }

    public function addToCart(int $itemId): void
    {
        $item = collect($this->items)->firstWhere('id', $itemId);

        if ( ! $item) {
            Notification::make()->title('Item not found!')->danger()->send();

            return;
        }

        $stock = $item['stock'];

        if ($stock <= 0) {
            Notification::make()->title('Out of stock!')->danger()->send();

            return;
        }

        $currentQty = $this->cart[$itemId]['quantity'] ?? 0;

        if ($currentQty >= $stock) {
            Notification::make()->title("Only {$stock} in stock")->warning()->send();

            return;
        }

        $this->cart[$itemId] = [
            'id' => $item['id'],
            'name' => $item['name'],
            'sku' => $item['sku'],
            'price' => $item['price'],
            'quantity' => $currentQty + 1,
        ];
    }

    public function removeCart(int $itemId): void
    {
        unset($this->cart[$itemId]);
    }

    public function updateQuantity($itemId, $quantity): void
    {
        $quantity = max(1, (int) $quantity);

        $inventory = Inventory::firstWhere('item_id', $itemId);

        if ($quantity > $inventory->quantity) {

            Notification::make()
                ->title("Cannot add more. Only {$inventory->quantity} in stock.")
                ->danger()
                ->send();
            $this->cart[$itemId]['quantity'] = $inventory->quantity;
        } else {
            $this->cart[$itemId]['quantity'] = $quantity;
        }
    }

    public function incrementQuantity(int $itemId): void
    {
        if (isset($this->cart[$itemId])) {
            $item = collect($this->items)->firstWhere('id', $itemId);
            if ($this->cart[$itemId]['quantity'] < $item['stock']) {
                $this->cart[$itemId]['quantity']++;
            }
        }
    }

    public function decrementQuantity(int $itemId): void
    {
        if (isset($this->cart[$itemId])) {
            if ($this->cart[$itemId]['quantity'] > 1) {
                $this->cart[$itemId]['quantity']--;
            } else {
                $this->removeCart($itemId);
            }
        }
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->discountAmount = 0;
        $this->paidAmount = 0;
    }

    public function holdOrder(): void
    {
        // 1. Cek apakah keranjang kosong
        if ($this->cart === []) {
            Notification::make()
                ->title('Gagal!')
                ->body('Tidak dapat menahan pesanan kosong.')
                ->warning()
                ->send();
            return;
        }

        // 2. Ambil data hold order yang sudah ada di session (jika ada)
        $heldOrders = session()->get('held_orders', []);

        // 3. Tambahkan order saat ini ke dalam array
        $heldOrders[] = [
            'time' => now()->format('H:i'),
            'customer_id' => $this->customerId,
            'cart' => $this->cart,
            'discount' => $this->discountAmount,
            'total' => $this->total,
        ];

        // 4. Simpan kembali ke session
        session()->put('held_orders', $heldOrders);

        // 5. Bersihkan layar kasir untuk pelanggan berikutnya
        $this->clearCart();
        $this->customerId = null;
        $this->paymentMethodId = null;

        Notification::make()
            ->title('Order Held!')
            ->body('Pesanan berhasil disimpan sementara.')
            ->success()
            ->send();
    }

    public function restoreOrder(int $index): void
    {
        $heldOrders = session()->get('held_orders', []);

        if (!isset($heldOrders[$index])) {
            return;
        }

        // Pastikan kasir mengosongkan layar dulu sebelum memanggil order yang ditahan
        if ($this->cart !== []) {
            Notification::make()
                ->title('Tidak bisa memuat pesanan!')
                ->body('Harap selesaikan atau kosongkan pesanan saat ini terlebih dahulu.')
                ->warning()
                ->send();
            return;
        }

        // 1. Panggil data dari session
        $order = $heldOrders[$index];
        
        // 2. Kembalikan data ke layar kasir
        $this->cart = $order['cart'];
        $this->customerId = $order['customer_id'];
        $this->discountAmount = $order['discount'];

        // 3. Hapus data order tersebut dari daftar hold
        unset($heldOrders[$index]);
        session()->put('held_orders', array_values($heldOrders)); // Reindex urutan array

        Notification::make()
            ->title('Pesanan Dimuat!')
            ->success()
            ->send();
    }
    
    public function submit(): void
    {
        $this->form->getState();

    }

    #[Computed]
    public function filteredCustomers()
    {
        if (in_array($this->customerSearch, [null, '', '0'], true)) {
            return $this->customers;
        }

        $search = mb_strtolower($this->customerSearch);

        return $this->customers->filter(
            fn($customer): bool => str_contains(mb_strtolower($customer->name), $search)
                || str_contains(mb_strtolower($customer->phone ?? ''), $search)
                || str_contains(mb_strtolower($customer->email ?? ''), $search),
        );
    }

    public function clearDiscount(): void
    {
        $this->discountAmount = 0;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

            ])
            ->statePath('data');
    }

    public function checkout(): void
    {
        if ($this->cart === []) {
            Notification::make()
                ->title('Failed Sale!')
                ->body('Please add items to cart before checkout.')
                ->danger()
                ->send();

            return;
        }

        if ( ! $this->paymentMethodId) {
            Notification::make()
                ->title('Failed Sale!')
                ->body('Please select a payment method.')
                ->danger()
                ->send();

            return;
        }

        if ($this->paidAmount < $this->total) {
            Notification::make()
                ->title('Failed Sale!')
                ->body('Insufficient payment amount.')
                ->danger()
                ->send();

            return;
        }

        try {
            DB::transaction(function (): void {
                $sale = Sale::create([
                    'customer_id' => $this->customerId,
                    'payment_method_id' => $this->paymentMethodId,
                    'total' => $this->total,
                    'paid_amount' => $this->paidAmount,
                    'discount' => $this->discountAmount,
                ]);

                foreach ($this->cart as $item) {
                    $sale->salesItems()->create([
                        'item_id' => $item['id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                    ]);

                    Inventory::where('item_id', $item['id'])
                        ->decrement('quantity', $item['quantity']);
                }
            });

            Notification::make()
                ->title('Sale Completed!')
                ->body('Change: $' . number_format($this->change, 2))
                ->success()
                ->send();


            $this->cart = [];
            $this->search = '';
            $this->customerSearch = '';
            $this->customerId = null;
            $this->paymentMethodId = null;
            $this->paidAmount = 0;
            $this->discountAmount = 0;

        } catch (Exception $e) {
            report($e);

            Notification::make()
                ->title('Sale Failed!')
                ->body('An error occurred. Please try again.')
                ->danger()
                ->send();
        }
    }

    public function render(): View
    {
        return view('livewire.pos.index');
    }
}
