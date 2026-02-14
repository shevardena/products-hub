<?php
namespace App\Jobs;

use App\Models\MerchantApi;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use App\Models\ProductAttribute;
use App\Services\ApiFieldMapperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncMerchantApiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;
    public array $backoff = [5, 15, 30];

    protected MerchantApi $merchantApi;
    protected ApiFieldMapperService $mapperService;

    /**
     * Create a new job instance.
     */
    public function __construct(MerchantApi $merchantApi)
    {
        $this->merchantApi = $merchantApi;
        $this->mapperService = app(ApiFieldMapperService::class);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting merchant API sync', [
                'api_id' => $this->merchantApi->id,
                'merchant_id' => $this->merchantApi->merchant_id,
                'merchant' => $this->merchantApi->merchant->name ?? 'Unknown',
                'entity_type' => $this->merchantApi->entity_type,
                'api_name' => $this->merchantApi->name,
                'field_mappings' => $this->merchantApi->field_mappings,
                'category_data_path' => $this->merchantApi->category_data_path,
                'image_data_path' => $this->merchantApi->image_data_path,
                'contains_category_data' => $this->merchantApi->contains_category_data
            ]);

            // Fetch data from API
            $response = $this->fetchApiData();

            // Log full response for debugging
            Log::debug('API Response', [
                'api_id' => $this->merchantApi->id,
                'response' => $response
            ]);

            // Extract the actual data array from a response
            $items = $this->mapperService->extractResponseData($response, $this->merchantApi);

            if (!is_array($items)) {
                Log::warning('Response data is not an array', [
                    'api_id' => $this->merchantApi->id,
                    'type' => gettype($items)
                ]);
                return;
            }

            if (empty($items)) {
                Log::info('No data received from API', [
                    'api_id' => $this->merchantApi->id
                ]);
                return;
            }

            Log::info('Processing items', [
                'api_id' => $this->merchantApi->id,
                'count' => count($items),
                'first_item' => isset($items[0]) ? array_keys($items[0]) : 'No items'
            ]);

            $syncedCount = 0;
            $errors = [];

            foreach ($items as $index => $item) {
                try {
                    if (!is_array($item)) {
                        Log::warning('Item is not an array, skipping', [
                            'api_id' => $this->merchantApi->id,
                            'index' => $index,
                            'type' => gettype($item)
                        ]);
                        continue;
                    }

                    if ($this->merchantApi->entity_type === 'products') {
                        $this->syncProduct($item, $index);
                    } elseif ($this->merchantApi->entity_type === 'categories') {
                        $this->syncCategory($item);
                    }

                    $syncedCount++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'error' => $e->getMessage()
                    ];

                    Log::error('Error syncing item', [
                        'api_id' => $this->merchantApi->id,
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Update last synced timestamp
            $this->merchantApi->update([
                'last_synced_at' => now()
            ]);

            Log::info('Merchant API sync completed', [
                'api_id' => $this->merchantApi->id,
                'synced_count' => $syncedCount,
                'errors' => count($errors),
                'total_items' => count($items)
            ]);

        } catch (\Exception $e) {
            Log::error('Merchant API sync failed', [
                'api_id' => $this->merchantApi->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Fetch data from the merchant API
     */
    protected function fetchApiData(): array
    {
        $http = Http::timeout(60)->retry(3, 1000);

        // Apply authentication
        $http = $this->applyAuthentication($http);

        // Make the request
        $response = $http->get($this->merchantApi->base_url);

        if (!$response->successful()) {
            throw new \Exception("API request failed with status: " . $response->status());
        }

        return $response->json();
    }

    /**
     * Apply authentication to HTTP client
     */
    protected function applyAuthentication($http)
    {
        $authType = $this->merchantApi->auth_type;
        $authPayload = $this->merchantApi->auth_payload ?? [];

        switch ($authType) {
            case 'bearer':
                $token = $authPayload['token'] ?? '';
                if ($token) {
                    $http = $http->withToken($token);
                }
                break;

            case 'basic':
                $username = $authPayload['username'] ?? '';
                $password = $authPayload['password'] ?? '';
                if ($username && $password) {
                    $http = $http->withBasicAuth($username, $password);
                }
                break;

            case 'api_key':
                $key = $authPayload['key'] ?? '';
                $value = $authPayload['value'] ?? '';
                $placement = $authPayload['placement'] ?? 'header';

                if ($key && $value) {
                    if ($placement === 'header') {
                        $http = $http->withHeaders([$key => $value]);
                    } elseif ($placement === 'query') {
                        $http = $http->withOptions(['query' => [$key => $value]]);
                    }
                }
                break;

            case 'custom':
                $headers = $authPayload['headers'] ?? [];
                if (!empty($headers)) {
                    $http = $http->withHeaders($headers);
                }
                break;
        }

        return $http;
    }

    /**
     * Sync a single product
     */
    protected function syncProduct(array $productData, int $index): void
    {
        Log::info('Processing product', [
            'api_id' => $this->merchantApi->id,
            'index' => $index,
            'product_keys' => array_keys($productData)
        ]);

        // Apply field mappings
        $mappedData = $this->mapperService->applyFieldMappings($productData, $this->merchantApi);

        Log::debug('Product mapped data', [
            'api_id' => $this->merchantApi->id,
            'index' => $index,
            'mapped_data' => $mappedData
        ]);

        // If no mapped data at all, skip
        if (empty($mappedData)) {
            Log::warning('No field mappings applied for product, skipping', [
                'api_id' => $this->merchantApi->id,
                'index' => $index
            ]);
            return;
        }

        // Prepare product data
        $data = [
            'merchant_id' => $this->merchantApi->merchant_id,
            'is_active' => true
        ];

        // Map database fields
        $dbFields = ['name', 'description', 'price', 'currency', 'quantity', 'sku', 'external_id'];

        foreach ($dbFields as $field) {
            if (isset($mappedData[$field]) && $mappedData[$field] !== null && $mappedData[$field] !== '') {
                if ($field === 'price') {
                    $data[$field] = $this->cleanPrice($mappedData[$field]);
                } elseif ($field === 'quantity') {
                    $data[$field] = $this->cleanQuantity($mappedData[$field]);
                } else {
                    $data[$field] = $mappedData[$field];
                }
            }
        }

        // Set default name if not provided
        if (!isset($data['name'])) {
            $data['name'] = 'Unnamed Product ' . ($mappedData['external_id'] ?? $index);
        }

        // Set default currency if not provided
        if (!isset($data['currency'])) {
            $data['currency'] = 'GEL';
        }

        // Generate identifier
        $identifier = $this->generateProductIdentifier($mappedData);

        // If we have no way to identify this product, skip it
        if (empty($identifier)) {
            Log::warning('Cannot generate unique identifier for product, skipping', [
                'api_id' => $this->merchantApi->id,
                'index' => $index,
                'mapped_data' => $mappedData
            ]);
            return;
        }


        // Find or create a product
        $product = $this->findOrCreateProduct($identifier, $data);

        Log::info('Product saved', [
            'product_id' => $product->id,
            'name' => $product->name,
            'identifier' => $identifier
        ]);


        $this->syncCategoriesFromProduct($product, $productData);
        $this->syncImages($product, $productData);
        $this->syncAttributes($product, $productData, $mappedData);
    }

    /**
     * Generate a unique product identifier
     */
    protected function generateProductIdentifier(array $mappedData): array
    {
        $identifier = ['merchant_id' => $this->merchantApi->merchant_id];

        if (!empty($mappedData['external_id'])) {
            $identifier['external_id'] = (string) $mappedData['external_id'];
            return $identifier;
        }

        if (!empty($mappedData['sku'])) {
            $identifier['sku'] = (string) $mappedData['sku'];
            return $identifier;
        }

        return [];
    }

    /**
     * Find or create product
     */
    protected function findOrCreateProduct(array $identifier, array $data): Product
    {
        $product = null;

        // Try to find by external_id
        if (isset($identifier['external_id'])) {
            $product = Product::where('merchant_id', $this->merchantApi->merchant_id)
                ->where('external_id', $identifier['external_id'])
                ->first();
        }

        // Try to find by SKU
        if (!$product && isset($identifier['sku'])) {
            $product = Product::where('merchant_id', $this->merchantApi->merchant_id)
                ->where('sku', $identifier['sku'])
                ->first();
        }

        // Update or create
        if ($product) {
            $product->update($data);
            return $product;
        }

        return Product::create(array_merge($identifier, $data));
    }

    /**
     * Clean price value
     */
    protected function cleanPrice($price): ?float
    {
        if ($price === null || $price === '') {
            return null;
        }

        if (is_string($price)) {
            // Remove currency symbols, spaces, and convert comma to dot
            $price = preg_replace('/[^0-9.,]/', '', $price);
            $price = str_replace(',', '.', $price);
            // Remove extra dots
            $parts = explode('.', $price);
            if (count($parts) > 2) {
                $price = $parts[0] . '.' . implode('', array_slice($parts, 1));
            }
        }

        return (float) $price;
    }

    /**
     * Clean quantity value
     */
    protected function cleanQuantity($quantity): ?int
    {
        if ($quantity === null || $quantity === '') {
            return null;
        }

        return (int) $quantity;
    }

    /**
     * Sync a single category
     */
    protected function syncCategory(array $categoryData): void
    {
        // Apply field mappings
        $mappedData = $this->mapperService->applyFieldMappings($categoryData, $this->merchantApi);

        Log::debug('Category mapped data', [
            'api_id' => $this->merchantApi->id,
            'mapped_data' => $mappedData
        ]);

        // If no mapped data at all, skip
        if (empty($mappedData)) {
            Log::warning('No field mappings applied for category, skipping', [
                'api_id' => $this->merchantApi->id
            ]);
            return;
        }

        // Prepare category data
        $data = [
            'merchant_id' => $this->merchantApi->merchant_id,
            'is_active' => true
        ];

        // Map database fields
        $dbFields = ['name', 'slug', 'external_id', 'sort_order'];

        foreach ($dbFields as $field) {
            if (isset($mappedData[$field]) && $mappedData[$field] !== null && $mappedData[$field] !== '') {
                $data[$field] = $mappedData[$field];
            }
        }

        // Set default name if not provided
        if (!isset($data['name'])) {
            Log::warning('Category missing name in mapped data, skipping', [
                'api_id' => $this->merchantApi->id,
                'mapped_data' => $mappedData
            ]);
            return;
        }

        // Generate slug if not provided
        if (!isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // Generate identifier
        $identifier = $this->generateCategoryIdentifier($mappedData);

        // If we have no way to identify this category, skip it
        if (empty($identifier)) {
            Log::warning('Cannot generate unique identifier for category, skipping', [
                'api_id' => $this->merchantApi->id,
                'mapped_data' => $mappedData
            ]);
            return;
        }

        // Handle parent category
        if (isset($mappedData['parent_id'])) {
            $parentCategory = Category::where('merchant_id', $this->merchantApi->merchant_id)
                ->where('external_id', (string) $mappedData['parent_id'])
                ->first();

            if ($parentCategory) {
                $data['parent_id'] = $parentCategory->id;
            }
        }

        // Update or create category
        Category::updateOrCreate(
            $identifier,
            $data
        );
    }

    /**
     * Generate category identifier
     */
    protected function generateCategoryIdentifier(array $mappedData): array
    {
        $identifier = ['merchant_id' => $this->merchantApi->merchant_id];

        if (!empty($mappedData['external_id'])) {
            $identifier['external_id'] = (string) $mappedData['external_id'];
            return $identifier;
        }

        return [];
    }

    /**
     * Sync categories from product data - FIXED: Always try to extract
     */
    protected function syncCategoriesFromProduct(Product $product, array $productData): void
    {
        // Always try to extract categories, regardless of contains_category_data
        // This helps with debugging
        $categories = $this->mapperService->extractCategoriesFromProduct($productData, $this->merchantApi);

        Log::info('Category extraction result', [
            'product_id' => $product->id,
            'contains_category_data' => $this->merchantApi->contains_category_data,
            'category_data_path' => $this->merchantApi->category_data_path,
            'extracted_categories' => $categories,
            'raw_product_data_keys' => array_keys($productData)
        ]);

        if (empty($categories)) {
            Log::debug('No categories extracted for product', [
                'product_id' => $product->id
            ]);
            return;
        }

        $categoryIds = [];

        foreach ($categories as $categoryData) {
            $categoryId = $this->findOrCreateCategoryFromProduct($categoryData);
            if ($categoryId) {
                $categoryIds[] = $categoryId;
            }
        }

        if (!empty($categoryIds)) {
            // Sync the categories (attach)
            $product->categories()->syncWithoutDetaching($categoryIds);

//            Log::info('Categories attached to product', [
//                'product_id' => $product->id,
//                'category_ids' => $categoryIds,
//                'product_categories_count' => $product->categories()->count()
//            ]);

            // Verify they were attached
            $attachedCount = $product->categories()->count();
            Log::debug('Product categories count after sync', [
                'product_id' => $product->id,
                'count' => $attachedCount
            ]);
        }
    }

    /**
     * Find or create a category from product data
     */
    protected function findOrCreateCategoryFromProduct($categoryData): ?int
    {
        $merchantId = $this->merchantApi->merchant_id;

        $name = null;
        $externalId = null;

        // Handle different category data formats
        if (is_string($categoryData)) {
            // Simple string category name
            $name = $categoryData;
            $externalId = Str::slug($categoryData);
        } elseif (is_array($categoryData)) {
            // Array format
            $name = $categoryData['name'] ?? $categoryData['title'] ?? null;
            $externalId = $categoryData['id'] ?? $categoryData['external_id'] ?? ($name ? Str::slug($name) : null);
        } elseif (is_numeric($categoryData)) {
            // Numeric ID
            $name = 'Category ' . $categoryData;
            $externalId = (string) $categoryData;
        }

        if (!$name) {
            Log::warning('Category missing name', ['category_data' => $categoryData]);
            return null;
        }

        // Try to find existing category
        $category = Category::where('merchant_id', $merchantId)
            ->where(function($query) use ($externalId, $name) {
                $query->where('external_id', $externalId)
                    ->orWhere('name', $name);
            })
            ->first();

        if (!$category) {
            // Create new category
            $category = Category::create([
                'merchant_id' => $merchantId,
                'name' => $name,
                'slug' => Str::slug($name),
                'external_id' => (string) $externalId,
                'is_active' => true,
            ]);

            Log::info('Created new category from product', [
                'category_id' => $category->id,
                'name' => $name,
                'external_id' => $externalId
            ]);
        } else {
            Log::debug('Found existing category', [
                'category_id' => $category->id,
                'name' => $name
            ]);
        }

        return $category->id;
    }

    /**
     * Sync product images - FIXED: Always try to extract
     */
    protected function syncImages(Product $product, array $productData): void
    {
        $images = $this->mapperService->extractImages($productData, $this->merchantApi);

        if (empty($images)) {
            return;
        }

        // remove old images
        $product->images()->delete();

        foreach ($images as $index => $imageData) {
            if (empty($imageData['url'])) {
                continue;
            }

            $product->images()->create([
                'url' => $imageData['url'],
                'collection' => $imageData['collection'] ?? 'gallery',
                'is_primary' => $imageData['is_primary'] ?? ($index === 0),
                'sort_order' => $imageData['sort_order'] ?? $index,
            ]);
        }
    }

    /**
     * Sync unmapped fields as product attributes - FIXED: Always try to extract
     */
    protected function syncAttributes(Product $product, array $originalData, array $mappedData): void
    {
        // Always try to extract attributes
        $attributes = $this->mapperService->extractUnmappedFields($originalData, $mappedData, $this->merchantApi);


        Log::info('Attribute extraction result', [
            'product_id' => $product->id,
            'extracted_attributes' => $attributes,
            'mapped_keys' => array_keys($mappedData),
            'original_keys' => array_keys($originalData)
        ]);

        if (empty($attributes)) {
            Log::debug('No attributes extracted for product', [
                'product_id' => $product->id
            ]);
            return;
        }

        $createdCount = 0;

        foreach ($attributes as $attributeData) {
            $key = $attributeData['key'] ?? null;
            $value = $attributeData['value'] ?? null;

            if (!$key) {
                continue;
            }

            // Format value if it's an array
            if (is_array($value)) {
                $value = json_encode($value);
            }

            // Create the attribute (don't delete existing ones)
            ProductAttribute::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'key' => $key
                ],
                ['value' => (string) $value]
            );

            $createdCount++;
        }

        Log::info('Attributes created/updated for product', [
            'product_id' => $product->id,
            'created_count' => $createdCount,
            'product_attributes_count' => $product->attributes()->count()
        ]);
    }
}
