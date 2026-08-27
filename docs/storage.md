# Multi-Disk Filesystem & Cloud Storage

Spinx provides a clean, unified filesystem abstraction (`Storage::`) supporting local disks and S3-compatible cloud object storage providers (**AWS S3**, **Cloudflare R2**, **MinIO**, **DigitalOcean Spaces**, **Wasabi**).

---

## ⚡ Key Highlights
- **Zero Heavy SDK Dependencies:** Uses native AWS Signature V4 over HTTP/cURL.
- **Signed Temporary URLs:** Generate time-limited secure download links (`temporaryUrl()`).
- **Path Traversal & Null-Byte Defense:** Built-in jailing and traversal sanitization preventing unauthorized file access.

---

## 🚀 Quick Usage

### 1. Basic File Operations
```php
use Spinx\Filesystem\Storage;

// Write string content to default disk
Storage::put('documents/report.txt', 'Quarterly Financial Summary 2026');

// Check existence and read content
if (Storage::exists('documents/report.txt')) {
    $content = Storage::get('documents/report.txt');
}

// Delete one or multiple files
Storage::delete('documents/report.txt');
Storage::delete(['file1.txt', 'file2.txt']);
```

### 2. Multi-Disk Switching
```php
// Write to Cloudflare R2 / AWS S3
Storage::disk('s3')->put('avatars/user_42.png', $imageBinary);

// Read from local private disk
$contents = Storage::disk('local')->get('backups/db.sql');
```

### 3. Secure Temporary Signed URLs
```php
// Generate a signed URL valid for 2 hours
$url = Storage::disk('s3')->temporaryUrl('contracts/nda_102.pdf', now()->addHours(2));

// Resulting URL contains HMAC-SHA256 signature and expiration timestamp:
// https://bucket.s3.us-east-1.amazonaws.com/contracts/nda_102.pdf?X-Amz-Expires=7200&...
```

---

## ⚙️ Configuration (`config/filesystem.php`)

```php
return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'url'    => env('APP_URL') . '/storage',
        ],

        's3' => [
            'driver'   => 's3',
            'key'      => env('AWS_ACCESS_KEY_ID'),
            'secret'   => env('AWS_SECRET_ACCESS_KEY'),
            'region'   => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket'   => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'), // Optional: Cloudflare R2 / MinIO URL
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],
    ],
];
```
