<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppNotificationService;

class WhatsAppController extends Controller
{
    public function index(WhatsAppNotificationService $whatsApp)
    {
        return view('admin.whatsapp.index', [
            'status' => $whatsApp->status(),
            'qr' => $whatsApp->qr(),
            'qrImage' => $whatsApp->qrImage(),
        ]);
    }

    public function disconnect(WhatsAppNotificationService $whatsApp)
    {
        try {
            $result = $whatsApp->disconnectBridge();

            return redirect()
                ->route('admin.whatsapp.index')
                ->with('status', $result['message']);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.whatsapp.index')
                ->withErrors(['whatsapp' => 'WhatsApp gagal diputuskan. Coba lagi beberapa saat.']);
        }
    }
}
