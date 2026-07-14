<?php

namespace Vendor\LicenseGuard\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vendor\LicenseGuard\Contracts\LicenseCheckerInterface;
use Vendor\LicenseGuard\Services\LicenseSetupService;
use Vendor\LicenseGuard\Support\EnvFileWriter;

class LicenseSetupController extends Controller
{
    public function show(string $token, LicenseCheckerInterface $checker): View
    {
        $this->authorizeToken($token);

        $status = $checker->isBypassed()
            ? 'bypassed (local dev)'
            : ($checker->check() ? 'valid' : 'invalid');

        return view('license-guard::setup-form', [
            'token' => $token,
            'currentUrl' => (string) config('license-guard.server_url'),
            'currentKey' => (string) config('license-guard.license_key'),
            'hasLicense' => (string) config('license-guard.license_key', '') !== '',
            'status' => $status,
        ]);
    }

    public function store(Request $request, string $token, LicenseSetupService $service): View
    {
        $this->authorizeToken($token);

        $data = $request->validate([
            'url' => ['required', 'string'],
            'key' => ['required', 'string'],
            'secret' => ['required', 'string'],
        ]);

        $result = $service->apply(
            $data['url'],
            $data['key'],
            $data['secret'],
            app()->environmentFilePath(),
            false,
        );

        return view('license-guard::setup-result', [
            'token' => $token,
            'result' => $result,
        ]);
    }

    public function disable(string $token, EnvFileWriter $writer): View
    {
        $this->authorizeToken($token);

        $writer->write(app()->environmentFilePath(), ['LICENSE_SETUP_TOKEN' => '']);

        return view('license-guard::setup-disabled');
    }

    private function authorizeToken(string $token): void
    {
        $configured = (string) config('license-guard.setup_token', '');

        if ($configured === '' || ! hash_equals($configured, $token)) {
            throw new NotFoundHttpException();
        }
    }
}
