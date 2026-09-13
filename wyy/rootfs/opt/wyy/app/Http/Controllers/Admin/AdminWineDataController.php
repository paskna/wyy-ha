<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GrapeVariety;
use App\Models\Producer;
use App\Models\Wine;
use App\Models\WineVintage;
use App\Services\AuditLogService;
use App\Services\SettingsService;
use App\Services\WineAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminWineDataController extends Controller
{
    public function __construct(
        private readonly WineAdminService $wineAdmin,
        private readonly AuditLogService $auditLog,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request, string $section = 'wines'): View
    {
        $search = $request->string('q')->toString();

        $perPage = max(5, (int) $this->settings->get('general', 'per_page', 12));

        return view('admin.wine-data.index', [
            'section' => $section,
            'sections' => [
                'wines' => 'Weine',
                'producers' => 'Produzenten',
                'vintages' => 'Jahrgaenge',
                'grapes' => 'Rebsorten',
                'duplicates' => 'Moegliche Duplikate',
            ],
            'search' => $search,
            'records' => match ($section) {
                'wines' => Wine::query()->with('producer')->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))->latest()->paginate($perPage)->withQueryString(),
                'producers' => Producer::query()->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))->latest()->paginate($perPage)->withQueryString(),
                'vintages' => WineVintage::query()->with('wine.producer')->when($search, fn ($query) => $query->where('vintage', 'like', '%'.$search.'%')->orWhereHas('wine', fn ($wine) => $wine->where('name', 'like', '%'.$search.'%')))->latest()->paginate($perPage)->withQueryString(),
                'grapes' => GrapeVariety::query()->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))->paginate($perPage)->withQueryString(),
                'duplicates' => $this->wineAdmin->possibleDuplicates(),
                default => abort(404),
            },
        ]);
    }

    public function editWine(Wine $wine): View
    {
        return view('admin.wine-data.edit-wine', ['wine' => $wine->load('producer')]);
    }

    public function updateWine(Request $request, Wine $wine): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'wine_type' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'subregion' => ['nullable', 'string', 'max:100'],
            'appellation' => ['nullable', 'string', 'max:100'],
            'classification' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $wine->update($data + ['normalized_name' => str($data['name'])->lower()->ascii()->replace(' ', '-')]);
        $this->auditLog->log($request->user(), $request->user(), 'wine_data_updated', ['type' => 'wine', 'wine_id' => $wine->id]);

        return redirect()->route('admin.wine-data.index', ['section' => 'wines'])->with('status', 'Weinstammdaten aktualisiert.');
    }

    public function editProducer(Producer $producer): View
    {
        return view('admin.wine-data.edit-producer', compact('producer'));
    }

    public function updateProducer(Request $request, Producer $producer): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $producer->update($data + ['normalized_name' => str($data['name'])->lower()->ascii()->replace(' ', '-')]);
        $this->auditLog->log($request->user(), $request->user(), 'wine_data_updated', ['type' => 'producer', 'producer_id' => $producer->id]);

        return redirect()->route('admin.wine-data.index', ['section' => 'producers'])->with('status', 'Produzent aktualisiert.');
    }

    public function editVintage(WineVintage $vintage): View
    {
        return view('admin.wine-data.edit-vintage', ['vintage' => $vintage->load('wine.producer')]);
    }

    public function updateVintage(Request $request, WineVintage $vintage): RedirectResponse
    {
        $data = $request->validate([
            'vintage' => ['nullable', 'string', 'max:10'],
            'colour' => ['nullable', 'string', 'max:100'],
            'body' => ['nullable', 'numeric', 'between:0,1'],
            'tannin' => ['nullable', 'numeric', 'between:0,1'],
            'acidity' => ['nullable', 'numeric', 'between:0,1'],
            'sweetness' => ['nullable', 'numeric', 'between:0,1'],
            'oak' => ['nullable', 'numeric', 'between:0,1'],
            'description' => ['nullable', 'string'],
            'pairing_suggestions' => ['nullable', 'string'],
        ]);

        $vintage->update($data);
        $this->auditLog->log($request->user(), $request->user(), 'wine_data_updated', ['type' => 'vintage', 'vintage_id' => $vintage->id]);

        return redirect()->route('admin.wine-data.index', ['section' => 'vintages'])->with('status', 'Jahrgang aktualisiert.');
    }

    public function editGrape(GrapeVariety $grape): View
    {
        return view('admin.wine-data.edit-grape', compact('grape'));
    }

    public function updateGrape(Request $request, GrapeVariety $grape): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $grape->update($data);
        $this->auditLog->log($request->user(), $request->user(), 'wine_data_updated', ['type' => 'grape', 'grape_id' => $grape->id]);

        return redirect()->route('admin.wine-data.index', ['section' => 'grapes'])->with('status', 'Rebsorte aktualisiert.');
    }

    public function mergeVintages(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_id' => ['required', 'integer', 'different:target_id'],
            'target_id' => ['required', 'integer'],
        ]);

        $source = WineVintage::query()->findOrFail($data['source_id']);
        $target = WineVintage::query()->findOrFail($data['target_id']);

        $this->wineAdmin->mergeVintages($source, $target);
        $this->auditLog->log($request->user(), $request->user(), 'wine_merged', ['source_vintage_id' => $source->id, 'target_vintage_id' => $target->id]);

        return back()->with('status', 'Duplikate wurden zusammengefuehrt.');
    }
}
