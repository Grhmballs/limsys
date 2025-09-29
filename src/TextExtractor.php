<?php

namespace LIMSys;

use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

/**
 * Text Extraction Utility for LIMSys
 * Extracts text content from various document formats
 */
class TextExtractor
{
    /**
     * Extract text from a file based on its extension
     * 
     * @param string $filePath Path to the file
     * @param string $extension File extension
     * @return string Extracted text content
     */
    public static function extractText($filePath, $extension)
    {
        try {
            switch (strtolower($extension)) {
                case 'pdf':
                    return self::extractFromPdf($filePath);
                case 'doc':
                case 'docx':
                    return self::extractFromWord($filePath);
                case 'txt':
                    return self::extractFromText($filePath);
                default:
                    return '';
            }
        } catch (Exception $e) {
            error_log("Text extraction error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Extract text from PDF files
     * 
     * @param string $filePath Path to PDF file
     * @return string Extracted text
     */
    private static function extractFromPdf($filePath)
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            return self::cleanText($text);
        } catch (Exception $e) {
            error_log("PDF extraction error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Extract text from Word documents
     * 
     * @param string $filePath Path to Word document
     * @return string Extracted text
     */
    private static function extractFromWord($filePath)
    {
        try {
            $phpWord = IOFactory::load($filePath);
            $text = '';
            
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . ' ';
                    } elseif (method_exists($element, 'getElements')) {
                        foreach ($element->getElements() as $childElement) {
                            if (method_exists($childElement, 'getText')) {
                                $text .= $childElement->getText() . ' ';
                            }
                        }
                    }
                }
            }
            
            return self::cleanText($text);
        } catch (Exception $e) {
            error_log("Word extraction error: " . $e->getMessage());
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
