<?php

namespace Fitzage\OptimusBard;

use Illuminate\Support\Str;
use Statamic\Facades\Blueprint;
use Statamic\Support\Arr;
use Statamic\Facades\Markdown;

class TransformBard
{
    protected static $contentTypes = [
        'text', 'textarea', 'markdown', 'bard', 'content', 
        'title', 'description', 'heading', 'body'
    ];

    private static $logFile = '/Users/matt.fitzsimmons/Source/optimus_bard_debug.log';

    private static function log($message)
    {
        file_put_contents(self::$logFile, date('[Y-m-d H:i:s]') . ' ' . $message . "\n", FILE_APPEND);
    }

    /**
     * Transform Bard field content into searchable text
     *
     * @param mixed $bard The Bard field content
     * @param string $blueprintPath Path to the blueprint
     * @param string $fieldName Name of the field
     * @param array $userSetTypes Additional set types to include
     * @param array $options Additional configuration options
     * @return string
     */
    public static function transform($bard, $blueprintPath, $fieldName, $userSetTypes = [], $options = [])
    {
        self::log("TransformBard::transform - Initial Bard: " . json_encode($bard));

        if (empty($bard)) {
            self::log("TransformBard::transform - Bard is empty, returning empty string.");
            return '';
        }

        $setTypes = []; // Initialize $setTypes

        try {
            self::log("TransformBard::transform - Set Types: " . json_encode($setTypes));
            $setTypes = array_merge(['text'], $userSetTypes);
            $field = Blueprint::find($blueprintPath)?->field($fieldName);
            
            if (!$field) {
                self::log("TransformBard::transform - Blueprint field not found, processing raw content.");
                return static::processRawContent($bard, $setTypes, $options);
            }

            // Extract content from all blocks/sets
            $extractedContent = static::extractContent($bard, $setTypes, $options);
            self::log("TransformBard::transform - Extracted Content before cleanText: " . json_encode($extractedContent));
            
            // Clean and return the final string
            return static::cleanText(implode(' ', $extractedContent), $options);

        } catch (\Exception $e) {
            self::log("TransformBard::transform - Exception caught: " . $e->getMessage());
            // Graceful fallback for any errors
            return static::processRawContent($bard, $setTypes, $options);
        }
    }

    /**
     * Extract content from Bard blocks and sets
     *
     * @param mixed $content
     * @param array $setTypes
     * @param array $options
     * @return array
     */
    protected static function extractContent($content, $setTypes, $options = [])
    {
        self::log("TransformBard::extractContent - Processing content: " . json_encode($content));
        $extractedContent = [];

        if (!is_iterable($content)) {
            self::log("TransformBard::extractContent - Content is not iterable.");
            return $extractedContent;
        }

        foreach ($content as $block) {
            self::log("TransformBard::extractContent - Processing block: " . json_encode($block));
            if (!is_array($block) && !is_object($block)) {
                self::log("TransformBard::extractContent - Block is not array or object, skipping.");
                continue;
            }

            $blockArray = is_object($block) ? $block->toArray() : $block;
            $type = Arr::get($blockArray, 'type');
            self::log("TransformBard::extractContent - Block type: " . $type);

            if ($type === 'text') {
                // Handle standard text blocks
                $text = Arr::get($blockArray, 'text');
                if ($text) {
                    if (!is_string($text)) {
                        $text = is_object($text) && method_exists($text, 'raw') ? $text->raw() : (string) $text;
                    }
                    $extractedContent[] = Markdown::parse($text);
                    self::log("TransformBard::extractContent - Extracted text from 'text' block: " . $text);
                }
            } elseif (in_array($type, $setTypes)) {
                // Handle user-specified set types
                $setText = static::extractFromSet($blockArray, $options);
                if ($setText) {
                    $extractedContent[] = $setText;
                    self::log("TransformBard::extractContent - Extracted text from user-specified set type '" . $type . "': " . $setText);
                }
            } else {
                // Handle other set types by looking for content fields
                $setText = static::extractFromSet($blockArray, $options);
                if ($setText) {
                    $extractedContent[] = $setText;
                    self::log("TransformBard::extractContent - Extracted text from other set type '" . $type . "': " . $setText);
                }
            }
        }

        return $extractedContent;
    }

    /**
     * Extract content from a set/block
     *
     * @param array $set
     * @param array $options
     * @return string
     */
    protected static function extractFromSet($set, $options = [])
    {
        self::log("TransformBard::extractFromSet - Processing set: " . json_encode($set));
        self::log("TransformBard::extractFromSet - Options: " . json_encode($options));
        $content = [];
        $maxDepth = Arr::get($options, 'max_depth', 3);
        $currentDepth = Arr::get($options, '_current_depth', 0);

        if ($currentDepth >= $maxDepth) {
            self::log("TransformBard::extractFromSet - Max depth reached (" . $currentDepth . "/" . $maxDepth . ").");
            return '';
        }

        foreach ($set as $key => $value) {
            self::log("TransformBard::extractFromSet - Key: " . $key . ", Value: " . json_encode($value));
            if (in_array($key, ['type', 'id', 'enabled']) || empty($value)) {
                self::log("TransformBard::extractFromSet - Skipping key: " . $key);
                continue;
            }

            $text = static::extractTextFromValue($value, [
                ...$options,
                '_current_depth' => $currentDepth + 1
            ]);
            
            if ($text) {
                $content[] = $text;
                self::log("TransformBard::extractFromSet - Extracted text for key '" . $key . "': " . $text);
            }
        }

        return implode(' ', $content);
    }

    /**
     * Extract text from various field value types
     *
     * @param mixed $value
     * @param array $options
     * @return string
     */
    protected static function extractTextFromValue($value, $options = [])
    {
        self::log("TransformBard::extractTextFromValue - Processing value: " . json_encode($value));
        if (is_string($value)) {
            self::log("TransformBard::extractTextFromValue - Value is string.");
            return static::cleanText($value, $options);
        }

        if (is_array($value)) {
            self::log("TransformBard::extractTextFromValue - Value is array.");
            // Check if it's a Bard field (array of blocks)
            if (static::isBardContent($value)) {
                self::log("TransformBard::extractTextFromValue - Value is Bard content.");
                return static::extractContentFromBardArray($value, $options);
            }
            
            // Otherwise, recursively extract from array
            self::log("TransformBard::extractTextFromValue - Value is regular array, recursing.");
            $content = [];
            foreach ($value as $item) {
                $text = static::extractTextFromValue($item, $options);
                if ($text) {
                    $content[] = $text;
                }
            }
            return implode(' ', $content);
        }

        if (is_object($value)) {
            self::log("TransformBard::extractTextFromValue - Value is object.");
            if (method_exists($value, 'raw')) {
                return static::extractTextFromValue($value->raw(), $options);
            }
            if (method_exists($value, 'toArray')) {
                return static::extractTextFromValue($value->toArray(), $options);
            }
            return (string) $value;
        }

        self::log("TransformBard::extractTextFromValue - Value type not handled.");
        return '';
    }

    /**
     * Check if an array represents Bard content
     *
     * @param array $value
     * @return bool
     */
    protected static function isBardContent($value)
    {
        if (empty($value) || !is_array($value)) {
            return false;
        }

        // Check if it looks like a Bard content array
        $firstItem = reset($value);
        return is_array($firstItem) && isset($firstItem['type']);
    }

    /**
     * Extract content from a Bard content array
     *
     * @param array $bardContent
     * @param array $options
     * @return string
     */
    protected static function extractContentFromBardArray($bardContent, $options = [])
    {
        self::log("TransformBard::extractContentFromBardArray - Processing Bard content array: " . json_encode($bardContent));
        $content = [];
        
        foreach ($bardContent as $block) {
            self::log("TransformBard::extractContentFromBardArray - Processing block in Bard array: " . json_encode($block));
            if (!is_array($block)) {
                self::log("TransformBard::extractContentFromBardArray - Block is not array, skipping.\n");
                continue;
            }

            $type = Arr::get($block, 'type');
            self::log("TransformBard::extractContentFromBardArray - Block type: " . $type);
            
            if ($type === 'text') {
                $text = Arr::get($block, 'text', '');
                $marks = Arr::get($block, 'marks', []);

                // Process marks to remove statamic:// links
                foreach ($marks as &$mark) {
                    if (Arr::get($mark, 'type') === 'link') {
                        $href = Arr::get($mark, 'attrs.href');
                        if (is_string($href) && Str::startsWith($href, 'statamic://')) {
                            Arr::set($mark, 'attrs.href', null);
                            self::log("TransformBard::extractContentFromBardArray - Nullified statamic:// link in text block.");
                        }
                    }
                }
                // Re-set the modified marks back to the block if needed, though Markdown::parse usually takes the text directly
                // This step might not be strictly necessary for Markdown::parse but ensures data integrity if block is used elsewhere
                Arr::set($block, 'marks', $marks);

                if ($text) {
                    $content[] = Markdown::parse($text);
                    self::log("TransformBard::extractContentFromBardArray - Extracted text from 'text' block: " . $text);
                }
            } else {
                // Extract from other block types
                $blockText = static::extractFromSet($block, $options);
                if ($blockText) {
                    $content[] = $blockText;
                    self::log("TransformBard::extractContentFromBardArray - Extracted text from other block type '" . $type . "': " . $blockText);
                }
            }
        }

        return implode(' ', $content);
    }

    /**
     * Process raw content when blueprint is unavailable
     *
     * @param mixed $bard
     * @param array $setTypes
     * @param array $options
     * @return string
     */
    protected static function processRawContent($bard, $setTypes, $options = [])
    {
        self::log("TransformBard::processRawContent - Processing raw content.");
        if (is_string($bard)) {
            return static::cleanText($bard, $options);
        }

        if (is_array($bard)) {
            $content = [];
            foreach ($bard as $item) {
                $text = static::extractTextFromValue($item, $options);
                if ($text) {
                    $content[] = $text;
                }
            }
            return static::cleanText(implode(' ', $content), $options);
        }

        return '';
    }

    /**
     * Clean and optimize text for search indexing
     *
     * @param string $text
     * @param array $options
     * @return string
     */
    protected static function cleanText($text, $options = [])
    {
        
        if (empty($text)) {
            return '';
        }

        $maxLength = Arr::get($options, 'max_length', 90000);
        
        // Basic cleanup pipeline
        $text = str_replace('<', ' <', $text);           // Add space before tags
        $text = strip_tags($text);                       // Remove HTML tags
        $text = str_replace(['&nbsp;', '&amp;'], [' ', '&'], $text); // Replace entities
        $text = preg_replace('/\s+/', ' ', $text);       // Normalize whitespace
        $text = str_replace([' .', ' ,', ' ;'], ['.', ',', ';'], $text); // Fix punctuation
        $text = trim($text);                             // Trim whitespace
        
        // Limit length (using modern Laravel helper)
        return Str::limit($text, $maxLength, '');
    }

    /**
     * Legacy method for backward compatibility
     * 
     * @deprecated Use transform() method instead
     */
    public static function transformLegacy($bard, $blueprintPath, $fieldName, $userSetTypes = [])
    {
        return static::transform($bard, $blueprintPath, $fieldName, $userSetTypes);
    }
}
