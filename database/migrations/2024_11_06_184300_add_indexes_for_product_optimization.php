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
        Schema::table('products', function (Blueprint $table) {
            // Index for status filtering (most common filter)
            $table->index('status', 'idx_products_status');

            // Index for featured filtering
            $table->index('featured', 'idx_products_featured');

            // Composite index for status and featured (common combination)
            $table->index(['status', 'featured'], 'idx_products_status_featured');

            // Index for search functionality on name
            $table->index('name', 'idx_products_name');

            // Index for search functionality on slug
            $table->index('slug', 'idx_products_slug');

            // Composite index for pagination with status
            $table->index(['status', 'id'], 'idx_products_status_id');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            // Index for product-category relationships
            $table->index('id_product', 'idx_product_categories_product');
            $table->index('id_categorie', 'idx_product_categories_category');
        });

        Schema::table('product_materials', function (Blueprint $table) {
            // Index for product-material relationships
            $table->index('id_product', 'idx_product_materials_product');
            $table->index('id_material', 'idx_product_materials_material');
        });

        Schema::table('product_attributes', function (Blueprint $table) {
            // Index for product-attribute relationships
            $table->index('id_product', 'idx_product_attributes_product');
            $table->index('id_attribute_value', 'idx_product_attributes_attribute');
        });

        Schema::table('product_galleries', function (Blueprint $table) {
            // Index for product gallery relationships
            $table->index('id_product', 'idx_product_galleries_product');
        });

        Schema::table('product_components', function (Blueprint $table) {
            // Index for product-component relationships
            $table->index('id_product', 'idx_product_components_product');
            $table->index('id_component', 'idx_product_components_component');
        });

        Schema::table('products_related', function (Blueprint $table) {
            // Index for related products
            $table->index('id_product', 'idx_products_related_product');
            $table->index('id_product_related', 'idx_products_related_related');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_status');
            $table->dropIndex('idx_products_featured');
            $table->dropIndex('idx_products_status_featured');
            $table->dropIndex('idx_products_name');
            $table->dropIndex('idx_products_slug');
            $table->dropIndex('idx_products_status_id');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropIndex('idx_product_categories_product');
            $table->dropIndex('idx_product_categories_category');
        });

        Schema::table('product_materials', function (Blueprint $table) {
            $table->dropIndex('idx_product_materials_product');
            $table->dropIndex('idx_product_materials_material');
        });

        Schema::table('product_attributes', function (Blueprint $table) {
            $table->dropIndex('idx_product_attributes_product');
            $table->dropIndex('idx_product_attributes_attribute');
        });

        Schema::table('product_galleries', function (Blueprint $table) {
            $table->dropIndex('idx_product_galleries_product');
        });

        Schema::table('product_components', function (Blueprint $table) {
            $table->dropIndex('idx_product_components_product');
            $table->dropIndex('idx_product_components_component');
        });

        Schema::table('products_related', function (Blueprint $table) {
            $table->dropIndex('idx_products_related_product');
            $table->dropIndex('idx_products_related_related');
        });
    }
};
