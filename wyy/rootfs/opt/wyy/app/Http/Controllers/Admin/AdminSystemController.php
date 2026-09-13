<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemDiagnosisService;
use Illuminate\View\View;

class AdminSystemController extends Controller
{
    public function __construct(private readonly SystemDiagnosisService $diagnosis) {}

    public function show(): View
    {
        return view('admin.system.diagnosis', [
            'checks' => $this->diagnosis->checks(),
            'recentErrors' => $this->diagnosis->recentApiErrors(),
        ]);
    }
}
