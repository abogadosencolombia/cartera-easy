<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class AlertasPageController extends Controller
{
    public function preventivas()
    {
        return Inertia::render('Alertas/Preventivas', [
            'defaultDays' => 7,
        ]);
    }

    public function reactivas()
    {
        return Inertia::render('Alertas/Reactivas', [
            'defaultDays' => 7,
        ]);
    }

    public function hallazgos()
    {
        return Inertia::render('Alertas/Hallazgos', [
            'defaultDays' => 30,
        ]);
    }

    public function reporteCliente()
    {
        return Inertia::render('Alertas/ReporteCliente', [
            'defaultDays' => 15,
        ]);
    }

    public function vigilanciaJudicial()
    {
        return Inertia::render('Alertas/VigilanciaJudicial', [
            'defaultDays' => 8,
        ]);
    }
}
