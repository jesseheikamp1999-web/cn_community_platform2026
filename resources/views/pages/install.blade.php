@extends('layouts.app')
@section('title', 'Installatie — CN Community')
@section('content')
<div class="install-wrap"><div class="install-card"><span class="eyebrow"><i></i> INSTALLATIEWIZARD</span><h1>CN Community Platform 2026</h1><p>Controleer de server en maak de database gereed.</p>
    @foreach($checks as $label => $ok)<div class="check"><span>{{ $label }}</span><b class="{{ $ok ? 'ok' : 'fail' }}">{{ $ok ? 'Gereed' : 'Niet beschikbaar' }}</b></div>@endforeach
    @if($databaseMessage)<p style="color:#777;font-size:12px;line-height:1.6"><strong>Databasecontrole:</strong> {{ $databaseMessage }}</p>@endif
    @if($errors->any())<p style="color:#d71920">{{ $errors->first() }}</p>@endif
    <form method="post" action="{{ route('install.store') }}">@csrf<div class="form-row"><label><input style="width:auto" type="checkbox" name="confirm" value="1" required> Ik heb `.env` ingevuld en een lege database aangemaakt.</label></div><button class="button button-primary">Database installeren →</button></form>
</div></div>
@endsection
