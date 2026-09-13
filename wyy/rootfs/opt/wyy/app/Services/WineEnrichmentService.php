<?php

namespace App\Services;

class WineEnrichmentService
{
    public function __construct(private readonly IntegrationManager $integrations) {}

    public function enrich(array $recognized): array
    {
        return $this->integrations->enrichWineData($recognized);
    }
}
