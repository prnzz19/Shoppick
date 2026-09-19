<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $category = DB::table('categories')->where('slug', 'logistics-demo')->first();
        if (!$category) return;

        DB::table('products')->where('category_id', $category->id)->update([
            'is_active' => false,
            'publication_status' => 'draft',
            'updated_at' => now(),
        ]);
        DB::table('categories')->where('id', $category->id)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Intentionally irreversible: development marketplace data must not be republished.
    }
};
