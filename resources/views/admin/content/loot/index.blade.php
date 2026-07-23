@extends('layouts.game')
@section('title','Admin · Loot')
@section('content')
@include('admin.content.partials.navigation')
@include('admin.content.partials.messages')
<h1>Loot global</h1>
<table class="table"><thead><tr><th>Monster</th><th>Item</th><th>PPM</th><th>Visual</th><th>Cantidad</th><th>Estado</th></tr></thead><tbody>@foreach($entries as $entry)<tr><td><a href="{{ route('admin.content.monsters.show',$entry->monster) }}">{{ $entry->monster->name }}</a></td><td>{{ $entry->item->name }}</td><td>{{ $entry->drop_probability_ppm }}</td><td>{{ $entry->drop_probability_percent }}%</td><td>{{ $entry->minimum_quantity }}–{{ $entry->maximum_quantity }}</td><td>{{ $entry->status }}</td></tr>@endforeach</tbody></table>
{{ $entries->links() }}
<p class="text-secondary">1,000,000 PPM = 100% · 1 PPM = 0.0001%</p>
@endsection
