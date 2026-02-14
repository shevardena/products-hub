<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('merchant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('external_id')->index()->nullable();

            $table->string('name');
            $table->text('description')->nullable();

            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 10)->nullable();

            $table->integer('quantity')->nullable();
            $table->string('sku')->nullable();


            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['merchant_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
