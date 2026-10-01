<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\FreeDeliveryRule;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\Catalog\CityCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_descendant_ids_path_and_depth(): void
    {
        $root = $this->makeCategory('Groceries', 'groceries');
        $child = $this->makeCategory('Rice', 'rice', $root);
        $grandchild = $this->makeCategory('Basmati', 'basmati', $child);

        $this->assertSame([$root->id, $child->id, $grandchild->id], $root->descendantIds());
        $this->assertSame([$child->id, $grandchild->id], $child->descendantIds());
        $this->assertSame([$grandchild->id], $grandchild->descendantIds());

        $this->assertSame(0, $root->depth);
        $this->assertSame(2, $grandchild->depth);
        $this->assertSame('Groceries › Rice › Basmati', $grandchild->path);

        $this->assertTrue($grandchild->isDescendantOf($root));
        $this->assertFalse($root->isDescendantOf($grandchild));
    }

    public function test_category_cannot_be_its_own_parent(): void
    {
        $root = $this->makeCategory('Groceries', 'groceries');

        $this->expectException(InvalidArgumentException::class);

        $root->parent_id = $root->id;
        $root->save();
    }

    public function test_category_cannot_sit_under_its_own_child(): void
    {
        $root = $this->makeCategory('Groceries', 'groceries');
        $child = $this->makeCategory('Rice', 'rice', $root);

        $this->expectException(InvalidArgumentException::class);

        $root->parent_id = $child->id;
        $root->save();
    }

    public function test_find_by_path(): void
    {
        $groceries = $this->makeCategory('Groceries', 'groceries');
        $rice = $this->makeCategory('Rice', 'rice-grocery', $groceries);
        $snacks = $this->makeCategory('Snacks', 'snacks');
        $snackRice = $this->makeCategory('Rice', 'rice-snack', $snacks);

        $this->assertSame($rice->id, Category::findByPath('Groceries > Rice')->id);
        $this->assertSame($snackRice->id, Category::findByPath('Snacks > Rice')->id);
        $this->assertSame($snacks->id, Category::findByPath('Snacks')->id);
        $this->assertNull(Category::findByPath('Rice'));
        $this->assertNull(Category::findByPath('Nope > Missing'));
        $this->assertNull(Category::findByPath(''));
    }

    public function test_catalog_parent_category_includes_subcategory_products(): void
    {
        [$city, $warehouse] = $this->makeCityWithWarehouse();

        $parent = $this->makeCategory('Groceries', 'groceries');
        $child = $this->makeCategory('Rice', 'rice', $parent);
        $other = $this->makeCategory('Snacks', 'snacks');

        $this->makeStockedProduct('Parent Lentils', 'parent-lentils', $parent, $warehouse);
        $this->makeStockedProduct('Child Basmati', 'child-basmati', $child, $warehouse);
        $this->makeStockedProduct('Other Chips', 'other-chips', $other, $warehouse);

        $parentResult = app(CityCatalogService::class)->getProductsForCity($city->id, $parent->id);
        $parentNames = collect($parentResult['products'])->pluck('name')->all();

        $this->assertContains('Parent Lentils', $parentNames);
        $this->assertContains('Child Basmati', $parentNames);
        $this->assertNotContains('Other Chips', $parentNames);

        $childResult = app(CityCatalogService::class)->getProductsForCity($city->id, $child->id);
        $childNames = collect($childResult['products'])->pluck('name')->all();

        $this->assertSame(['Child Basmati'], $childNames);
    }

    public function test_free_delivery_rule_parent_category_matches_child_item(): void
    {
        $parent = $this->makeCategory('Groceries', 'groceries');
        $child = $this->makeCategory('Rice', 'rice', $parent);
        $other = $this->makeCategory('Snacks', 'snacks');

        $rule = FreeDeliveryRule::create([
            'name' => 'Free delivery on groceries',
            'category_id' => $parent->id,
            'min_quantity' => 2,
            'active' => true,
        ]);

        $this->assertTrue($rule->matches(1, [
            ['product_id' => 10, 'category_id' => $child->id, 'quantity' => 2],
        ], 100.0));

        $this->assertFalse($rule->matches(1, [
            ['product_id' => 11, 'category_id' => $other->id, 'quantity' => 2],
        ], 100.0));
    }

    private function makeCategory(string $name, string $slug, ?Category $parent = null): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'parent_id' => $parent?->id,
        ]);
    }

    /**
     * @return array{0: City, 1: Warehouse}
     */
    private function makeCityWithWarehouse(): array
    {
        $warehouse = Warehouse::create([
            'name' => 'Test WH',
            'code' => 'WH-CAT-01',
            'address' => 'Test address',
            'latitude' => 26.81,
            'longitude' => 87.28,
            'status' => 'active',
        ]);

        $city = City::create([
            'name' => 'Dharan',
            'slug' => 'dharan-cat-test',
            'province' => 'Koshi Province',
            'status' => 'active',
            'cod_enabled' => true,
            'prepaid_enabled' => true,
            'default_delivery_fee' => 40,
            'free_delivery_minimum' => 1000,
            'estimated_delivery_minutes' => 30,
        ]);

        $city->warehouses()->attach($warehouse->id, [
            'priority' => 1,
            'delivery_minutes' => 30,
            'delivery_fee' => 40,
            'status' => 'active',
        ]);

        return [$city, $warehouse];
    }

    private function makeStockedProduct(string $name, string $slug, Category $category, Warehouse $warehouse): void
    {
        $product = Product::create([
            'name' => $name,
            'slug' => $slug,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => strtoupper($slug).'-SKU',
            'name' => 'Pack',
            'price' => 100,
            'cod_allowed' => true,
            'status' => 'active',
        ]);

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
            'reorder_level' => 5,
        ]);
    }
}
