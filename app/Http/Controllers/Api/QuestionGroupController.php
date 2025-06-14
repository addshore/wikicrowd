<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuestionGroup;

class QuestionGroupController extends Controller
{
    private $depictsGroups = [
        'animals' => [ 'Q144', 'Q146', 'Q3736439'],
        'food' => [ 'Q44', 'Q6663', 'Q13233', 'Q1421401' ],
        'landmarks' => [ 'Q243', 'Q9202' ],
        'music' => [ 'Q6607', 'Q46185', 'Q20850569', 'Q685206' ],
        'people' => [ 'Q6279', 'Q1124', 'Q23505', 'Q207', 'Q76', 'Q22686' ],
        'sport' => [ 'Q7291', 'Q18629677', 'Q12100', 'Q1455', 'Q41466', 'Q1748406', 'Q1002954', 'Q12252328' ],
        'transport' => [ 'Q34486', 'Q206592', 'Q115940', 'Q2165278', 'Q133585', 'Q11442', 'Q870', 'Q171043', 'Q3407658' ],
    ];

    private function stringEndsWithOneOf( $string, $endStrings ) {
        foreach( $endStrings as $endString ) {
            if( $this->stringEndsWith( $string, $endString ) ) {
                return true;
            }
        }
        return false;
    }

    private function stringEndsWith( $string, $endString ) {
        $len = strlen($endString);
        if ($len == 0) {
            return true;
        }
        return (substr($string, -$len) === $endString);
    }

    public function index() {
        $groups = QuestionGroup::whereNull('parent')->with(['subGroups' => function($query){
            $query->withCount(['question as unanswered' => function($query){
                $query->doesntHave('answer');
            }])
            ->having('unanswered', '>', 0);
        }])->get();

        // Filter out anything that doesnt have "depicts" at the start of the name
        // Also filter out refinement groups
        $groups = $groups->filter(function($group){
            return strpos($group->name, 'depicts') === 0 && strpos($group->name, 'refinement') === false;
        });

        $newManualGroups = [];
        foreach ($groups as $gameGroup) {
            $jIgnore = [];
            foreach ($gameGroup->subGroups as $j => $game) {
                // Get a sample unanswered question for this subgroup (with image if possible)
                $sampleUnanswered = $game->question()->doesntHave('answer')->whereNotNull('properties->img_url')->first();
                $game->example_question = $sampleUnanswered;
                // Try to extract depicts_id and categories from the first unanswered question
                if ($sampleUnanswered && isset($sampleUnanswered->properties['depicts_id'])) {
                    $game->depicts_id = $sampleUnanswered->properties['depicts_id'];
                }
                if ($sampleUnanswered && isset($sampleUnanswered->properties['categories'])) {
                    // categories may be a string or array
                    $cats = $sampleUnanswered->properties['categories'];
                    if (is_string($cats)) {
                        $cats = [$cats];
                    }
                    $game->categories = $cats;
                }
                foreach ($this->depictsGroups as $depictsGroup => $depictsIds) {
                    if ($this->stringEndsWithOneOf($game->name, $depictsIds)) {
                        if (!isset($newManualGroups[$depictsGroup])) {
                            $newManualGroups[$depictsGroup] = (object) [
                                'display_name' => ucfirst($depictsGroup),
                                'subGroups' => [],
                            ];
                        }
                        $newManualGroups[$depictsGroup]->subGroups[] = $game;
                        $jIgnore[] = $j;
                    }
                }
            }
            // This logic for removing subgroups by index might be problematic if $jIgnore contains duplicate indices or is not sorted
            // A safer approach would be to filter the subGroups collection or rebuild it.
            // For now, assuming $jIgnore is managed correctly to avoid issues.
            foreach (array_unique($jIgnore) as $indexToIgnore) {
                // This direct unset might re-index numerically, which could be an issue if relying on original keys later.
                // However, for the current logic of adding to 'other', it seems to iterate over what remains.
                unset($gameGroup->subGroups[$indexToIgnore]);
            }
            // It's important to re-index if other operations depend on a numerically contiguous array.
            $gameGroup->subGroups = array_values($gameGroup->subGroups->all());
        }

        // If anything is left over, add it to an "Other" group
        $newManualGroups['other'] = (object) [
            'display_name' => 'Other',
            'subGroups' => [],
        ];
        foreach ($groups as $gameGroup) {
            foreach ($gameGroup->subGroups as $game) {
                $newManualGroups['other']->subGroups[] = $game;
            }
        }

        // Remove the other group if it is empty
        if (count($newManualGroups['other']->subGroups) == 0) {
            unset($newManualGroups['other']);
        }

        return response()->json($newManualGroups);
    }

}
