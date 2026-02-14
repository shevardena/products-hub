<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'price' => (float) $this->price,
            'merchant_id' => $this->merchant_id,
            'categories' => $this->categories->pluck('name')->toArray(),
        ];
    }

    protected $fillable = [
        'merchant_id',
        'external_id',
        'name',
        'description',
        'price',
        'currency',
        'quantity',
        'sku',
        'is_active',
        'url',
        'collection',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'product_categories',
            'product_id',
            'category_id'
        )->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)
            ->where('is_primary', true);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForMerchant(Builder $query, int $merchantId): Builder
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function getDiscountPercentageAttribute(): ?float
    {
        if (!$this->price || !$this->real_price) {
            return null;
        }

        if ($this->real_price == 0) {
            return null;
        }

        return round(
            (($this->real_price - $this->price) / $this->real_price) * 100,
            2
        );
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        return $this->primaryImage?->url;
    }
}
