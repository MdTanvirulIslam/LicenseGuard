<?php

namespace Vendor\LicenseGuard\Exceptions;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LicenseInvalidException extends \RuntimeException
{
    public function render(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'License validation failed.'], 403);
        }

        return response('License validation failed.', 403);
    }
}
