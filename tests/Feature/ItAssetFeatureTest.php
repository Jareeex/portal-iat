<?php

use App\Models\Department;
use App\Models\Page;
use App\Models\User;
use App\Modules\ItAsset\Models\Asset;
use App\Modules\ItAsset\Models\AssetCategory;
use Laravel\Sanctum\Sanctum;

function createItAssetPage(): Page
{
    $department = Department::create([
        'name' => 'Information Technology',
        'code' => 'IT',
        'has_full_access' => false,
    ]);

    return Page::create([
        'department_id' => $department->id,
        'key' => 'it-assets',
        'name' => 'IT Assets',
        'route' => '/it/assets',
        'is_active' => true,
    ]);
}

test('master can manage assets through the api route', function () {
    createItAssetPage();
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    $category = AssetCategory::query()->where('code', 1)->firstOrFail();
    Sanctum::actingAs($master);

    $this->getJson('/api/it/assets')
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);
    $this->getJson('/api/it/assets/categories')
        ->assertOk()
        ->assertJsonCount(7);
    $this->getJson('/api/it/assets/summary')
        ->assertOk()
        ->assertJsonPath('total', 0);

    $created = $this->postJson('/api/it/assets', [
        'asset_category_id' => $category->id,
        'brand' => 'ThinkPad',
        'serial_number' => 'IAT-IT-001',
        'user_name' => 'Portal User',
        'division' => 'IT',
        'condition' => 'Baik',
        'notes' => 'Test asset',
        'status' => 'Aktif',
    ])->assertCreated()
        ->assertJsonPath('data.brand', 'ThinkPad')
        ->assertJsonPath('data.holder', 'Portal User')
        ->json('data');

    $assetId = $created['id'];
    $this->putJson("/api/it/assets/{$assetId}", [
        'asset_category_id' => $category->id,
        'brand' => 'ThinkPad T14',
        'serial_number' => 'IAT-IT-001',
        'user_name' => 'Portal User',
        'status' => 'Aktif',
    ])->assertOk()->assertJsonPath('data.brand', 'ThinkPad T14');

    $this->deleteJson("/api/it/assets/{$assetId}")->assertNoContent();
    expect(Asset::find($assetId))->toBeNull();
});

test('only users with it asset page access can view the asset directory', function () {
    $page = createItAssetPage();
    $user = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($user);

    $this->getJson('/api/it/assets')->assertForbidden();

    $user->pages()->attach($page, ['access_level' => 'view']);
    $this->getJson('/api/it/assets')->assertOk();
    $this->postJson('/api/it/assets', [])->assertForbidden();
});

test('asset export downloads a formatted pdf and template route is not available', function () {
    createItAssetPage();
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    $category = AssetCategory::query()->where('code', 1)->firstOrFail();
    Asset::create([
        'asset_category_id' => $category->id,
        'brand' => 'Dell Latitude',
        'serial_number' => 'PDF-IT-01',
        'user_name' => 'Portal User',
        'division' => 'IT',
        'condition' => 'Baik',
        'notes' => 'Office Jakarta',
        'status' => 'Aktif',
    ]);
    Sanctum::actingAs($master);

    $exportResponse = $this->get('/api/it/assets/export')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition');
    $pdf = $exportResponse->getContent();

    expect($pdf)
        ->toStartWith('%PDF-')
        ->and(strlen($pdf))
        ->toBeGreaterThan(1000)
        ->and($exportResponse->headers->get('content-disposition'))
        ->toContain('attachment;')
        ->toContain('.pdf')
        ->and(app('router')->getRoutes()->hasNamedRoute('it-assets.template'))
        ->toBeFalse();
});

test('empty inventory can still be exported as a pdf', function () {
    createItAssetPage();
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    Sanctum::actingAs($master);

    $response = $this->get('/api/it/assets/export')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF-');
});

test('csv import endpoint is not registered', function () {
    expect(app('router')->getRoutes()->hasNamedRoute('it-assets.import.store'))
        ->toBeFalse();
});
