<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Canonical Stage Flow (SOI)
    |--------------------------------------------------------------------------
    */
    'stages' => [
        'Proposal',
        'Evaluation',
        'Approval',
        'Implementation',
        'Construction',
        'Operation',
        'Completion',
        'Divestment',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stage-specific minimum required fields
    |--------------------------------------------------------------------------
    */
    'required_fields' => [
        'Proposal' => [
            'title',
            'description',
            'project_type_id',
            'industry_id',
            'sector_id',
            'proposal_date',
        ],
        'Evaluation' => [
            'title',
            'project_type_id',
            'industry_id',
            'sector_id',
            'proposal_date',
        ],
        'Approval' => [
            'title',
            'project_type_id',
            'industry_id',
            'sector_id',
            'proposal_date',
        ],
        'Implementation' => [
            'start_date',
            'target_completion_date',
            'estimated_cost',
            'currency',
        ],
        'Construction' => [
            'start_date',
            'target_completion_date',
            'location_address',
        ],
        'Operation' => [
            'start_date',
        ],
        'Completion' => [
            'actual_completion_date',
        ],
        'Divestment' => [
            'actual_completion_date',
        ],
    ],

    'field_labels' => [
        'title' => 'project title',
        'description' => 'project description',
        'project_type_id' => 'project type',
        'industry_id' => 'industry',
        'sector_id' => 'sector',
        'proposal_date' => 'proposal date',
        'start_date' => 'start date',
        'target_completion_date' => 'target completion date',
        'actual_completion_date' => 'actual completion date',
        'estimated_cost' => 'estimated cost',
        'currency' => 'currency',
        'location_address' => 'location address',
    ],
];
