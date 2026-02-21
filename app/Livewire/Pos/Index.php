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

    public array $selectedOptions = []; // Menyimpan pilihan kasir

    public ?string $search = null;

    public ?string $customerSearch = null;

    public string $activeCategory = 'All'; // <--- TAMBAHKAN BARIS INI

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

    #[Computed]
    public function filteredItems(): array
    {
        $result = $this->items;

        // 1. Filter Kategori
        if ($this->activeCategory !== 'All') {
            $result = array_filter(
                $result,
                fn(array $item): bool => ($item['category'] ?? '') === $this->activeCategory,
            );
        }

        // 2. Filter Pencarian Nama (SKU sudah dihapus)
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
        $item = Item::with(['variantGroups.options'])->find($itemId);

        if ($item && $item->variantGroups->count() > 0) {
            // FIX 500 ERROR: Kita ubah model relasi menjadi Array murni agar Livewire tidak tersedak
            $this->selectedItemIdForVariant = $item->id;
            $this->selectedItemNameForVariant = $item->name;
            $this->selectedOptions = []; // Reset pilihan kasir

            $this->itemVariants = $item->variantGroups->map(function ($group) use ($item) {
                return [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'track_stock' => $group->track_stock,
                    'options' => $group->options->map(function ($opt) use ($item, $group) {
                        // Jika grup ini mendeteksi stok, ambil stok dari tabel inventory
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

        // Jika barang reguler tanpa varian
        $this->processAddToCart($item);
    }

    public function confirmVariantSelection(): void
    {
        // Pastikan kasir memilih 1 opsi dari setiap grup (cth: wajib pilih Rasa & wajib pilih Suhu)
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

            // Validasi limitasi stok
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
        $this->showVariantModal = false; // Tutup modal setelah varian dipilih
    }

    public function removeCart(string $itemId): void
    {
        unset($this->cart[$itemId]);
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

    public function incrementQuantity(string $itemId): void
    {
        if (isset($this->cart[$itemId])) {
            $item = collect($this->items)->firstWhere('id', $itemId);
            if ($this->cart[$itemId]['quantity'] < $item['stock']) {
                $this->cart[$itemId]['quantity']++;
            }
        }
    }

    public function decrementQuantity(string $itemId): void
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

        if ( ! isset($heldOrders[$index])) {
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
                    
                    // ⚠️ CATATAN PENTING:
                    // Kalau di tabel 'sales' kamu ada kolom 'user_id' (untuk tahu kasir siapa yang melayani),
                    // kamu WAJIB ngebuka komentar baris di bawah ini:
                    // 'user_id' => auth()->id(), 
                ]);

                foreach ($this->cart as $item) {
                    // 1. Catat ke history penjualan
                    $sale->salesItems()->create([
                        'item_id' => $item['id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                    ]);

                    $stokVarianBerkurang = false;

                    // 2. Coba potong stok khusus varian (Jika item punya varian dan track_stock-nya aktif)
                    if (!empty($item['variant_ids']) && is_array($item['variant_ids'])) {
                        foreach ($item['variant_ids'] as $vId) {
                            $inv = Inventory::where('item_id', $item['id'])
                                ->where('variant_option_id', $vId)
                                ->first();
                            
                            if ($inv) {
                                Inventory::where('id', $inv->id)->decrement('quantity', $item['quantity']);
                                $stokVarianBerkurang = true;
                            }
                        }
                    } elseif (!empty($item['variant_id'])) {
                        // Support untuk format varian tunggal lama
                        $inv = Inventory::where('item_id', $item['id'])
                            ->where('variant_option_id', $item['variant_id'])
                            ->first();
                        
                        if ($inv) {
                            Inventory::where('id', $inv->id)->decrement('quantity', $item['quantity']);
                            $stokVarianBerkurang = true;
                        }
                    }

                    // 3. FALLBACK: Jika varian tidak pakai stok (contoh: Level Pedas) ATAU menu ini reguler (contoh: Seafood)
                    if (!$stokVarianBerkurang) {
                        // Langsung hajar stok induknya yang variant_option_id-nya kosong
                        $invParent = Inventory::where('item_id', $item['id'])
                            ->whereNull('variant_option_id')
                            ->first();
                        
                        // Jaga-jaga kalau data di database formatnya beda, ambil baris pertama yang nempel ke item ini
                        if (!$invParent) {
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

            // Panggil fungsi bawaan untuk mereset layar kasir
            $this->clearCart();
            $this->loadItems();
            $this->search = '';
            $this->customerSearch = '';
            $this->customerId = null;
            $this->paymentMethodId = null;

        } catch (Exception $e) {
            report($e);

            // --- PERBAIKAN PESAN ERROR ---
            // Kita keluarkan $e->getMessage() agar kamu tahu persis apa penyebab aplikasinya gagal
            Notification::make()
                ->title('Sale Failed!')
                ->body('Penyebab Error: ' . $e->getMessage())
                ->danger()
                ->persistent() // Biar notifikasinya gak cepet hilang
                ->send();
        }
    }

    public function render(): View
    {
        return view('livewire.pos.index');
    }

    private function processAddToCart($item, $variant = null): void
    {
        // Kunci keranjang gabungan ID Item + ID Varian, agar nutrisari semangka & nanas terpisah barisnya
        $cartKey = $variant ? $item->id . '-' . $variant->id : (string) $item->id;

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
