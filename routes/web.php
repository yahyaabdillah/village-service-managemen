<?php

use App\Http\Controllers\Admin\AdminCrudController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentTemplateController;
use App\Http\Controllers\Admin\ObservabilityController;
use App\Http\Controllers\Admin\ResidentImportExportController;
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
Route::get('/pengajuan/{serviceType:slug}', [PublicController::class, 'requestForm'])->name('requests.create');
Route::post('/pengajuan', [PublicController::class, 'submitRequest'])->middleware('throttle:10,1')->name('requests.store');
Route::get('/pengajuan-sukses/{serviceRequest}', [PublicController::class, 'success'])->name('requests.success');
Route::get('/cek-status', [PublicController::class, 'checkStatusForm'])->name('status.form');
Route::post('/cek-status', [PublicController::class, 'checkStatus'])->middleware('throttle:20,1')->name('status.check');
Route::get('/dokumen/{serviceRequest}/download', [DocumentDownloadController::class, 'prompt'])->name('documents.download');
Route::post('/dokumen/{serviceRequest}/download', [DocumentDownloadController::class, 'authorizeDownload'])->middleware('throttle:20,1')->name('documents.download.authorize');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth'])
    ->group(function () {
        Route::get('/', DashboardController::class)
            ->middleware(EnsureUserCan::class.':manage service requests')
            ->name('dashboard');

        Route::middleware(EnsureUserCan::class.':manage service requests')->group(function () {
            Route::get('/service-requests', [ServiceRequestController::class, 'index'])->name('service-requests.index');
            Route::get('/service-requests/{serviceRequest}', [ServiceRequestController::class, 'show'])->name('service-requests.show');
            Route::get('/service-requests/{serviceRequest}/files/{requestFile}/preview', [ServiceRequestController::class, 'previewRequirementFile'])->name('service-requests.files.preview');
            Route::get('/service-requests/{serviceRequest}/files/{requestFile}/download', [ServiceRequestController::class, 'downloadRequirementFile'])->name('service-requests.files.download');
        });
        Route::patch('/service-requests/{serviceRequest}/verify', [ServiceRequestController::class, 'verify'])->middleware(EnsureUserCan::class.':verify service requests')->name('service-requests.verify');
        Route::patch('/service-requests/{serviceRequest}/process', [ServiceRequestController::class, 'process'])->middleware(EnsureUserCan::class.':process service requests')->name('service-requests.process');
        Route::patch('/service-requests/{serviceRequest}/reject', [ServiceRequestController::class, 'reject'])->middleware(EnsureUserCan::class.':process service requests')->name('service-requests.reject');
        Route::patch('/service-requests/{serviceRequest}/complete', [ServiceRequestController::class, 'complete'])->middleware(EnsureUserCan::class.':process service requests')->name('service-requests.complete');
        Route::patch('/service-requests/{serviceRequest}/publish', [ServiceRequestController::class, 'publish'])
            ->middleware([
                EnsureUserCan::class.':process service requests',
                EnsureUserCan::class.':generate documents',
            ])->name('service-requests.publish');
        Route::post('/service-requests/{serviceRequest}/manual-document', [ServiceRequestController::class, 'uploadManualDocument'])->middleware(EnsureUserCan::class.':upload final documents')->name('service-requests.manual-document');
        Route::post('/service-requests/{serviceRequest}/generate-document', [ServiceRequestController::class, 'generateDocument'])->middleware(EnsureUserCan::class.':generate documents')->name('service-requests.generate-document');
        Route::get('/service-requests/{serviceRequest}/documents/{generatedDocument}', [ServiceRequestController::class, 'downloadDocument'])->middleware(EnsureUserCan::class.':generate documents')->name('service-requests.documents.download');
        Route::post('/service-requests/{serviceRequest}/documents/send-whatsapp', [ServiceRequestController::class, 'sendDocumentWhatsApp'])->middleware(EnsureUserCan::class.':generate documents')->name('service-requests.documents.send-whatsapp');

        Route::middleware(EnsureUserCan::class.':manage document templates')->group(function () {
            Route::get('/document-templates', [DocumentTemplateController::class, 'index'])->name('document-templates.index');
            Route::get('/document-templates/create', [DocumentTemplateController::class, 'create'])->name('document-templates.create');
            Route::post('/document-templates', [DocumentTemplateController::class, 'store'])->name('document-templates.store');
            Route::get('/document-templates/{documentTemplate}/builder', [DocumentTemplateController::class, 'builder'])->name('document-templates.builder');
            Route::get('/document-templates/{documentTemplate}/preview', [DocumentTemplateController::class, 'preview'])->name('document-templates.preview');
            Route::post('/document-templates/{documentTemplate}/fields', [DocumentTemplateController::class, 'storeField'])->name('document-templates.fields.store');
            Route::put('/document-templates/{documentTemplate}/fields/{templateField}', [DocumentTemplateController::class, 'updateField'])->name('document-templates.fields.update');
            Route::delete('/document-templates/{documentTemplate}/fields/{templateField}', [DocumentTemplateController::class, 'destroyField'])->name('document-templates.fields.destroy');
            Route::post('/document-templates/{documentTemplate}/variables', [DocumentTemplateController::class, 'storeVariable'])->name('document-templates.variables.store');
            Route::patch('/document-templates/{documentTemplate}/activate', [DocumentTemplateController::class, 'activate'])->name('document-templates.activate');
        });

        Route::get('/activity-logs', [ObservabilityController::class, 'activityLogs'])->middleware(EnsureUserCan::class.':view activity logs')->name('activity-logs.index');
        Route::get('/security-logs', [ObservabilityController::class, 'securityLogs'])->middleware(EnsureUserCan::class.':view activity logs')->name('security-logs.index');
        Route::get('/notification-logs', [ObservabilityController::class, 'notificationLogs'])->middleware(EnsureUserCan::class.':view activity logs')->name('notification-logs.index');
        Route::get('/whatsapp', [WhatsAppController::class, 'index'])->middleware(EnsureUserCan::class.':manage notifications')->name('whatsapp.index');
        Route::post('/whatsapp/disconnect', [WhatsAppController::class, 'disconnect'])->middleware(EnsureUserCan::class.':manage notifications')->name('whatsapp.disconnect');

        Route::get('/residents/export', [ResidentImportExportController::class, 'export'])->middleware(EnsureUserCan::class.':manage residents')->name('residents.export');
        Route::get('/residents/template', [ResidentImportExportController::class, 'template'])->middleware(EnsureUserCan::class.':manage residents')->name('residents.template');
        Route::post('/residents/import-preview', [ResidentImportExportController::class, 'preview'])->middleware(EnsureUserCan::class.':manage residents')->name('residents.import-preview');
        Route::post('/residents/import', [ResidentImportExportController::class, 'import'])->middleware(EnsureUserCan::class.':manage residents')->name('residents.import');

        $resourcePermissions = [
            'village-profiles' => 'manage village profiles',
            'family-cards' => 'manage family cards',
            'residents' => 'manage residents',
            'service-types' => 'manage service types',
            'service-requirements' => 'manage service requirements',
            'service-type-fields' => 'manage service fields',
            'announcements' => 'manage announcements',
            'users' => 'manage users',
            'roles' => 'manage roles',
        ];

        foreach ($resourcePermissions as $resource => $permission) {
            Route::middleware(EnsureUserCan::class.':'.$permission)->group(function () use ($resource) {
                Route::get("/$resource", [AdminCrudController::class, 'index'])->defaults('resource', $resource)->name($resource.'.index');
                Route::get("/$resource/create", [AdminCrudController::class, 'create'])->defaults('resource', $resource)->name($resource.'.create');
                Route::post("/$resource", [AdminCrudController::class, 'store'])->defaults('resource', $resource)->name($resource.'.store');
                Route::get("/$resource/{id}/edit", [AdminCrudController::class, 'edit'])->defaults('resource', $resource)->name($resource.'.edit');
                Route::patch("/$resource/{id}", [AdminCrudController::class, 'update'])->defaults('resource', $resource)->name($resource.'.update');
                Route::delete("/$resource/{id}", [AdminCrudController::class, 'destroy'])->defaults('resource', $resource)->name($resource.'.destroy');
            });
        }
    });
