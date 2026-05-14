# Napi CDN Adapter - ImageKit-like Interface

Simple, clean interface that makes working with Napi CDN as easy as ImageKit.

## ✨ Features

- **ImageKit-compatible API** - Drop-in replacement
- **Laravel Storage integration** - Works with `Storage::disk('cdn')`
- **Filament FileUpload support** - Native integration
- **Global helpers** - `cdn()` and `napi_cdn()`
- **Facade support** - `NapiCdn::`
- **Multiple config options** - Both `url` and `endpoint_url` supported

## 🚀 Installation

The adapter is automatically available when you install the cdn-sdk package.

## ⚙️ Configuration

### Option 1: Standard CDN Config
```php
// .env
CDN_URL=https://delivery.napicompany.com
CDN_API_KEY=your-api-key

// config/filesystems.php
'cdn' => [
    'driver' => 'cdn',
    'url' => env('CDN_URL'),
    'api_key' => env('CDN_API_KEY'),
],
```

### Option 2: ImageKit-style Config
```php
// .env
CDN_ENDPOINT_URL=https://delivery.napicompany.com
CDN_API_KEY=your-api-key

// config/filesystems.php
'cdn' => [
    'driver' => 'cdn',
    'endpoint_url' => env('CDN_ENDPOINT_URL'),
    'api_key' => env('CDN_API_KEY'),
],
```

## 📝 Usage Examples

### 1. Basic Upload
```php
// Using helper
$url = cdn()->upload($file, 'logo.jpg', 'branding');

// Using facade
$url = NapiCdn::upload($file, 'logo.jpg', 'branding');

// Using class
$adapter = new NapiCdnAdapter();
$url = $adapter->upload($file, 'logo.jpg', 'branding');
```

### 2. Generate URLs
```php
// Simple path
$url = cdn()->url('branding/logo.jpg');

// ImageKit-style options array
$url = cdn()->url(['path' => 'branding/logo.jpg']);
$url = cdn()->url(['src' => 'branding/logo.jpg']);
```

### 3. File Operations
```php
// Check if exists
if (cdn()->exists('branding/logo.jpg')) {
    echo 'File exists!';
}

// Delete file
cdn()->delete('branding/old-logo.jpg');

// List files
$files = cdn()->listFiles('branding');

// Get file size
$size = cdn()->getFileSize('branding/logo.jpg');

// Download file
$contents = cdn()->download('branding/logo.jpg');

// Move/rename
cdn()->move('branding/old.jpg', 'branding/new.jpg');

// Copy
cdn()->copy('branding/logo.jpg', 'backup/logo.jpg');
```

### 4. Filament FileUpload
```php
// Works natively - no complex callbacks needed!
FileUpload::make('logomarca')
    ->label('Logo')
    ->disk('cdn')
    ->directory('branding')
    ->visibility('public')
    ->image()
    ->required()
    ->maxSize(2 * 1024)
    ->acceptedFileTypes(['image/png', 'image/jpg', 'image/jpeg'])
```

## 🆚 Comparison with ImageKit

| Feature | ImageKit | Napi CDN Adapter |
|---------|----------|------------------|
| Upload | `$imagekit->upload($file, 'name.jpg', 'folder')` | `cdn()->upload($file, 'name.jpg', 'folder')` ✅ |
| URL | `$imagekit->url(['path' => 'file.jpg'])` | `cdn()->url(['path' => 'file.jpg'])` ✅ |
| Laravel Integration | ❌ Limited | ✅ **Full Storage disk** |
| Filament Support | ❌ Complex setup | ✅ **Native integration** |
| File Operations | ❌ Basic | ✅ **Complete** |

## 🎯 Migration from ImageKit

Replace ImageKit calls with equivalent cdn() calls:

```php
// Before (ImageKit)
$imagekit = new ImageKit($publicKey, $privateKey, $urlEndpoint);
$url = $imagekit->upload($file, 'logo.jpg');
$fileUrl = $imagekit->url(['path' => 'logo.jpg']);

// After (Napi CDN Adapter)
$url = cdn()->upload($file, 'logo.jpg');
$fileUrl = cdn()->url('logo.jpg');
```

## 🔧 Advanced Usage

### Custom Disk
```php
// Use different disk
$adapter = new NapiCdnAdapter('custom-cdn');
```

### Batch Operations
```php
$files = ['logo.jpg', 'banner.jpg', 'favicon.ico'];
foreach ($files as $file) {
    if (cdn()->exists($file)) {
        $url = cdn()->url($file);
        echo "File: {$file} -> {$url}\n";
    }
}
```

## 🚨 Important Notes

- File names may include timestamps added by the CDN backend
- URLs are generated automatically using Laravel Storage
- All operations use the underlying Flysystem adapter for reliability
- The adapter maintains full compatibility with Laravel ecosystem

## 📞 Support

This adapter provides a clean, ImageKit-like interface while leveraging the full power of Laravel Storage and Flysystem. No more complex callbacks or manual URL generation needed!