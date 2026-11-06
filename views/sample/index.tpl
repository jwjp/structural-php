{{-- A page that extends the layout and fills its sections. --}}
@extends('layouts/app')

@section('title')
{{ $title }} · structural-php
@endsection

@section('content')
<h1>{{ $title }}</h1>
<p>{{ $content }}</p>

<h2>Sample API</h2>
<table>
    <thead><tr><th>Method</th><th>Path</th><th>Route name</th></tr></thead>
    <tbody>
    @foreach($endpoints as $endpoint)
        @include('partials/endpoint', ['readOnly' => $readOnly])
    @endforeach
    </tbody>
</table>

@if($readOnly)
    <p class="note">Read-only mode is on: write requests return 503.</p>
@else
    <p class="note">Write requests are accepted. Set <code>READ_ONLY = "true"</code> under <code>[APP]</code> to block them.</p>
@endif

<p class="note">Template syntax such as @{{ $title }} is printed literally when it starts with @.</p>
@endsection
