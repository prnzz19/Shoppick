<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('children.products')->whereNull('parent_id')->orderBy('sort_order')->get();
        $allCategories = Category::whereNull('parent_id')->get();
        return view('admin.categories.index', compact('categories', 'allCategories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:2048'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['parent_id'] = $data['parent_id'] ?? null;

        if (! empty($data['image'])) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category = Category::create($data);

        AdminActivityLog::record('category.created', 'category', $category->id, ['name' => $category->name]);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:2048'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['parent_id'] = $data['parent_id'] ?? null;

        // Prevent setting a category as its own child.
        if ($data['parent_id'] == $category->id) {
            $data['parent_id'] = $category->parent_id;
        }

        if (! empty($data['image'])) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);

        AdminActivityLog::record('category.updated', 'category', $category->id, ['name' => $category->name]);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'Cannot delete a category that still has products.');
        }

        AdminActivityLog::record('category.deleted', 'category', $category->id, ['name' => $category->name]);
        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    public function toggleActive(Category $category)
    {
        $category->update(['is_active' => ! $category->is_active]);
        return back()->with('success', 'Category status updated.');
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
