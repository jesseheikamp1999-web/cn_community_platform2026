@extends('layouts.dashboard')
@section('title', 'Staff Messenger installeren - MijnCN')

@section('content')
<main class="module-page">
    <section class="module-hero">
        <div>
            <span>MIJNCN</span>
            <h1>Staff Messenger voorbereiden</h1>
            <p>De Messenger-code staat klaar, maar de bijbehorende databasetabellen ontbreken nog op deze server.</p>
        </div>
    </section>

    <section class="chat-setup-card">
        <div class="chat-setup-icon">@include('components.icon', ['name' => 'mail'])</div>
        <div>
            <span>Eenmalige database-update</span>
            <h2>Maak Staff Messenger actief</h2>
            <p>Deze actie voert de nog openstaande veilige database-updates uit. Bestaande MijnCN-berichten en gebruikers blijven behouden.</p>

            @if($errors->has('chat_installation'))
                <div class="form-error">{{ $errors->first('chat_installation') }}</div>
            @endif

            @if(auth()->user()->role->value === 'owner')
                <form method="post" action="{{ route('mijncn.chat.install') }}">
                    @csrf
                    <button class="button button-primary" type="submit">Messenger installeren</button>
                </form>
            @else
                <div class="form-notice">Vraag de Eigenaar om deze eenmalige installatie uit te voeren.</div>
            @endif
        </div>
    </section>
</main>
@endsection
