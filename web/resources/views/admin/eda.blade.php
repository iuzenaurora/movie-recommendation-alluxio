@extends('layouts.app')
@section('title', 'Dữ liệu MovieLens')
@section('content')
<h1 class="text-3xl font-bold mb-6">Dữ liệu MovieLens</h1>
@if(!$manifest)<p>Chưa có manifest model.</p>@else
<p>Dataset: {{ $manifest['datasetVersion'] }} · Nguồn: manifest.json</p>
<table class="w-full text-left"><tbody>
@foreach(['rawRows','validRows','exactDedupRows','latestRows','metadataJoinedRows','sampleRows','eligibleSplitRows','users','moviesRated'] as $key)
<tr class="border-b border-slate-700"><td class="py-3">{{ $key }}</td><td>{{ isset($manifest[$key]) ? number_format($manifest[$key]) : 'Chưa có' }}</td></tr>
@endforeach
@foreach($manifest['splitCounts'] ?? [] as $split => $count)<tr><td>{{ $split }}</td><td>{{ number_format($count) }}</td></tr>@endforeach
</tbody></table><p>{{ $manifest['splitPolicy'] ?? '' }}</p>
<p class="mt-6 text-slate-400">Chưa có artifact phân bố rating/thể loại và dung lượng Parquet.</p>
@endif
@endsection
