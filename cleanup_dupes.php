<?php

use App\Models\Caso;
use Illuminate\Support\Facades\DB;

// Find duplicates based on radicado and referencia_credito
$duplicates = Caso::select('radicado', 'referencia_credito')
    ->whereNotNull('radicado')
    ->where('radicado', '!=', '')
    ->whereNotNull('referencia_credito')
    ->where('referencia_credito', '!=', '')
    ->groupBy('radicado', 'referencia_credito')
    ->havingRaw('count(*) > 1')
    ->get();

echo "Found " . $duplicates->count() . " sets of exact duplicates (Radicado + Pagare)" . PHP_EOL;

foreach ($duplicates as $set) {
    $cases = Caso::where('radicado', $set->radicado)
        ->where('referencia_credito', $set->referencia_credito)
        ->withCount(['actuaciones', 'documentos', 'pagos'])
        ->get();
    
    // Sort by "completeness"
    $cases = $cases->sortByDesc(function($case) {
        $score = 0;
        $score += $case->actuaciones_count * 10;
        $score += $case->documentos_count * 5;
        $score += $case->pagos_count * 20;
        if ($case->monto_total > 0) $score += 5;
        if ($case->juzgado_id) $score += 5;
        if ($case->especialidad_id) $score += 5;
        if ($case->link_drive) $score += 5;
        return $score;
    });

    $keep = $cases->shift();
    echo "Keeping Case ID {$keep->id} (Score: ...) for Radicado {$set->radicado}" . PHP_EOL;

    foreach ($cases as $dupe) {
        echo "Deleting Duplicate Case ID {$dupe->id}" . PHP_EOL;
        $dupe->delete(); // Use soft delete if available
    }
}

// Now check duplicates for just Radicado
$radDupes = Caso::select('radicado')
    ->whereNotNull('radicado')
    ->where('radicado', '!=', '')
    ->groupBy('radicado')
    ->havingRaw('count(*) > 1')
    ->get();

echo "Found " . $radDupes->count() . " sets of Radicado duplicates" . PHP_EOL;

foreach ($radDupes as $set) {
    $cases = Caso::where('radicado', $set->radicado)
        ->withCount(['actuaciones', 'documentos', 'pagos'])
        ->get();
    
    if ($cases->count() <= 1) continue;

    $cases = $cases->sortByDesc(function($case) {
        $score = 0;
        $score += $case->actuaciones_count * 10;
        $score += $case->documentos_count * 5;
        $score += $case->pagos_count * 20;
        return $score;
    });

    $keep = $cases->shift();
    echo "Keeping Case ID {$keep->id} for Radicado {$set->radicado}" . PHP_EOL;
    foreach ($cases as $dupe) {
        echo "Deleting Duplicate Case ID {$dupe->id}" . PHP_EOL;
        $dupe->delete();
    }
}

// Now check duplicates for just Pagare (referencia_credito)
$refDupes = Caso::select('referencia_credito')
    ->whereNotNull('referencia_credito')
    ->where('referencia_credito', '!=', '')
    ->groupBy('referencia_credito')
    ->havingRaw('count(*) > 1')
    ->get();

echo "Found " . $refDupes->count() . " sets of Pagare duplicates" . PHP_EOL;

foreach ($refDupes as $set) {
    $cases = Caso::where('referencia_credito', $set->referencia_credito)
        ->withCount(['actuaciones', 'documentos', 'pagos'])
        ->get();
    
    if ($cases->count() <= 1) continue;

    $cases = $cases->sortByDesc(function($case) {
        $score = 0;
        $score += $case->actuaciones_count * 10;
        $score += $case->documentos_count * 5;
        $score += $case->pagos_count * 20;
        return $score;
    });

    $keep = $cases->shift();
    echo "Keeping Case ID {$keep->id} for Pagare {$set->referencia_credito}" . PHP_EOL;
    foreach ($cases as $dupe) {
        echo "Deleting Duplicate Case ID {$dupe->id}" . PHP_EOL;
        $dupe->delete();
    }
}
