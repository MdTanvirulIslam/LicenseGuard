@extends('license-guard::layout')

@section('title', 'Setup Page Disabled')

@section('content')
    <h1>Setup Page Disabled</h1>
    <div class="status success">LICENSE_SETUP_TOKEN has been cleared from .env.</div>
    <p class="subtitle">This page is no longer reachable. To use it again, set LICENSE_SETUP_TOKEN in .env via your host's file manager.</p>
@endsection
