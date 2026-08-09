<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A single "coming soon" screen standing in for admin sections that are wired
 * into the sidebar but not yet built (Orders, Customers, Categories, …).
 *
 * Each placeholder route names its section so the shared page can title itself.
 * These are replaced by real screens in later phases.
 */
class PlaceholderController extends Controller
{
    public function show(string $section, string $titleKey): Response
    {
        return Inertia::render('admin/coming-soon', [
            'section' => $section,
            'titleKey' => $titleKey,
        ]);
    }
}
