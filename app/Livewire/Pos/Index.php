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

    public bool $showVariantModal = false;

    public int $selectedItemIdForVariant = 0;

    public string $selectedItemNameForVariant = '';

    public array $itemVariants = [];

    public array $selectedOptions = [];

    public ?string $search = null;

    public ?string $customerSearch = null;

    public string $activeCategory = 'Menu Nasi';

    public array $cart = [];

    public ?array $data = [];

    public $customerId;

    public $paymentMethodId;

    public int $paidAmount = 0;

    public float $discountAmount = 0;

    public function mount(): void
    {
        $this->loadItems();
        $this->customers = Customer::all();
        $this->paymentMethods = PaymentMethod::select(['id', 'name'])->get();
    }

    public function loadItems(): void
    {
        $this->items = Item::with('inventories')
            ->active()
            ->latest()
            ->get()
            ->filter(fn(Item $item) => $item->inventories->sum('quantity') > 0)
            ->map(fn(Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category ?? 'Makanan',
                'price' => $item->price,
                'stock' => $item->inventories->sum('quantity'),
            ])
            ->values()
            ->toArray();
    }

    public function removeItem($index): void
    {
        if (isset($this->cart[$index])) {
            unset($this->cart[$index]);
        }
    }

    #[Computed]
    public function filteredItems(): array
    {
        $result = $this->items;
        if ($this->activeCategory !== 'All') {
            $result = array_filter(
                $result,
                fn(array $item): bool => ($item['category'] ?? '') === $this->activeCategory,
            );
        }
        if ( ! in_array($this->search, [null, '', '0'], true)) {
            $search = mb_strtolower($this->search);
            $result = array_filter(
                $result,
                fn(array $item): bool => str_contains(mb_strtolower((string) $item['name']), $search),
            );
        }

        return $result;
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
        return 0;
    }

    #[Computed]
    public function totalBeforeDiscount(): int|float
    {
        return $this->subtotal;
    }

    #[Computed]
    public function total(): float
    {
        $calculatedTotal = $this->subtotal - (float) $this->discountAmount;

        return $calculatedTotal > 0 ? $calculatedTotal : 0;
    }

    #[Computed]
    public function change(): int|float
    {
        return $this->paidAmount - $this->total;
    }

    public function addToCart(int $itemId): void
    {
        $item = Item::with(['variantGroups.options'])->find($itemId);

        if ($item && $item->variantGroups->count() > 0) {
            $this->selectedItemIdForVariant = $item->id;
            $this->selectedItemNameForVariant = $item->name;
            $this->selectedOptions = [];
            $this->itemVariants = $item->variantGroups->map(function ($group) use ($item) {
                return [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'track_stock' => $group->track_stock,
                    'options' => $group->options->map(function ($opt) use ($item, $group) {
                        $stock = null;
                        if ($group->track_stock) {
                            $inv = Inventory::where('item_id', $item->id)
                                ->where('variant_option_id', $opt->id)->first();
                            $stock = $inv ? $inv->quantity : 0;
                        }

                        return [
                            'id' => $opt->id,
                            'name' => $opt->name,
                            'stock' => $stock,
                        ];
                    })->toArray(),
                ];
            })->toArray();
            $this->showVariantModal = true;

            return;
        }
        $this->processAddToCart($item);
    }

    public function confirmVariantSelection(): void
    {
        if (count($this->selectedOptions) !== count($this->itemVariants)) {
            Notification::make()->title('Gagal!')->body('Harap pilih semua varian.')->warning()->send();

            return;
        }
        $item = Item::find($this->selectedItemIdForVariant);
        $selectedOptionNames = [];
        $variantIds = [];
        foreach ($this->itemVariants as $group) {
            $optId = $this->selectedOptions[$group['group_id']];
            $variantIds[] = $optId;
            $opt = collect($group['options'])->firstWhere('id', $optId);
            $selectedOptionNames[] = $opt['name'];
            if ($group['track_stock']) {
                $cartKey = $item->id . '-' . implode('-', $variantIds);
                $currentQty = $this->cart[$cartKey]['quantity'] ?? 0;
                if ($currentQty >= $opt['stock']) {
                    Notification::make()->title("Stok {$opt['name']} tidak cukup!")->danger()->send();

                    return;
                }
            }
        }
        $variantString = implode(', ', $selectedOptionNames);
        $cartKey = $item->id . '-' . implode('-', $variantIds);
        if (isset($this->cart[$cartKey])) {
            $this->cart[$cartKey]['quantity']++;
        } else {
            $this->cart[$cartKey] = [
                'id' => $item->id,
                'variant_ids' => $variantIds,
                'name' => $item->name . ' (' . $variantString . ')',
                'price' => $item->price,
                'quantity' => 1,
            ];
        }
        $this->showVariantModal = false;
        Notification::make()->title('Masuk Keranjang!')->success()->send();
    }

    public function addVariantToCart(int $variantId): void
    {
        $variant = \App\Models\ItemVariant::with('item')->find($variantId);
        if ($variant->stock !== null && $variant->stock <= 0) {
            Notification::make()
                ->title('Gagal!')
                ->body('Stok varian ini sudah habis.')
                ->warning()
                ->send();

            return;
        }
        $this->processAddToCart($variant->item, $variant);
        $this->showVariantModal = false;
    }

    public function removeCart($index): void
    {
        if (isset($this->cart[$index])) {
            unset($this->cart[$index]);
        }
    }

    public function updateQuantity(string $itemId, $quantity): void
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

    public function incrementQuantity($index): void
    {
        if (isset($this->cart[$index])) {
            $cartItem = $this->cart[$index];
            $itemId = $cartItem['id'];
            $currentQty = $cartItem['quantity'];

            $isStockSufficient = true;
            $maxStock = 0;
            $stokVarianBerkurang = false;
            if ( ! empty($cartItem['variant_ids']) && is_array($cartItem['variant_ids'])) {
                foreach ($cartItem['variant_ids'] as $vId) {
                    $inv = Inventory::where('item_id', $itemId)
                        ->where('variant_option_id', $vId)
                        ->first();
                    if ($inv) {
                        $stokVarianBerkurang = true;
                        if ($currentQty >= $inv->quantity) {
                            $isStockSufficient = false;
                            $maxStock = $inv->quantity;

                            break;
                        }
                    }
                }
            } elseif ( ! empty($cartItem['variant_id'])) {
                $inv = Inventory::where('item_id', $itemId)
                    ->where('variant_option_id', $cartItem['variant_id'])
                    ->first();
                if ($inv) {
                    $stokVarianBerkurang = true;
                    if ($currentQty >= $inv->quantity) {
                        $isStockSufficient = false;
                        $maxStock = $inv->quantity;
                    }
                }
            }

            // 3. Jika varian tidak punya stok sendiri (misal: Level Pedas) ATAU ini menu reguler,
            // maka cek sisa stok menu induknya
            if ( ! $stokVarianBerkurang) {
                $invParent = Inventory::where('item_id', $itemId)
                    ->whereNull('variant_option_id')
                    ->first();
                if ( ! $invParent) {
                    $invParent = Inventory::where('item_id', $itemId)->first();
                }
                if ($invParent && $currentQty >= $invParent->quantity) {
                    $isStockSufficient = false;
                    $maxStock = $invParent->quantity;
                }
            }

            // Tampilkan Alert & Tolak Penambahan jika stok nggak cukup
            if ( ! $isStockSufficient) {
                Notification::make()
                    ->title('Stok Habis!')
                    ->body("Sisa maksimal yang bisa dibeli hanya {$maxStock}.")
                    ->warning()
                    ->send();

                return;
            }

            $this->cart[$index]['quantity']++;
        }
    }

    public function decrementQuantity($index): void
    {
        if (isset($this->cart[$index])) {
            if ($this->cart[$index]['quantity'] > 1) {
                $this->cart[$index]['quantity']--;
            } else {
                $this->removeCart($index);
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
        if ($this->cart === []) {
            Notification::make()
                ->title('Gagal!')
                ->body('Tidak dapat menahan pesanan kosong.')
                ->warning()
                ->send();

            return;
        }
        if ( ! $this->customerId) {
            Notification::make()
                ->title('Pilih Pelanggan!')
                ->body('Harap pilih atau tambahkan nama pelanggan terlebih dahulu agar pesanan mudah dicari nanti.')
                ->warning()
                ->send();

            return;
        }
        $customerName = Customer::find($this->customerId)?->name ?? 'Pelanggan';
        $heldOrders = session()->get('held_orders', []);
        $heldOrders[] = [
            'time' => now()->format('H:i'),
            'customer_id' => $this->customerId,
            'customer_name' => $customerName,
            'cart' => $this->cart,
            'discount' => $this->discountAmount,
            'total' => $this->total,
        ];
        session()->put('held_orders', $heldOrders);
        $this->clearCart();
        $this->customerId = null;
        $this->paymentMethodId = null;
        Notification::make()
            ->title('Pesanan Disimpan!')
            ->body('Pesanan atas nama ' . $customerName . ' berhasil ditahan.')
            ->success()
            ->send();
    }

    public function restoreOrder(int $index): void
    {
        $heldOrders = session()->get('held_orders', []);

        if ( ! isset($heldOrders[$index])) {
            return;
        }
        if ($this->cart !== []) {
            Notification::make()
                ->title('Tidak bisa memuat pesanan!')
                ->body('Harap selesaikan atau kosongkan pesanan saat ini terlebih dahulu.')
                ->warning()
                ->send();

            return;
        }
        $order = $heldOrders[$index];
        $this->cart = $order['cart'];
        $this->customerId = $order['customer_id'];
        $this->discountAmount = $order['discount'];
        unset($heldOrders[$index]);
        session()->put('held_orders', array_values($heldOrders));
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
                ->body('Harap tambahkan barang ke keranjang sebelum melakukan pembayaran.')
                ->danger()
                ->send();

            return;
        }
        if ( ! $this->paymentMethodId) {
            Notification::make()
                ->title('Failed Sale!')
                ->body('Silakan pilih metode pembayaran')
                ->danger()
                ->send();

            return;
        }
        if ($this->paidAmount < $this->total) {
            Notification::make()
                ->title('Failed Sale!')
                ->body('Jumlah pembayaran tidak mencukupi.')
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
                        'item_name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                    ]);
                    $stokVarianBerkurang = false;
                    if ( ! empty($item['variant_ids']) && is_array($item['variant_ids'])) {
                        foreach ($item['variant_ids'] as $vId) {
                            $inv = Inventory::where('item_id', $item['id'])
                                ->where('variant_option_id', $vId)
                                ->first();
                            if ($inv) {
                                Inventory::where('id', $inv->id)->decrement('quantity', $item['quantity']);
                                $stokVarianBerkurang = true;
                            }
                        }
                    } elseif ( ! empty($item['variant_id'])) {
                        $inv = Inventory::where('item_id', $item['id'])
                            ->where('variant_option_id', $item['variant_id'])
                            ->first();
                        if ($inv) {
                            Inventory::where('id', $inv->id)->decrement('quantity', $item['quantity']);
                            $stokVarianBerkurang = true;
                        }
                    }
                    if ( ! $stokVarianBerkurang) {
                        $invParent = Inventory::where('item_id', $item['id'])
                            ->whereNull('variant_option_id')
                            ->first();
                        if ( ! $invParent) {
                            $invParent = Inventory::where('item_id', $item['id'])->first();
                        }
                        if ($invParent) {
                            Inventory::where('id', $invParent->id)->decrement('quantity', $item['quantity']);
                        }
                    }
                }
            });
            Notification::make()
                ->title('Sale Completed!')
                ->body('Change: ' . \Illuminate\Support\Number::currency($this->change, 'IDR'))
                ->success()
                ->send();
            $this->clearCart();
            $this->loadItems();
            $this->search = '';
            $this->customerSearch = '';
            $this->customerId = null;
            $this->paymentMethodId = null;

        } catch (Exception $e) {
            report($e);
            Notification::make()
                ->title('Sale Failed!')
                ->body('Penyebab Error: ' . $e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function render(): View
    {
        return view('livewire.pos.index');
    }

    private function processAddToCart($item, $variant = null): void
    {
        $cartKey = $variant ? $item->id . '-' . $variant->id : (string) $item->id;
        $currentQty = $this->cart[$cartKey]['quantity'] ?? 0;

        $isStockSufficient = true;
        $maxStock = 0;
        $stokVarianBerkurang = false;

        if ($variant) {
            $inv = Inventory::where('item_id', $item->id)
                ->where('variant_option_id', $variant->id)
                ->first();
            if ($inv) {
                $stokVarianBerkurang = true;
                if ($currentQty >= $inv->quantity) {
                    $isStockSufficient = false;
                    $maxStock = $inv->quantity;
                }
            }
        }

        if ( ! $stokVarianBerkurang) {
            $invParent = Inventory::where('item_id', $item->id)
                ->whereNull('variant_option_id')
                ->first();
            if ( ! $invParent) {
                $invParent = Inventory::where('item_id', $item->id)->first();
            }
            if ($invParent && $currentQty >= $invParent->quantity) {
                $isStockSufficient = false;
                $maxStock = $invParent->quantity;
            }
        }

        if ( ! $isStockSufficient) {
            Notification::make()
                ->title('Stok Habis!')
                ->body("Sisa stok {$item->name} hanya {$maxStock}.")
                ->warning()
                ->send();

            return;
        }

        if (isset($this->cart[$cartKey])) {
            $this->cart[$cartKey]['quantity']++;
        } else {
            $this->cart[$cartKey] = [
                'id' => $item->id,
                'variant_id' => $variant ? $variant->id : null,
                'name' => $item->name . ($variant ? ' (' . $variant->name . ')' : ''),
                'price' => $item->price,
                'quantity' => 1,
            ];
        }
    }
}
