# laravel-photo-frame

A Laravel 10 application that lets users drag-and-drop a photo onto a Vue.js frontend, applies a configurable photo frame (managed via a Filament admin panel), and returns two outputs: **fill (cover)** and **contain** — both stored on DigitalOcean Spaces.

---

## Features

- **Drag-and-drop frontend** — built with Vue 3 + Vite, integrated into Laravel (no standalone SPA).
- **Admin panel** — FilamentPHP admin to upload frame images and configure per-frame settings (name, active state, cover anchor point).
- **Two output modes**:
  - **Fill / Cover** — photo is cropped to exactly fill the frame canvas at the chosen anchor point; frame overlaid on top.
  - **Contain** — photo is scaled down to fit inside the frame canvas (never upscaled, letter-boxed centered); frame overlaid on top.
- **DigitalOcean Spaces** default filesystem via the S3-compatible driver.
- **Spatie Laravel Backup** configured to back up to Spaces.
- **Intervention Image v3** for all image manipulation (GD driver).
- Output files named `{id}_{original_basename}_{mode}.{ext}` (e.g. `3_DSCE123_fill.jpg`).

---

## Requirements

- PHP 8.1+
- Composer 2
- Node 18+
- A DigitalOcean Spaces bucket (or any S3-compatible store)
- MySQL / PostgreSQL / SQLite

---

## Installation

```bash
# 1. Clone the repository
git clone <repo-url> laravel-photo-frame
cd laravel-photo-frame

# 2. Install PHP dependencies
composer install

# 3. Install Node dependencies
npm install

# 4. Copy environment file and set values
cp .env.example .env
php artisan key:generate

# 5. Configure your database and DigitalOcean Spaces in .env
#    DB_*, DO_SPACES_KEY, DO_SPACES_SECRET, DO_SPACES_REGION,
#    DO_SPACES_BUCKET, DO_SPACES_ENDPOINT, DO_SPACES_CDN_URL

# 6. Run migrations and seeders
php artisan migrate --seed

# 7. Build frontend assets
npm run build
# or for development:
npm run dev

# 8. Start the development server
php artisan serve
```

---

## Configuration

### DigitalOcean Spaces

Add the following to your `.env`:

```dotenv
FILESYSTEM_DISK=spaces

DO_SPACES_KEY=your-access-key
DO_SPACES_SECRET=your-secret-key
DO_SPACES_REGION=nyc3
DO_SPACES_BUCKET=your-bucket-name
DO_SPACES_ENDPOINT=https://nyc3.digitaloceanspaces.com
DO_SPACES_CDN_URL=https://your-bucket.nyc3.cdn.digitaloceanspaces.com
```

### Admin Panel

Visit `/admin` to access the FilamentPHP admin panel. You will need to create an admin user first:

```bash
php artisan make:filament-user
```

In the admin panel, navigate to **Photo Frames** to:
- Upload a frame PNG image (stored to `frames/` in Spaces).
- Set the **cover anchor point** (top-left, top, top-right, left, center, right, bottom-left, bottom, bottom-right).
- Mark the frame as **active** — only one frame is active at a time.

### Image Processing

The image processing service (`App\Services\PhotoFrameService`) applies the active photo frame:

| Mode    | Description |
|---------|-------------|
| Fill    | Photo is scaled & cropped (using the configured anchor point) to exactly cover the frame canvas; the frame PNG is overlaid on top. |
| Contain | Photo is scaled *down* (never up) to fit within the frame canvas, centered, letter-boxed; the frame PNG is overlaid on top. |

Outputs are stored to `outputs/` in Spaces, accessible via the URLs returned by the API.

---

## API Endpoints

| Method | URI                     | Description |
|--------|-------------------------|-------------|
| GET    | `/api/active-frame`     | Returns info about the currently active photo frame. |
| POST   | `/api/process-image`    | Accepts a multipart `image` upload; returns `fill_url` and `contain_url`. |

### POST `/api/process-image`

**Request** (multipart/form-data):

| Field  | Type | Notes |
|--------|------|-------|
| image  | file | JPEG, PNG, GIF, or WebP; max 20 MB |

**Response** (JSON):

```json
{
  "id": 1,
  "original_filename": "DSCE123.jpg",
  "fill_url": "https://cdn.example.com/outputs/1_DSCE123_fill.jpg",
  "contain_url": "https://cdn.example.com/outputs/1_DSCE123_contain.jpg"
}
```

---

## Testing

```bash
php artisan test
```

Tests use SQLite `:memory:` and `Storage::fake('spaces')`.

---

## Backups

Backups via `spatie/laravel-backup` are stored to the `spaces` disk:

```bash
php artisan backup:run
```
