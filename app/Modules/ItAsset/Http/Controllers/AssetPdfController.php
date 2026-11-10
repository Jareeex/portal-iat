<?php

namespace App\Modules\ItAsset\Http\Controllers;

use App\Modules\ItAsset\Models\Asset;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AssetPdfController
{
    public function export(Request $request): Response
    {
        $assets = Asset::with(['category', 'user'])
            ->filter($request->only(['q', 'category', 'status', 'condition', 'division']))
            ->orderBy('id')
            ->get();

        return Pdf::loadView('it-asset::pdf.assets', [
            'assets' => $assets,
            'generatedAt' => now(),
        ])
            ->setPaper('a4', 'landscape')
            ->download('it-assets-'.now()->format('Ymd-His').'.pdf');
    }
}
