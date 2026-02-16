<?php

namespace App\Http\Controllers;

use App\Models\Roller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $rollers = Roller::query()
            ->visibleTo($user)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_core']);

        return Inertia::render('Dashboard', [
            'rollers' => $rollers,
        ]);
    }
}
