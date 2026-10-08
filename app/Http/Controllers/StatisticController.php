<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

final class StatisticController extends Controller
{
    public function index()
    {
        return Inertia::render('app/statistics/index');
    }
}
