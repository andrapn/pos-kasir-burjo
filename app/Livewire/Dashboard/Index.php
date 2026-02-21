<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\Sale;
use App\Models\VariantOption;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class Index extends Component
{
    #[Computed]
    public function todaySales(): array
    {
        $sales = Sale::whereDate('created_at', today());

        return [
            'total' => $sales->sum('total'),
            'count' => $sales->count(),
        ];
    }

    #[Computed]
    public function todayCustomers(): int
    {
        return Sale::whereDate('created_at', today())
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');
    }

    #[Computed]
    public function lowStockItems(): int
    {
        return Inventory::where('quantity', '<=', 5)
            ->where('quantity', '>', 0)
            ->count();
    }

    public function outOfStockItems(): int
    {
        return Inventory::where('quantity', '<=', 0)->count();
    }

    public function pendingPayments(): array
    {
        $sales = Sale::whereColumn('paid_amount', '<', 'total');

        return [
            'total' => $sales->sum(DB::raw('total - paid_amount')),
            'count' => $sales->count(),
        ];
    }

    #[Computed]
    public function totalCustomers(): int
    {
        return Customer::count();
    }

    #[Computed]
    public function totalItems(): int
    {
        return Item::active()->count();
    }

    #[Computed]
    public function monthSales(): array
    {
        $sales = Sale::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);

        return [
            'total' => $sales->sum('total'),
            'count' => $sales->count(),
        ];
    }

    #[Computed]
    public function recentSales(): Collection
    {
        return Sale::with(['customer:id,name', 'paymentMethod:id,name'])
            ->withCount('salesItems')
            ->latest()
            ->take(5)
            ->get();
    }

    public function topSellingItems(): \Illuminate\Support\Collection
    {
        return DB::table('sales_items')
            ->join('items', 'sales_items.item_id', 'items.id')
            ->select(
                DB::raw('COALESCE(sales_items.item_name, items.name) as name'), 
                DB::raw('SUM(sales_items.quantity) as total_sold')
            )
            ->whereMonth('sales_items.created_at', now()->month)
            // 👇 FIX UNTUK POSTGRESQL 👇
            // Kita pakai groupByRaw dan jabarkan fungsinya secara utuh
            ->groupByRaw('COALESCE(sales_items.item_name, items.name)') 
            ->orderByDesc('total_sold')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function lowStockList(): array
    {
        return Inventory::with('item:id,name,low_stock_threshold') // Ambil relasi item sekalian
            ->get()
            ->filter(function ($inventory) {
                // Saring jika stok <= batas minimum dari item induk (default 10 kalau kosong)
                $threshold = $inventory->item->low_stock_threshold ?? 10;
                return $inventory->quantity <= $threshold && $inventory->quantity > 0;
            })
            ->map(function ($inventory) {
                // Format nama biar variannya ikutan mejeng
                $name = ucfirst((string) $inventory->item->name);
                
                if ($inventory->variant_option_id) {
                    $variant = VariantOption::find($inventory->variant_option_id);
                    if ($variant) {
                        $name .= ' (' . $variant->name . ')';
                    }
                }

                return [
                    'id' => $inventory->id, // Bawa ID buat jaga-jaga kalau dibutuhin di blade
                    'item' => [
                        'name' => $name, // Nama yang udah digabung sama varian
                    ],
                    'quantity' => $inventory->quantity,
                ];
            })
            ->sortBy('quantity') // Urutkan dari stok yang paling kritis
            ->values()
            ->toArray();
    }

    #[Computed]
    public function weeklySalesChart(): array
    {
        $days = collect(range(6, 0))->map(function ($daysAgo): array {
            $date = now()->subDays($daysAgo);
            
            // Bikin kamus translate hari
            $namaHari = [
                'Sun' => 'Min',
                'Mon' => 'Sen',
                'Tue' => 'Sel',
                'Wed' => 'Rab',
                'Thu' => 'Kam',
                'Fri' => 'Jum',
                'Sat' => 'Sab',
            ];

            return [
                // Panggil kamusnya berdasarkan format 'D' (Sun, Mon, dst)
                'label' => $namaHari[$date->format('D')], 
                'date' => $date->toDateString(),
                'total' => Sale::whereDate('created_at', $date)->sum('total'),
            ];
        });

        return [
            'labels' => $days->pluck('label')->toArray(),
            'data' => $days->pluck('total')->toArray(),
        ];
    }

    public function paymentMethodsChart(): array
    {
        return Sale::whereMonth('created_at', now()->month)
            ->selectRaw('payment_method_id, SUM(total) as total')
            ->groupBy('payment_method_id')
            ->with('paymentMethod:id,name')
            ->get()
            ->map(callback: fn($sale): array => [
                'label' => $sale->paymentMethod?->name ?? 'Unknown',
                'total' => $sale->total,
            ])
            ->toArray();
    }

    public function render(): Factory|View
    {
        return view('livewire.dashboard.index');
    }
}
