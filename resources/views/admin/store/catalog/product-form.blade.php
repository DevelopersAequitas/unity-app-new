@extends('admin.layouts.app')

@section('title', $isEdit ? 'Edit Product: ' . $product->name : 'Create New Product — Peers Store')

@section('content')
<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-box me-2 text-primary"></i>{{ $isEdit ? 'Edit Product: ' . $product->name : 'Add New Merchandise Product' }}
            </h1>
            <p class="text-muted small mb-0">Configure product specifications, coin pricing, delivery modes, eligibility rules, and inventory variants.</p>
        </div>
        <a href="{{ route('admin.store.products.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Catalog
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0 small">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="row">
        <!-- Main Form Column -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="bi bi-info-circle me-2"></i>Product Specifications & Rules</h6>
                </div>
                <div class="card-body">
                    <form action="{{ $isEdit ? route('admin.store.products.update', $product->id) : route('admin.store.products.store') }}" method="POST">
                        @csrf
                        @if($isEdit)
                            @method('PUT')
                        @endif

                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label small fw-bold">Product Name *</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}" placeholder="e.g. Peers Official Polo T-Shirt" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Master SKU Code *</label>
                                <input type="text" name="sku" class="form-control text-uppercase" value="{{ old('sku', $product->sku) }}" placeholder="e.g. POLO-001" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Category *</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select Category</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Product Type *</label>
                                <select name="type" class="form-select" required>
                                    <option value="PHYSICAL" {{ old('type', $product->type) === 'PHYSICAL' ? 'selected' : '' }}>PHYSICAL (Shipped or Hub Pickup)</option>
                                    <option value="DIGITAL" {{ old('type', $product->type) === 'DIGITAL' ? 'selected' : '' }}>DIGITAL (Signed Asset Download)</option>
                                    <option value="COURSE" {{ old('type', $product->type) === 'COURSE' ? 'selected' : '' }}>COURSE (Masterclass Video Stream)</option>
                                    <option value="MEMBERSHIP" {{ old('type', $product->type) === 'MEMBERSHIP' ? 'selected' : '' }}>MEMBERSHIP (VIP Store Pass)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-success">Redemption Price (in Unity Coins) *</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold text-success">Coins</span>
                                    <input type="number" name="price_coins" class="form-control fw-bold" value="{{ old('price_coins', $product->price_coins) }}" min="0" required>
                                </div>
                                <div class="form-text extra-small">Coins debited from peer wallet upon order confirmation.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Internal Unit Cost (INR) <span class="badge bg-secondary">Finance Only</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">₹</span>
                                    <input type="number" step="0.01" name="unit_cost_inr" class="form-control" value="{{ old('unit_cost_inr', $product->unit_cost_inr) }}" min="0">
                                </div>
                                <div class="form-text extra-small text-danger">Internal cost only. Never shown to peers.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Short Summary / Tagline</label>
                            <input type="text" name="short_description" class="form-control" value="{{ old('short_description', $product->short_description) }}" placeholder="e.g. 100% Breathable Pique Cotton with embroidered golden crest">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Full Detailed Description (HTML Supported)</label>
                            <textarea name="description" class="form-control font-monospace small" rows="5" placeholder="<p>Full product description, materials, wash care instructions, etc.</p>">{{ old('description', $product->description) }}</textarea>
                        </div>

                        <!-- Delivery Modes & Limits -->
                        <div class="row g-3 mb-3 p-3 bg-light rounded border">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Permitted Delivery Modes *</label>
                                @php
                                    $modes = (array)(old('delivery_modes', $product->delivery_modes ?? ['DELIVERY', 'PICKUP']));
                                @endphp
                                <div class="d-flex gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="delivery_modes[]" value="DELIVERY" id="modeDelivery" {{ in_array('DELIVERY', $modes) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="modeDelivery">Home Courier Delivery</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="delivery_modes[]" value="PICKUP" id="modePickup" {{ in_array('PICKUP', $modes) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="modePickup">Central Hub Pickup</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Min Qty / Order</label>
                                <input type="number" name="min_quantity_per_order" class="form-control form-control-sm" value="{{ old('min_quantity_per_order', $product->min_quantity_per_order ?? 1) }}" min="1">
                                <small class="text-muted d-block" style="font-size: 10px;">Min units allowed</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Max Qty / Order</label>
                                <input type="number" name="max_quantity_per_order" class="form-control form-control-sm" value="{{ old('max_quantity_per_order', $product->max_quantity_per_order ?? 5) }}" min="1">
                                <small class="text-muted d-block" style="font-size: 10px;">Max units allowed</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Max Qty / Peer Month</label>
                                <input type="number" name="max_quantity_per_peer_month" class="form-control form-control-sm" value="{{ old('max_quantity_per_peer_month', $product->max_quantity_per_peer_month ?? 10) }}" min="1">
                                <small class="text-muted d-block" style="font-size: 10px;">Monthly limit</small>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="return_allowed" value="1" id="returnAllowed" {{ old('return_allowed', $product->return_allowed ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label small fw-bold" for="returnAllowed">7-Day Return Allowed</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="customised" value="1" id="customised" {{ old('customised', $product->customised) ? 'checked' : '' }}>
                                    <label class="form-check-label small fw-bold" for="customised">Customised (Non-returnable)</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="isFeatured" {{ old('is_featured', $product->is_featured) ? 'checked' : '' }}>
                                    <label class="form-check-label small fw-bold text-primary" for="isFeatured">Featured Collection</label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Catalog Status *</label>
                                <select name="status" class="form-select fw-bold">
                                    <option value="ACTIVE" {{ old('status', $product->status) === 'ACTIVE' ? 'selected' : '' }}>ACTIVE (Visible to Peers)</option>
                                    <option value="DRAFT" {{ old('status', $product->status) === 'DRAFT' ? 'selected' : '' }}>DRAFT (Under Preparation)</option>
                                    <option value="HIDDEN" {{ old('status', $product->status) === 'HIDDEN' ? 'selected' : '' }}>HIDDEN (Private)</option>
                                    <option value="OUT_OF_STOCK" {{ old('status', $product->status) === 'OUT_OF_STOCK' ? 'selected' : '' }}>OUT OF STOCK</option>
                                    <option value="ARCHIVED" {{ old('status', $product->status) === 'ARCHIVED' ? 'selected' : '' }}>ARCHIVED (Retired)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Base Stock Quantity</label>
                                <input type="number" name="stock_qty" class="form-control" value="{{ old('stock_qty', $product->stock_qty ?? 50) }}" min="0">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                <i class="bi bi-save me-1"></i> {{ $isEdit ? 'Save Changes' : 'Create Product' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar: Variants & Images (When Editing) -->
        <div class="col-lg-4">
            @if($isEdit)
            <!-- Images Gallery Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-images me-2 text-primary"></i>Media Gallery (Up to 6)</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary py-0" data-bs-toggle="modal" data-bs-target="#addImageModal">
                        + Add Image
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        @forelse($product->images as $img)
                        <div class="col-6 position-relative">
                            <div class="border rounded p-1 text-center bg-light">
                                <img src="{{ $img->image_url }}" alt="Image" class="img-fluid rounded" style="height: 100px; object-fit: cover; width: 100%;">
                                <div class="mt-1 d-flex justify-content-between align-items-center">
                                    @if($img->is_primary)
                                        <span class="badge bg-primary extra-small">Primary</span>
                                    @else
                                        <span></span>
                                    @endif
                                    <form action="{{ route('admin.store.products.images.delete', $img->id) }}" method="POST" onsubmit="return confirm('Delete image?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link text-danger p-0 extra-small"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="col-12 text-center py-3 text-muted small">No images uploaded yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- SKU Variants Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-layers me-2 text-success"></i>SKU Variants</h6>
                    <button type="button" class="btn btn-sm btn-outline-success py-0" data-bs-toggle="modal" data-bs-target="#addVariantModal">
                        + Add Variant
                    </button>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($product->variants as $var)
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div>
                                <div class="fw-bold text-dark small">{{ $var->name }}</div>
                                <div class="text-muted extra-small">SKU: <code>{{ $var->sku }}</code> | Stock: <strong class="text-success">{{ $var->stock_quantity }}</strong></div>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <span class="badge bg-light text-dark border extra-small">{{ number_format($var->price_coins ?? $product->price_coins) }} Coins</span>
                                <form action="{{ route('admin.store.products.variants.delete', $var->id) }}" method="POST" onsubmit="return confirm('Delete variant?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-link text-danger p-0 ms-2"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </li>
                        @empty
                        <li class="list-group-item text-center py-3 text-muted small">No variants configured.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
            @else
            <div class="alert alert-info border-0 shadow-sm">
                <i class="bi bi-info-circle me-1"></i> Once the basic product is created, you can attach multiple images and SKU variants (sizes, colors, stock numbers).
            </div>
            @endif
        </div>
    </div>
</div>

@if($isEdit)
<!-- Add Image Modal -->
<div class="modal fade" id="addImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('admin.store.products.images.store', $product->id) }}" method="POST" enctype="multipart/form-data" id="addProductImageForm">
                @csrf
                <input type="hidden" name="image_data" id="prodImgData">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="bi bi-card-image me-2 text-primary"></i>Add Product Image
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Source Selection Nav -->
                    <ul class="nav nav-pills nav-fill mb-3 bg-light p-1 rounded-3" id="prodImageSourceTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-2 fw-semibold" id="prodTabDeviceBtn" type="button" onclick="switchProdImgMode('device')">
                                <i class="bi bi-laptop me-1"></i> Browse Device / Upload
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2 fw-semibold" id="prodTabUrlBtn" type="button" onclick="switchProdImgMode('url')">
                                <i class="bi bi-link-45deg me-1"></i> Paste Image Web URL
                            </button>
                        </li>
                    </ul>

                    <!-- Device Upload Dropzone -->
                    <div id="prodDeviceBox">
                        <div class="img-adjuster-dropzone p-4 text-center mb-3" id="prodDropzone" onclick="document.getElementById('prodImgFileInput').click()">
                            <i class="bi bi-cloud-arrow-up display-5 text-primary d-block mb-2"></i>
                            <h6 class="fw-bold mb-1">Click to browse or drag & drop image here</h6>
                            <p class="text-muted extra-small mb-2">Supports JPG, PNG, WEBP, GIF up to 10MB</p>
                            <button type="button" class="btn btn-sm btn-outline-primary px-3 rounded-pill">
                                <i class="bi bi-folder2-open me-1"></i> Browse from Device
                            </button>
                            <input type="file" id="prodImgFileInput" name="image_file" accept="image/*" class="d-none">
                        </div>
                    </div>

                    <!-- URL Input Box (Hidden by default) -->
                    <div id="prodUrlBox" class="mb-3 d-none">
                        <label class="form-label small fw-bold">Image Web URL</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-link-45deg"></i></span>
                            <input type="url" name="image_url" id="prodImgUrlInput" class="form-control" placeholder="https://images.unsplash.com/photo-...">
                            <button class="btn btn-outline-primary" type="button" onclick="loadProdImgFromUrl()">
                                <i class="bi bi-box-arrow-in-down me-1"></i> Load & Adjust
                            </button>
                        </div>
                        <div class="form-text extra-small">Paste image URL and click "Load & Adjust" to preview and adjust.</div>
                    </div>

                    <!-- Live Adjustment Studio (Cropper + Controls) -->
                    <div id="prodCropperStudio" class="d-none border rounded-3 p-3 bg-light mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-dark">
                                <i class="bi bi-crop me-1 text-primary"></i> Adjust & Crop Image Before Upload
                            </span>
                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 extra-small" onclick="window.prodCropperInstance.clear()">
                                <i class="bi bi-x-circle me-1"></i> Choose Different Image
                            </button>
                        </div>

                        <div class="row g-3">
                            <!-- Cropper Canvas -->
                            <div class="col-md-8">
                                <div class="cropper-container-box">
                                    <img id="prodImageEl" src="" alt="To Crop" style="max-width: 100%; display: block;">
                                </div>
                            </div>

                            <!-- Controls & Live Preview -->
                            <div class="col-md-4 d-flex flex-column justify-content-between">
                                <div>
                                    <label class="form-label extra-small fw-bold text-muted text-uppercase mb-1">Live Store Preview</label>
                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <div class="crop-preview-box shadow-sm">
                                            <img id="prodPreviewBox" src="" alt="Preview">
                                        </div>
                                        <div class="small">
                                            <div class="fw-semibold text-dark">Product View</div>
                                            <div class="text-muted extra-small">Square format</div>
                                            <span class="badge bg-success-subtle text-success extra-small mt-1">Adjusted & Ready</span>
                                        </div>
                                    </div>

                                    <!-- Aspect Ratio Presets -->
                                    <label class="form-label extra-small fw-bold text-muted text-uppercase mb-1">Aspect Ratio</label>
                                    <div class="btn-group btn-group-sm w-100 mb-3" role="group">
                                        <button type="button" class="btn btn-outline-secondary active" id="btnRatio1" onclick="setProdRatio(1, this)">1:1</button>
                                        <button type="button" class="btn btn-outline-secondary" id="btnRatio43" onclick="setProdRatio(4/3, this)">4:3</button>
                                        <button type="button" class="btn btn-outline-secondary" id="btnRatio169" onclick="setProdRatio(16/9, this)">16:9</button>
                                        <button type="button" class="btn btn-outline-secondary" id="btnRatioFree" onclick="setProdRatio(NaN, this)">Free</button>
                                    </div>

                                    <!-- Zoom & Rotate Controls -->
                                    <label class="form-label extra-small fw-bold text-muted text-uppercase mb-1">Zoom & Rotate</label>
                                    <div class="d-flex gap-1 mb-2">
                                        <button type="button" class="btn btn-sm btn-light border flex-fill" title="Zoom Out" onclick="window.prodCropperInstance.zoom(-0.1)">
                                            <i class="bi bi-zoom-out"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-light border flex-fill" title="Zoom In" onclick="window.prodCropperInstance.zoom(0.1)">
                                            <i class="bi bi-zoom-in"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-light border flex-fill" title="Rotate Left (-90°)" onclick="window.prodCropperInstance.rotate(-90)">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-light border flex-fill" title="Rotate Right (+90°)" onclick="window.prodCropperInstance.rotate(90)">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    </div>
                                    <div class="d-flex gap-1 mb-2">
                                        <button type="button" class="btn btn-sm btn-light border flex-fill" title="Flip Horizontal" onclick="window.prodCropperInstance.flipH()">
                                            <i class="bi bi-symmetry-vertical"></i> Flip H
                                        </button>
                                        <button type="button" class="btn btn-sm btn-light border flex-fill" title="Reset" onclick="window.prodCropperInstance.reset()">
                                            <i class="bi bi-arrow-repeat"></i> Reset
                                        </button>
                                    </div>
                                </div>
                                <div class="text-muted extra-small mt-2">
                                    <i class="bi bi-arrows-move me-1"></i> Drag to reposition or pan image inside box.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Primary Switch -->
                    <div class="form-check form-switch p-3 bg-light rounded-3 mt-3">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="is_primary" id="isPrimaryCheck" value="1">
                        <label class="form-check-label small fw-bold" for="isPrimaryCheck">
                            Set as Primary Display Image (Main thumbnail for product)
                        </label>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm" id="btnSubmitProdImage">
                        <i class="bi bi-cloud-arrow-up me-1"></i> Upload & Add Image
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Variant Modal -->
<div class="modal fade" id="addVariantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.store.products.variants.store', $product->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Add SKU Variant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Variant Title *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Navy Blue - Large (L)" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Variant SKU *</label>
                        <input type="text" name="sku" class="form-control text-uppercase" placeholder="e.g. {{ $product->sku }}-BLU-L" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Stock Units *</label>
                            <input type="number" name="stock_quantity" class="form-control" value="50" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Low-Stock Alert</label>
                            <input type="number" name="low_stock_threshold" class="form-control" value="5" min="0">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Price Override (Coins)</label>
                            <input type="number" name="price_coins" class="form-control" value="{{ $product->price_coins }}" min="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="status" class="form-select">
                                <option value="ACTIVE" selected>ACTIVE</option>
                                <option value="INACTIVE">INACTIVE</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">Add Variant</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/cropper.min.css') }}">
<style>
.img-adjuster-dropzone {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    background-color: #f8fafc;
    transition: all 0.2s ease;
    cursor: pointer;
}
.img-adjuster-dropzone:hover, .img-adjuster-dropzone.border-primary {
    border-color: #3b82f6 !important;
    background-color: #eff6ff !important;
}
.cropper-container-box {
    height: 340px;
    background-color: #0f172a;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
.crop-preview-box {
    width: 100px;
    height: 100px;
    border-radius: 8px;
    border: 2px solid #cbd5e1;
    overflow: hidden;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.crop-preview-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/cropper.min.js') }}"></script>
<script src="{{ asset('js/store-image-adjuster.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    window.prodCropperInstance = StoreImageAdjuster.init({
        id: 'product_image',
        fileInputId: 'prodImgFileInput',
        urlInputId: 'prodImgUrlInput',
        hiddenDataId: 'prodImgData',
        dropzoneId: 'prodDropzone',
        cropperContainerId: 'prodCropperStudio',
        imageElementId: 'prodImageEl',
        previewBoxId: 'prodPreviewBox',
        aspectRatio: 1,
        formId: 'addProductImageForm'
    });
});

function switchProdImgMode(mode) {
    const devBox = document.getElementById('prodDeviceBox');
    const urlBox = document.getElementById('prodUrlBox');
    const tabDev = document.getElementById('prodTabDeviceBtn');
    const tabUrl = document.getElementById('prodTabUrlBtn');

    if (mode === 'device') {
        if (devBox) devBox.classList.remove('d-none');
        if (urlBox) urlBox.classList.add('d-none');
        if (tabDev) tabDev.classList.add('active');
        if (tabUrl) tabUrl.classList.remove('active');
    } else {
        if (devBox) devBox.classList.add('d-none');
        if (urlBox) urlBox.classList.remove('d-none');
        if (tabDev) tabDev.classList.remove('active');
        if (tabUrl) tabUrl.classList.add('active');
    }
}

function loadProdImgFromUrl() {
    const url = document.getElementById('prodImgUrlInput').value.trim();
    if (!url) {
        alert('Please enter a valid image URL first.');
        return;
    }
    if (window.prodCropperInstance) {
        window.prodCropperInstance.loadFromUrl(url);
    }
}

function setProdRatio(ratio, btn) {
    if (window.prodCropperInstance) {
        window.prodCropperInstance.setRatio(ratio);
    }
    const group = btn.closest('.btn-group');
    if (group) {
        group.querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }
}
</script>
@endpush
