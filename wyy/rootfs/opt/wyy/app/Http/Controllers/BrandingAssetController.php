<?php

namespace App\Http\Controllers;

use App\Services\BrandingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class BrandingAssetController extends Controller
{
    public function __construct(private readonly BrandingService $branding) {}

    public function show(Request $request, string $slot): Response
    {
        [$path, $mime] = $this->branding->publicAssetForSlot($slot);

        if (str_starts_with($path, public_path())) {
            return response()->file($path, [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=604800',
            ]);
        }

        return Storage::disk('local')->response($path, basename($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
