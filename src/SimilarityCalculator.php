<?php

namespace LIMSys;

/**
 * Text Similarity Calculator for LIMSys
 * Calculates similarity between text documents using various algorithms
 */
class SimilarityCalculator
{
    /**
     * Calculate cosine similarity between two texts
     * 
     * @param string $text1 First text
     * @param string $text2 Second text
     * @return float Similarity score between 0 and 1
     */
    public static function cosineSimilarity($text1, $text2)
    {
        if (empty($text1) || empty($text2)) {
            return 0.0;
        }

        // Tokenize texts into words
        $words1 = self::tokenize($text1);
        $words2 = self::tokenize($text2);

        if (empty($words1) || empty($words2)) {
            return 0.0;
        }

        // Create term frequency vectors
        $vector1 = self::createTermFrequencyVector($words1);
        $vector2 = self::createTermFrequencyVector($words2);

        // Get all unique terms
        $allTerms = array_unique(array_merge(array_keys($vector1), array_keys($vector2)));

        // Create normalized vectors
        $v1 = [];
        $v2 = [];
        
        foreach ($allTerms as $term) {
            $v1[] = isset($vector1[$term]) ? $vector1[$term] : 0;
            $v2[] = isset($vector2[$term]) ? $vector2[$term] : 0;
        }

        // Calculate cosine similarity
        return self::calculateCosine($v1, $v2);
    }

    /**
     * Calculate Jaccard similarity (text overlap)
     * 
     * @param string $text1 First text
     * @param string $text2 Second text
     * @return float Similarity score between 0 and 1
     */
    public static function jaccardSimilarity($text1, $text2)
    {
        if (empty($text1) || empty($text2)) {
            return 0.0;
        }

        $words1 = array_unique(self::tokenize($text1));
        $words2 = array_unique(self::tokenize($text2));

        if (empty($words1) || empty($words2)) {
            return 0.0;
        }

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        return count($intersection) / count($union);
    }

    /**
     * Calculate combined similarity score
     * Uses weighted average of cosine and Jaccard similarity
     * 
     * @param string $text1 First text
     * @param string $text2 Second text
     * @param float $cosineWeight Weight for cosine similarity (default 0.7)
     * @param float $jaccardWeight Weight for Jaccard similarity (default 0.3)
     * @return float Combined similarity score between 0 and 1
     */
    public static function combinedSimilarity($text1, $text2, $cosineWeight = 0.7, $jaccardWeight = 0.3)
    {
        $cosine = self::cosineSimilarity($text1, $text2);
        $jaccard = self::jaccardSimilarity($text1, $text2);
        
        return ($cosine * $cosineWeight) + ($jaccard * $jaccardWeight);
    }

    /**
     * Tokenize text into words
     * 
     * @param string $text Input text
     * @return array Array of words
     */
    private static function tokenize($text)
    {
        // Convert to lowercase and split by whitespace and punctuation
        $text = strtolower($text);
        $words = preg_split('/[\s\.,\!\?\;\:\(\)\[\]\{\}\"\']+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Filter out very short words and numbers
        $words = array_filter($words, function($word) {
            return strlen($word) > 2 && !is_numeric($word);
        });
        
        return array_values($words);
    }

    /**
     * Create term frequency vector
     * 
     * @param array $words Array of words
     * @return array Term frequency vector
     */
    private static function createTermFrequencyVector($words)
    {
        $vector = [];
        $totalWords = count($words);
        
        foreach ($words as $word) {
            if (!isset($vector[$word])) {
                $vector[$word] = 0;
            }
            $vector[$word]++;
        }
        
        // Normalize by total word count
        foreach ($vector as $term => $count) {
            $vector[$term] = $count / $totalWords;
        }
        
        return $vector;
    }

    /**
     * Calculate cosine between two vectors
     * 
     * @param array $v1 First vector
     * @param array $v2 Second vector
     * @return float Cosine similarity
     */
    private static function calculateCosine($v1, $v2)
    {
        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;
        
        for ($i = 0; $i < count($v1); $i++) {
            $dotProduct += $v1[$i] * $v2[$i];
            $magnitude1 += $v1[$i] * $v1[$i];
            $magnitude2 += $v2[$i] * $v2[$i];
        }
        
        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);
        
        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0;
        }
        
        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    /**
     * Get similarity percentage
     * 
     * @param string $text1 First text
     * @param string $text2 Second text
     * @return float Similarity percentage (0-100)
     */
    public static function getSimilarityPercentage($text1, $text2)
    {
        return self::combinedSimilarity($text1, $text2) * 100;
    }
}
?>
