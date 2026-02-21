<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_items', function (Blueprint $table) {
            // Kita bikin nullable biar transaksi lama nggak error
            $table->string('item_name')->nullable()->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_items', function (Blueprint $table) {
            $table->dropColumn('item_name');
        });
    }
};