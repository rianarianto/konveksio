# Konveksio - Development Log

**Project**: Multi-Outlet Convection Management System  
**Stack**: Laravel 12, Filament v3, Livewire  
**Database**: MySQL (via Laragon)  
**Started**: 2026-02-16

---

## Architecture Overview

### User Roles
- **Owner**: Full access to all shops and data
- **Admin**: Access only to assigned shop
- **Designer**: Access only to design tasks in assigned shop

### Multi-Tenancy Strategy
- Global Scope on models to filter data by `shop_id`
- Owner role bypasses Global Scope
- Admin/Designer roles see only their shop data

---

## Development Progress

### 2026-02-16: Project Initialization

#### ✅ Pre-Installation Check
- Laravel 12.51.0 installed
- Database `konveksio` configured in `.env`
- Git repository initialized with remote

#### ✅ Phase 1: Setup & Foundation (COMPLETED)
- Filament v3 installed with `filament/filament:^3.3` (requires PHP 8.2+)
- Admin panel created (ID: admin) with default theme
- First user 'Owner' created manually
- **Theme Customization**:
  - Font: **Rethink Sans**
  - Primary Color: **#7F00FF** (Purple)
  - Custom Login Page:
    - Split-screen design (CSS Grid/Flexbox)
    - Illustration placeholder on right
    - Custom input styling (Soft Gray #F9F9F9)
    - Custom "Sign In" header alignment

#### ✅ Phase 2: Database & Models (COMPLETED)
- `shops` table created (id, name, address, phone, timestamps)
- `users` table updated (role: owner/admin/designer, shop_id)
- Models `Shop` and `User` configured with relationships
- Database seeded with Owner, Shop, Admin, and Designer

#### ✅ Phase 3: Multi-Tenancy (COMPLETED)
- `ShopScope` implemented to filter data by `shop_id`
- Scope applied to `User` and `Shop` models via `booted` method
- **Logic**:
  - Owner: Sees all data
  - Admin/Designer: Sees only data related to their assigned `shop_id`
- Verified with `tests/Feature/MultiTenancyTest.php`

#### ✅ Phase 4: Filament Resources (COMPLETED)
- `ShopResource` created
- Form configured: Name, Address, Phone
- Table configured: Name, Address, Phone, Created At
- Multi-tenancy scope automatically applies to resource queries
- **Fixes**:
  - `User` model now implements `HasTenants` native interface
  - `AdminPanelProvider` configured with `tenant(Shop::class)`
  - `ShopResource` decoupled from tenant scope (`$isScopedToTenant = false`)
  - **Tenant Registration**: Implemented `RegisterShop` page to handle new shop creation flow

### 2026-02-17: UserResource Implementation

#### ✅ Phase 5: User Management (COMPLETED)
- `UserResource` created with comprehensive authorization
- **Authorization**:
  - Only Owner can view/create/edit/delete users
  - Created `EditProfile` page (accessible via top-right avatar menu)
  - Allows all users to update name, email, and password securely
- **System URL Rename**:
  - Changed panel path from `/admin` to `/app`
  - New URL format: `http://konveksio.test/app/{shop_id}`
  - Reduces confusion between "Admin System" and "Admin Role"
  - Admin/Designer cannot access User menu (hidden from navigation)
- **Form Schema**:
  - Fields: name, email, password (with Hash::make), role (admin/designer only)
  - Password: optional on edit, uses `dehydrated(fn($state) => filled($state))`
  - shop_id removed from form (auto-injected)
- **Automatic Tenant Association**:
  - `CreateUser::mutateFormDataBeforeCreate()` injects `shop_id` from `Filament::getTenant()`
- **Table Display**:
  - Columns: name, email, role (with badge colors), and **Toko** (shop name).
  - Owner: red, Admin: green, Designer: blue.
  - Automatic tenant scoping enabled (`$isScopedToTenant = true`).

#### ✅ Phase 6: Architecture & Authorization Fixes (COMPLETED)
- **ShopResource Security**:
  - Added strict authorization: Only `Owner` can view/manage Shops.
  - Result: Shops menu is now **hidden** for Admin/Designer users.
- **System URL Rename**:
  - Changed panel path from `/admin` to `/app`.
  - New URL format: `http://konveksio.test/app/{shop_id}`.
  - Reduces confusion between "Admin System" and "Admin Role".
- **Self-Management**:
  - Created `EditProfile` page (accessible via top-right avatar menu).
  - Allows all users to update name, email, and password securely.

---

## 2026-02-17: Shop Management UI Reorganization

#### ✅ Phase 7: Shop Management UI Improvements (COMPLETED)
- **Problem**: ShopResource in sidebar was redundant when already inside a tenant context
- **Solution**: Reorganized shop management access points for better UX
- **Changes**:
  - **Sidebar**: Hidden `ShopResource` from navigation (`shouldRegisterNavigation = false`)
  - **Tenant Dropdown**: Added **"Settings"** menu item via `tenantProfile()`
    - Allows Owner to edit current shop (name, address, phone)
    - Created `EditTenantProfile` page extending Filament's base class
  - **User Menu**: Added **"Manage All Shops"** menu item
    - Only visible to Owner role
    - Direct link to ShopResource list page
    - Uses `heroicon-o-building-storefront` icon
- **Authorization**: All authorization logic remains unchanged (Owner-only access)
- **Result**: Cleaner UI with contextual access to shop settings


---

## 2026-02-17: Session Checkpoint & Planning

### [19:35] Project Status Handoff
- **Completed**:
  - Multi-tenancy architecture (ShopScope, User::HasTenants).
  - Role-based Access Control (Owner/Admin/Designer).
  - UI/UX Refinements (Custom Login, Profile Edit, Shop Settings).
- **Checkpoints**:
  - [19:35] Project Status Handoff
  - [19:42] Fix: Hidden Owner from User List (`UsersTable::modifyQueryUsing`).
  - [19:48] Fix: Prevent `UrlGenerationException` on Register Shop page (Conditional rendering for both `EditProfile` and `Manage All Shops` menu items).
  - [20:20] Final Revert: Returned to default Filament simple layout for Register Shop. Custom layout attempts caused persistent tenant context & static property errors.
  - [20:45] Final Revert: Returned to default Filament simple layout for Register Shop to ensure stability for initial registration.
  - [20:45] Final Revert: Returned to default Filament simple layout for Register Shop to ensure stability for initial registration.
  - [20:55] Dual-Page Strategy Implemented:
    - `RegisterShop` (Simple Layout) for new users (0 shops).
    - Auto-redirect to `AddShop` Page (Panel Layout with existing Tenant Context) for existing owners (>0 shops).
- **Immediate Next Steps**: Begin implementation of core business logic.
- **Pending Decisions**:
  - [ ] Priority: Product Management vs Order Management?
  - [ ] Database Schema design for Products (Variants, Stock?).

### [21:20] Critical Fixes: Shop Registration Flow
- **User Experience Issue**: 
  - `RegisterShop` redirect was ignored due to incorrect helper usage (`redirect()` vs `$this->redirect()`).
  - Result: Fixed infinite loop for existing owners.
- **Fatal Error (AddShop.php)**:
  - `FatalError`: `Cannot redeclare non static Filament\Pages\Page::$view as static`.
  - Fix: Removed `static` keyword from `$view` property in `AddShop`.
- **Livewire Component Error**:
  - `Property [$form] not found`.
  - Fix: Added `InteractsWithForms` trait and `HasForms` interface to `AddShop`.
- **Type Error (Filament 5.2 Compatibility)**:
  - `TypeError`: `Argument #1 ($form) must be of type Filament\Forms\Form, Filament\Schemas\Schema given`.
  - Fix: Updated `form()` method signature to use `Schema $schema` and `->components()` instead of `Form $form` and `->schema()`.
- **Configuration Error**:
  - `Class "App\Filament\Pages\Dashboard" not found`.
  - Fix: Updated `AdminPanelProvider` to use default `Filament\Pages\Dashboard::class`.
- **Trait Not Found Error**:
  - `Trait "App\Filament\Pages\Filament\Schemas\Schema" not found`.
  - Fix: Moved `use Filament\Schemas\Schema;` from inside `AddShop` class to top of file (namespace import).
- **Blade Component Errors**:
  - `Unable to locate a class or view for component [filament-panels::form]`.
  - Cause: Installed Filament version uses `filament-schemas` namespace for form components.
  - Fix: Updated `add-shop.blade.php`:

### [21:35] Major Simplification: Using Existing ShopResource
- **User Insight**: Instead of creating a custom `AddShop` page with compatibility issues, use the existing `ShopResource::create` page which already works perfectly.
- **Decision**: Removed all custom `AddShop` implementation.
- **Changes**:
  - Updated `RegisterShop::mount()` to redirect existing users to `ShopResource::getUrl('create')`.
  - Deleted `app/Filament/Pages/AddShop.php`.
  - Deleted `resources/views/filament/pages/add-shop.blade.php`.
  - Removed `AddShop` from `AdminPanelProvider::pages()`.
- **Result**: 
  - Zero maintenance overhead.
  - No version compatibility issues.
  - Uses proven Filament Resource functionality.
  - Consistent UX across all shop management features.

---

## 2026-02-17: Order Management Foundation

### [22:58] Phase 8: Database Foundation (COMPLETED)
- **Objective**: Build database structure for Order Management with multi-tenancy support.
- **Migrations Created**:
  - `2026_02_17_160613_create_customers_table`
  - `2026_02_17_161024_create_orders_table`
  - `2026_02_17_161027_create_order_items_table`
- **Schema Details**:
  - **Customers**: id, shop_id (FK), name, phone, address (text)
  - **Orders**: id, shop_id (FK), customer_id (FK), order_number (unique), order_date, deadline, status (enum: diterima, antrian, diproses, selesai, siap_diambil), subtotal, total_price
  - **OrderItems**: id, order_id (FK), product_name, quantity, price
- **Models Implemented**:
  - **Customer**: ShopScope trait, belongsTo(Shop), hasMany(Order)
  - **Order**: ShopScope trait, auto-generate order_number (#ORD-YYYYMM-XXX), belongsTo(Shop, Customer), hasMany(OrderItem)
  - **OrderItem**: belongsTo(Order) - no ShopScope (scoped via Order)
- **Auto-Generation Logic**: 
  - Order model automatically generates unique `order_number` in format `#ORD-YYYYMM-XXX`
  - XXX is sequential per shop per month (001, 002, 003...)
  - Implemented in `Order::booted()` using `creating` event
- **Multi-Tenancy**: All models use ShopScope for data isolation by shop_id
- **Migration Status**: ✅ All tables created successfully

### [23:39] Phase 9: OrderResource Implementation (COMPLETED)
- **Objective**: Create comprehensive OrderResource matching hi-fi design mockup with advanced form layout
- **Payment Fields Migration**: Added `tax`, `shipping_cost`, `discount`, `down_payment` to orders table
- **Form Layout Strategy**:
  - 3-column layout: 2 columns (main content left) + 1 column (sidebar right)
  - Left side: Order data, Customer selection, Product repeater, Payment fields
  - Right side: Live summary with reactive calculations
- **Key Features Implemented**:
  - **Data Pesanan**: order_number (auto-generated, disabled), order_date, deadline
  - **Data Pelanggan**: 
    - Searchable customer dropdown with createOptionForm (name, phone, address)
    - Live auto-fill: phone and address fields populate automatically when customer selected
  - **Data Produk**: Repeater with product_name, quantity, price
    - Auto-calculates subtotal when items added/removed/modified
  - **Pembayaran**: subtotal, tax, shipping_cost, discount, down_payment, total_price
    - Live calculations update total_price whenever any field changes
  - **Ringkasan Pesanan** (Sidebar):
    - Product list with "Produksi" badges
    - Live placeholders: Subtotal, Ongkos Kirim, PPN 11%, Diskon, Total, DP, Total Pelunasan, Sisa
    - "Lunas" status badge when remaining balance is 0
- **Technical Implementation**:
  - `updateSubtotal()` method: Calculates sum of all order items
  - `updateTotalPrice()` method: Calculates total (subtotal + tax + shipping - discount)
  - All fields use `live()` for reactive updates
  - Placeholders use `Get $get` for real-time value display
- **Multi-Tenancy**: shop_id automatically assigned via Filament::getTenant()
- **Authorization**: Designers cannot access OrderResource
- **Table View**: Shows order_number, customer name, dates, status badges, total with search/sort capabilities

---

## 2026-02-18: Order Form Refinements

### Phase 10: Button Styling & Bug Fixes (COMPLETED)
- **Objective**: Apply custom color styles to key action buttons in the order form.
- **Changes**:
  - **"Simpan Pesanan" button**: Overrode `getCreateFormAction()` in `CreateOrder.php` → background `#7F00FF`, white text
  - **"+ Tambah Produk" button**: Styled via `addAction` on `Repeater` → purple outline, light purple background (`#F3E8FF`)
- **Bug Fix**:
  - `TypeError` on `addAction` closure: Filament passes `Filament\Actions\Action` but type hint expected `Filament\Forms\Components\Actions\Action`
  - Fix: Removed strict type hint → `fn($action)` instead of `fn(Action $action)`

### Phase 11: New Features — Maps, DP Upload, Ringkasan Redesign (COMPLETED)
- **Objective**: Enhance order form with 3 new features.

#### Google Maps Link
- Added `Placeholder` below "Alamat" field in Data Pelanggan section
- Renders a clickable "Buka di Google Maps" link (purple, with SVG pin icon)
- Opens Google Maps Search in new tab based on customer address text
- Only visible when address is filled
- Note: `suffixAction` not supported in this Filament version → used Placeholder HTML approach

#### Upload Bukti Pembayaran DP
- **Migration**: `2026_02_18_082359_add_dp_proof_to_orders_table` — added `dp_proof` (string, nullable) and `notes` (text, nullable) to `orders` table
- **Model**: Added `dp_proof` and `notes` to `Order::$fillable`
- **Form**: `FileUpload::make('dp_proof')` in Pembayaran section
  - Supports image + PDF, mobile-friendly (camera), preview, download
  - Max 5MB, stored in `storage/dp-proofs/`

#### Ringkasan Pesanan Redesign
- Replaced multiple `Placeholder` components with single HTML-rendered `Placeholder`
- Layout matches hi-fi design:
  - Product cards: border ungu, background `#faf5ff`, badge "Produksi"
  - Rows: Subtotal (purple bold), Ongkos Kirim, PPn 11%, Diskon
  - `<hr>` divider → Total (purple bold), DP
  - `<hr>` divider → Sisa: "Lunas" badge (green) or purple amount
- All values reactive via `Get $get`

### Phase 12: Manual UI Refinements (COMPLETED)
- **Objective**: Fine-tune specific UI elements based on user feedback.
- **Changes**:
  - **"Sisa Tagihan" (Main Form)**: Replaced default text with a custom `HtmlString` span.
    - Large size (`text-xl`) initially, then adjusted to `text-sm` per user preference.
    - Bold weight (`font-bold`).
    - Dynamic color: Red (`text-danger-600`) if balance remaining, Green (`text-success-600`) if paid/surplus.
- **Environment Note**: Resolved "Read Error" on local server caused by active VPN interference with local domain resolution.

---

## 2026-02-19: Production Workflow Foundation

### Phase 13: Database Schema Enhancement (COMPLETED)

#### Objective
Memperkuat fondasi database untuk mendukung: logika pengupahan berjenjang, audit trail penugasan, dan status desain order.

#### ✅ Migration 1: `order_items` Table
- Tambah `production_category` (enum: produksi, custom, non_produksi, jasa) — menentukan alur tahapan kerja otomatis.
- Tambah `size_and_request_details` (JSON, nullable) — menyimpan detail granular (ukuran, extras).

#### ✅ Migration 2: `orders` Table
- Tambah `design_status` (enum: pending, uploaded, approved, default: pending) — workflow mockup Designer.
- Tambah `design_image` (string, nullable) — path file mockup yang diupload Designer.

#### ✅ Migration 3: Tabel Baru `production_tasks`
Tabel ini adalah inti dari sistem penugasan dan pengupahan:
- `order_item_id` + `shop_id`: relasi ke item dan multi-tenancy.
- `stage_name`: nama tahapan (Potong, Jahit, Kancing, QC, dll).
- `assigned_to` (nullable FK → users): karyawan yang mengerjakan.
- `assigned_by` (FK → users): Admin/Designer yang membuat penugasan (**Audit Trail**).
- `wage_amount` (bigint): upah per unit saat penugasan dibuat (snapshot).
- `quantity`: jumlah unit di tahap ini.
- `status` (enum: pending, in_progress, done).
- `description` (text, nullable): catatan khusus pengerjaan.

#### ✅ Model Updates
- **`OrderItem`**: fillable + casts updated, relasi `hasMany(ProductionTask)` ditambahkan.
- **`Order`**: `design_status` + `design_image` ditambahkan ke fillable + casts.
- **`ProductionTask`** (NEW): ShopScope global scope, relasi `assignedTo()`, `assignedBy()`, `orderItem()`, `shop()`.

#### Notes
- Semua kolom baru bersifat nullable atau punya default value → data lama aman.
- **Logika upah berjenjang (WageCalculatorService)** dan **UI Penugasan** → Phase 14B/14C.

### Phase 14A: Modal "Tambah Produk" — Dynamic Form (COMPLETED)
**Tanggal**: 2026-02-19

#### Objective
Mengganti repeater `orderItems` sederhana dengan form dinamis yang reaktif berdasarkan kategori produksi.

#### ✅ Dua Alur UI Reaktif (live di `production_category`)
**Produksi (Size Toko)**:
- Section Sablon/Bordir: Repeater (Jenis, Lokasi) — ketentuan ukuran dari toko
- Section Varian Ukuran: Repeater (Ukuran XS–XXXL, Harga Satuan, Qty) + baris Total otomatis
- Section Request Tambahan: Repeater (Jenis, Harga Extra/unit) — hanya tambah harga, tidak tambah qty baju

**Produksi Custom (Ukur Badan)**:
- Section Detail Ukuran Badan: Repeater per orang (Nama, LD, PL, LP, LB, LPi, PB)
- Section Sablon/Bordir: Repeater (Jenis, Lokasi, Ukuran cmxcm — TextInput bebas: `5x5 cm`, `A4`)
- Section Jumlah & Harga: Qty auto dari count orang + Harga Satuan per orang
- Section Request Tambahan custom

#### ✅ Logika Kalkulasi
- `calcItemTotal(Get $get)`: Hitung total satu item dari live form state
- `calcItemTotalFromArray(array)`: Versi array untuk card label & sidebar preview
- `recalcItemTotal(Set, Get)`: Update `price` & `quantity` item secara live (debounce 500ms)
- `updateSubtotal(Set, Get)`: Bubble-up ke subtotal pesanan + sidebar Ringkasan
- `mutateItemData(array)`: Sebelum simpan ke DB → pack ke JSON `size_and_request_details`, unset field sementara

#### ✅ Struktur JSON `size_and_request_details`
```json
// Produksi
{ "category": "produksi", "bahan": "...", "sablon_bordir": [...], "varian_ukuran": [...], "request_tambahan": [...] }

// Custom
{ "category": "custom", "bahan": "...", "detail_custom": [...], "sablon_bordir": [...], "request_tambahan": [...], "harga_satuan": 0 }
```
Payroll-ready: Phase 14B cukup cek `category === 'custom'` → upah ×2 otomatis.

#### ✅ File Modified
- `app/Filament/Resources/Orders/OrderResource.php`: Full rewrite (syntax OK, cache cleared)

---

### Phase 14A-Rev1: UI Revisions & Customer Measurements (COMPLETED)
**Tanggal**: 2026-02-19

#### Revisi 1 — Bahan Baju: Color Swatch Dropdown
- Setiap opsi bahan kini menampilkan **lingkaran warna** via `allowHtml()` + inline HTML
- ~13 pilihan hardcode sementara (Parasut, Drill, Polo) dengan warna mendekati aslinya
- Nanti diatur dari **data master** (kode hex per bahan)

#### Revisi 2 — Sablon/Bordir (Produksi): Sederhanakan Field
- **Dihapus**: input ukuran (cm) — karena size toko pakai ketentuan toko sendiri
- **Tersisa**: Jenis (Sablon/Bordir/DTF) + Lokasi titik sablon

#### Revisi 3 — Sablon/Bordir (Custom): Ukuran jadi Text Bebas
- Field ukuran berubah dari `numeric+suffix cm` → `TextInput` biasa
- Label: **"Ukuran (cmxcm)"** — user ketik bebas: `5x5 cm`, `A4`, `30x10 cm`

#### Revisi 4 — Ukuran Badan: Disimpan per Pelanggan (`customer_measurements`)
- **Tabel baru**: `customer_measurements` (migration berhasil)
  - Kolom: `customer_id` (FK), `nama`, `LD`, `PL`, `LP`, `LB`, `LPi`, `PB`, `catatan`
  - Semua ukuran nullable (decimal 5,1), bisa diisi sebagian
- **Model baru**: `CustomerMeasurement` dengan `belongsTo(Customer)` dan cast otomatis ke float
- **Customer model**: ditambah relasi `measurements()` → `hasMany(CustomerMeasurement)`
- **Alur simpan**: Data ukuran masuk ke `size_and_request_details` JSON tiap order. Di form, sistem menampilkan hitungan anggota tim tersimpan untuk pelanggan yang dipilih
- **Roadmap**: Load ulang ukuran tersimpan saat order baru → Phase selanjutnya

#### Revisi 5 — Card View setelah Tambah Produk
- Repeater berubah jadi `collapsible()->collapsed()`
- Setiap item collapse jadi card dengan label dinamis:
  `[Produksi] 20x Baju Putih SMKN — Rp 500.000`
  `[Custom] 3x Kemeja Tim — Rp 600.000`
- Klik item untuk expand & edit — **data tidak hilang** saat close

#### Revisi 6 — Ringkasan Pesanan: Sticky Sidebar
- Group sidebar kanan mendapat `style="position:sticky;top:1rem;"`
- Laptop/desktop: sidebar mengikuti scroll
- Tablet/mobile: otomatis collapse ke bawah form (Filament responsive grid)

#### ✅ Files Modified / Created
| File | Status |
|---|---|
| `app/Filament/Resources/Orders/OrderResource.php` | MODIFIED (full rewrite v2) |
| `app/Models/Customer.php` | MODIFIED (+ `measurements()` relation) |
| `app/Models/CustomerMeasurement.php` | NEW |
| `database/migrations/2026_02_19_084935_create_customer_measurements_table.php` | NEW |

---

### Phase 14A-Rev2: Order Form UI/UX Optimization (2026-02-19)

#### ✅ Request Tambahan: Layout & Logic Refinement
- **New 2-Row Layout**: Reorganized "Request Tambahan" fields into 2 rows (Baris 1: Jenis/Ukuran, Baris 2: Qty/Harga) to avoid cramped UI.
- **"Semua Ukuran" Option**: Added a special option `✦ Semua Ukuran` in the "Request Tambahan" dropdown.
  - Automatically calculates and fills the total quantity of all variants when selected.
  - Validates against the total pooled quantity of all size variants.
- **Strict Validation**: Replaced hidden error fields with dynamic `helperText` and server-side `rules()`. Prevents quantity input from exceeding available variant stock with real-time feedback.

#### ✅ Repeater UI Improvements
- **Live Item Labeling**: Fixed the `itemLabel` calculation. Collapsed cards now accurately show `[Category] Qty x Product Name — Total Price` in real-time.
- **Bottom Navigation**: Added a **"Sembunyikan Detail"** button at the bottom of each order item for easier navigation without scrolling to the top.
- **Collapsible Custom Details**: "Detail Ukuran Badan (per Orang)" in Custom Category is now collapsible and **collapsed by default**, making long lists manageable.

#### ✅ System Stability
- **Deadlock Resolution**: Fixed `Serialization failure: 1213 Deadlock found` on session table by switching to `SESSION_DRIVER=file` in `.env`.
- **Navigation**: Enabled `sidebarCollapsibleOnDesktop()` in `AdminPanelProvider` for a more flexible workspace.

#### ✅ Files Modified
| File | Status |
|---|---|
| `app/Filament/Resources/Orders/OrderResource.php` | MODIFIED (UI & Logic update) |
| `app/Providers/Filament/AdminPanelProvider.php` | MODIFIED (Collapsible sidebar) |
| `.env` | MODIFIED (Session driver) |

---

## 2026-02-20: Order Form UI Refinements & Bug Fixes (IN PROGRESS)

#### ✅ UI/UX Improvements
- **"Sembunyikan Detail" Button Rework**: 
  - After multiple attempts with Alpine.js state and DOM traversal, simplified the button to **"Kembali ke Atas"**.
  - Action: Smooth scroll to the top of the item header so the user can easily click the product title to collapse.
  - Reason: Most robust solution across different Filament versions and layout states.
- **Scroll Button in Detail Ukuran**: Added a matching "Kembali ke Atas" button inside the "Detail Ukuran Badan" section for long lists of people.
- **Total Display Enhancement**: 
  - Added **"Total Biaya Tambahan"** row in the order item summary section.
  - Mode Produksi: `qty_tambahan * harga_extra_satuan`.
  - Mode Custom: `jumlah_orang * harga_extra_satuan`.
  - Color-coded: Green for > 0, Gray for 0.

#### ✅ Critical Bug Fixes
- **Error `array_sum` on String**: fixed `ErrorException` when user enters non-numeric characters in price fields. Switched to `foreach` loops with explicit `(int)` casting.
- **Subtotal Calculation Precision**:
  - Found bug where subtotal showed weird values (e.g., 776.246 instead of 560.000).
  - Cause: Calculation relied on `qty * price` (where price was an integer-divided average).
  - Solution: Rewrote `updateSubtotal` to sum items using `calcItemTotalFromArray()` directly for 100% accuracy.

#### 🔄 Current Task: Debugging Saving Issue (Empty JSON in DB) — ✅ RESOLVED

### Phase 15: Fix — Virtual Fields Not Saving to JSON (COMPLETED)
**Tanggal**: 2026-02-20

#### Root Cause
`mutateRelationshipDataBeforeCreateUsing` dan `mutateRelationshipDataBeforeSaveUsing` sudah terhubung dengan benar ke `mutateItemData()`, **tapi** Filament secara default strips/exclude fields yang tidak ada di kolom database sebelum memanggil hook tersebut — kecuali field diberi tanda `->dehydrated(true)`.

#### ✅ Fix 1: Tambah `->dehydrated(true)` ke Virtual Fields yang Hilang
4 virtual field/repeater di dalam `orderItems` Repeater yang belum punya flag ini:
| Field | Keterangan |
|---|---|
| `Repeater::make('detail_custom')` | Data ukuran badan per orang (kategori Custom) |
| `Repeater::make('sablon_bordir_custom')` | Data sablon/bordir (kategori Custom) |
| `TextInput::make('harga_custom_satuan')` | Harga satuan per orang (kategori Custom) |
| `Repeater::make('request_tambahan_custom')` | Request tambahan (kategori Custom) |

#### ✅ Fix 2: Tambah `->mutateRelationshipDataBeforeFillUsing()` ke Repeater
- Menambahkan hook di Repeater `orderItems` untuk **saat Edit** — memanggil `unmutateItemData()` yang sudah ada
- Ini unpack JSON `size_and_request_details` kembali ke virtual fields saat form Edit dibuka
- Sebelumnya: hanya ada hook untuk Create/Save, Edit dilewati → form kosong saat Edit

#### ✅ Fix 3: Update `EditOrder.php`
- Ditambahkan `mutateFormDataBeforeFill()` untuk inject `shop_id` dari tenant
- **Auto-Fill Customer Data**: Menambahkan logika untuk mengisi virtual fields `customer_phone` dan `customer_address` dari model `Customer` saat halaman Edit dibuka.
- File dibersihkan dari semua stub/approach yang tidak tepat

### Phase 16: Implementasi Kategori Non-Produksi & Jasa (COMPLETED)
**Tanggal**: 2026-02-20

#### 1. Kategori "Non-Produksi" (Barang Jadi Supplier)
- **UI**: Menampilkan field `supplier_product`, sablon/bordir statis, varian ukuran grid, dan request tambahan.
- **Logika Harga**: Total = (Qty Ukuran * Harga Barang Jadi) + Biaya Tambahan.
- **JSON**: Detail disimpan dalam key `supplier_product` di `size_and_request_details`.

#### 2. Kategori "Jasa" (Murni Jasa)
- **UI**: Menyembunyikan varian ukuran & bahan. Menonjolkan `nama_jasa`, `jumlah_jasa`, dan `harga_satuan_jasa`.
- **Logika Harga**: Total = Jumlah * Harga Satuan.
- **JSON**: Data pengerjaan (sablon) tetap disimpan sebagai informasi detail.

#### 3. Core Logic Updates
- **Calc Functions**: `calcItemTotal` & `calcItemTotalFromArray` mendukung 4 kategori (Produksi, Custom, Non-Produksi, Jasa).
- **Mutate/Unmutate**: Menangani packing/unpacking JSON untuk semua kategori baru, memastikan data tampil kembali saat Edit.
- **UI Refinement**: `itemLabel` otomatis menyesuaikan label (cth: "[Jasa] 10x Sablon Logo — Rp 100.000").

#### ✅ Files Modified
| File | Status |
|---|---|
| `app/Filament/Resources/Orders/OrderResource.php` | MODIFIED (Kategori Jasa & Non-Produksi) |

#### Refinement (2026-02-20, Rev 1)
- **Fix Double Input**: Memperbaiki logika `visible()` pada section "Produksi" agar tidak muncul di kategori lain.
- **Non-Produksi**: Menghapus section "Request Tambahan" dan memindahkan "Jenis Produk Supplier" ke baris bahan agar lebih intuitif.
- **Jasa**: Menyembunyikan field "Nama Produk Pesanan" di level atas dan mensinkronisasinya dengan field "Nama Jasa" agar tidak double input.

#### Refinement (2026-02-20, Rev 2)
- **Styling**: Unified sidebar summary badges and quantity display for Non-Produksi & Jasa.

---

## 2026-02-22: Designer Workflow & Critical Fixes

### Phase 13: Order Item Deletion Fix (COMPLETED)
- **Problem**: Deleting an order item from the repeater in Edit mode removed it from the UI but the item persisted in the database after saving.
- **Root Cause**: The unique `id` of the order item was being lost or unset during the `mutate/unmutate` cycle, preventing Filament from identifying which records to delete.
- **Fix**: 
  - Updated `OrderResource::unmutateItemData` to strictly preserve or restore the item `id`.
  - Refined `unset` logic in `mutateItemData` to ensure internal metadata used by Filament for relationship tracking is not accidentally destroyed.
- **Result**: Items are now correctly deleted from the database when removed from the form and saved.

### Phase 14B: Design Task Management (COMPLETED)
- **Objective**: Create a dedicated workspace for Designers to process visual requirements without accessing full order management.
- **New Resource: `DesignTaskResource`**:
  - **Access Control**: Restricted strictly to the **Designer** role. Hidden from Owner/Admin.
  - **Query Filter**: Table only shows orders with `design_status` in `['pending', 'uploaded']`.
  - **Task Table**: Displays Order No, Customer, Summary of Products, Deadline, and Design Badge.
- **Designer Workspace (Form)**:
  - **2-Column Layout**:
    - **Left**: "Rincian Teknis" — A read-only HTML block that dynamically parses JSON `size_and_request_details` from all order items. Shows Materials, Print locations, and Custom Measurements (LD/LP).
    - **Right**: File upload for `design_image` (stored in `public/designs`).
- **Automation Logic**:
  - Overrode the `EditAction::using` logic.
  - Upon saving a design:
    - `design_status` is automatically set to `approved`.
    - Order `status` is automatically updated to `antrian` (if it was 'diterima').
- **Fixed Technical Issues**:
  - Corrected `EditAction` namespace from `Tables` to `Actions`.
  - Fixed `Group` and `Section` namespace conflicts between `Forms` and `Schemas`.

#### ✅ Files Modified / Created
| File | Status |
|---|---|
| `app/Filament/Resources/Orders/OrderResource.php` | MODIFIED (Deletion Fix) |
| `app/Filament/Resources/DesignTasks/DesignTaskResource.php` | NEW (Designer Workspace) |
### Phase 14C: Granular Design Tasks (Per-Item) [COMPLETED]
- **Objective**: Improve the design workflow by allowing each product item within an order to have its own design status and file, rather than blocking the entire order.
- **Database Schema**:
  - Dropped `design_status` and `design_image` from the `orders` table.
  - Added `design_status` and `design_image` to the `order_items` table.
- **Models Update**: 
  - Adjusted `$fillable` definitions in both `Order` and `OrderItem` models.
  - Added a `shop()` relationship via `hasOneThrough` to `OrderItem` to support Filament's multi-tenancy scoping.
- **Resource Refactoring (`DesignTaskResource`)**:
  - Changed base model from `Order` to `OrderItem`.
  - Updated table columns to display granular data: No. Pesanan, Nama Produk, Qty, Kategori, Deadline, and Status Desain.
  - Simplified the `Edit` modal to only parse and render the JSON `size_and_request_details` for that specific item.
  - **Automation**: Saving an item's design automatically sets its status to `approved`. A check is then performed: if all items in the parent order are `approved`, the parent order's status is automatically bumped from `diterima` to `antrian`.
  - **UI Refinement**: Added Table Grouping to group the granular order items by their parent `Order Number` and `Customer Name` for better readability.

#### ✅ Files Modified / Created
| File | Status |
|---|---|
| `database/migrations/*_move_design_fields...` | NEW (Migration) |
| `app/Models/Order.php` | MODIFIED (Remove design fields) |
| `app/Models/OrderItem.php` | MODIFIED (Add design fields & relations) |
| `app/Filament/Resources/DesignTasks/DesignTaskResource.php` | MODIFIED (Refactored to OrderItem, Fixed Ambiguous ID, Added Table Grouping) |
### Phase 15: Control Produksi Resource - Hybrid Layout (COMPLETED)
**Tanggal**: 2026-02-23 & 2026-02-24

#### Objective
Membangun pusat komando untuk mendelegasikan dan melacak proses produksi per item pesanan setelah desainnya disetujui. Membutuhkan UI hybrid dengan card layout dan sidebar workload karyawan.

####  1. Database & Master Data Tahapan
- **Master Data**: Tabel production_stages untuk fleksibilitas tahapan pengerjaan (nama, urutan estafet).
- **Migration**: Menambahkan size_quantities (JSON) ke tabel production_tasks untuk pencatatan jumlah per size.
- **Resource**: Membuat ProductionStageResource untuk manajemen master data tahapan.

####  2. Control Produksi Resource (Hybrid Layout)
- **Base Model**: Diubah menjadi Order::class (bukan OrderItem) agar Card berbasis 1 Pesanan penuh.
- **Scope Query**: Hanya menampilkan Order yang memiliki orderItems dengan design_status = 'approved'.
- **Custom Table View**:
  - Menggunakan contentGrid(['md' => 1]).
  - Override view dengan order-card.blade.php rendering Card Pesanan berisi daftar OrderItem dan Status Tracker Stages (P, J, dll).
- **Tab Filters**: Mengimplementasikan filter cepat: Semua, Siap Potong, Sedang Jahit, Siap QC.

####  3. Custom Page Layout & Sidebar
- **ManageControlProduksis Page**: Di-override menggunakan custom blade view untuk membentuk **Split Layout**.
- **Sidebar**: Komponen Livewire EmployeeWorkloadSidebar di sisi kanan layar untuk memantau realtime beban kerja aktif tiap karyawan (pending/in progress).
- **User Relations**: Menambahkan relasi ssignedProductionTasks dan createdProductionTasks di model User untuk komputasi workload.

####  4. Top Widgets (ProduksiStats)
- Membuat Custom Filament Widget ProduksiStats.php dan produksi-stats.blade.php.
- **UI Mockup Compliance**:
  - Hero Card (Kiri): Menampilkan Total Beban Produksi (Pcs) beserta persentase trend vs hari kemarin.
  - Stage Mini-Cards Grid (Kanan): Otomatis melooping dari production_stages.
  - Status Indicators Dinamis: Lancar (< 50 pcs), Menumpuk (>= 50 pcs), atau Kosong (0 pcs).

####  5. Modal Penugasan (Atur Tugas)
- Action: Diubah menjadi **Page Action** sehingga bisa dipicu dari dalam item card melalui argumen item_id.
- Modal Schema: Render spesifikasi teknis dinamis dari size_and_request_details.
- Validasi Relay/Estafet: Mencegah alokasi jumlah pcs melebihi tahapan sebelumnya.

### Phase 16: Control Produksi - Refactor ke Standard Filament Table dengan Grouping (COMPLETED)
**Tanggal**: 2026-02-24

#### Objective
Merombak UI Control Produksi dari Card Grid kustom ke tabel standar Filament dengan pengelompokan per nomor pesanan (mirip halaman Meja Design), untuk meningkatkan keterbacaan dan manageability data.

#### Perubahan Teknis

1. **Model Refactor (`ControlProduksiResource`)**:
   - Base model diubah dari `Order::class` menjadi `OrderItem::class`.
   - `scopeEloquentQueryToTenant()` diperbarui untuk melakukan scope via relasi `order.shop_id`.
   - `getEloquentQuery()` diperbarui: hanya menampilkan item dengan `design_status = 'approved'`, dengan eager loading `order.customer` dan `productionTasks`.

2. **Table Columns Baru**:
   - `order.order_number` — Nomor pesanan, sortable, searchable
   - `product_name` — Nama produk item, sortable
   - `quantity` — Jumlah pcs
   - `production_category` — Badge berwarna (Produksi/Custom/Non-Produksi/Jasa)
   - `order.deadline` — Badge merah deadline
   - `progress_status` — Computed badge: Belum Diproses / Sedang Berjalan / Selesai

3. **Grouping oleh Order Number**:
   - Menggunakan `->defaultGroup(TableGroup::make('order.order_number'))`.
   - Judul grup menampilkan `No. Pesanan - Nama Pelanggan`.
   - Grup bisa di-collapse untuk menyembunyikan item di dalamnya.

4. **CSS Hacks Dihapus**:
   - Semua CSS override di `manage-control-produksis.blade.php` yang memaksa tampilan grid kustom dihapus.
   - Tabel sekarang menggunakan Filament native styling.

5. **Blade Views Lama Dihapus**:
   - `order-card.blade.php` dan `table-items-cell.blade.php` dihapus karena tidak lagi digunakan.

#### Files Modified
| File | Status |
|---|---|
| `app/Filament/Resources/ControlProduksis/ControlProduksiResource.php` | MODIFIED (Model → OrderItem, Columns, Grouping) |
| `resources/views/filament/resources/control-produksi/pages/manage-control-produksis.blade.php` | MODIFIED (Hapus CSS overrides) |
| `resources/views/filament/resources/control-produksi/order-card.blade.php` | DELETED |
| `resources/views/filament/resources/control-produksi/table-items-cell.blade.php` | DELETED |

### Phase 17: UI Refinements & Detail View for Control Produksi (COMPLETED)
**Tanggal**: 2026-02-25

#### Objective
Menyempurnakan antarmuka modal "Atur" tugas produksi, menghilangkan parsing array mentah (seperti ID bawaan Filament cache), serta memastikan indikator *status* tahapan estafet (Selesai/Dibatalkan/Pending) terpusat dan mudah diakses dari header modal.

#### Perubahan Teknis
1. **Modal Atur Tugas (Spesifikasi Produksi)**:
   - **Request Tambahan**: Memisahkan rendering dari logika standar `implode` Filament yang sering menyertakan key index array/ UUID aneh (`1 62 15000` dsb), dan mem-parsingnya ke ekstrak khusus hanya `jenis`, `ukuran`, `qty_tambahan`, dan custom text. Tampilannya kini rapi sebagai list berformat text murni (e.g. *Kancing Ekstra Semua Ukuran (1 pcs)*).
   - **Badge Material**: Mengubah logika badge untuk kasus produk **Non-Produksi**, di mana detail bahan kini otomatis menarik dari teks *Product dari Supplier*. Menambahkan separator `&nbsp;&nbsp;|&nbsp;&nbsp;` agar UI tidak saling bertumpukan.
   - **Collapsible Layout**: Membungkus rincian list ukuran tubuh pelanggan Custom (lebih dari 2 orang) ke dalam elemen HTML `<details>` untuk menghemat tinggi layar modal.

2. **Aksi Tombol Update Progress (`ControlProduksiResource`)**:
   - Memindahkan *Update Progress Action* yang sebelumnya di level _column_ (tombol dalam tabel) langsung ke _header Action_ di dalam UI modal Atur Tugas beserta Action Group-nya jika perlu.
   - Memastikan hierarki skema status update terkini. Tombol status dibedakan jelas dengan warna (Emerald untuk *Tandai Selesai*, Danger untuk *Batal*, Primary untuk *Kerjakan*).

#### Files Modified
| File | Status |
|---|---|
| `app/Filament/Resources/ControlProduksis/ControlProduksiResource.php` | MODIFIED (HTML Builder layout, regex extraction arrays logic, Header actions) |

### Phase 18: Dashboard Widget & Control Produksi Fixes (COMPLETED)
**Tanggal**: 2026-02-26

#### 1. Produksi Stats Widget (UI/UX)
- **Dark Mode CSS Conflict**: Resolved a conflict between Filament v3 class-based dark mode (`.dark`) and Tailwind v4's default media query strategy by injecting `@custom-variant dark (&:where(.dark, .dark *));` into `resources/css/app.css`. The widget now seamlessly syncs with the Filament panel's light/dark mode.
- **Responsive Layout Improvements**: 
  - Converted the "Antrian" badge into a clean subtitle below the title to prevent text wrapping on small screens.
  - Adjusted text scaling (`text-5xl sm:text-6xl lg:text-7xl`) for the main metric numbers.
  - Set the active production stage cards to `flex-1 min-w-[120px]` to automatically stretch and fill the available container width, maintaining a horizontal scroll fallback for mobile devices.

#### 2. Order Item Formatting & Seeding
- **Custom Item Quantity Fix**: Updated `ControlProduksiResource`'s Placeholder. When the category is `custom`, the displayed quantity next to the product name is automatically calculated by counting the number of individuals in the `detail_custom` array rather than relying on the generic quantity input.
- **Production Stage Seeder**:
  - Implemented `ProductionStageSeeder` to automatically populate the `production_stages` table for all registered shops (`shop_id`).
  - Standardized the estafet stages sequence: **Potong → Jahit → Kancing → Bordir/Sablon → Finishing → QC**.

#### Files Modified
| File | Status |
|---|---|
| `resources/css/app.css` | MODIFIED (Added custom dark variant) |
| `resources/views/filament/resources/control-produksi/widgets/produksi-stats.blade.php` | MODIFIED (Responsive + Flex fixes) |
| `app/Filament/Resources/ControlProduksis/ControlProduksiResource.php` | MODIFIED (Fix Custom Qty Calculation) |
| `database/seeders/ProductionStageSeeder.php` | NEW (Tenant-scoped seeder) |

### Phase 19: Worker Master Data & Wage Balancing (COMPLETED)
**Tanggal**: 2026-02-26

#### Objective
Membuat Master Data untuk Karyawan Produksi (Tukang) yang bebas dari tabel Users (tanpa login), menerapkan sistem kalkulasi Upah Borongan berjenjang sesuai tahapan, dan memberikan fitur sinkronisasi Load Balancing di dropdown penugasan.

#### Perubahan Teknis
1. **Model & Migration `Worker`**:
   - Menambahkan tabel `workers` dengan `shop_id` (Tenant Scope), `name`, `category`, `phone`, `is_active`.
   - Mengubah relasi `ProductionTask::assignedTo()` agar menunjuk ke `Worker::class`, bukan lagi ke `User::class`.

2. **Load Balancing via Accessor**:
   - Menambahkan attr custom `$appends = ['active_queue_count']` pada model `Worker`. 
   - Ini menghitung `sum('quantity')` dari task-task yang berstatus `pending` atau `in_progress`.
   - Admin Panel -> Dropdown `Tugaskan Ke` akan merender jumlah beban aktif Karyawan secara live (Misal: *Budi - Antrian: 15 pcs*).

3. **Filament Worker Resource**:
   - `WorkerResource` diciptakan agar Pemilik/Admin bisa mengelola data tim produksi per-tenant (Tambah/Edit Pekerja).
   - Tabel Karyawan mendukung pencarian dan memformat nilai `active_queue_count` menjadi badge warna dinamis (Hijau = < 20, Kuning = < 50, Merah = >= 50).

4. **Sistem Kalkulasi Upah Borongan (`ControlProduksiResource`)**:
   - Menambahkan text input numerik `wage_per_pcs` di area Repeater Modal "Atur Tugas".
   - `ProductionTask` save action dikonfigurasi agar secara otomatis mengambil kuantitas yang baru disimpan dan mengalikannya dengan *Base Rate* = `wage_amount`. 
   - Modal edit juga di-_reinject_ logic untuk mendeteksi *Base Rate* aslinya dengan mengekstrak kembali nilai `wage_amount / quantity`.

5. **UI Request Tambahan (`ControlProduksiResource`)**:
   - Menyingkirkan entitas `Placeholder` `_req_info` yang sebelumnya disematkan ke dalam ukuran-ukuran dinamis yang bermasalah.
   - Peringatan Request Tambahan/ Ekstra secara keseluruhan sudah tersedia dan _dipatenkan_ ke dalam `fieldset` header card informasi pesanan, sehingga UI kolom penugasan terlihat jauh lebih bersih dan bebas dari error path resolusi.

#### Files Modified / Created
| File | Status |
|---|---|
| `database/migrations/2026_02_26_142318_create_workers_table.php` | NEW |
| `app/Models/Worker.php` | NEW |
| `app/Models/ProductionTask.php` | MODIFIED (assigned_to -> Worker) |
| `app/Filament/Resources/Workers/WorkerResource.php` | NEW |
| `app/Filament/Resources/ControlProduksis/ControlProduksiResource.php` | MODIFIED (Worker Assign, Wage calculation, Dropdown Queue) |

---

### 2026-02-28: Production Task Validation, Worker Queue Detail & Per-Task Progress Modal

#### ✅ Bug Fixes & Validation – `ControlProduksiResource`

1. **Validasi Submit "Atur Tugas"**:
   - Menambahkan validasi **kelengkapan tahapan wajib**: sistem mengambil semua tahapan dari DB sesuai kategori produk (`for_produksi_custom`, `for_non_produksi`, dll.), lalu memastikan semua tahapan tersebut sudah ditugaskan sebelum bisa disimpan.
   - Menambahkan validasi **akumulasi kuantitas per tahap**: untuk setiap `stage_name` (termasuk yang dipecah ke 2+ tukang), total qty dari semua baris harus sama persis dengan total qty item pesanan.
   - Satu tahapan **boleh muncul lebih dari sekali** (misal Jahit dibagi ke 2 tukang) — opsi `->disableOptionsWhenSelectedInSiblingRepeaterItems()` dihapus dari dropdown tahap.

2. **Bug Fix `quantity = 0` saat Save**:
   - Field "Total Qty" di repeater adalah *read-only/disabled* sehingga tidak dikirim ke server saat form submit. Quantity kini dihitung ulang dari penjumlahan field ukuran (`sizeQuantities`) di dalam action save.
   - Akibatnya, `wage_amount = quantity × wage_per_pcs` juga ikut benar.

3. **Bug Fix `base_wage` null constraint** (`ProductionStageResource`):
   - Menambahkan `->required()` pada input `base_wage` agar nilai `0` tidak diperlakukan sebagai `null`.

#### ✅ Foreign Key Migration – `production_tasks.assigned_to`

- `assigned_to` di tabel `production_tasks` sebelumnya masih menunjuk ke `users(id)`, padahal karyawan sudah dipindahkan ke tabel `workers`.
- Menambahkan migration baru: `2026_02_28_112451_update_assigned_to_foreign_key_on_production_tasks_table.php` yang menghapus FK lama dan membuat FK baru ke `workers(id) ON DELETE SET NULL`.

#### ✅ Worker Queue Detail – `Worker` model & `WorkersTable`

1. **Model `Worker`**:
   - Menambahkan 3 accessor baru: `pending_count`, `in_progress_count`, `done_count` — masing-masing menjumlahkan `quantity` dari task per status.
   - `$appends` diperbarui agar ketiganya otomatis tersedia.

2. **WorkersTable** — kolom antrian diperinci menjadi 3 badge terpisah:
   - ⏳ **Antrian** (kuning): task `pending`
   - 🔨 **Dikerjakan** (biru): task `in_progress`
   - ✅ **Selesai** (hijau): task `done`

3. **WorkerInfolist** (halaman View Detail Karyawan) — kini menampilkan:
   - Informasi dasar karyawan (relayout dengan `Section`)
   - Section ⏳ Antrian Tugas: daftar task `pending` dengan nama produk & tanggal penugasan
   - Section 🔨 Sedang Dikerjakan: daftar task `in_progress`
   - Section ✅ Riwayat Selesai: daftar task `done` (collapsed by default)

#### ✅ Per-Task Progress Modal – `ControlProduksiResource`

Tombol **"Update Progress"** diganti dari modal sederhana (dropdown per-task) menjadi **modal tabel per-baris** dengan aturan:
- Setiap baris = 1 tugas (1 karyawan, 1 porsi qty)
- Tombol **▶ Mulai** → ubah status `pending` → `in_progress`
- Tombol **✓ Tandai Selesai** → ubah status `in_progress` → `done`
- **Stage berikutnya terkunci** (🔒) sampai semua task di stage sebelumnya berstatus `done`
- Split stage (Jahit 2x) → keduanya dibuka **independen** berdasarkan stage sebelumnya
- Setelah aksi → status Order disinkronkan otomatis (`antrian` / `diproses` / `selesai`)

Perubahan dilakukan via **dedicated route** (`GET /task-action/{task}/{action}/{item}`) dan controller baru `TaskActionController`.

#### Files Modified / Created
| File | Status |
|---|---|
| `app/Filament/Resources/ControlProduksis/ControlProduksiResource.php` | MODIFIED (validasi, qty fix, progress modal baru) |
| `app/Filament/Resources/ProductionStages/ProductionStageResource.php` | MODIFIED (required base_wage) |
| `app/Filament/Resources/Workers/Tables/WorkersTable.php` | MODIFIED (3 kolom badge antrian) |
| `app/Filament/Resources/Workers/Schemas/WorkerInfolist.php` | MODIFIED (task breakdown per status) |
| `app/Models/Worker.php` | MODIFIED (accessor pending/in_progress/done count) |
| `app/Http/Controllers/TaskActionController.php` | NEW |
| `routes/web.php` | MODIFIED (route task-action) |
| `database/migrations/2026_02_28_112451_update_assigned_to_foreign_key_on_production_tasks_table.php` | NEW |

---

### 2026-03-01: Status Produksi, Fix Redirect & Stage Stats, Sistem Upah Karyawan

#### ✅ Status Produksi — 4 State yang Jelas (`ControlProduksiResource`)

Kolom **"Status Produksi"** direvisi dari 3 status ambigu menjadi **4 status presisi**:

| Status | Kondisi | Warna |
|---|---|---|
| `Belum Diatur` | Task belum di-assign setelah desain approved | Abu |
| `Antrian` | Task ada, semua masih `pending` | Kuning |
| `Diproses` | Minimal 1 task `in_progress` | Biru |
| `Selesai` | Semua task `done` | Hijau |

Deskripsi kolom diperbarui menjadi lebih detail: `🔨 Potong — Pak Joko` (in_progress) atau `⏳ Menunggu: Potong` (antrian).

#### ✅ Fix Redirect 404 Setelah Klik Mulai/Selesai (`TaskActionController`)

- Typo slug redirect: `/control-produksis` (dengan `s`) → diperbaiki ke `/control-produksi` (tanpa `s`)

#### ✅ Fix Stage Card Widget — Hanya Hitung Item Aktif (`ProduksiStats`)

Widget "Detail Produksi Berjalan" sebelumnya menghitung semua task `pending OR in_progress` per tahap, sehingga Finishing & QC yang belum dimulai ikut terhitung. Fix: tiap card kini hanya menghitung task `in_progress`.

#### ✅ Fix Tabel Tanpa Horizontal Scroll (`ControlProduksiResource`)

Hapus kolom "No. Pesanan" (sudah ada di group header) dan "Kategori". Info kategori kini ditampilkan sebagai description emoji di kolom Produk (🏭/🧵/📦/🔧). Tabel jadi 4 kolom.

#### ✅ Fix WorkerInfolist — Namespace Filament v3

`Filament\Infolists\Components\Section` tidak ada di v3, diganti ke `Filament\Schemas\Components\Section`.

#### ✅ Sistem Upah Karyawan — Tracking & Audit Trail

**Tujuan**: Upah dihitung dari `wage_amount × quantity` per task selesai, harus bisa direkap & difilter per periode & karyawan.

**Perubahan:**

1. **Migration** `add_completed_at_to_production_tasks_table`: tambah kolom `completed_at` (timestamp nullable) — diisi `now()` otomatis saat "Tandai Selesai" diklik.
2. **`TaskActionController`**: action `done` kini set `completed_at = now()`.
3. **`Worker` model**: tambah accessor `total_earned` (all time) dan `monthly_earned` (bulan berjalan) berdasarkan `SUM(wage_amount × quantity)` task done.
4. **`WorkerPayrollResource`** (BARU) — halaman `/app/{tenant}/worker-payroll`:
   - Tabel rekap pekerjaan selesai: Karyawan, Tahap, Produk, No. Pesanan, Qty, Upah/pcs, Total Upah, Selesai Pada
   - Filter Periode (dari–sampai `completed_at`) dan filter Karyawan
   - Default pagination 25 per halaman
5. **`WorkerInfolist`**: tambah section "💰 Ringkasan Upah" (Upah Bulan Ini / All Time / Total Pcs) dan perluas section riwayat selesai dengan kolom upah & tanggal selesai.

#### Files Modified / Created

| File | Status |
|---|---|
| `app/Filament/Resources/ControlProduksis/ControlProduksiResource.php` | MODIFIED |
| `app/Filament/Resources/ControlProduksis/Widgets/ProduksiStats.php` | MODIFIED |
| `app/Filament/Resources/Workers/Schemas/WorkerInfolist.php` | MODIFIED |
| `app/Http/Controllers/TaskActionController.php` | MODIFIED |
| `app/Models/Worker.php` | MODIFIED |
| `app/Filament/Resources/WorkerPayrolls/WorkerPayrollResource.php` | NEW |
| `app/Filament/Resources/WorkerPayrolls/Pages/ManageWorkerPayrolls.php` | NEW |
| `database/migrations/2026_02_28_190706_add_completed_at_to_production_tasks_table.php` | NEW |

---

### 2026-03-01 (Sesi Lanjutan): Express Order, Monitor Produksi, Redesign Tabel, Sistem Pembayaran Cicilan

#### ✅ Fitur Express Order

Pesanan prioritas dengan biaya tambahan (diinput manual admin).

- **Migration**: tambah `is_express` (bool) + `express_fee` (int) ke tabel `orders`
- **Order Model**: keduanya masuk `$fillable` + `$casts`
- **OrderResource Form**: Toggle `is_express` dengan `live()` → tampilkan `express_fee` secara kondisional
- **OrderResource Table**: badge ⚡ EXPRESS merah inline di kolom No. Pesanan; sort Express-first lalu deadline
- **ControlProduksiResource**: badge EXPRESS di group header; `modifyQueryUsing` sort `is_express DESC` → `deadline ASC`

---

#### ✅ Monitor Produksi Full-Screen

Halaman tanpa navigasi admin untuk TV/monitor di ruang produksi.

- **URL**: `/monitor/{shop_id}` — public, tanpa auth
- **Auto-refresh**: 30 detik via `<meta http-equiv="refresh">`
- **Live clock**: JavaScript update tiap detik
- **Sidebar**: antrian item pending (Express-first, max 10)
- **Main cards**: item in-progress dengan deadline badge H-X, progress bar tahapan, task list color-coded, nama karyawan, badge ⚡ pulse merah untuk Express

| File | Status |
|---|---|
| `app/Http/Controllers/MonitorController.php` | NEW |
| `resources/views/monitor/produksi.blade.php` | NEW |
| `routes/web.php` | MODIFIED |

---

#### ✅ Redesign Tabel Daftar Pesanan — 4 Kolom Padat HTML

Dari 7 kolom terpisah → 4 kolom HTML kaya informasi dengan `->html()->state()`.

| Kolom | Konten |
|---|---|
| **Pesanan & Pelanggan** | No. pesanan bold + badge ⚡EXPRESS + nama (badge ungu) + WA icon + nomor |
| **Timeline** | Tanggal masuk + Deadline + badge Sisa X hari (merah/kuning/hijau) |
| **Finance** | Sisa tagihan (dari `SUM(payments)`) + Total + Dibayar Nx |
| **Produk & Status** | List item: Qty × Nama + badge kategori + status + progress bar ungu `#7F00FF` |

- Quick Filters: Belum Lunas, Deadline ≤3 Hari, Tipe Produk
- Tombol **Detail** kuning (eye icon) + dropdown Edit/Hapus
- CSS `vertical-align:top` untuk semua sel via `AdminPanelProvider` renderHook

---

#### ✅ Sistem Pembayaran Cicilan (Multi-Payment)

Menggantikan field `down_payment` tunggal agar DP bisa berkali-kali dengan tracking lengkap.

**Database:**
- Tabel `payments`: `order_id`, `amount`, `payment_date`, `payment_method` (cash/transfer/qris), `note`, `proof_image`, `recorded_by`
- Drop kolom `down_payment` + `dp_proof` dari `orders`

**Model & Logika:**
- `Payment` model: relasi ke Order + User; method `methodLabel()`
- `Order` model: relasi `hasMany Payment`; accessor `total_paid` (SUM) + `remaining_balance` (total_price - total_paid)

**UI:**
- `PaymentsRelationManager` → tab "Riwayat Pembayaran" di halaman Edit Pesanan
- Form: Jumlah, Tanggal, Metode, Catatan, **Upload foto bukti** (disk: public, dir: `payments/proofs`)
- Auto-fill `recorded_by = auth()->id()`
- Tabel: Tanggal | Nominal | Metode badge | Catatan | Preview foto | Dicatat Oleh

| File | Status |
|---|---|
| `app/Models/Payment.php` | NEW |
| `app/Models/Order.php` | MODIFIED |
| `app/Filament/Resources/Orders/RelationManagers/PaymentsRelationManager.php` | NEW |
| `app/Filament/Resources/Orders/OrderResource.php` | MODIFIED |
| `database/migrations/2026_03_01_*_create_payments_table.php` | NEW |
| `database/migrations/2026_03_01_*_remove_down_payment_from_orders_table.php` | NEW |
| `app/Providers/Filament/AdminPanelProvider.php` | MODIFIED (CSS vertical-align) |

---

#### 🔜 Rencana: Halaman Keuangan (Belum Diimplementasi)

Desain 3-tab sudah dikonfirmasi dari mockup.

**Header stat cards**: Total Piutang (Siap Diambil) | Uang Masuk Hari Ini | Saldo Kas Kecil (otomatis: `SUM(payments) - SUM(expenses)`)

**Tab 1 — Daftar Piutang & Penagihan**: order belum lunas, tombol Bayar/Pelunasan, tombol WhatsApp dengan pesan otomatis sisa tagihan

**Tab 2 — Riwayat Kas Masuk**: semua record `payments`, preview foto bukti, filter tanggal & status

**Tab 3 — Pengeluaran Operasional**: tabel `expenses` baru (keperluan bebas, nominal, penanggung jawab, foto struk)

**Perlu dibuat:**
- Migration + Model `Expense` (`shop_id`, `description`, `amount`, `expense_date`, `proof_image`, `recorded_by`)
- `KeuanganResource` Filament dengan 3 tab
- 3 stat widgets

