@extends('license-guard::layout')

@section('title', 'License Setup')

@section('content')
    <h1>License Setup</h1>
    <p class="subtitle">Configure this application's license without needing terminal access.</p>

    <div class="status {{ $status === 'valid' ? 'valid' : (str_starts_with($status, 'bypassed') ? 'bypassed' : 'invalid') }}">
        @if ($hasLicense)
            Current license status: <strong>{{ $status }}</strong>
        @else
            No license is configured yet.
        @endif
    </div>

    @if ($errors->any())
        <div class="errors">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ url('/license-setup/'.$token) }}">
        @csrf

        <label for="url">License server URL</label>
        <input type="url" id="url" name="url" value="{{ old('url', $currentUrl) }}" placeholder="https://license.example.com" required>

        <label for="key">License key</label>
        <input type="text" id="key" name="key" value="{{ old('key', $currentKey) }}" placeholder="XXXX-XXXX-XXXX-XXXX" required>

        <label for="secret">Product secret key</label>
        <input type="password" id="secret" name="secret" placeholder="Paste the product secret key" required>

        <button type="submit">Verify &amp; Save</button>
    </form>
@endsection
