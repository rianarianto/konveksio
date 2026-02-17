# Konveksio

**Konveksio** is a Multi-Outlet Convection Management System built with Laravel 12, Filament v3, and Livewire. It handles multi-tenancy for different shops/branches, role-based access control (Owner, Admin, Designer), and order management.

## System Requirements

- PHP 8.2+
- Composer
- Node.js & NPM
- MySQL

## Installation & Setup

Follow these steps to set up the project on a new machine:

1.  **Clone the Repository**
    ```bash
    git clone https://github.com/rianarianto/konveksio.git
    cd konveksio
    ```

2.  **Install PHP Dependencies**
    ```bash
    composer install
    ```

3.  **Install Frontend Dependencies**
    ```bash
    npm install
    npm run build
    ```

4.  **Environment Setup**
    - Copy the example environment file:
      ```bash
      cp .env.example .env
      ```
    - Open `.env` and configure your database settings:
      ```env
      DB_CONNECTION=mysql
      DB_HOST=127.0.0.1
      DB_PORT=3306
      DB_DATABASE=konveksio
      DB_USERNAME=root
      DB_PASSWORD=
      ```

5.  **Generate App Key**
    ```bash
    php artisan key:generate
    ```

6.  **Database Migration & Seeding**
    - Run migrations and seed the database with default roles and users:
      ```bash
      php artisan migrate --seed
      ```

7.  **Link Storage**
    ```bash
    php artisan storage:link
    ```

8.  **Run the Application**
    ```bash
    php artisan serve
    ```
    Access the app at `http://localhost:8000/app`.

## Default Access Credentials

After seeding, you can log in with the following accounts:

| Role | Email | Password | Scope |
| :--- | :--- | :--- | :--- |
| **Owner** | `owner@konveksio.test` | `password` | Global Access (All Shops) |
| **Admin** | `admin@jakarta.test` | `password` | Shop Specific (Jakarta) |
| **Designer** | `designer@jakarta.test` | `password` | Shop Specific (Jakarta) |
| **Admin** | `admin@bandung.test` | `password` | Shop Specific (Bandung) |

## Development Notes

- **Multi-Tenancy**: The system uses a single-database multi-tenancy approach. Data is scoped by `shop_id`.
- **Filament Panel**: Located at `/app`. The default `/admin` path has been renamed.
