<?php
/**
 * Author: Arda Baran
 *
 * Description:
 *
 * This class is responsible for analyzing user messages and
 * determining the appropriate category and decision-tree nodes
 * for the ISS Chatbot.
 *
 * The class loads chatbot knowledge from a JSON file and uses
 * the TextSimilarityService to compare user messages with
 * predefined intent triggers.
 *
 * The main responsibilities of this class are:
 *
 * - Loading and validating chatbot knowledge from JSON.
 * - Matching user messages with the most relevant category.
 * - Finding decision-tree nodes by their IDs.
 * - Retrieving the root node of a category.
 * - Retrieving a category by its unique ID.
 *
 * The category matching process uses a hybrid similarity score
 * that combines multiple text similarity algorithms to handle
 * spelling mistakes, different word usage, and variations
 * in user input.
 */
namespace App\Services\bot;

class DecisionTree
{

    protected array $data;
    protected TextSimilarityService $sim;

    public function __construct(?string $path = null)
    {
		 /**
     * ------------------------------
     * Summary:
     * Initializes the DecisionTree service and loads the chatbot
     * knowledge from the specified JSON file.
     *
     * If no file path is provided, the default knowledge file
     * located in the application's storage directory is used.
     *
     * The JSON data is decoded into a PHP array and validated
     * to ensure that the expected "categories" structure exists.
     *
     * A TextSimilarityService instance is also created and stored
     * for later use during category matching.
     *
     * If the JSON file does not contain a valid chatbot knowledge
     * structure, a RuntimeException is thrown.
     * ------------------------------
     *
     * @param string|null $path Optional path to the chatbot knowledge file.
     *
     * @throws \RuntimeException If the knowledge JSON is invalid.
     */
		
        $path ??= storage_path('bot/knowledge.json');

        $json = file_get_contents($path);
        $this->data = json_decode($json, true);

        if (!is_array($this->data) || !isset($this->data['categories'])) {
            throw new \RuntimeException('Knowledge JSON geçersiz.');
        }

        $this->sim = new TextSimilarityService();
    }


  
    public function matchCategory(string $message): ?array
 /**
     * ------------------------------
     * Summary:
     * Finds the category that best matches the given user message.
     *
     * The user's message is first normalized to remove differences
     * caused by capitalization, Turkish characters, punctuation,
     * and unnecessary whitespace.
     *
     * The function then iterates through every category and compares
     * the normalized user message with each predefined intent trigger.
     *
     * For every trigger, a hybrid similarity score is calculated
     * using the TextSimilarityService.
     *
     * The category with the highest similarity score is stored
     * as the current best match.
     *
     * After all categories and triggers have been evaluated,
     * the best category is returned only if its similarity score
     * reaches the minimum threshold of 0.55.
     *
     * If no category reaches this threshold, null is returned.
     *
     * Example:
     *
     * User input:
     *     "internte baglantim yok"
     *
     * Intent trigger:
     *     "internet bağlantım yok"
     *
     * The hybrid similarity calculation can detect that these
     * messages are highly similar despite the spelling differences.
     *
     * This allows the chatbot to recognize different variations
     * of the same user request.
     * ------------------------------
     *
     * @param string $message User message to analyze.
     *
     * @return array|null The best matching category, or null
     *                   if no category reaches the threshold.
     */  

  {
        $msgNorm = $this->sim->normalize($message);

        $bestScore = 0;
        $bestCategory = null;

        foreach ($this->data['categories'] as $category) {

            foreach ($category['intent_triggers'] as $trigger) {
                $triggerNorm = $this->sim->normalize($trigger);

                $score = $this->sim->hybridScore($msgNorm, $triggerNorm);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestCategory = $category;
                }
            }
        }

        return $bestScore >= 0.55 ? $bestCategory : null;
    }



    public function getNode(array $category, string $nodeId): ?array
    {
    /**
     * ------------------------------
     * Summary:
     * Retrieves a specific decision-tree node from a category
     * using its unique node ID.
     *
     * The method accesses the "nodes" section of the category's
     * decision tree and returns the node associated with the
     * provided ID.
     *
     * If the requested node does not exist, null is returned.
     * ------------------------------
     *
     * @param array  $category Category containing the decision tree.
     * @param string $nodeId    Unique ID of the requested node.
     *
     * @return array|null The requested node, or null if it does not exist.
     */
		
        return $category['tree']['nodes'][$nodeId] ?? null;
    }


    /** ROOT NODE ID */
    public function getRoot(array $category): string
    {
		
				  /**
     * ------------------------------
     * Summary:
     * Retrieves the root node ID of a category's decision tree.
     *
     * The root node represents the starting point of the
     * decision tree and is used to begin the conversation flow.
     * ------------------------------
     *
     * @param array $category Category containing the decision tree.
     *
     * @return string The ID of the root node.
     */
        return $category['tree']['root'];
    }

    public function getCategoryById(string $id): ?array
    {
    /**
     * ------------------------------
     * Summary:
     * Finds a category using its unique category ID.
     *
     * The method iterates through all available categories and
     * compares their IDs with the requested ID.
     *
     * If a matching category is found, it is returned immediately.
     * If no category has the requested ID, null is returned.
     * ------------------------------
     *
     * @param string $id Unique ID of the category to find.
     *
     * @return array|null The matching category, or null if
     *                    no category is found.
     */
        foreach ($this->data['categories'] as $category) {
            if ($category['id'] === $id) {
                return $category;
            }
        }

        return null;
    }


}
