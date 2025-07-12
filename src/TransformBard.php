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

        if (empty($bard)) {
            return '';
        }

        $setTypes = []; // Initialize $setTypes

        try {
            $setTypes = array_merge(['text'], $userSetTypes);
            $field = Blueprint::find($blueprintPath)?->field($fieldName);
            
            if (!$field) {
                return static::processRawContent($bard, $setTypes, $options);
            }

            // Extract content from all blocks/sets
            $extractedContent = static::extractContent($bard, $setTypes, $options);
            
            // Clean and return the final string
            return static::cleanText(implode(' ', $extractedContent), $options);

        } catch (\Exception $e) {
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
        $extractedContent = [];

        if (!is_iterable($content)) {
            return $extractedContent;
        }

        foreach ($content as $block) {
            if (!is_array($block) && !is_object($block)) {
                continue;
            }

            $blockArray = is_object($block) ? $block->toArray() : $block;
            $type = Arr::get($blockArray, 'type');

            if ($type === 'text') {
                // Handle standard text blocks
                $text = Arr::get($blockArray, 'text');
                if ($text) {
                    if (!is_string($text)) {
                        $text = is_object($text) && method_exists($text, 'raw') ? $text->raw() : (string) $text;
                    }
                    $extractedContent[] = Markdown::parse($text);
                }
            } elseif (in_array($type, $setTypes)) {
                // Handle user-specified set types
                $setText = static::extractFromSet($blockArray, $options);
                if ($setText) {
                    $extractedContent[] = $setText;
                }
            } else {
                // Handle other set types by looking for content fields
                $setText = static::extractFromSet($blockArray, $options);
                if ($setText) {
                    $extractedContent[] = $setText;
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
        $content = [];
        $maxDepth = Arr::get($options, 'max_depth', 3);
        $currentDepth = Arr::get($options, '_current_depth', 0);

        if ($currentDepth >= $maxDepth) {
            return '';
        }

        foreach ($set as $key => $value) {
            if (in_array($key, ['type', 'id', 'enabled']) || empty($value)) {
                continue;
            }

            $text = static::extractTextFromValue($value, [
                ...$options,
                '_current_depth' => $currentDepth + 1
            ]);
            
            if ($text) {
                $content[] = $text;
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
        if (is_string($value)) {
            return static::cleanText($value, $options);
        }

        if (is_array($value)) {
            // Check if it's a Bard field (array of blocks)
            if (static::isBardContent($value)) {
                return static::extractContentFromBardArray($value, $options);
            }
            
            // Otherwise, recursively extract from array
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
            if (method_exists($value, 'raw')) {
                return static::extractTextFromValue($value->raw(), $options);
            }
            if (method_exists($value, 'toArray')) {
                return static::extractTextFromValue($value->toArray(), $options);
            }
            return (string) $value;
        }

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
        $content = [];
        
        foreach ($bardContent as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = Arr::get($block, 'type');
            
            if ($type === 'text') {
                $text = Arr::get($block, 'text', '');
                $marks = Arr::get($block, 'marks', []);

                // Process marks to remove statamic:// links
                foreach ($marks as &$mark) {
                    if (Arr::get($mark, 'type') === 'link') {
                        $href = Arr::get($mark, 'attrs.href');
                        if (is_string($href) && Str::startsWith($href, 'statamic://')) {
                            Arr::set($mark, 'attrs.href', null);
                        }
                    }
                }
                // Re-set the modified marks back to the block if needed, though Markdown::parse usually takes the text directly
                // This step might not be strictly necessary for Markdown::parse but ensures data integrity if block is used elsewhere
                Arr::set($block, 'marks', $marks);

                if ($text) {
                    $content[] = Markdown::parse($text);
                }
            } else {
                // Extract from other block types
                $blockText = static::extractFromSet($block, $options);
                if ($blockText) {
                    $content[] = $blockText;
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
