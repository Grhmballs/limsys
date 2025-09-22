<?php

namespace LIMSys;

/**
 * Fallback Text Extractor for LIMSys
 * Simple text extraction without external dependencies
 */
class TextExtractorFallback
{
    /**
     * Extract text from a file based on its extension (fallback version)
     * 
     * @param string $filePath Path to the file
     * @param string $extension File extension
     * @return string Extracted text content
     */
    public static function extractText($filePath, $extension)
    {
        try {
            switch (strtolower($extension)) {
                case 'txt':
                    return self::extractFromText($filePath);
                default:
                    // For other formats, return empty string if no libraries available
                    return '';
            }
        } catch (Exception $e) {
            error_log("Fallback text extraction error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Extract text from plain text files
     * 
     * @param string $filePath Path to text file
     * @return string File content
     */
    private static function extractFromText($filePath)
    {
        try {
            $content = file_get_contents($filePath);
            return self::cleanText($content);
        } catch (Exception $e) {
            error_log("Text file extraction error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Clean and normalize text for comparison
     * 
     * @param string $text Raw text
     * @return string Cleaned text
     */
    private static function cleanText($text)
    {
        // Remove extra whitespace and normalize
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove special characters but keep alphanumeric and basic punctuation
        $text = preg_replace('/[^\p{L}\p{N}\s\.\,\!\?\;\:]/u', ' ', $text);
        
        // Convert to lowercase for comparison
        $text = strtolower($text);
        
        // Trim whitespace
        $text = trim($text);
        
        return $text;
    }
}
?>
