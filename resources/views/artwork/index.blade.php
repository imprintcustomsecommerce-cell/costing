@extends('layouts.app')
@section('content')
<div class="top">
    <div>
        <div class="page-kicker">Files</div>
        <h1>Artwork</h1>
        <div class="muted">Everything attached to a quotation.</div>
    </div>
</div>

<div class="card">
    <form style="max-width:430px">
        <label>Search artwork<input name="q" value="{{ request('q') }}" placeholder="File name, quotation or customer"></label>
    </form>

    <div class="table-wrap" style="margin-top:18px">
        <table class="config-table">
            <thead><tr><th>File</th><th>Location</th><th>Quotation</th><th>Uploaded by</th><th class="num">Size</th><th>When</th><th></th></tr></thead>
            <tbody>
            @forelse($artworks as $artwork)
                <tr>
                    <td>
                        <span class="name">{{ $artwork->original_name }}</span>
                        @if($artwork->notes)<span class="sub">{{ $artwork->notes }}</span>@endif
                        @unless($artwork->exists())<span class="sub" style="color:var(--danger)">file missing from disk</span>@endunless
                    </td>
                    <td>{{ $artwork->print_location ?: '—' }}</td>
                    <td>
                        @if($artwork->quotation)
                            <a class="table-link" href="{{ route('quotations.show',$artwork->quotation) }}">{{ $artwork->quotation->number }}</a>
                            <span class="sub">{{ $artwork->quotation->customer_name }}</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>{{ $artwork->uploader?->name ?? '—' }}</td>
                    <td class="num">{{ $artwork->humanSize() }}</td>
                    <td>{{ $artwork->created_at->toDateString() }}</td>
                    <td><a class="table-link" href="{{ route('artwork.download',$artwork) }}">Download</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-state">No artwork uploaded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $artworks->links() }}
</div>
@endsection
