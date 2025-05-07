<?php

namespace App\Http\Controllers;

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

    public function getTopLevelGroups() {
        $groups = QuestionGroup::whereNull('parent')->with(['subGroups' => function($query){
            $query->withCount(['question as unanswered' => function($query){
                $query->doesntHave('answer');
            }])
            ->having('unanswered', '>', 0);
        }])->get();

        // Filter out anything that doesnt have "depicts" at the start of the name
        // As right now we still have "alias" questions and we don't want to show them..
        $groups = $groups->filter(function($group){
            return strpos($group->name, 'depicts') === 0;
        });

        $newManualGroups = [];
        foreach ($groups as $gameGroup) {
            $jIgnore = [];
            foreach ($gameGroup->subGroups as $j => $game) {
                // Separate out the depict-refine names
                if (strpos($game->name, 'depicts-refine') === 0) {
                    if (!isset($newManualGroups['refinement'])) {
                        $newManualGroups['refinement'] = (object) [
                            'display_name' => "Refinement",
                            'display_description' => "Refine the depiction of images",
                            'subGroups' => [],
                        ];
                    }
                    $newManualGroups['refinement']->subGroups[] = $game;
                    $jIgnore[] = $j;
                    continue;
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
            for ($j = 0; $j < count($jIgnore); $j++) {
                unset($gameGroup->subGroups[$jIgnore[$j]]);
            }
        }
        // If anything is left over, add it to an "Other" group
        $newManualGroups['other'] = (object) [
            'display_name' => 'Other',
            'display_description' => 'Other ungrouped images',
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

        // Move the `refinement` group to the end
        if( array_key_exists('refinement', $newManualGroups)){
            $refinement = $newManualGroups['refinement'];
            unset($newManualGroups['refinement']);
            $newManualGroups['refinement'] = $refinement;
        }

        // And then totally restructure the groups, to focus on depicts, and the types of depicts we are doing ;D

        return $newManualGroups;
    }

    public function showTopLevelGroups()
    {
        $groups = $this->getTopLevelGroups();
        // For each subGroup, fetch an example unanswered question (with image)
        foreach ($groups as $group) {
            foreach ($group->subGroups as $subGroup) {
                $example = \App\Models\Question::where('question_group_id', $subGroup->id)
                    ->doesntHave('answer')
                    ->whereNotNull('properties->img_url')
                    ->inRandomOrder()
                    ->first();
                $subGroup->example_question = $example;
            }
        }
        return view('groups', [
            'groups' => $groups,
            'stats' => [
                'questions' => \App\Models\Question::count(),
                'answers' => \App\Models\Answer::count(),
                'edits' => \App\Models\Edit::count(),
                'users' => \App\Models\User::count(),
            ]
        ]);
    }

}
