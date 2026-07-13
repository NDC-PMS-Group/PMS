<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDivestmentCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exit_strategy' => 'sometimes|required|string|max:10000',
            'target_exit_date' => 'sometimes|nullable|date',
            'estimated_proceeds' => 'sometimes|nullable|numeric|min:0',
            'actual_proceeds' => 'sometimes|nullable|numeric|min:0',
            'notes' => 'sometimes|nullable|string|max:10000',
            'board_approved_at' => 'sometimes|nullable|date',
            'transfer_completed_at' => 'sometimes|nullable|date',
            'proceeds_collected_at' => 'sometimes|nullable|date',
            'closing_documents_completed_at' => 'sometimes|nullable|date',
        ];
    }
}
