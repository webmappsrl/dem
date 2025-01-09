<?php

namespace App\Services;

use App\Jobs\SqlImportJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class GridImporterService
{

    private $importChunkSize = 500;

    private function getInsertIntoQueries($sql): array
    {
        $matched = preg_match_all('#^INSERT INTO.+#m', $sql, $matches, PREG_SET_ORDER);
        return $matches;
    }

    public function dispatchGridImportBatch($gridSquare, $country = false): int
    {
        $this->validateGridSquare($gridSquare);
        $insertInto = $this->getInsertIntoQueries($this->getImportSquareSql($gridSquare));
        $chunks = array_chunk($insertInto, $this->importChunkSize);
        unset($insertInto);

        $jobs = [];
        foreach ($chunks as $chunk) {
            $jobs[] = new SqlImportJob(implode("", $chunk));
        }

        $batchName = $country ? "{$country}_{$gridSquare}" : $gridSquare;
        Bus::batch([$jobs])->name($batchName)->dispatch();
        return count($jobs);
    }

    private function getImportSquareSql($gridSquare): string
    {
        $squareSize = '25x25';
        $srid = 4326;
        $awsFilePath = "eu_original/{$squareSize}/SQL/{$gridSquare}_{$squareSize}_{$srid}.sql";
        $sql = Storage::disk('wmmapdata')->get($awsFilePath);
        if (empty($sql)) {
            throw new \Exception("File not found: {$awsFilePath}");
        }
        return $sql;
    }

    private function validateGridSquare($gridSquare): bool
    {
        if (strpos($gridSquare, 'Grid_') !== 0) {
            throw new \Exception("Invalid grid square format: {$gridSquare}. 'Grid_' prefix is missing");
        }

        $gridSquare = explode('_', $gridSquare);
        if (count($gridSquare) !== 3) {
            throw new \Exception("Invalid grid square format: {$gridSquare}. Expected format is 'Grid_lng_lat'");
        }
        return true;
    }
}
