<div>
    <div class="container-fluid py-4">
        {{-- Header --}}
        <div class="mb-4">
            <h3 class="fw-bold mb-0">Kelola Menu & Topping</h3>
            <p class="text-muted mb-0 small">Ubah kategori, produk baru, harga dasar, atau kelola variasi topping di sini.</p>
        </div>

        {{-- Nav Tabs --}}
        <ul class="nav nav-tabs border-bottom mb-4">
            <li class="nav-item">
                <button class="nav-link py-2.5 px-4 fw-bold border-0 {{ $activeTab === 'kategori' ? 'active text-warning border-bottom border-warning border-3' : 'text-muted' }}" 
                        wire:click="switchTab('kategori')">
                    <i class="bi bi-tags-fill me-2"></i>Kelola Kategori
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-2.5 px-4 fw-bold border-0 {{ $activeTab === 'produk' ? 'active text-success border-bottom border-success border-3' : 'text-muted' }}" 
                        wire:click="switchTab('produk')">
                    <i class="bi bi-box-seam me-2"></i>Daftar Produk (Harga Dasar)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-2.5 px-4 fw-bold border-0 {{ $activeTab === 'topping' ? 'active text-primary border-bottom border-primary border-3' : 'text-muted' }}" 
                        wire:click="switchTab('topping')">
                    <i class="bi bi-egg-fried me-2"></i>Kelola Topping
                </button>
            </li>
        </ul>

        @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('message') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if($activeTab === 'kategori')
            {{-- Kategori Header Action --}}
            <div class="d-flex justify-content-end mb-3">
                <button type="button" class="btn btn-warning text-white shadow-sm" wire:click="openModalCategory">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Kategori
                </button>
            </div>
            {{-- Search - Kategori --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" placeholder="Cari nama kategori..." wire:model.live.debounce.300ms="search">
                    </div>
                </div>
            </div>

            {{-- Category Table --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">No.</th>
                                    <th>Nama Kategori</th>
                                    <th>Slug</th>
                                    <th>Jumlah Produk</th>
                                    <th class="text-end pe-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $category)
                                <tr>
                                    <td class="ps-4 text-muted">{{ $categories->firstItem() + $loop->index }}</td>
                                    <td>
                                        <span class="fw-bold text-dark">{{ $category->name }}</span>
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ $category->slug }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info">
                                            {{ $category->products->count() }} Produk
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-warning me-1" wire:click="editCategory('{{ $category->id }}')">
                                            <i class="bi bi-pencil-fill"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="confirm('Apakah Anda yakin ingin menghapus kategori ini?') || event.stopImmediatePropagation()"
                                                wire:click="deleteCategory('{{ $category->id }}')">
                                            <i class="bi bi-trash-fill"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-tags fs-1 d-block mb-2 opacity-50"></i>
                                        Tidak ada kategori ditemukan.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($categories->hasPages())
                <div class="card-footer bg-white border-top-0">
                    {{ $categories->links() }}
                </div>
                @endif
            </div>

        @elseif($activeTab === 'produk')
            {{-- Produk Header Action --}}
            <div class="d-flex justify-content-end mb-3">
                <button type="button" class="btn btn-success shadow-sm" wire:click="openModal">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Produk
                </button>
            </div>
            {{-- Filter & Search - Produk --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" placeholder="Cari nama produk..." wire:model.live.debounce.300ms="search">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <select class="form-select" wire:model.live="categoryFilter">
                                <option value="">Semua Kategori</option>
                                @foreach($categoriesList as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Product Table --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">No.</th>
                                    <th>Nama Produk</th>
                                    <th>Kategori</th>
                                    <th>Harga Dasar</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $product)
                                <tr>
                                    <td class="ps-4 text-muted">{{ $products->firstItem() + $loop->index }}</td>
                                    <td>
                                        <span class="fw-bold text-dark">{{ $product->name }}</span>
                                        @if($product->description)
                                            <small class="text-muted d-block">{{ Str::limit($product->description, 50) }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                            {{ $product->category->name ?? 'Tanpa Kategori' }}
                                        </span>
                                    </td>
                                    <td class="fw-bold text-success">Rp {{ number_format($product->base_price, 0, ',', '.') }}</td>
                                    <td>
                                        @if($product->is_active)
                                            <span class="badge bg-success bg-opacity-10 text-success">Aktif</span>
                                        @else
                                            <span class="badge bg-danger bg-opacity-10 text-danger">Tidak Aktif</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-primary me-1" wire:click="edit('{{ $product->id }}')">
                                            <i class="bi bi-pencil-fill"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="confirm('Apakah Anda yakin ingin menghapus produk ini?') || event.stopImmediatePropagation()"
                                                wire:click="delete('{{ $product->id }}')">
                                            <i class="bi bi-trash-fill"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-box2 fs-1 d-block mb-2 opacity-50"></i>
                                        Tidak ada produk ditemukan.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($products->hasPages())
                <div class="card-footer bg-white border-top-0">
                    {{ $products->links() }}
                </div>
                @endif
            </div>
        @else
            {{-- Topping Header Action --}}
            <div class="d-flex justify-content-end mb-3">
                <button type="button" class="btn btn-primary shadow-sm" wire:click="openModalTopping">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Topping
                </button>
            </div>
            {{-- Search - Topping --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" placeholder="Cari topping..." wire:model.live.debounce.300ms="search">
                    </div>
                </div>
            </div>

            {{-- Topping Table --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">No.</th>
                                    <th>Nama Topping</th>
                                    <th>Harga Tambahan</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($toppings as $topping)
                                <tr>
                                    <td class="ps-4 text-muted">{{ $toppings->firstItem() + $loop->index }}</td>
                                    <td>
                                        <span class="fw-bold text-dark">{{ $topping->name }}</span>
                                    </td>
                                    <td class="fw-bold text-primary">Rp {{ number_format($topping->price, 0, ',', '.') }}</td>
                                    <td>
                                        @if($topping->is_active)
                                            <span class="badge bg-success bg-opacity-10 text-success">Aktif</span>
                                        @else
                                            <span class="badge bg-danger bg-opacity-10 text-danger">Tidak Aktif</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-primary me-1" wire:click="editTopping('{{ $topping->id }}')">
                                            <i class="bi bi-pencil-fill"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="confirm('Apakah Anda yakin ingin menghapus topping ini?') || event.stopImmediatePropagation()"
                                                wire:click="deleteTopping('{{ $topping->id }}')">
                                            <i class="bi bi-trash-fill"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-egg-fried fs-1 d-block mb-2 opacity-50"></i>
                                        Tidak ada topping ditemukan.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($toppings->hasPages())
                <div class="card-footer bg-white border-top-0">
                    {{ $toppings->links() }}
                </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Category Modal --}}
    @if($isOpenCategory)
    <div class="modal-backdrop fade show" style="z-index: 1040;"></div>
    <div class="modal fade show d-block" tabindex="-1" style="z-index: 1050;" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-white border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="modal-title fw-bold text-warning">{{ $isEditCategory ? 'Edit Kategori' : 'Tambah Kategori Baru' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModalCategory" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="saveCategory">
                    <div class="modal-body px-4 pb-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nama Kategori <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('categoryName') is-invalid @enderror" wire:model="categoryName" placeholder="Contoh: Snack / Dessert / Drink">
                            @error('categoryName') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0 px-4 pb-4">
                        <button type="button" class="btn btn-light" wire:click="closeModalCategory">Batal</button>
                        <button type="submit" class="btn btn-warning text-white px-4">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Product Modal --}}
    @if($isOpen)
    <div class="modal-backdrop fade show" style="z-index: 1040;"></div>
    <div class="modal fade show d-block" tabindex="-1" style="z-index: 1050;" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-white border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="modal-title fw-bold text-success">{{ $isEdit ? 'Edit Produk' : 'Tambah Produk Baru' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="save">
                    <div class="modal-body px-4 pb-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nama Produk <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder="Contoh: Gabin Fla Keju">
                            @error('name') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select @error('category_id') is-invalid @enderror" wire:model="category_id">
                                <option value="">Pilih Kategori</option>
                                @foreach($categoriesList as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Harga Dasar <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control @error('base_price') is-invalid @enderror" wire:model="base_price" placeholder="0">
                            </div>
                            @error('base_price') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Deskripsi</label>
                            <textarea class="form-control" rows="3" wire:model="description" placeholder="Deskripsi opsional..."></textarea>
                        </div>

                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="product_active" wire:model="is_active">
                            <label class="form-check-label fw-bold" for="product_active">Aktif & Tampilkan di Kasir</label>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0 px-4 pb-4">
                        <button type="button" class="btn btn-light" wire:click="closeModal">Batal</button>
                        <button type="submit" class="btn btn-success px-4">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Topping Modal --}}
    @if($isOpenTopping)
    <div class="modal-backdrop fade show" style="z-index: 1040;"></div>
    <div class="modal fade show d-block" tabindex="-1" style="z-index: 1050;" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-white border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="modal-title fw-bold text-primary">{{ $isEditTopping ? 'Edit Topping' : 'Tambah Topping Baru' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModalTopping" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="saveTopping">
                    <div class="modal-body px-4 pb-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nama Topping <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('toppingName') is-invalid @enderror" wire:model="toppingName" placeholder="Contoh: Keju Parut / Meses">
                            @error('toppingName') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Harga Tambahan <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control @error('toppingPrice') is-invalid @enderror" wire:model="toppingPrice" placeholder="0">
                            </div>
                            @error('toppingPrice') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="topping_active" wire:model="toppingIsActive">
                            <label class="form-check-label fw-bold" for="topping_active">Aktif & Tampilkan di Kasir</label>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0 px-4 pb-4">
                        <button type="button" class="btn btn-light" wire:click="closeModalTopping">Batal</button>
                        <button type="submit" class="btn btn-primary px-4">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
