<?php

use App\Http\Controllers\Admin\AdminCrudController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentTemplateController;
use App\Http\Controllers\Admin\ObservabilityController;
use App\Http\Controllers\Admin\ResidentImportExportController;
use App\Http\Controllers\Admin\ServiceConfigurationController;
use App\Http\Controllers\Admin\ServiceRequestController;
use App\Http\Controllers\Admin\WhatsAppController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PublicController;
use App\Http\Middleware\EnsureUserCan;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', HealthController::class)->name('health');
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/layanan', [PublicController::class, 'services'])->name('services.index');
Route::get('/layanan/{serviceType:slug}', [PublicController::class, 'serviceDetail'])->name('services.show');
Route::get('/pengumuman/{announcement:slug}', [PublicController::class, 'announcement'])->name('announcements.show');
Route::get('/pengajuan/{serviceType:slug}', [PublicController::class, 'requestForm'])->name('requests.create');
Route::post('/pengajuan', [PublicController::class, 'submitRequest'])->middleware('throttle:10,1')->name('requests.store');
Route::get('/pengajuan-sukses/{serviceRequest}', [PublicController::class, 'success'])->name('requests.success');
Route::get('/cek-status', [PublicController::class, 'checkStatusForm'])->name('status.form');
Route::post('/cek-status', [PublicController::class, 'checkStatus'])->middleware('throttle:20,1')->name('status.check');
Route::get('/dokumen/{serviceRequest}/download', [DocumentDownloadController::class, 'prompt'])->name('documents.download');
Route::post('/dokumen/{serviceRequest}/download', [DocumentDownloadController::class, 'authorizeDownload'])->middleware('throttle:20,1')->name('documents.download.authorize');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

$can = fn (string $permission) => EnsureUserCan::class.':'.$permission;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth'])
    ->group(function () use ($can) {
        Route::get('/', DashboardController::class)->middleware($can('dashboard.view'))->name('dashboard');

        Route::middleware($can('service-requests.view'))->group(function () {
            Route::get('/service-requests', [ServiceRequestController::class, 'index'])->name('service-requests.index');
        });
        // Registered ahead of the {serviceRequest} wildcard below so "report" is never
        // captured as a request id.
        Route::get('/service-requests/report', [ServiceRequestController::class, 'exportReport'])->middleware($can('service-requests.export'))->name('service-requests.report');
        Route::middleware($can('service-requests.view'))->group(function () {
            Route::get('/service-requests/{serviceRequest}', [ServiceRequestController::class, 'show'])->name('service-requests.show');
            Route::get('/service-requests/{serviceRequest}/files/{requestFile}/preview', [ServiceRequestController::class, 'previewRequirementFile'])->name('service-requests.files.preview');
            Route::get('/service-requests/{serviceRequest}/files/{requestFile}/download', [ServiceRequestController::class, 'downloadRequirementFile'])->name('service-requests.files.download');
            Route::get('/service-requests/{serviceRequest}/documents/{generatedDocument}', [ServiceRequestController::class, 'downloadDocument'])->name('service-requests.documents.download');
        });
        Route::patch('/service-requests/{serviceRequest}/verify', [ServiceRequestController::class, 'verify'])->middleware($can('service-requests.verify'))->name('service-requests.verify');
        Route::patch('/service-requests/{serviceRequest}/process', [ServiceRequestController::class, 'process'])->middleware($can('service-requests.process'))->name('service-requests.process');
        Route::patch('/service-requests/{serviceRequest}/reject', [ServiceRequestController::class, 'reject'])->middleware($can('service-requests.reject'))->name('service-requests.reject');
        Route::patch('/service-requests/{serviceRequest}/complete', [ServiceRequestController::class, 'complete'])->middleware($can('service-requests.complete'))->name('service-requests.complete');
        Route::patch('/service-requests/{serviceRequest}/publish', [ServiceRequestController::class, 'publish'])->middleware($can('service-requests.generate-document'))->name('service-requests.publish');
        Route::post('/service-requests/{serviceRequest}/manual-document', [ServiceRequestController::class, 'uploadManualDocument'])->middleware($can('service-requests.upload-document'))->name('service-requests.manual-document');
        Route::post('/service-requests/{serviceRequest}/generate-document', [ServiceRequestController::class, 'generateDocument'])->middleware($can('service-requests.generate-document'))->name('service-requests.generate-document');
        Route::post('/service-requests/{serviceRequest}/documents/send-whatsapp', [ServiceRequestController::class, 'sendDocumentWhatsApp'])->middleware($can('service-requests.send-whatsapp'))->name('service-requests.documents.send-whatsapp');

        Route::middleware($can('document-templates.view'))->group(function () {
            Route::get('/document-templates', [DocumentTemplateController::class, 'index'])->name('document-templates.index');
            Route::get('/document-templates/{documentTemplate}/builder', [DocumentTemplateController::class, 'builder'])->name('document-templates.builder');
            Route::get('/document-templates/{documentTemplate}/preview', [DocumentTemplateController::class, 'preview'])->name('document-templates.preview');
            Route::get('/document-templates/{documentTemplate}/sample', [DocumentTemplateController::class, 'sample'])->name('document-templates.sample');
        });
        Route::middleware($can('document-templates.create'))->group(function () {
            Route::get('/document-templates/create', [DocumentTemplateController::class, 'create'])->name('document-templates.create');
            Route::post('/document-templates', [DocumentTemplateController::class, 'store'])->name('document-templates.store');
        });
        Route::middleware($can('document-templates.update'))->group(function () {
            Route::patch('/document-templates/{documentTemplate}', [DocumentTemplateController::class, 'update'])->name('document-templates.update');
            Route::post('/document-templates/{documentTemplate}/fields', [DocumentTemplateController::class, 'storeField'])->name('document-templates.fields.store');
            Route::put('/document-templates/{documentTemplate}/fields/{templateField}', [DocumentTemplateController::class, 'updateField'])->name('document-templates.fields.update');
            Route::delete('/document-templates/{documentTemplate}/fields/{templateField}', [DocumentTemplateController::class, 'destroyField'])->name('document-templates.fields.destroy');
            Route::post('/document-templates/{documentTemplate}/variables', [DocumentTemplateController::class, 'storeVariable'])->name('document-templates.variables.store');
            Route::patch('/document-templates/{documentTemplate}/activate', [DocumentTemplateController::class, 'activate'])->name('document-templates.activate');
        });
        Route::delete('/document-templates/{documentTemplate}', [DocumentTemplateController::class, 'destroy'])->middleware($can('document-templates.delete'))->name('document-templates.destroy');

        Route::get('/activity-logs', [ObservabilityController::class, 'activityLogs'])->middleware($can('activity-logs.view'))->name('activity-logs.index');
        Route::get('/security-logs', [ObservabilityController::class, 'securityLogs'])->middleware($can('activity-logs.view'))->name('security-logs.index');
        Route::get('/notification-logs', [ObservabilityController::class, 'notificationLogs'])->middleware($can('notification-logs.view'))->name('notification-logs.index');
        Route::get('/whatsapp', [WhatsAppController::class, 'index'])->middleware($can('whatsapp.view'))->name('whatsapp.index');
        Route::post('/whatsapp/start', [WhatsAppController::class, 'start'])->middleware($can('whatsapp.manage'))->name('whatsapp.start');
        Route::post('/whatsapp/disconnect', [WhatsAppController::class, 'disconnect'])->middleware($can('whatsapp.manage'))->name('whatsapp.disconnect');

        Route::get('/residents/export', [ResidentImportExportController::class, 'export'])->middleware($can('residents.export'))->name('residents.export');
        Route::get('/residents/template', [ResidentImportExportController::class, 'template'])->middleware($can('residents.import'))->name('residents.template');
        Route::post('/residents/import-preview', [ResidentImportExportController::class, 'preview'])->middleware($can('residents.import'))->name('residents.import-preview');
        Route::post('/residents/import', [ResidentImportExportController::class, 'import'])->middleware($can('residents.import'))->name('residents.import');

        // Service configuration: one screen per letter type (info, custom fields, documents, templates).
        Route::get('/service-types', [ServiceConfigurationController::class, 'index'])->middleware($can('service-types.view'))->name('service-types.index');
        Route::get('/service-types/create', [AdminCrudController::class, 'create'])->middleware($can('service-types.create'))->defaults('resource', 'service-types')->name('service-types.create');
        Route::post('/service-types', [AdminCrudController::class, 'store'])->middleware($can('service-types.create'))->defaults('resource', 'service-types')->name('service-types.store');
        Route::middleware($can('service-types.update'))->group(function () {
            Route::get('/service-types/{serviceType}/edit', [ServiceConfigurationController::class, 'edit'])->name('service-types.edit');
            Route::patch('/service-types/{serviceType}', [ServiceConfigurationController::class, 'update'])->name('service-types.update');
            Route::post('/service-types/{serviceType}/fields', [ServiceConfigurationController::class, 'storeField'])->name('service-types.fields.store');
            Route::patch('/service-types/{serviceType}/fields/{field}', [ServiceConfigurationController::class, 'updateField'])->name('service-types.fields.update');
            Route::delete('/service-types/{serviceType}/fields/{field}', [ServiceConfigurationController::class, 'destroyField'])->name('service-types.fields.destroy');
            Route::post('/service-types/{serviceType}/requirements', [ServiceConfigurationController::class, 'storeRequirement'])->name('service-types.requirements.store');
            Route::patch('/service-types/{serviceType}/requirements/{requirement}', [ServiceConfigurationController::class, 'updateRequirement'])->name('service-types.requirements.update');
            Route::delete('/service-types/{serviceType}/requirements/{requirement}', [ServiceConfigurationController::class, 'destroyRequirement'])->name('service-types.requirements.destroy');
        });
        Route::delete('/service-types/{id}', [AdminCrudController::class, 'destroy'])->middleware($can('service-types.delete'))->defaults('resource', 'service-types')->name('service-types.destroy');

        // Generic CRUD screens. Each URL resource maps onto a catalogued permission resource;
        // the three service-configuration tables share one, since they describe one thing.
        $resourcePermissions = [
            'village-profiles' => 'village-profile',
            'family-cards' => 'family-cards',
            'residents' => 'residents',
            'announcements' => 'announcements',
            'users' => 'users',
            'roles' => 'roles',
        ];

        foreach ($resourcePermissions as $resource => $permission) {
            Route::get("/$resource", [AdminCrudController::class, 'index'])->middleware($can("$permission.view"))->defaults('resource', $resource)->name($resource.'.index');
            Route::get("/$resource/create", [AdminCrudController::class, 'create'])->middleware($can("$permission.create"))->defaults('resource', $resource)->name($resource.'.create');
            Route::post("/$resource", [AdminCrudController::class, 'store'])->middleware($can("$permission.create"))->defaults('resource', $resource)->name($resource.'.store');
            Route::get("/$resource/{id}/edit", [AdminCrudController::class, 'edit'])->middleware($can("$permission.update"))->defaults('resource', $resource)->name($resource.'.edit');
            Route::patch("/$resource/{id}", [AdminCrudController::class, 'update'])->middleware($can("$permission.update"))->defaults('resource', $resource)->name($resource.'.update');
            Route::delete("/$resource/{id}", [AdminCrudController::class, 'destroy'])->middleware($can("$permission.delete"))->defaults('resource', $resource)->name($resource.'.destroy');
        }
    });
