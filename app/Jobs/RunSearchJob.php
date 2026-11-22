<?php

namespace App\Jobs;

use App\Models\District;
use App\Models\SearchJob;
use App\Services\Places\BusinessImporter;
use App\Services\Places\PlacesClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $searchJobId) {}

    public function handle(PlacesClient $places, BusinessImporter $importer): void
    {
        $job = SearchJob::with(['province', 'category'])->findOrFail($this->searchJobId);
        $job->update(['status' => 'running', 'error' => null]);

        $districts = District::whereIn('id', $job->district_ids ?? [])->get();
        $found = 0;
        $qualified = 0;

        foreach ($districts as $district) {
            $query = trim("{$job->category->search_term} {$district->name} {$job->province->name}");
            $bias = $district->lat ? ['lat' => $district->lat, 'lng' => $district->lng, 'radius' => 6000.0] : null;

            $results = $places->searchText($query, $job->category->included_type, $bias);
            $found += count($results);

            foreach ($results as $place) {
                if (! $this->inDistrict($place, $district->name)) {
                    continue; // metin araması komşu ilçelerden de sonuç döndürebilir
                }

                if ($business = $importer->import($place, $job, $district->id)) {
                    $qualified++;
                    $importer->dispatchGenerationIfAutomatic($business);
                }
            }

            $job->update(['found_count' => $found, 'qualified_count' => $qualified, 'api_calls' => $places->calls]);
        }

        $job->update(['status' => 'completed', 'finished_at' => now()]);
    }

    private function inDistrict(array $place, string $districtName): bool
    {
        $address = $place['formattedAddress'] ?? '';

        return $address === '' || mb_stripos(mb_strtolower($address, 'UTF-8'), mb_strtolower($districtName, 'UTF-8')) !== false;
    }

    public function failed(Throwable $e): void
    {
        SearchJob::whereKey($this->searchJobId)->update([
            'status' => 'failed',
            'error' => mb_substr($e->getMessage(), 0, 2000),
            'finished_at' => now(),
        ]);
    }
}
