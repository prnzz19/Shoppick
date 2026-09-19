<?php

namespace App\Http\Controllers;

use App\Services\PhilippineGeographyService;
use Illuminate\Http\JsonResponse;
use Throwable;

class PhilippineLocationController extends Controller
{
    public function __construct(private readonly PhilippineGeographyService $geography) {}

    public function regions(): JsonResponse
    {
        return $this->respond(fn () => $this->geography->regions());
    }

    public function provinces(string $region): JsonResponse
    {
        return $this->respond(fn () => $this->geography->provinces($region));
    }

    public function regionCitiesMunicipalities(string $region): JsonResponse
    {
        return $this->respond(fn () => $this->geography->citiesMunicipalitiesForRegion($region));
    }

    public function provinceCitiesMunicipalities(string $province): JsonResponse
    {
        return $this->respond(fn () => $this->geography->citiesMunicipalitiesForProvince($province));
    }

    public function barangays(string $cityMunicipality): JsonResponse
    {
        return $this->respond(fn () => $this->geography->barangays($cityMunicipality));
    }

    private function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Philippine locations are temporarily unavailable. Please try again.',
            ], 503);
        }
    }
}
