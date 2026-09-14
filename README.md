# Word to Article Importer

A WordPress plugin that imports .docx files as WordPress articles with advanced formatting options, bulk upload capabilities, scheduled publishing, and automatic image insertion.

## Features

- **Bulk Document Import**: Upload multiple .docx files at once for efficient content migration
- **Content Processing**: 
  - Convert numbered lists to bullet points
  - Remove bold formatting while preserving heading styles
  - Maintain document structure and hierarchy
  - Specify maximum number of images per post (0-10)
- **Scheduled Publishing**: 
  - Set consecutive publish dates across date ranges
  - Choose intervals (daily, weekly, monthly)
  - Support for specific start dates or date ranges
- **Automatic Image Insertion**: 
  - Match images from media library based on content
  - Use image titles, descriptions, or both for matching
  - Intelligent keyword-based image selection
  - Specify maximum number of images per post (0-10)
- **User-Friendly Interface**: Drag-and-drop file upload with real-time feedback
- **Import History**: Track all imports with detailed logs
- **Server Information**: Display PHP server configuration and upload limits
- **Error Handling**: Comprehensive error codes and debugging information

## Installation

### Method 1: Manual Installation

1. Download the plugin zip file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin"
4. Select the zip file and install
5. Activate the plugin

### Method 2: FTP Installation

1. Extract the plugin zip file
2. Upload the `word-to-article-importer` folder to `/wp-content/plugins/`
3. Go to WordPress Admin → Plugins
4. Activate "Word to Article Importer"

### Method 3: Direct Installation

1. Clone or download this repository
2. Place the `word-to-article-importer` folder in your WordPress plugins directory
3. Activate the plugin through WordPress admin

## Usage

### Basic Import

1. Navigate to **Word Importer** in the WordPress admin menu
2. Drag and drop .docx files or click to browse
3. Configure content processing options:
   - Toggle numbered list conversion
   - Enable/disable bold removal
   - Set automatic image insertion
4. Choose publish settings (author, category, schedule)
5. Click "Import Documents"

### Advanced Features

#### Content Processing

**Numbered to Dotted Lists**: Converts all numbered lists (1, 2, 3...) to bullet points for consistent styling.

**Remove Bold Except Headings**: Removes bold formatting from regular text while preserving heading styles for better readability.

**Automatic Image Insertion**: Intelligently inserts relevant images from your media library based on:
- Content keywords and phrases
- Heading text matching
- Image titles and/or descriptions
- Configurable maximum images per post (0-10)

#### Scheduled Publishing

**Immediate Publishing**: All documents are published immediately upon import.

**Date Range Scheduling**: Documents are scheduled evenly between start and end dates.
- Example: August 1 to August 7 with 10 documents = posts scheduled every ~17 hours

**Specific Date Start**: All documents are scheduled consecutively from a chosen start date.
- Example: Start August 1 with daily interval = posts on August 1, 2, 3, etc.

**Interval Options**:
- Daily: One post per day
- Weekly: One post per week  
- Monthly: One post per month

### Settings

Configure default settings in **Word Importer → Settings**:
- Content processing defaults
- Default author and category
- Scheduling preferences
- Image matching methods

## Technical Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- ZIP extension enabled (for .docx parsing)
- Appropriate file upload permissions

## File Structure

```
word-to-article-importer/
├── word-to-article-importer.php    # Main plugin file
├── includes/
│   ├── class-docx-parser.php       # DOCX file parsing
│   ├── class-content-processor.php # Content transformation
│   └── class-image-matcher.php     # Image matching logic
├── admin/
│   ├── class-admin-page.php        # Admin functionality
│   └── views/
│       ├── import-page.php         # Import interface
│       └── settings-page.php       # Settings interface
├── assets/
│   ├── css/
│   │   └── admin.css               # Admin styles
│   └── js/
│       └── admin.js                # Admin JavaScript
└── README.md                        # Documentation
```

## Implications and Delimitations

### Implications

**Performance Considerations**:
- Large .docx files (>5MB) may take longer to process
- Bulk imports with many files can be resource-intensive
- Image matching queries may impact performance with large media libraries

**Content Accuracy**:
- Complex document formatting may not be perfectly preserved
- Tables and advanced Word features are not fully supported
- Image matching is based on text analysis and may not always be contextually perfect

**Scheduling Limitations**:
- WordPress cron limitations may affect precise scheduling
- Time zone differences can affect publish times
- Server time settings influence actual publish dates

**Media Library Dependencies**:
- Automatic image insertion requires pre-uploaded images
- Image quality and relevance depend on proper media library organization
- Duplicate image insertion prevention is based on simple tracking

### Delimitations

**Supported Features**:
- ✅ Basic text content (paragraphs, headings, lists)
- ✅ Simple formatting (bold, italic, underline)
- ✅ Basic list structures
- ✅ Heading hierarchy (H1-H6)
- ✅ Bulk file upload
- ✅ Scheduled publishing

**Unsupported Features**:
- ❌ Complex tables and nested tables
- ❌ Advanced Word formatting (styles, themes, templates)
- ❌ Images embedded within .docx files
- ❌ Footnotes, endnotes, and citations
- ❌ Page breaks and section formatting
- ❌ Track changes and comments
- ❌ Macros and embedded objects
- ❌ Headers and footers
- ❌ Columns and text boxes
- ❌ SmartArt and diagrams
- ❌ Custom fonts and advanced typography

**File Format Limitations**:
- Only .docx format is supported (not .doc or other formats)
- Password-protected files cannot be processed
- Corrupted or malformed .docx files will fail

**WordPress Integration Limits**:
- Custom post types are not supported (only standard posts)
- Advanced SEO plugins may require manual configuration
- Theme-specific shortcodes are not automatically applied

## Alternative Approaches

If this plugin doesn't fully meet your needs, consider these alternatives:

### Option 1: Enhanced Document Processing
- **Approach**: Integrate PHPWord library for more robust .docx parsing
- **Benefits**: Better support for complex formatting, tables, and images
- **Trade-offs**: Larger plugin size, external dependency management
- **Implementation**: Add PHPWord via Composer and update parser class

### Option 2: Content API Integration
- **Approach**: Use Microsoft Word Online API or Google Docs API
- **Benefits**: Cloud-based processing, better format support, real-time collaboration
- **Trade-offs**: Requires API keys, internet connection, potential costs
- **Implementation**: Add OAuth authentication and API integration

### Option 3: Manual Import with Enhanced Preview
- **Approach**: Add document preview and manual editing before import
- **Benefits**: Complete control over content, error prevention
- **Trade-offs**: More time-consuming per document, less automation
- **Implementation**: Add preview interface with inline editing

### Option 4: Specialized Migration Service
- **Approach**: Use dedicated content migration services
- **Benefits**: Professional handling, complex format support, custom workflows
- **Trade-offs**: Cost, dependency on external service, data privacy concerns
- **Examples**: CMS2CMS, LitExtension, automated migration services

## Troubleshooting

### Import Fails
- Check PHP error logs for specific error messages
- Verify .docx file is not corrupted
- Ensure sufficient server memory for processing
- Check file upload permissions

### Images Not Inserting
- Verify images exist in media library
- Check image titles and descriptions are descriptive
- Try different matching methods (title vs description)
- Ensure media library is accessible

### Scheduling Issues
- Verify WordPress cron is functioning
- Check server time settings
- Confirm user has permission to schedule posts
- Test with immediate publishing first

### Formatting Issues
- Complex documents may require manual cleanup
- Check that headings use proper Word heading styles
- Verify list formatting in original document
- Test content processing options individually

## Support and Development

### Contributing
Contributions are welcome! Please follow these guidelines:
- Follow WordPress coding standards
- Add comments for complex functionality
- Test thoroughly before submitting
- Include documentation for new features

### Reporting Issues
When reporting issues, please include:
- WordPress version
- PHP version
- Plugin version
- Specific error messages
- Sample .docx file (if possible)

### Feature Requests
Feature requests should include:
- Clear description of desired functionality
- Use cases and benefits
- Potential implementation approach
- Priority level

## License

This plugin is licensed under GPL v2 or later.

## Credits

Developed for WordPress content management and document import automation.

## Error Codes

The plugin uses specific error codes for debugging and troubleshooting:

| Error Code | Description | Severity |
|------------|-------------|----------|
| WTAI_001 | File not found or inaccessible | Error |
| WTAI_002 | File upload failed | Error |
| WTAI_003 | Invalid file type | Error |
| WTAI_004 | File size exceeds limit | Error |
| WTAI_005 | Document parsing failed | Error |
| WTAI_006 | Invalid document structure | Error |
| WTAI_007 | XML parsing failed | Error |
| WTAI_008 | Temporary directory creation failed | Error |
| WTAI_009 | Content processing failed | Error |
| WTAI_010 | Image matching failed | Warning |
| WTAI_011 | Post creation failed | Error |
| WTAI_012 | Permission denied | Error |
| WTAI_013 | Required PHP extension missing | Error |
| WTAI_014 | Memory limit exceeded | Error |
| WTAI_015 | Execution timeout | Error |
| WTAI_016 | Database error | Error |
| WTAI_017 | Invalid settings | Error |
| WTAI_018 | Schedule calculation failed | Warning |
| WTAI_019 | Media library access failed | Warning |
| WTAI_999 | Unknown error occurred | Error |

Error logs can be viewed and managed from the plugin settings page.

## Changelog

### Version 1.1.0
- Added maximum images per post setting (0-10)
- Added PHP server information display
- Added upload limits indicator
- Added comprehensive error codes and debugging
- Enhanced error handling and logging
- Improved date input box styling

### Version 1.0.0
- Initial release
- Bulk .docx file import
- Content processing options
- Scheduled publishing
- Automatic image insertion
- Import history tracking
- Admin interface with drag-and-drop upload