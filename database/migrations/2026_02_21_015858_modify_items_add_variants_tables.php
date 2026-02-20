<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // 1. Ubah tabel items (hapus sku, tambah category)
        Schema::table('items', function (Blueprint $table): void {
            $table->dropColumn('sku');
            $table->string('category')->default('Makanan')->after('name');
        });

        // 2. Buat tabel varian baru
        Schema::create('item_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('group_name'); // Cth: 'Varian Rasa', 'Tingkat Kepedasan'
            $table->string('name');       // Cth: 'Semangka', 'Pedas'
            $table->integer('stock')->nullable(); // Boleh kosong (opsional)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_variants');

        Schema::table('items', function (Blueprint $table): void {
            $table->string('sku')->nullable();
            $table->dropColumn('category');
        });
    }
};
