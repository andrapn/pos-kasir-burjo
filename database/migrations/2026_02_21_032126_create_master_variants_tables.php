<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Bersihkan tabel percobaan sebelumnya (jika ada)
        Schema::dropIfExists('item_variants');

        // 2. Tabel Master Group (Cth: Rasa Nutrisari, Level Pedas)
        Schema::create('variant_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name'); 
            $table->boolean('track_stock')->default(false); // Aktifkan jika butuh potong stok (cth: Rasa = true, Pedas = false)
            $table->timestamps();
        });

        // 3. Tabel Master Opsi (Cth: Semangka, Nanas, Pedas, Sedang)
        Schema::create('variant_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_group_id')->constrained('variant_groups')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        // 4. Tabel Pivot: Menghubungkan Item (Cth: Nasi Goreng) dengan Group (Cth: Level Pedas)
        Schema::create('item_variant_group', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_group_id')->constrained('variant_groups')->cascadeOnDelete();
            $table->primary(['item_id', 'variant_group_id']);
        });

        // 5. Update tabel Inventory agar bisa menyimpan stok spesifik per opsi (Cth: Stok Semangka khusus Nutrisari)
        Schema::table('inventories', function (Blueprint $table) {
            $table->foreignId('variant_option_id')->nullable()->constrained('variant_options')->cascadeOnDelete()->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropForeign(['variant_option_id']);
            $table->dropColumn('variant_option_id');
        });
        Schema::dropIfExists('item_variant_group');
        Schema::dropIfExists('variant_options');
        Schema::dropIfExists('variant_groups');
    }
};