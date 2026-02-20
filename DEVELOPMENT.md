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
#### Refinement (2026-02-20, Rev 1)
- **Fix Double Input**: Memperbaiki logika `visible()` pada section "Produksi" agar tidak muncul di kategori lain.
- **Non-Produksi**: Menghapus section "Request Tambahan" dan memindahkan "Jenis Produk Supplier" ke baris bahan agar lebih intuitif.
- **Jasa**: Menyembunyikan field "Nama Produk Pesanan" di level atas dan mensinkronisasinya dengan field "Nama Jasa" agar tidak double input.

#### Refinement (2026-02-20, Rev 2)


