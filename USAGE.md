# Usage Guide

## Getting Started

### First Time Setup

1. **Access the Plugin**
   - Log in to WordPress admin
   - Navigate to **Word Importer** in the left sidebar
   - You'll see the main import interface

2. **Configure Default Settings**
   - Go to **Word Importer → Settings**
   - Set your preferred defaults for content processing
   - Choose default author and category
   - Configure scheduling preferences
   - Click **"Save Settings"**

## Basic Import Workflow

### Step 1: Prepare Your Documents

**Best Practices for .docx Files**:
- Use proper Word heading styles (Heading 1, Heading 2, etc.)
- Keep formatting simple and consistent
- Avoid complex tables, columns, or advanced features
- Use standard fonts and formatting
- Test with a single document first

**Document Structure Tips**:
```
Title/Heading 1
├── Heading 2
│   ├── Paragraph content
│   └── Lists (numbered or bulleted)
└── Heading 2
    └── More content
```

### Step 2: Upload Documents

**Method 1: Drag and Drop**
1. Drag .docx files from your computer
2. Drop them onto the upload zone
3. Files will appear in the file list

**Method 2: Click to Browse**
1. Click the upload zone
2. Select files from your computer
3. Multiple files can be selected at once

**File Management**:
- View uploaded files in the file list
- See file sizes for reference
- Remove individual files by clicking the X icon
- Clear all files by refreshing the page

### Step 3: Configure Content Processing

**Convert Numbered to Dotted Lists**
- **When to use**: If you prefer bullet points over numbered lists
- **Effect**: Converts all numbered lists (1, 2, 3...) to bullet points
- **Example**: 
  - Before: `1. First item, 2. Second item`
  - After: `• First item, • Second item`

**Remove Bold Except Headings**
- **When to use**: To clean up excessive bold formatting
- **Effect**: Removes bold from paragraphs but keeps headings bold
- **Example**:
  - Before: `**Bold paragraph**` and `**Bold heading**`
  - After: `Regular paragraph` and `**Bold heading**`

**Automatic Image Insertion**
- **When to use**: To add visual content automatically
- **Effect**: Inserts relevant images from your media library
- **Methods**:
  - **By Title**: Matches image titles to content keywords
  - **By Description**: Uses image descriptions for matching
  - **Both**: Combines title and description matching

### Step 4: Set Publish Settings

**Default Author**
- Choose who will be credited as the post author
- Only users with appropriate permissions are shown
- Useful for multi-author blogs

**Default Category**
- Select the category for imported posts
- Can be changed individually after import
- Useful for content organization

**Publish Schedule Options**

**Immediate Publishing**
- All documents publish immediately upon import
- Best for content that should go live right away
- No date configuration needed

**Date Range Scheduling**
- Documents are scheduled evenly between start and end dates
- Example: August 1-7 with 10 documents
- System calculates intervals automatically
- Documents beyond end date publish immediately

**Specific Date Start**
- All documents scheduled consecutively from start date
- Example: Start August 1 with daily interval
- Results in posts on August 1, 2, 3, etc.
- No end date limitation

**Interval Options**
- **Daily**: One post per day
- **Weekly**: One post per week (every 7 days)
- **Monthly**: One post per month

### Step 5: Import and Review

**Start Import**
1. Click **"Import Documents"** button
2. Watch progress indicator
3. Wait for completion message

**Review Results**
- **Successful Imports**: Show post IDs and publish dates
- **Failed Imports**: Display error messages for troubleshooting
- **Summary**: Total count of successful vs failed imports

**Post-Import Actions**
- Click "Edit Post" links to review individual posts
- Make manual adjustments if needed
- Check formatting and image placement
- Verify publish dates are correct

## Advanced Features

### Media Library Integration

**Browse Available Images**
1. Use the media library sidebar
2. Click **"Load Images"** to see available images
3. Search by keyword to find specific images
4. Review image titles and descriptions

**Optimize Image Matching**
- Use descriptive image titles
- Add detailed image descriptions
- Include relevant keywords in both
- Organize media library with consistent naming

**Image Placement**
- Images are inserted after relevant headings
- Matching is based on content analysis
- Duplicate images are avoided
- Images include captions when available

### Bulk Import Strategies

**Content Series Import**
1. Prepare all documents in a series
2. Set up date range for the entire series
3. Choose appropriate interval (daily/weekly)
4. Import all at once for automatic scheduling

**Category-Based Import**
1. Group documents by category
2. Import each category separately
3. Change default category between imports
4. Maintain organized content structure

**Author-Specific Import**
1. Assign documents to specific authors
2. Import by author for proper attribution
3. Useful for multi-author blogs
4. Maintains author consistency

### Scheduling Best Practices

**Daily Content Strategy**
- Choose "Specific Date Start" with daily interval
- Set start date to begin your content calendar
- Import all content at once
- System handles daily scheduling automatically

**Weekly Content Strategy**
- Use weekly interval for consistent posting
- Choose start date for first post
- Import all weekly content in one batch
- Reduces manual scheduling work

**Monthly Content Strategy**
- Set monthly interval for long-term planning
- Perfect for evergreen content
- Import quarterly or yearly content
- Maintain consistent monthly publishing

**Campaign-Based Scheduling**
- Use date range for specific campaigns
- Set campaign start and end dates
- Import all campaign content
- Ensures content coverage during campaign period

## Troubleshooting Common Issues

### Import Problems

**Files Not Uploading**
- Check file size limits (WordPress/server)
- Verify .docx format (not .doc)
- Ensure proper file permissions
- Try smaller files first

**Processing Errors**
- Verify document is not corrupted
- Check for complex formatting
- Test with simpler document
- Review error messages for specifics

**Formatting Issues**
- Use proper Word heading styles
- Simplify document formatting
- Check content processing options
- Manual cleanup may be needed

### Scheduling Problems

**Incorrect Publish Dates**
- Verify server time settings
- Check WordPress timezone configuration
- Test with immediate publishing first
- Review date range calculations

**Posts Not Publishing**
- Check WordPress cron functionality
- Verify user permissions for scheduling
- Review post status after import
- Manual publish if needed

### Image Matching Issues

**No Images Inserted**
- Verify images exist in media library
- Check image titles are descriptive
- Try different matching methods
- Ensure auto-insert is enabled

**Irrelevant Images**
- Improve image titles and descriptions
- Use more specific keywords
- Try different matching method
- Manual image adjustment may be needed

## Tips and Best Practices

### Document Preparation
- **Use Heading Styles**: Always use Word's built-in heading styles
- **Keep It Simple**: Avoid complex formatting and features
- **Test First**: Always test with a single document
- **Consistent Naming**: Use descriptive filenames for easier tracking

### Content Organization
- **Plan Categories**: Organize documents by category before import
- **Author Assignment**: Know which author should receive credit
- **Scheduling Strategy**: Plan your content calendar in advance
- **Image Preparation**: Have relevant images ready in media library

### Quality Control
- **Review First Import**: Check first imported post carefully
- **Adjust Settings**: Fine-tune settings based on results
- **Manual Review**: Spot-check imported posts
- **Backup First**: Always backup before bulk imports

### Performance Optimization
- **Batch Size**: Import 10-20 documents at a time for best performance
- **Server Resources**: Ensure adequate memory and execution time
- **Off-Peak Hours**: Schedule large imports during low traffic periods
- **Monitor Performance**: Watch server resources during imports

## Integration with Other Plugins

### SEO Plugins
- **Yoast SEO**: Configure default SEO settings after import
- **All in One SEO**: Set up template variables for imported posts
- **Rank Math**: Configure automatic SEO analysis

### Caching Plugins
- **W3 Total Cache**: Clear cache after imports
- **WP Super Cache**: Purge cache for new posts
- **WP Rocket**: Auto-cache new posts

### Editorial Workflow
- **Edit Flow**: Use for editorial calendar integration
- **PublishPress**: Manage approval workflows
- **CoSchedule**: Editorial calendar integration

## Maintenance

### Regular Tasks
- **Review Import History**: Check for failed imports
- **Clean Up Media Library**: Remove unused images
- **Update Settings**: Adjust defaults as needed
- **Monitor Performance**: Watch for slowdowns

### Database Maintenance
- **Optimize Tables**: Regular database optimization
- **Clean History**: Clear old import history
- **Backup Database**: Regular backups before bulk operations

### Plugin Updates
- **Test Updates**: Test on staging environment first
- **Review Changelog**: Check for breaking changes
- **Backup First**: Always backup before updating
- **Monitor After Update**: Check functionality after updates

## Support Resources

### Documentation
- **README.md**: Overview and features
- **INSTALLATION.md**: Installation guide
- **USAGE.md**: This comprehensive usage guide

### Common Issues
- Check WordPress debug logs for errors
- Review system requirements
- Verify file permissions
- Test with simpler documents

### Getting Help
- Report issues with detailed error messages
- Include WordPress and PHP versions
- Provide sample .docx file if possible
- Describe expected vs actual behavior