<?php

namespace Fitzage\OptimusBard;

use Statamic\Facades\Blueprint;
use Statamic\Support\Arr;
use Statamic\Facades\Markdown;

$setTypes = [];

class TransformBard
{
    public static function transform($bard, $blueprintPath, $fieldName, $userSetTypes = []) {
        global $setTypes;
        $setTypes = array_merge(['text'], $userSetTypes);
        $field = Blueprint::find($blueprintPath)->field($fieldName);

        // augment everything
        $content = $field->fieldtype()->shallowAugment($bard);

        // process fields, removing useless data and `raw`ing when necessary
        $fieldContent = collect($content)->map(function ($field) {
            global $setTypes;
            $type = Arr::get($field, 'type');
            if (in_array($type, $setTypes)) {
                $text = Arr::get($field, 'text')->raw();
                $text = Markdown::parse($text);

                return $text;
            }
        })->all();

        //TODO make all this more user configurable

        $fieldString = implode(',', $fieldContent);
        $extraSpaces = str_replace('<', ' <', $fieldString);
        $noTags = strip_tags($extraSpaces);
        $cleanSpaces = str_replace('  ', ' ', $noTags);
        $noNbsp = str_replace('&nbsp;', '', $cleanSpaces);
        $noSpacePeriod = str_replace(' .', '.', $noNbsp);
        $noComma = str_replace(',','',$noSpacePeriod);
        $noAmpEntity = str_replace('&amp;', '&', $noComma);

        return $noAmpEntity;
    }
}
