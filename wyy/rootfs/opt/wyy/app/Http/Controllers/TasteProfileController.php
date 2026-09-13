<?php

namespace App\Http\Controllers;

use App\Services\TasteProfileService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TasteProfileController extends Controller
{
    public function __invoke(Request $request, TasteProfileService $tasteProfile): View
    {
        $summary = $tasteProfile->summary($request->user());

        return view('taste-profile.show', compact('summary'));
    }
}
