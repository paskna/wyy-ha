<?php

namespace App\Http\Controllers;

use App\Models\WineImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageController extends Controller
{
    public function __invoke(Request $request, WineImage $wineImage): StreamedResponse
    {
        $this->authorize('view', $wineImage);

        return Storage::disk('local')->response($wineImage->optimized_path ?: $wineImage->original_path);
    }
}
