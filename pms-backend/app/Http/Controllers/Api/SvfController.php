<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSvfApplicationRequest;
use App\Http\Requests\EvaluateSvfRequest;
use App\Models\SvfApplication;
use App\Models\SvfEvaluation;
use Illuminate\Http\Request;

class SvfController extends Controller
{
    public function index(Request $request)
    {
        $applications = SvfApplication::with('project')->paginate(15);
        return response()->json($applications);
    }

    public function store(StoreSvfApplicationRequest $request)
    {
        $application = SvfApplication::create(array_merge(
            $request->validated(),
            ['application_number' => $this->generateApplicationNumber()]
        ));

        return response()->json($application, 201);
    }

    public function show(SvfApplication $svfApplication)
    {
        return response()->json($svfApplication->load(['project', 'evaluations']));
    }

    public function evaluate(EvaluateSvfRequest $request, SvfApplication $svfApplication)
    {
        foreach ($request->evaluations as $evaluation) {
            SvfEvaluation::create([
                'svf_application_id' => $svfApplication->id,
                'criteria_id' => $evaluation['criteria_id'],
                'evaluator_id' => auth()->id(),
                'score' => $evaluation['score'],
                'comments' => $evaluation['comments'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Evaluation submitted successfully']);
    }

    public function evaluations(SvfApplication $svfApplication)
    {
        $evaluations = $svfApplication->evaluations()
            ->with(['criteria', 'evaluator'])
            ->get();

        return response()->json($evaluations);
    }

    private function generateApplicationNumber()
    {
        $year = date('Y');
        $lastApp = SvfApplication::where('application_number', 'like', "SVF-{$year}-%")
            ->orderBy('application_number', 'desc')
            ->first();

        if ($lastApp) {
            $lastNumber = (int) substr($lastApp->application_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "SVF-{$year}-{$newNumber}";
    }
}