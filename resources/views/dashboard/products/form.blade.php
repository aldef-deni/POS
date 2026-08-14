@extends('layouts.app')

@section('title', $product->exists ? 'Ubah Produk' : 'Produk Baru')
@section('subtitle', $product->exists ? $product->name : 'ID, barcode, dan QR dibuat otomatis saat disimpan')

@section('content')

<form method="POST"
      action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
    @csrf
    @if ($product->exists) @method('PUT') @endif

    <div class="page-head">
        <div>
            <h1>{{ $product->exists ? 'Ubah Produk' : 'Produk Baru' }}</h1>
            <p class="muted mt-4">
                @if ($product->exists)
                    ID produk <span class="code-chip">{{ $product->sku }}</span>
                @else
                    ID berikutnya akan menjadi <span class="code-chip" data-sku-preview>{{ $skuPreview }}</span>
                @endif
            </p>
        </div>
        <div class="row g-8">
            <a href="{{ route('admin.products.index') }}" class="btn btn--ghost">Batal</a>
            <button type="submit" class="btn btn--primary">
                <x-icon name="check" size="16"/> Simpan Produk
            </button>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert--bad">
            <x-icon name="alert" size="17" class="alert__icon"/>
            <div>
                <div class="semi">Periksa kembali isian berikut:</div>
                <ul style="margin:6px 0 0;padding-left:18px">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="grid grid-2-1">
        <div class="stack g-16">
            {{-- Identity --}}
            <div class="card">
                <div class="card__head"><div class="card__title">Informasi Produk</div></div>
                <div class="card__body">
                    <div class="field">
                        <label class="field__label" for="name">Nama Produk <span class="field__req">*</span></label>
                        <input type="text" id="name" name="name" class="input"
                               value="{{ old('name', $product->name) }}" required autofocus
                               placeholder="mis. Kopi Susu Gula Aren">
                    </div>

                    <div class="grid grid-2">
                        <div class="field">
                            <label class="field__label" for="category_id">Kategori</label>
                            <select id="category_id" name="category_id" class="select">
                                <option value="">— Tanpa kategori —</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}"
                                        @selected(old('category_id', $product->category_id) == $category->id)>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="field__hint">Kode kategori dipakai dalam pola ID produk.</span>
                        </div>

                        <div class="field">
                            <label class="field__label" for="unit">Satuan <span class="field__req">*</span></label>
                            <input type="text" id="unit" name="unit" class="input"
                                   value="{{ old('unit', $product->unit ?: 'pcs') }}" required
                                   placeholder="pcs / cup / kg / box">
                        </div>
                    </div>

                    <div class="field" style="margin-bottom:0">
                        <label class="field__label" for="description">Deskripsi</label>
                        <textarea id="description" name="description" class="textarea"
                                  placeholder="Catatan internal atau keterangan produk">{{ old('description', $product->description) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Pricing --}}
            <div class="card">
                <div class="card__head">
                    <div>
                        <div class="card__title">Harga &amp; Margin</div>
                        <div class="card__sub">Modal disimpan sebagai snapshot pada setiap transaksi</div>
                    </div>
                </div>
                <div class="card__body">
                    <div class="grid grid-2">
                        <div class="field">
                            <label class="field__label" for="cost_price">Harga Modal <span class="field__req">*</span></label>
                            <div class="input-group">
                                <span class="input-group__addon">{{ $tenant?->currency_symbol ?? 'Rp' }}</span>
                                <input type="number" step="0.01" min="0" id="cost_price" name="cost_price" class="input"
                                       value="{{ old('cost_price', (float) $product->cost_price) }}" required>
                            </div>
                        </div>

                        <div class="field">
                            <label class="field__label" for="price">Harga Jual <span class="field__req">*</span></label>
                            <div class="input-group">
                                <span class="input-group__addon">{{ $tenant?->currency_symbol ?? 'Rp' }}</span>
                                <input type="number" step="0.01" min="0" id="price" name="price" class="input"
                                       value="{{ old('price', (float) $product->price) }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-2">
                        <div class="field">
                            <label class="field__label" for="wholesale_price">Harga Grosir</label>
                            <div class="input-group">
                                <span class="input-group__addon">{{ $tenant?->currency_symbol ?? 'Rp' }}</span>
                                <input type="number" step="0.01" min="0" id="wholesale_price" name="wholesale_price"
                                       class="input" value="{{ old('wholesale_price', $product->wholesale_price) }}">
                            </div>
                            <span class="field__hint">Kosongkan bila tidak ada harga grosir.</span>
                        </div>

                        <div class="field">
                            <label class="field__label" for="min_wholesale_qty">Minimum Qty Grosir</label>
                            <input type="number" min="0" id="min_wholesale_qty" name="min_wholesale_qty" class="input"
                                   value="{{ old('min_wholesale_qty', $product->min_wholesale_qty ?: 0) }}">
                            <span class="field__hint">Harga grosir aktif otomatis di kasir bila qty tercapai.</span>
                        </div>
                    </div>

                    <label class="check">
                        <input type="checkbox" name="tax_exempt" value="1"
                               @checked(old('tax_exempt', $product->tax_exempt))>
                        <span>
                            <span class="check__text">Bebas pajak</span>
                            <span class="check__hint">Produk ini tidak dikenakan PPN saat transaksi.</span>
                        </span>
                    </label>
                </div>
            </div>

            {{-- Stock --}}
            <div class="card">
                <div class="card__head"><div class="card__title">Stok</div></div>
                <div class="card__body">
                    <label class="check mb-16">
                        <input type="checkbox" name="track_stock" value="1"
                               @checked(old('track_stock', $product->exists ? $product->track_stock : true))>
                        <span>
                            <span class="check__text">Lacak stok produk ini</span>
                            <span class="check__hint">Matikan untuk jasa atau produk tanpa persediaan.</span>
                        </span>
                    </label>

                    <div class="grid grid-2" style="margin-bottom:0">
                        <div class="field" style="margin-bottom:0">
                            <label class="field__label" for="stock">
                                {{ $product->exists ? 'Stok Saat Ini' : 'Stok Awal' }}
                            </label>
                            <input type="number" step="0.001" min="0" id="stock" name="stock" class="input"
                                   value="{{ old('stock', $product->exists ? (float) $product->stock : 0) }}"
                                   @disabled($product->exists)>
                            <span class="field__hint">
                                @if ($product->exists)
                                    Stok hanya dapat diubah lewat menu Stok &amp; Inventori agar riwayatnya tercatat.
                                @else
                                    Akan dicatat sebagai penerimaan stok pertama.
                                @endif
                            </span>
                        </div>

                        <div class="field" style="margin-bottom:0">
                            <label class="field__label" for="min_stock">Stok Minimum</label>
                            <input type="number" step="0.001" min="0" id="min_stock" name="min_stock" class="input"
                                   value="{{ old('min_stock', (float) $product->min_stock) }}">
                            <span class="field__hint">Peringatan muncul saat stok mencapai angka ini.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Side column --}}
        <div class="stack g-16">
            <div class="card">
                <div class="card__head"><div class="card__title">Status</div></div>
                <div class="card__body">
                    <label class="switch mb-16">
                        <input type="checkbox" name="is_active" value="1"
                               @checked(old('is_active', $product->exists ? $product->is_active : true))>
                        <span class="switch__track"></span>
                        <span class="check__text">Aktif &amp; dapat dijual</span>
                    </label>

                    <label class="switch">
                        <input type="checkbox" name="is_favorite" value="1"
                               @checked(old('is_favorite', $product->is_favorite))>
                        <span class="switch__track"></span>
                        <span class="check__text">Tampilkan di awal grid kasir</span>
                    </label>
                </div>
            </div>

            <div class="card">
                <div class="card__head">
                    <div>
                        <div class="card__title">Identitas &amp; Barcode</div>
                        <div class="card__sub">Dibuat otomatis, tetapi bisa ditimpa manual</div>
                    </div>
                </div>
                <div class="card__body">
                    <div class="alert alert--info" style="margin-bottom:16px">
                        <x-icon name="barcode" size="17" class="alert__icon"/>
                        <div>
                            <div class="semi">Pola aktif: {{ $skuPattern }}</div>
                            <div class="tiny mt-4">Kosongkan kolom di bawah agar sistem yang membuatkan.</div>
                        </div>
                    </div>

                    <div class="field">
                        <label class="field__label" for="sku">ID Produk / SKU</label>
                        <input type="text" id="sku" name="sku" class="input mono"
                               value="{{ old('sku', $product->sku) }}"
                               placeholder="{{ $skuPreview }}">
                    </div>

                    <div class="field">
                        <label class="field__label" for="barcode_type">Jenis Barcode</label>
                        <select id="barcode_type" name="barcode_type" class="select">
                            <option value="C128" @selected(old('barcode_type', $product->barcode_type) === 'C128')>
                                Code 128 (fleksibel, huruf &amp; angka)
                            </option>
                            <option value="EAN13" @selected(old('barcode_type', $product->barcode_type) === 'EAN13')>
                                EAN-13 (13 digit angka)
                            </option>
                        </select>
                    </div>

                    <div class="field" style="margin-bottom:0">
                        <label class="field__label" for="barcode_value">Nilai Barcode</label>
                        <input type="text" id="barcode_value" name="barcode_value" class="input mono"
                               value="{{ old('barcode_value', $product->barcode_value) }}"
                               placeholder="Mengikuti ID produk">
                        <span class="field__hint">QR akan berisi ID produk agar bisa langsung dipindai di kasir.</span>
                    </div>
                </div>
            </div>

            @if ($product->exists)
                <div class="card">
                    <div class="card__body t-center">
                        <div class="tiny subtle upper mb-8">Pratinjau Barcode</div>
                        <img src="{{ route('media.barcode', $product) }}" alt="Barcode {{ $product->sku }}"
                             style="max-height:64px">
                        <div class="mono tiny mt-8">{{ $product->barcode_value }}</div>

                        <div class="divider"></div>

                        <div class="tiny subtle upper mb-8">Pratinjau QR</div>
                        <img src="{{ route('media.qr', $product) }}" alt="QR {{ $product->sku }}" style="width:112px">
                    </div>
                </div>
            @endif
        </div>
    </div>
</form>

@endsection
