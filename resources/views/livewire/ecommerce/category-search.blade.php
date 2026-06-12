<div>
    <div class="d-flex align-items-center justify-content-between mb-6">
        <h2 class="fw-bolder fs-2x mb-0" style="color: #1a1a2e;">Shop by Category</h2>
        <div style="max-width: 300px;">
            <input type="text" class="form-control form-control-sm" placeholder="Search categories..." wire:model.live.debounce.300ms="search" style="border-radius: 8px;">
        </div>
    </div>
    <div class="row">
        @forelse($categories as $category)
            <div class="col-md-3 col-xl-2 mb-4 px-md-2">
                <a href="{{ route('shops.products.index', ['category' => $category->id]) }}" class="card qb-card qb-category-card h-100 text-decoration-none">
                    @if($category->image)
                        <div style="height: 100px; overflow: hidden; border-radius: 8px 8px 0 0;">
                            <img src="{{ asset($category->image) }}" alt="{{ $category->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    @endif
                    <div class="card-body">
                        <span class="fs-5 fw-semibold d-block" style="color: #1a1a2e;">
                            {{ ucwords(strtolower($category->name)) }}
                        </span>
                        @if($category->description)
                            <small class="text-muted d-block mt-1" style="line-height: 1.3;">{{ Str::limit($category->description, 60) }}</small>
                        @endif
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12">
                <p class="text-muted fs-6">No categories found.</p>
            </div>
        @endforelse
    </div>
</div>
