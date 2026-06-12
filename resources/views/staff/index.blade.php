@extends('layouts.dashboard')
@section('title', 'Staff omgeving — CN Community')
@section('content')
<div class="dashboard-shell app-dashboard">
    <div class="dashboard-top"><div><span class="eyebrow"><i></i> STAFF OMGEVING</span><h1>Command center</h1><p>Beoordelen, samenwerken en bijsturen vanuit één overzicht.</p></div></div>
    <div class="metric-row" style="max-width:1200px;margin:0 auto 18px">
        <div class="metric"><small>NOMINATIES WACHTEND</small><strong>{{ $pendingNominations }}</strong></div>
        <div class="metric"><small>FRAUDE SIGNALEN</small><strong>{{ $fraudAlerts }}</strong></div>
        <div class="metric"><small>OPEN TAKEN</small><strong>{{ $openTasks }}</strong></div>
    </div>
    <section class="panel" style="max-width:1200px;margin:0 auto 18px"><h2>Nominaties beoordelen</h2>
        @forelse($nominations as $nomination)
            <div class="list-row"><span><strong>{{ $nomination->nominee_name }}</strong><br><small>{{ $nomination->category->name }} · door {{ $nomination->user->name }}</small></span><form method="post" action="{{ route('staff.nominations.review', $nomination) }}">@csrf @method('PATCH')<button class="button button-small button-dark" name="status" value="approved">Goedkeuren</button> <button class="button button-small" name="status" value="rejected">Afwijzen</button></form></div>
        @empty<p>Er wachten geen nominaties.</p>@endforelse
    </section>
    <section class="panel" style="max-width:1200px;margin:auto"><h2>Takenbord</h2><div class="board">
        @foreach(['open'=>'Open','in_progress'=>'Bezig','waiting'=>'Wachten','completed'=>'Voltooid'] as $status => $label)
            <div class="board-column" data-column="{{ $status }}"><h3>{{ strtoupper($label) }} · {{ ($tasks[$status] ?? collect())->count() }}</h3><div class="task-list">
                @foreach($tasks[$status] ?? [] as $task)<article class="task-card" data-task data-update-url="{{ route('staff.tasks.move', $task) }}"><strong>{{ $task->title }}</strong><small>{{ strtoupper($task->priority) }} @if($task->deadline) · {{ $task->deadline->format('d M') }} @endif</small></article>@endforeach
            </div></div>
        @endforeach
    </div></section>
</div>
@endsection
