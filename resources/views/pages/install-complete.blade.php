@extends('layouts.app')
@section('title', 'Installatie voltooid')
@section('content')
<div class="install-wrap"><div class="install-card"><span class="eyebrow"><i></i> KLAAR</span><h1>Het platform is geïnstalleerd.</h1><p>Zet nu <code>INSTALLATION_LOCKED=true</code> in `.env` en log in met Discord.</p><a class="button button-primary" href="{{ route('home') }}">Naar het platform →</a></div></div>
@endsection
