<?php

namespace App\Services;

use App\Models\MerchantApi;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ApiFieldMapperService
{
    public function extractValue(array $data, ?string $path): mixed
    {
        if (!$path) {
            return null;
        }

        // Handle dot notation paths
        return Arr::get($data, $path);
    }

    public function applyFieldMappings(array $item, MerchantApi $api): array
    {
        $mappings = $api->field_mappings ?? [];
        $mappedData = [];

        if (empty($mappings)) {
            Log::warning('No field mappings found for API', [
                'api_id' => $api->id
            ]);
            return [];
        }

        foreach ($mappings as $mapping) {
            $target = $mapping['database_column'] ?? null;
            $source = $mapping['response_field'] ?? null;

            if (!$target || !$source) {
                continue;
            }

            $value = $this->extractValue($item, $source);

            if ($value !== null) {
                $mappedData[$target] = $value;
            }
        }

        return $mappedData;
    }

    public function extractResponseData(array $response, MerchantApi $api): array
    {
        if (!$api->response_data_path) {
            return $response;
        }

        $data = $this->extractValue($response, $api->response_data_path);

        if (!is_array($data)) {
            Log::warning('Response data path did not return an array', [
                'api_id' => $api->id,
                'path' => $api->response_data_path,
                'type' => gettype($data)
            ]);
            return [];
        }

        return $data;
    }

    /**
     * FIXED: Extract categories from product data
     */
    public function extractCategoriesFromProduct(array $productData, MerchantApi $api): array
    {
        // If no category data path, return empty
        if (!$api->category_data_path) {
            return [];
        }

        $value = $this->extractValue($productData, $api->category_data_path);

        Log::debug('Raw category value extracted', [
            'api_id' => $api->id,
            'path' => $api->category_data_path,
            'value_type' => gettype($value),
            'value' => $value
        ]);

        if ($value === null) {
            return [];
        }

        $categories = [];

        // Handle single category value (like "80" or "ქვაბი")
        if (is_string($value) || is_numeric($value)) {
            // Return as array with the value
            $categories[] = $value;
        }
        // Handle array of categories
        elseif (is_array($value)) {
            // If it's an associative array with category data
            if (Arr::isAssoc($value)) {
                // Single category with properties
                $categories[] = $value;
            } else {
                // List of categories
                foreach ($value as $cat) {
                    if (is_string($cat) || is_numeric($cat)) {
                        $categories[] = $cat;
                    } elseif (is_array($cat)) {
                        $categories[] = $cat;
                    }
                }
            }
        }

        Log::info('Extracted categories from product', [
            'api_id' => $api->id,
            'path' => $api->category_data_path,
            'extracted_count' => count($categories),
            'categories' => $categories
        ]);

        return $categories;
    }

    /**
     * FIXED: Extract images from product data
     */
    public function extractImages(array $productData, MerchantApi $api): array
    {
        if (!$api->image_data_path) {
            return [];
        }

        $value = $this->extractValue($productData, $api->image_data_path);

        Log::debug('Raw image value extracted', [
            'api_id' => $api->id,
            'path' => $api->image_data_path,
            'value_type' => gettype($value),
            'value' => $value
        ]);

        if ($value === null) {
            return [];
        }

        $images = [];

        // Handle array of image URLs (your case: images array with strings)
        if (is_array($value)) {
            foreach ($value as $index => $img) {
                // If it's a string URL directly
                if (is_string($img)) {
                    $images[] = [
                        'url' => $img,
                        'is_primary' => $index === 0,
                        'sort_order' => $index,
                        'collection' => 'gallery'
                    ];
                }
                // If it's an array with image data
                elseif (is_array($img)) {
                    $url = $img['url'] ?? $img['src'] ?? $img['image'] ?? $img['path'] ?? null;
                    if ($url) {
                        $images[] = [
                            'url' => $url,
                            'is_primary' => $img['is_primary'] ?? ($index === 0),
                            'sort_order' => $img['sort_order'] ?? $index,
                            'collection' => $img['collection'] ?? 'gallery'
                        ];
                    }
                }
            }
        }
        // Handle single image as string
        elseif (is_string($value)) {
            $images[] = [
                'url' => $value,
                'is_primary' => true,
                'sort_order' => 0,
                'collection' => 'gallery'
            ];
        }

        Log::info('Extracted images from product', [
            'api_id' => $api->id,
            'path' => $api->image_data_path,
            'extracted_count' => count($images),
            'images' => $images
        ]);

        return $images;
    }

    /**
     * FIXED: Extract unmapped fields as attributes
     */
    public function extractUnmappedFields(array $originalData, array $mappedData, MerchantApi $api): array
    {
        // Get all source fields that are mapped to database columns
        $mappedSources = collect($api->field_mappings ?? [])
            ->pluck('response_field')
            ->map(function($path) {
                // Get the root key (first part of dot notation)
                return explode('.', $path)[0];
            })
            ->unique()
            ->toArray();

        // Get root keys for special paths
        $categoryRoot = $api->category_data_path
            ? explode('.', $api->category_data_path)[0]
            : null;

        $imageRoot = $api->image_data_path
            ? explode('.', $api->image_data_path)[0]
            : null;

        $attributes = [];

        foreach ($originalData as $key => $value) {
            // Skip if this key is used in mappings
            if (in_array($key, $mappedSources)) {
                continue;
            }

            // Skip if this key is used for categories
            if ($key === $categoryRoot) {
                continue;
            }

            // Skip if this key is used for images
            if ($key === $imageRoot) {
                continue;
            }

            // Skip empty values
            if ($value === null || $value === '') {
                continue;
            }

            // Handle different value types
            if (is_array($value)) {
                if (empty($value)) {
                    continue;
                }
                // For arrays, store as JSON
                $value = json_encode($value);
            } elseif (is_object($value)) {
                $value = json_encode($value);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } else {
                $value = (string) $value;
            }

            $attributes[] = [
                'key' => $key,
                'value' => $value
            ];
        }

        Log::info('Extracted unmapped fields', [
            'api_id' => $api->id,
            'attributes_count' => count($attributes),
            'attributes' => $attributes
        ]);

        return $attributes;
    }
}
