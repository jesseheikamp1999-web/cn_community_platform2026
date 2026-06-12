<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MijnCN herstellen</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
</head>
<body class="app-body">
    <main class="dashboard-shell product-dashboard">
        <section class="module-card recovery-card">
            <span class="dashboard-kicker">MIJN CN · HERSTELMODUS</span>
            <h1>MijnCN kon niet volledig worden geladen</h1>
            <p>Je bent correct ingelogd. Een onderdeel van de productiedatabase of MijnCN-layout loopt nog achter op de huidige platformversie.</p>

            @if($technicalMessage)
                <div class="recovery-technical">
                    <strong>Technische oorzaak voor Eigenaar</strong>
                    <code>{{ $technicalMessage }}</code>
                </div>
            @endif

            <div class="recovery-actions">
                <a class="button button-secondary" href="{{ route('home') }}">Naar de website</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="button button-primary" type="submit">Uitloggen</button></form>
            </div>
            <small>Referentie: {{ $reference }}</small>
        </section>
    </main>
</body>
</html>
