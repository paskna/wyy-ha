<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;

class WineRecognitionService
{
    public function __construct(private readonly IntegrationManager $integrations) {}

    public function recognize(UploadedFile $file, ?User $user = null): array
    {
        return $this->integrations->recognizeWineLabel($file, $user);
    }
}
