<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Canonical Stage Flow (SOI)
    |--------------------------------------------------------------------------
    */
    'stages' => [
        'Intake',
        'Requirements',
        'Due Diligence',
        'Management Review',
        'Board Approval',
        'Agreement & Fund Release',
        'Implementation & Monitoring',
        'Post-Investment Strategy',
        'Divestment',
        'Completion',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stage-specific minimum required fields
    |--------------------------------------------------------------------------
    */
    'required_fields' => [
        'Intake' => [
            'title',
            'description',
            'project_type_id',
            'industry_id',
            'sector_id',
            'proposal_date',
        ],
        'Requirements' => [
            'title',
            'project_type_id',
            'industry_id',
            'sector_id',
            'proposal_date',
            'proponent_name',
        ],
        'Due Diligence' => [
            'title',
            'project_type_id',
            'industry_id',
            'sector_id',
            'proposal_date',
            'estimated_cost',
            'target_amount_to_raise',
        ],
        'Management Review' => [
            'title',
            'project_type_id',
            'industry_id',
            'sector_id',
            'estimated_cost',
        ],
        'Board Approval' => [
            'title',
            'project_type_id',
            'industry_id',
            'sector_id',
            'estimated_cost',
        ],
        'Agreement & Fund Release' => [
            'start_date',
            'target_completion_date',
            'estimated_cost',
            'currency',
        ],
        'Implementation & Monitoring' => [],
        'Post-Investment Strategy' => [
            'start_date',
            'post_investment_strategy',
        ],
        'Divestment' => [],
        'Completion' => [
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
        'proponent_name' => 'proponent name',
        'start_date' => 'start date',
        'target_completion_date' => 'target completion date',
        'actual_completion_date' => 'actual completion date',
        'estimated_cost' => 'estimated cost',
        'target_amount_to_raise' => 'target amount to raise',
        'currency' => 'currency',
        'location_address' => 'location address',
        'post_investment_strategy' => 'post-investment strategy',
    ],
];
