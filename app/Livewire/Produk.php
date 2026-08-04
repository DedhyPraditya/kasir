<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Product;
use App\Models\Topping;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Produk extends Component
{
    use WithPagination, WithFileUploads;

    protected $paginationTheme = 'bootstrap';

    public $activeTab = 'kategori'; // Default tab: 'kategori', 'produk', or 'topping'
    public $search = '';
    public $categoryFilter = '';

    // Category Form fields
    public $categoryId;
    public $categoryName;

    // Category Modal state
    public $isOpenCategory = false;
    public $isEditCategory = false;

    // Product Form fields
    public $productId;
    public $name;
    public $category_id;
    public $base_price;
    public $description;
    public $image;
    public $old_image;
    public $is_active = true;
    public $variants = []; // Array of flavor variants

    // Product Modal state
    public $isOpen = false;
    public $isEdit = false;

    // Topping Form fields
    public $toppingId;
    public $toppingName;
    public $toppingPrice;
    public $toppingIsActive = true;

    // Topping Modal state
    public $isOpenTopping = false;
    public $isEditTopping = false;

    protected $rules = [
        'name'        => 'required|string|max:255',
        'category_id' => 'required|exists:categories,id',
        'base_price'  => 'required|numeric|min:0',
        'description' => 'nullable|string',
        'is_active'   => 'boolean',
        'image'       => 'nullable|image|max:2048',
    ];

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->search = '';
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    // --- Category CRUD ---
    public function openModalCategory()
    {
        $this->resetFormCategory();
        $this->isOpenCategory = true;
        $this->isEditCategory = false;
    }

    public function closeModalCategory()
    {
        $this->isOpenCategory = false;
        $this->resetFormCategory();
    }

    private function resetFormCategory()
    {
        $this->categoryId = null;
        $this->categoryName = '';
        $this->resetErrorBag();
    }

    public function editCategory($id)
    {
        $this->categoryId = $id;
        $category = Category::findOrFail($id);
        $this->categoryName = $category->name;

        $this->isEditCategory = true;
        $this->isOpenCategory = true;
    }

    public function saveCategory()
    {
        $this->validate([
            'categoryName' => 'required|string|max:255',
        ], [
            'categoryName.required' => 'Nama kategori wajib diisi.',
        ]);

        if ($this->isEditCategory) {
            $category = Category::findOrFail($this->categoryId);
            $category->update([
                'name' => $this->categoryName,
                'slug' => Str::slug($this->categoryName),
            ]);
            session()->flash('message', 'Kategori berhasil diperbarui.');
        } else {
            Category::create([
                'name' => $this->categoryName,
                'slug' => Str::slug($this->categoryName),
            ]);
            session()->flash('message', 'Kategori berhasil ditambahkan.');
        }

        $this->closeModalCategory();
    }

    public function deleteCategory($id)
    {
        $category = Category::findOrFail($id);
        if ($category->products()->count() > 0) {
            session()->flash('error', 'Kategori tidak dapat dihapus karena masih memiliki produk.');
            return;
        }
        $category->delete();
        session()->flash('message', 'Kategori berhasil dihapus.');
    }

    // --- Product CRUD ---
    public function openModal()
    {
        $this->resetForm();
        $this->isOpen = true;
        $this->isEdit = false;
    }

    public function closeModal()
    {
        $this->isOpen = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->productId = null;
        $this->name = '';
        $this->category_id = '';
        $this->base_price = '';
        $this->description = '';
        $this->image = null;
        $this->old_image = null;
        $this->is_active = true;
        $this->variants = [];
        $this->resetErrorBag();
    }

    public function edit($id)
    {
        $this->resetForm();
        $product = Product::with('variants')->findOrFail($id);
        $this->productId = $product->id;
        $this->name = $product->name;
        $this->category_id = $product->category_id;
        $this->base_price = $product->base_price;
        $this->description = $product->description;
        $this->old_image = $product->image;
        $this->is_active = (bool) $product->is_active;
        $this->variants = $product->variants->map(function($variant) {
            return [
                'id' => $variant->id,
                'name' => $variant->name,
                'price' => (float)$variant->price,
                'is_active' => (bool)$variant->is_active,
            ];
        })->toArray();

        $this->isEdit = true;
        $this->isOpen = true;
    }

    public function addVariant()
    {
        $this->variants[] = [
            'id' => null,
            'name' => '',
            'price' => '',
            'is_active' => true,
        ];
    }

    public function removeVariant($index)
    {
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
    }

    public function save()
    {
        $this->validate([
            'name'        => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'base_price'  => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image'       => 'nullable|image|max:2048',
            'is_active'   => 'boolean',
            'variants.*.name' => 'required|string|max:255',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.is_active' => 'boolean',
        ], [
            'name.required' => 'Nama produk wajib diisi.',
            'category_id.required' => 'Kategori wajib diisi.',
            'base_price.required' => 'Harga dasar wajib diisi.',
            'image.image' => 'File harus berupa gambar.',
            'image.max' => 'Ukuran gambar maksimal 2MB.',
            'variants.*.name.required' => 'Nama varian rasa wajib diisi.',
            'variants.*.price.required' => 'Harga varian wajib diisi.',
            'variants.*.price.numeric' => 'Harga varian harus berupa angka.',
            'variants.*.price.min' => 'Harga varian tidak boleh kurang dari 0.',
        ]);

        $data = [
            'name'        => $this->name,
            'slug'        => Str::slug($this->name),
            'category_id' => $this->category_id,
            'base_price'  => $this->base_price,
            'description' => $this->description,
            'is_active'   => $this->is_active,
        ];

        if ($this->image) {
            $data['image'] = $this->image->store('products', 'public');
        }

        if ($this->isEdit) {
            $product = Product::findOrFail($this->productId);
            $product->update($data);
            session()->flash('message', 'Produk berhasil diperbarui.');
        } else {
            $product = Product::create($data);
            session()->flash('message', 'Produk berhasil ditambahkan.');
        }

        // Sync Variants
        $keptIds = [];
        foreach ($this->variants as $variantData) {
            if (!empty($variantData['id'])) {
                $variant = $product->variants()->findOrFail($variantData['id']);
                $variant->update([
                    'name' => $variantData['name'],
                    'price' => $variantData['price'],
                    'is_active' => $variantData['is_active'],
                ]);
                $keptIds[] = $variant->id;
            } else {
                $newVariant = $product->variants()->create([
                    'name' => $variantData['name'],
                    'price' => $variantData['price'],
                    'is_active' => $variantData['is_active'],
                ]);
                $keptIds[] = $newVariant->id;
            }
        }
        $product->variants()->whereNotIn('id', $keptIds)->delete();

        $this->closeModal();
    }

    public function delete($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        session()->flash('message', 'Produk berhasil dihapus.');
    }

    // --- Topping CRUD ---
    public function openModalTopping()
    {
        $this->resetFormTopping();
        $this->isOpenTopping = true;
        $this->isEditTopping = false;
    }

    public function closeModalTopping()
    {
        $this->isOpenTopping = false;
        $this->resetFormTopping();
    }

    private function resetFormTopping()
    {
        $this->toppingId = null;
        $this->toppingName = '';
        $this->toppingPrice = '';
        $this->toppingIsActive = true;
        $this->resetErrorBag();
    }

    public function editTopping($id)
    {
        $this->resetFormTopping();
        $topping = Topping::findOrFail($id);
        $this->toppingId = $topping->id;
        $this->toppingName = $topping->name;
        $this->toppingPrice = $topping->price;
        $this->toppingIsActive = (bool) $topping->is_active;

        $this->isEditTopping = true;
        $this->isOpenTopping = true;
    }

    public function saveTopping()
    {
        $this->validate([
            'toppingName'     => 'required|string|max:255',
            'toppingPrice'    => 'required|numeric|min:0',
            'toppingIsActive' => 'boolean',
        ]);

        $data = [
            'name'      => $this->toppingName,
            'price'     => $this->toppingPrice,
            'is_active' => $this->toppingIsActive,
        ];

        if ($this->isEditTopping) {
            $topping = Topping::findOrFail($this->toppingId);
            $topping->update($data);
            session()->flash('message', 'Topping berhasil diperbarui.');
        } else {
            Topping::create($data);
            session()->flash('message', 'Topping berhasil ditambahkan.');
        }

        $this->closeModalTopping();
    }

    public function deleteTopping($id)
    {
        $topping = Topping::findOrFail($id);
        $topping->delete();
        session()->flash('message', 'Topping berhasil dihapus.');
    }

    public function render()
    {
        $categoriesList = Category::all();
        $categories = collect();
        $products = collect();
        $toppings = collect();

        if ($this->activeTab === 'kategori') {
            $query = Category::query();
            if ($this->search) {
                $query->where('name', 'like', '%' . $this->search . '%');
            }
            $categories = $query->latest()->paginate(10);
        } elseif ($this->activeTab === 'produk') {
            $query = Product::with(['category', 'variants']);
            if ($this->search) {
                $query->where('name', 'like', '%' . $this->search . '%');
            }
            if ($this->categoryFilter) {
                $query->where('category_id', $this->categoryFilter);
            }
            $products = $query->latest()->paginate(10);
        } else {
            $query = Topping::query();
            if ($this->search) {
                $query->where('name', 'like', '%' . $this->search . '%');
            }
            $toppings = $query->latest()->paginate(10);
        }

        return view('livewire.produk', [
            'products'       => $products,
            'categories'     => $categories,
            'categoriesList' => $categoriesList,
            'toppings'       => $toppings,
        ])->layout('layouts.app');
    }
}
