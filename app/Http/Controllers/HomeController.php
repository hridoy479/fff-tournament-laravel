<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        return Inertia::render('Home', [
            'featuredTournaments' => \App\Models\Tournament::featured()->get(),
            'ongoingTournaments' => \App\Models\Tournament::ongoing()->get(),
        ]);
    }
}