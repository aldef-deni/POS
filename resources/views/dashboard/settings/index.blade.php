@extends('layouts.app')

@section('title', 'Pengaturan')
@section('subtitle', 'Profil toko, format struk, dan mekanisme ID produk')

@section('content')

<div class="page-head">
    <div>
        <h1>Pengaturan</h1>
        <p class="muted mt-4">Perubahan berlaku langsung untuk seluruh terminal kasir.</p>
    </div>
</div>

<div data-tabs>
    <div class="tabs">
        <button type="button" class="tab is-active" data-tab="toko">Profil Toko</button>
        <button type="button" class="tab" data-tab="struk">Format Struk</button>
        <button type="button" class="tab" data-tab="id-produk">Mekanisme ID Produk</button>
    </div>

    {{-- ============================ Store profile ============================ --}}
    <div class="tab-panel is-active" data-tab-panel="toko">
        <form method="POST" action="{{ route('admin.settings.store') }}" enctype="multipart/form-data">
            @csrf @method('PUT')

            <div class="grid grid-2-1">
                <div class="stack g-16">
                    <div class="card">
                        <div class="card__head"><div class="card__title">Identitas Toko</div></div>
                        <div class="card__body">
                            <div class="field">
                                <label class="field__label">Nama Toko <span class="field__req">*</span></label>
                                <input type="text" name="name" class="input" value="{{ old('name', $tenant->name) }}" required>
                            </div>

                            <div class="grid grid-2">
                                <div class="field">
                                    <label class="field__label">Nama Badan Usaha</label>
                                    <input type="text" name="legal_name" class="input" value="{{ old('legal_name', $tenant->legal_name) }}">
                                </div>
                                <div class="field">
                                    <label class="field__label">Jenis Usaha</label>
                                    <input type="text" name="business_type" class="input"
                                           value="{{ old('business_type', $tenant->business_type) }}" placeholder="Kafe, Retail, Minimarket…">
                                </div>
                            </div>

                            <div class="field">
                                <label class="field__label">Alamat</label>
                                <textarea name="address" class="textarea">{{ old('address', $tenant->address) }}</textarea>
                            </div>

                            <div class="grid grid-3">
                                <div class="field">
                                    <label class="field__label">Kota</label>
                                    <input type="text" name="city" class="input" value="{{ old('city', $tenant->city) }}">
                                </div>
                                <div class="field">
                                    <label class="field__label">Telepon</label>
                                    <input type="text" name="phone" class="input" value="{{ old('phone', $tenant->phone) }}">
                                </div>
                                <div class="field">
                                    <label class="field__label">Email</label>
                                    <input type="email" name="email" class="input" value="{{ old('email', $tenant->email) }}">
                                </div>
                            </div>

                            <div class="grid grid-2" style="margin-bottom:0">
                                <div class="field" style="margin-bottom:0">
                                    <label class="field__label">Website</label>
                                    <input type="text" name="website" class="input" value="{{ old('website', $tenant->website) }}">
                                </div>
                                <div class="field" style="margin-bottom:0">
                                    <label class="field__label">NPWP</label>
                                    <input type="text" name="tax_number" class="input" value="{{ old('tax_number', $tenant->tax_number) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card__head">
                            <div>
                                <div class="card__title">Pajak &amp; Pembulatan</div>
                                <div class="card__sub">Berlaku otomatis pada setiap transaksi kasir</div>
                            </div>
                        </div>
                        <div class="card__body">
                            <label class="switch mb-16">
                                <input type="checkbox" name="tax_enabled" value="1" @checked(old('tax_enabled', $tenant->tax_enabled))>
                                <span class="switch__track"></span>
                                <span class="check__text">Kenakan PPN pada transaksi</span>
                            </label>

                            <div class="grid grid-2">
                                <div class="field">
                                    <label class="field__label">Persentase PPN</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" max="100" name="tax_percent" class="input"
                                               value="{{ old('tax_percent', (float) $tenant->tax_percent) }}">
                                        <span class="input-group__addon" style="border:1px solid var(--border-strong);border-left:0;border-radius:0 var(--r-sm) var(--r-sm) 0">%</span>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field__label">Biaya Layanan</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" max="100" name="service_charge_percent" class="input"
                                               value="{{ old('service_charge_percent', (float) $tenant->service_charge_percent) }}">
                                        <span class="input-group__addon" style="border:1px solid var(--border-strong);border-left:0;border-radius:0 var(--r-sm) var(--r-sm) 0">%</span>
                                    </div>
                                </div>
                            </div>

                            <label class="check mb-16">
                                <input type="checkbox" name="tax_inclusive" value="1" @checked(old('tax_inclusive', $tenant->tax_inclusive))>
                                <span>
                                    <span class="check__text">Harga sudah termasuk pajak</span>
                                    <span class="check__hint">Pajak ditampilkan sebagai informasi, bukan tambahan.</span>
                                </span>
                            </label>

                            <div class="grid grid-2" style="margin-bottom:0">
                                <div class="field" style="margin-bottom:0">
                                    <label class="field__label">Pembulatan Total</label>
                                    <select name="rounding_mode" class="select">
                                        <option value="none" @selected($tenant->rounding_mode === 'none')>Tanpa pembulatan</option>
                                        <option value="nearest_100" @selected($tenant->rounding_mode === 'nearest_100')>Ke 100 terdekat</option>
                                        <option value="nearest_500" @selected($tenant->rounding_mode === 'nearest_500')>Ke 500 terdekat</option>
                                        <option value="nearest_1000" @selected($tenant->rounding_mode === 'nearest_1000')>Ke 1.000 terdekat</option>
                                    </select>
                                </div>
                                <div class="field" style="margin-bottom:0">
                                    <label class="field__label">Simbol Mata Uang</label>
                                    <input type="text" name="currency_symbol" class="input"
                                           value="{{ old('currency_symbol', $tenant->currency_symbol) }}" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stack g-16">
                    <div class="card">
                        <div class="card__head"><div class="card__title">Logo Toko</div></div>
                        <div class="card__body t-center">
                            @if ($tenant->logoUrl())
                                <img src="{{ $tenant->logoUrl() }}" alt="Logo" style="max-height:88px" class="mb-12">
                            @else
                                <div class="brand-mark" style="width:72px;height:72px;font-size:24px;margin:0 auto 12px">
                                    {{ mb_substr($tenant->name, 0, 2) }}
                                </div>
                            @endif

                            <input type="file" name="logo" accept="image/*" class="input" data-file-name="#logo-name">
                            <div class="tiny subtle mt-8" id="logo-name">PNG/JPG, maksimal 2 MB. Tampil di struk.</div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card__head"><div class="card__title">Inventori</div></div>
                        <div class="card__body">
                            <div class="field">
                                <label class="field__label">Ambang Stok Menipis</label>
                                <input type="number" min="0" name="low_stock_threshold" class="input"
                                       value="{{ old('low_stock_threshold', $tenant->low_stock_threshold) }}">
                                <span class="field__hint">Dipakai sebagai penanda default pada terminal kasir.</span>
                            </div>

                            <label class="check" style="margin:0">
                                <input type="checkbox" name="allow_negative_stock" value="1"
                                       @checked(old('allow_negative_stock', $tenant->allow_negative_stock))>
                                <span>
                                    <span class="check__text">Izinkan stok minus</span>
                                    <span class="check__hint">Kasir tetap bisa menjual meski stok sistem habis.</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn--primary btn--lg btn--block">
                        <x-icon name="check" size="16"/> Simpan Profil Toko
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- ============================ Receipt ============================ --}}
    <div class="tab-panel" data-tab-panel="struk">
        <form method="POST" action="{{ route('admin.settings.receipt') }}">
            @csrf @method('PUT')

            <div class="grid grid-2-1">
                <div class="card">
                    <div class="card__head">
                        <div>
                            <div class="card__title">Format Struk</div>
                            <div class="card__sub">Berlaku untuk cetakan dari terminal kasir</div>
                        </div>
                    </div>
                    <div class="card__body">
                        <div class="field">
                            <label class="field__label">Ukuran Kertas</label>
                            <select name="receipt_paper" class="select">
                                <option value="80mm" @selected($tenant->receipt_paper === '80mm')>Termal 80 mm (umum)</option>
                                <option value="58mm" @selected($tenant->receipt_paper === '58mm')>Termal 58 mm (kecil)</option>
                                <option value="a4" @selected($tenant->receipt_paper === 'a4')>A4 (invoice)</option>
                            </select>
                        </div>

                        <div class="field">
                            <label class="field__label">Teks Kepala Struk</label>
                            <textarea name="receipt_header" class="textarea"
                                      placeholder="mis. Terima kasih telah berbelanja">{{ old('receipt_header', $tenant->receipt_header) }}</textarea>
                        </div>

                        <div class="field">
                            <label class="field__label">Teks Kaki Struk</label>
                            <textarea name="receipt_footer" class="textarea"
                                      placeholder="mis. Barang yang sudah dibeli dapat ditukar dalam 3 hari">{{ old('receipt_footer', $tenant->receipt_footer) }}</textarea>
                        </div>

                        <label class="switch mb-16">
                            <input type="checkbox" name="receipt_show_logo" value="1" @checked($tenant->receipt_show_logo)>
                            <span class="switch__track"></span>
                            <span class="check__text">Tampilkan logo pada struk</span>
                        </label>

                        <label class="switch">
                            <input type="checkbox" name="receipt_show_qr" value="1" @checked($tenant->receipt_show_qr)>
                            <span class="switch__track"></span>
                            <span class="check__text">Tampilkan QR verifikasi transaksi</span>
                        </label>
                    </div>
                    <div class="card__foot">
                        <button type="submit" class="btn btn--primary"><x-icon name="check" size="16"/> Simpan Format Struk</button>
                    </div>
                </div>

                <div class="card">
                    <div class="card__head"><div class="card__title">Pratinjau</div></div>
                    <div class="card__body">
                        <div style="background:#fff;color:#111;padding:16px;border-radius:var(--r-md);border:1px solid var(--border);font-family:var(--font-mono);font-size:11px;line-height:1.5">
                            <div style="text-align:center">
                                <div style="font-weight:700;font-size:13px">{{ strtoupper($tenant->name) }}</div>
                                <div>{{ $tenant->address }}</div>
                                <div>{{ $tenant->phone }}</div>
                            </div>
                            <div style="border-top:1px dashed #999;margin:8px 0"></div>
                            <div>No : INV-{{ now()->format('ymd') }}-0001</div>
                            <div>Kasir : {{ auth('web')->user()->name }}</div>
                            <div style="border-top:1px dashed #999;margin:8px 0"></div>
                            <div style="display:flex;justify-content:space-between"><span>Contoh Produk x1</span><span>25.000</span></div>
                            <div style="display:flex;justify-content:space-between"><span>Produk Lain x2</span><span>36.000</span></div>
                            <div style="border-top:1px dashed #999;margin:8px 0"></div>
                            <div style="display:flex;justify-content:space-between;font-weight:700"><span>TOTAL</span><span>61.000</span></div>
                            <div style="border-top:1px dashed #999;margin:8px 0"></div>
                            <div style="text-align:center;white-space:pre-line">{{ $tenant->receipt_footer }}</div>
                        </div>
                        <p class="tiny subtle mt-12">
                            Pratinjau menyederhanakan tata letak. Cetakan asli memuat logo, QR, dan barcode invoice.
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- ======================= Product ID mechanism ======================= --}}
    <div class="tab-panel" data-tab-panel="id-produk">
        <form method="POST" action="{{ route('admin.settings.sku') }}"
              data-sku-form data-preview-url="{{ route('admin.settings.sku.preview') }}">
            @csrf @method('PUT')

            <div class="grid grid-2-1">
                <div class="card">
                    <div class="card__head">
                        <div>
                            <div class="card__title">Mekanisme ID Produk</div>
                            <div class="card__sub">Menentukan pola ID yang dibuat otomatis untuk setiap produk baru</div>
                        </div>
                    </div>
                    <div class="card__body">
                        <div class="alert alert--info">
                            <x-icon name="barcode" size="17" class="alert__icon"/>
                            <div>
                                ID produk dibentuk dari empat segmen:
                                <b>PREFIX</b> · <b>KODE KATEGORI</b> · <b>TANGGAL</b> · <b>NOMOR URUT</b>.
                                Nonaktifkan segmen yang tidak diperlukan.
                            </div>
                        </div>

                        <div class="grid grid-2">
                            <div class="field">
                                <label class="field__label">Prefix</label>
                                <input type="text" name="sku_prefix" class="input mono" maxlength="12"
                                       value="{{ old('sku_prefix', $tenant->sku_prefix) }}" placeholder="PRD">
                                <span class="field__hint">Huruf/angka. Kosongkan untuk melewati segmen ini.</span>
                            </div>
                            <div class="field">
                                <label class="field__label">Pemisah <span class="field__req">*</span></label>
                                <input type="text" name="sku_separator" class="input mono" maxlength="2" required
                                       value="{{ old('sku_separator', $tenant->sku_separator) }}" placeholder="-">
                            </div>
                        </div>

                        <label class="check mb-16">
                            <input type="checkbox" name="sku_include_category" value="1"
                                   @checked(old('sku_include_category', $tenant->sku_include_category))>
                            <span>
                                <span class="check__text">Sertakan kode kategori</span>
                                <span class="check__hint">Diambil dari kolom "Kode ID" pada setiap kategori.</span>
                            </span>
                        </label>

                        <div class="grid grid-2">
                            <div class="field">
                                <label class="field__label">Segmen Tanggal</label>
                                <select name="sku_date_segment" class="select">
                                    <option value="none" @selected($tenant->sku_date_segment === 'none')>Tanpa tanggal</option>
                                    <option value="yy" @selected($tenant->sku_date_segment === 'yy')>Tahun (26)</option>
                                    <option value="yymm" @selected($tenant->sku_date_segment === 'yymm')>Tahun + Bulan (2608)</option>
                                    <option value="yymmdd" @selected($tenant->sku_date_segment === 'yymmdd')>Tanggal penuh (260814)</option>
                                </select>
                            </div>
                            <div class="field">
                                <label class="field__label">Panjang Nomor Urut <span class="field__req">*</span></label>
                                <input type="number" min="1" max="10" name="sku_sequence_length" class="input" required
                                       value="{{ old('sku_sequence_length', $tenant->sku_sequence_length) }}">
                                <span class="field__hint">4 digit menghasilkan 0001, 0002, …</span>
                            </div>
                        </div>

                        <div class="grid grid-2" style="margin-bottom:0">
                            <div class="field" style="margin-bottom:0">
                                <label class="field__label">Nomor Urut Berikutnya <span class="field__req">*</span></label>
                                <input type="number" min="1" name="sku_next_number" class="input" required
                                       value="{{ old('sku_next_number', $tenant->sku_next_number) }}">
                                <span class="field__hint">Ubah bila ingin melanjutkan penomoran dari sistem lama.</span>
                            </div>
                            <div class="field" style="margin-bottom:0">
                                <label class="field__label">Jenis Barcode <span class="field__req">*</span></label>
                                <select name="barcode_type" class="select">
                                    <option value="C128" @selected($tenant->barcode_type === 'C128')>Code 128 — sama dengan ID produk</option>
                                    <option value="EAN13" @selected($tenant->barcode_type === 'EAN13')>EAN-13 — 13 digit angka toko</option>
                                </select>
                                <span class="field__hint">EAN-13 memakai prefix internal "2" sesuai standar GS1.</span>
                            </div>
                        </div>
                    </div>

                    <div class="card__foot">
                        <button type="submit" class="btn btn--primary">
                            <x-icon name="check" size="16"/> Simpan Mekanisme ID
                        </button>
                    </div>
                </div>

                <div class="stack g-16">
                    <div class="card">
                        <div class="card__head"><div class="card__title">Pratinjau Langsung</div></div>
                        <div class="card__body t-center">
                            <div class="tiny subtle upper mb-8">ID produk berikutnya</div>
                            <div class="code-chip" style="font-size:17px;padding:9px 15px" data-sku-preview>{{ $skuPreview }}</div>

                            <div class="divider"></div>

                            <div class="tiny subtle upper mb-4">Pola aktif</div>
                            <div class="small semi" data-sku-pattern>{{ $skuPattern }}</div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card__head"><div class="card__title">Kode Kategori</div></div>
                        <div class="card__body">
                            @forelse ($categories as $category)
                                <div class="between" style="padding:7px 0;border-bottom:1px solid var(--border)">
                                    <span class="small">
                                        <span class="dot" style="background:{{ $category->color }};display:inline-block;margin-right:6px"></span>
                                        {{ $category->name }}
                                    </span>
                                    <span class="code-chip">{{ $category->skuCode() }}</span>
                                </div>
                            @empty
                                <p class="small muted">Belum ada kategori.</p>
                            @endforelse

                            <a href="{{ route('admin.categories.index') }}" class="btn btn--outline btn--sm btn--block mt-12">
                                Kelola kategori
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection
