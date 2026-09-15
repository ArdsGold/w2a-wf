# Installation Guide

## Quick Start Installation

### Method 1: WordPress Admin Installation (Recommended)

1. **Download the Plugin**
   - Download the `word-to-article-importer.zip` file
   - Ensure you have the complete zip file with all folders intact

2. **Upload to WordPress**
   - Log in to your WordPress admin panel
   - Navigate to **Plugins → Add New**
   - Click the **"Upload Plugin"** button at the top
   - Choose the `word-to-article-importer.zip` file
   - Click **"Install Now"**

3. **Activate the Plugin**
   - After installation, click **"Activate Plugin"**
   - You will see a success message
   - Find **"Word Importer"** in your admin menu

### Method 2: Manual FTP Installation

1. **Extract the Plugin**
   - Extract the downloaded zip file
   - You should have a folder named `word-to-article-importer`

2. **Upload via FTP**
   - Connect to your server using FTP client (FileZilla, CyberDuck, etc.)
   - Navigate to `/wp-content/plugins/`
   - Upload the entire `word-to-article-importer` folder

3. **Activate from WordPress Admin**
   - Log in to WordPress admin
   - Go to **Plugins**
   - Find "Word to Article Importer" in the list
   - Click **"Activate"**

### Method 3: Direct File System Installation

1. **Access Your Server**
   - Use SSH, cPanel File Manager, or direct server access
   - Navigate to your WordPress installation directory

2. **Place Plugin Files**
   - Copy the `word-to-article-importer` folder to `/wp-content/plugins/`
   - Ensure all files and subdirectories are included

3. **Set Permissions**
   - Ensure the plugin folder has appropriate read permissions
   - Typically 755 for folders, 644 for files

4. **Activate Plugin**
   - Log in to WordPress admin
   - Navigate to **Plugins**
   - Activate "Word to Article Importer"

## System Requirements

### Minimum Requirements
- **WordPress**: 5.0 or higher
- **PHP**: 7.0 or higher
- **MySQL**: 5.6 or higher
- **PHP Extensions**: 
  - `zip` (required for .docx parsing)
  - `xml` (required for document processing)
  - `mbstring` (recommended for text processing)

### Recommended Requirements
- **WordPress**: Latest version (6.0+)
- **PHP**: 7.4 or higher (8.0+ recommended)
- **Memory Limit**: 128MB or higher
- **Max Upload Size**: 10MB or higher

### Server Configuration

#### PHP Configuration
Check your `php.ini` file for these settings:

```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 128M
```

#### WordPress Configuration
Add to `wp-config.php` if needed:

```php
define('WP_MEMORY_LIMIT', '128M');
```

## Verification

### Check Plugin Activation
1. Go to **Plugins** in WordPress admin
2. Verify "Word to Article Importer" is active
3. Check for "Word Importer" menu item in admin sidebar

### Test Basic Functionality
1. Navigate to **Word Importer**
2. You should see the import interface
3. Try uploading a test .docx file
4. Check if the interface responds correctly

### Check Database Tables
1. Access your database via phpMyAdmin or similar
2. Look for table `wp_wtai_import_history`
3. If it exists, the plugin activated successfully

## Troubleshooting Installation

### Plugin Not Appearing in Admin

**Problem**: Plugin doesn't show up after upload

**Solutions**:
- Verify all files were uploaded correctly
- Check folder structure matches expected layout
- Ensure plugin folder is named exactly `word-to-article-importer`
- Clear WordPress cache if using caching plugins
- Check browser cache (Ctrl+F5 to refresh)

### Activation Errors

**Problem**: "Plugin could not be activated because it triggered a fatal error"

**Common Causes**:
- PHP version too old (requires 7.0+)
- Missing PHP extensions (zip, xml)
- Memory limit too low
- File permission issues

**Solutions**:
```php
// Add to wp-config.php for debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check `wp-content/debug.log` for specific error messages.

### Permission Issues

**Problem**: Cannot upload files or plugin won't activate

**Solutions**:
- Set correct permissions:
  ```bash
  chmod 755 wp-content/plugins/
  chmod 755 wp-content/plugins/word-to-article-importer/
  chmod 644 wp-content/plugins/word-to-article-importer/*.php
  ```
- Ensure web server has write access to temporary directories
- Check ownership of files (should match web server user)

### .docx Files Not Processing

**Problem**: Files upload but don't process

**Solutions**:
- Verify PHP zip extension is enabled: `php -m | grep zip`
- Check temporary directory permissions
- Increase PHP memory limit
- Verify file is valid .docx format (not .doc)

## Uninstallation

### Standard Uninstallation
1. Go to **Plugins** in WordPress admin
2. Deactivate "Word to Article Importer"
3. Click **Delete** to remove files

### Complete Removal (Including Data)
1. Deactivate and delete plugin
2. Remove database table via SQL:
   ```sql
   DROP TABLE IF EXISTS wp_wtai_import_history;
   ```
3. Remove plugin options via SQL:
   ```sql
   DELETE FROM wp_options WHERE option_name LIKE 'wtai_%';
   ```

## Updates

### Automatic Updates
- If distributed via WordPress.org, updates will appear in WordPress admin
- Click "Update Now" when prompted

### Manual Updates
1. Deactivate current version
2. Download new version
3. Replace files via FTP or upload
4. Reactivate plugin
5. Database migrations will run automatically

## Support

If you encounter installation issues:
1. Check this guide first
2. Review debug logs
3. Verify system requirements
4. Check WordPress codex for common issues
5. Report issues with detailed error messages

## Security Notes

- Always download from trusted sources
- Keep plugin updated
- Regular backups before updates
- Review file permissions
- Monitor for unusual activity