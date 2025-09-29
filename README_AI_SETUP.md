# LIMSys AI-Powered Version Control Setup

## Overview
LIMSys now includes AI-powered version control that automatically detects document similarities and manages versions intelligently.

## Features
- **Text Extraction**: Extracts text content from PDF, DOC, DOCX, and TXT files
- **Similarity Analysis**: Uses cosine similarity and Jaccard similarity algorithms
- **Automatic Versioning**: Documents with ≥85% similarity are stored as new versions
- **Smart Detection**: Combines content similarity (80%) and title similarity (20%) for better matching

## Installation

### Option 1: Full AI Features (Recommended)
Install the required PHP libraries using Composer:

```bash
# Install Composer if not already installed
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install dependencies
composer install
```

This will install:
- `phpoffice/phpword` for Word document text extraction
- `smalot/pdfparser` for PDF text extraction

### Option 2: Basic Features (TXT files only)
If you cannot install Composer dependencies, the system will automatically fall back to basic text extraction for TXT files only.

## Database Requirements
Add these columns to your `documents` table:

```sql
ALTER TABLE documents ADD COLUMN extracted_text LONGTEXT;
ALTER TABLE documents ADD COLUMN updated_at TIMESTAMP NULL;
```

## How It Works

### 1. Text Extraction
When a document is uploaded, the system:
- Checks if the file format is supported (PDF, DOC, DOCX, TXT)
- Extracts and cleans the text content
- Normalizes the text for comparison

### 2. Similarity Analysis
The system compares the new document with existing documents by:
- Computing cosine similarity of text content
- Computing Jaccard similarity for word overlap
- Checking title similarity
- Combining scores with weighted average (80% content, 20% title)

### 3. Version Detection
- **≥85% similarity**: Stored as new version of existing document
- **<85% similarity**: Stored as completely new document

### 4. User Feedback
Users receive clear messages:
- "Stored as new version (Version X) - Y% similarity detected"
- "Stored as new document - Y% similarity detected but below 85% threshold"

## Configuration

### Similarity Threshold
To change the 85% threshold, modify line 149 in `upload.php`:
```php
if ($best_similarity >= 85) { // Change this value
```

### Similarity Weights
To adjust content vs title similarity weights, modify lines 138-150 in `upload.php`:
```php
$combined_similarity = ($similarity * 0.8) + ($title_similarity * 0.2);
```

## Supported File Formats

### With Full Dependencies
- **PDF**: Complete text extraction
- **DOC/DOCX**: Microsoft Word documents
- **TXT**: Plain text files

### Fallback Mode (without dependencies)
- **TXT**: Plain text files only

## Troubleshooting

### Common Issues
1. **Composer not found**: Install Composer following the official guide
2. **Permission errors**: Ensure proper file permissions for uploaded files
3. **Memory limits**: Large documents may require increased PHP memory limit
4. **PDF extraction fails**: Some encrypted or scanned PDFs may not extract properly

### Error Logging
Text extraction errors are logged to PHP error log. Check your server's error log for debugging.

## Performance Considerations
- Text extraction adds processing time to uploads
- Large documents with extensive text may require more memory
- Consider implementing background processing for very large files

## Security Notes
- All text extraction is performed server-side
- Extracted text is stored in the database for future comparisons
- No external APIs are used for text analysis
