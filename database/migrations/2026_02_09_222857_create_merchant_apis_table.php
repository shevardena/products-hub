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
        Schema::create('merchant_apis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('base_url');

            $table->enum('entity_type', ['products', 'categories'])
                ->default('products');

            $table->string('response_data_path')
                ->nullable();

            $table->boolean('contains_category_data')
                ->default(false);

            $table->string('category_data_path')
                ->nullable();
            $table->string('image_data_path')
                ->nullable();

            $table->json('field_mappings')
                ->nullable();

            $table->string('auth_type')->default('none');
            $table->json('auth_payload')->nullable();

            $table->integer('sync_interval_minutes')->default(60);
            $table->timestamp('last_synced_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_apis');
    }
};
