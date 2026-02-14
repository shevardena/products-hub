<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantApi extends Model
{
    protected $fillable = [
        'merchant_id',
        'name',
        'base_url',
        'entity_type',
        'response_data_path',
        'contains_category_data',
        'category_data_path',
        'image_data_path',
        'field_mappings',
        'auth_type',
        'auth_payload',
        'sync_interval_minutes',
        'last_synced_at',
        'is_active',
    ];

    protected $casts = [
        'auth_payload' => 'array',
        'field_mappings' => 'json',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    // In your MerchantApi model
    public function getFieldMappingsArrayAttribute(): array
    {
        if (empty($this->field_mappings)) {
            return [];
        }

        $result = [];
        foreach ($this->field_mappings as $mapping) {
            $result[$mapping['database_column']] = $mapping['response_field'];
        }

        return $result;
    }
}
