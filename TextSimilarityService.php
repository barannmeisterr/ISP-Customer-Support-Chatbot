<?php

/**
Author:Arda Baran
Description:This class provides text similarity analysis for iss chatbot.This service compares user messages with predefined chatbot messages,commands, and intents to determine the most relevant textual match.
In order to do that, user input is normalized to handle common variations such as capitalization, Turkish characters, punctuation, extra spaces, and minor spelling differences.
Then performs similarity analysis by using these approaches:

1)Levenshtein similarity for detecting character-level differences and common spelling mistakes.


2)Jaro-Winkler similarity for identifying closely related strings, particularly when differences occur near the beginning of words.


3)Cosine similarity for comparing the word composition and frequency of two messages.

All of these similarity metrics are combined using weighted coefficients in order to produce a single hybrid similarity score between 0 and 1 so that  higher score indicates that the user's message is more similar to
the target message, allowing the ISS Chatbot to handle different variations of the same user request more effectively.

Summary , this service is intended to improve chatbot message matching,intent detection, command recognition, and tolerance to user spelling variations.
*/
namespace App\Services\bot;

class TextSimilarityService
{


    public function normalize(string $text): string
    
/**
 * ------------------------------
 * Summary:
 * This function converts user input into a comparable standard form.
 *
 * It converts uppercase letters to lowercase, replaces Turkish-specific
 * characters with their ASCII equivalents, removes unnecessary characters,
 * normalizes multiple spaces, and trims leading and trailing spaces.
 *
 * This process ensures that different representations of the same user input
 * can be compared more reliably by the similarity algorithms.
 * ------------------------------
 *//
	
	{
        $map = [
            'ş' => 's', 'ç' => 'c', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ı' => 'i', 'İ' => 'i',
        ];

        $text = strtr(mb_strtolower($text), $map);
        $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }


    public function tokenize(string $text): array
    {
		
		   /**
     * ------------------------------
     * Summary:
     * This function splits the normalized user input into individual words
     * called tokens.
     *
     * First, the input text is normalized to ensure consistent formatting.
     * If the resulting text is empty, an empty array is returned.
     * Otherwise, the text is split by spaces and each word is returned
     * as a separate token.
     *
     * These tokens are mainly used by the Cosine Similarity algorithm
     * to compare the word composition and frequency of two messages.
     * ------------------------------
     */
        $text = $this->normalize($text);
        if ($text === '') return [];

        return explode(' ', $text);
    }


    public function levenshteinSimilarity(string $a, string $b): float
    {
		/**
 * ------------------------------
 * Summary:
 * This function calculates the similarity between two strings
 * using the Levenshtein distance algorithm.
 *
 * Levenshtein distance measures how many character-level changes
 * are required to transform one string into another.
 *
 * These changes may include:
 * - Inserting a character
 * - Deleting a character
 * - Replacing a character
 *
 * The calculated distance is normalized according to the length
 * of the longer string and converted into a similarity score
 * between 0 and 1.
 *
 * A score of 1 means that both strings are identical,
 * while a score closer to 0 means that the strings are more different.
 *
 * This method is particularly useful for detecting spelling mistakes,
 * missing characters, extra characters, and small typing differences.
 * ------------------------------
 */
		
        $max = max(strlen($a), strlen($b));
        if ($max === 0) return 1;

        return 1 - (levenshtein($a, $b) / $max);
    }


    public function jaroWinkler(string $a, string $b): float
    {
		
		/**
 * ------------------------------
 * Summary:
 * This function calculates the textual similarity between two strings
 * using PHP's built-in similar_text() function.
 *
 * The similar_text() function calculates the percentage of similarity
 * between two strings based on their matching character sequences.
 *
 * The resulting percentage value is converted from a range of 0-100
 * into a normalized similarity score between 0 and 1.
 *
 * A score of 1 means that the strings are considered fully similar,
 * while a score closer to 0 means that they have fewer matching parts.
 *
 * This method is useful for detecting general textual similarity
 * between two messages, especially when their character sequences
 * are partially similar.
 
 * ------------------------------
 */
        similar_text($a, $b, $percent);
        return $percent / 100;
    }


    public function cosineSimilarity(string $a, string $b): float
    {
	/**
 * ------------------------------
 * Summary:
 * This function calculates the Cosine Similarity between two strings.
 *
 * First, both strings are normalized and divided into individual
 * words called tokens.
 *
 * The frequency of each token is then counted in both strings.
 * These token frequencies are used to create numerical vectors
 * representing the content of each message.
 *
 * The function then calculates:
 *
 * - Dot product:
 *   Measures how much the two vectors overlap.
 *
 * - Vector magnitude:
 *   Measures the size of each vector.
 *
 * Finally, the Cosine Similarity formula is applied:
 *
 *     similarity = dot product / (magnitude A * magnitude B)
 *
 * The resulting score is between 0 and 1. A score of 1 means that both messages have the same word  distribution, while a score closer to 0 means that they have little or no word overlap.
 *
 * Unlike character-based similarity methods, Cosine Similarity focuses primarily on the words contained in the messages.
 *
 * It also does not consider word order.
 * For example:
 *
 *     "bugün hava güzel"
 *     "güzel hava bugün"
 *
 * contain the same words and therefore produce a very high Cosine Similarity score.
 * ------------------------------
 */	
		
        $tokensA = array_count_values($this->tokenize($a));
        $tokensB = array_count_values($this->tokenize($b));

        $allTokens = array_unique(array_merge(
            array_keys($tokensA),
            array_keys($tokensB)
        ));

        $vecA = [];
        $vecB = [];

        foreach ($allTokens as $token) {
            $vecA[] = $tokensA[$token] ?? 0;
            $vecB[] = $tokensB[$token] ?? 0;
        }

        $dot = 0;
        $normA = 0;
        $normB = 0;

        for ($i = 0; $i < count($vecA); $i++) {
            $dot += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] ** 2;
            $normB += $vecB[$i] ** 2;
        }

        if ($normA == 0 || $normB == 0) {
            return 0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

  
    public function hybridScore(string $a, string $b): float
    {
		/**
 * ------------------------------
 * Summary:
 * This function combines multiple text similarity algorithms
 * into a single hybrid similarity score.
 *
 * First, both input strings are normalized to ensure that
 * capitalization, Turkish characters, punctuation, and whitespace
 * differences do not negatively affect the comparison.
 *
 * The function then calculates three different similarity scores:
 *
 * 1) Levenshtein Similarity:
 *    Measures character-level differences and helps detect
 *    spelling mistakes, missing characters, and typing errors.
 *
 * 2) Jaro-Winkler Similarity:
 *    Measures the general textual similarity between the two strings.
 *
 * 3) Cosine Similarity:
 *    Compares the words and their frequencies in both messages.
 *
 * Each similarity method focuses on a different aspect of the text. Therefore, combining them provides a more balanced similarity measurement than relying on a single algorithm.

 * The final score is calculated using weighted coefficients:
 *
 *     Cosine Similarity      -> 55%
 *     Jaro-Winkler Similarity -> 30%
 *     Levenshtein Similarity  -> 15%
 *
 * Cosine Similarity receives the highest weight because word-level
 * similarity is considered the primary factor when comparing
 * chatbot messages.
 *
 * Levenshtein Similarity receives a lower weight because character-level
 * similarity is mainly intended to compensate for spelling mistakes
 * and small typing differences.
 *
 * The final result is a normalized score between 0 and 1.
 *
 * A score closer to 1 indicates that the two messages are highly
 * similar, while a score closer to 0 indicates that they are
 * significantly different.
 *
 * Example:
 *
 * User input:
 *     "internte baglantim yok"
 *
 * Expected chatbot message:
 *     "internet bağlantım yok"
 *
 * In this case:
 * - Levenshtein Similarity helps detect the spelling differences.
 * - Jaro-Winkler / similar_text helps measure general character similarity.
 * - Cosine Similarity compares the common words between the messages.
 *
 * Combining these results allows the chatbot to recognize that
 * both messages represent a very similar user request.
 * ------------------------------
 */
		
        $a = $this->normalize($a);
        $b = $this->normalize($b);

        $lev = $this->levenshteinSimilarity($a, $b);
        $jaro = $this->jaroWinkler($a, $b);
        $cos = $this->cosineSimilarity($a, $b);

        return ($cos * 0.55) + ($jaro * 0.30) + ($lev * 0.15);
    }
}
