@extends('license-guard::layout')

@section('title', $result->success ? 'License Saved' : 'License Setup Failed')

@section('content')
    @if ($result->success)
        <h1>License Saved</h1>
        <div class="status success">
            Verified: <strong>{{ $result->verified }}</strong> &middot; Domain: <strong>{{ $result->domain }}</strong>
        </div>
        <p class="kv"><strong>Added:</strong> {{ $result->added ? implode(', ', $result->added) : 'none' }}</p>
        <p class="kv"><strong>Updated:</strong> {{ $result->updated ? implode(', ', $result->updated) : 'none' }}</p>
        <p class="subtitle">The application should now work normally. For security, disable this setup page once you're done.</p>

        <form method="POST" action="{{ url('/license-setup/'.$token.'/disable') }}">
            @csrf
            <button type="submit" class="secondary">Disable this setup page</button>
        </form>
    @else
        <h1>License Setup Failed</h1>
        <div class="status failure">{{ $result->message }}</div>
        <p class="subtitle">Nothing was saved. Double-check the URL, license key, and secret, then try again.</p>

        <div class="actions">
            <button type="button" class="secondary" onclick="history.back()">Back</button>
        </div>
    @endif
@endsection
