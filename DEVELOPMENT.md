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
