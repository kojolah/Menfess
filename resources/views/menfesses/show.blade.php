@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-3xl px-5 py-10">
    <a href="{{ route('menfesses.index') }}" class="text-sm font-semibold text-[#a64e73]">← Riwayat menfess</a>
    @if($errors->has('render'))<div class="mt-4 rounded-2xl bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first('render') }}</div>@endif
    <div class="mt-5 rounded-3xl border border-[#ece7e2] bg-white p-6 sm:p-8">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-sm font-semibold text-[#a64e73]">{{ $menfess->public_number ?? 'Draft' }}</p><h1 class="mt-2 text-2xl font-bold">{{ $menfess->category->name }}</h1></div><span class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold">{{ str_replace('_', ' ', $menfess->status) }}</span></div>
        <p class="mt-6 whitespace-pre-wrap break-words leading-8">{{ $menfess->body }}</p>
        @if($menfess->censored_count > 0)<p class="mt-3 text-xs text-slate-500">{{ $menfess->censored_count }} kata/frasa disensor otomatis.</p>@endif
        @if($menfess->publicationJobs->isNotEmpty())<div class="mt-6 rounded-2xl bg-[#fbf8f4] p-4"><h2 class="text-sm font-bold">Status per platform</h2><div class="mt-3 flex flex-wrap gap-2">@foreach($menfess->publicationJobs as $job)<span class="rounded-full bg-white px-3 py-1.5 text-xs font-semibold">{{ strtoupper($job->platform) }} · {{ str_replace('_', ' ', $job->status) }}</span>@endforeach</div></div>@endif
        <div class="mt-6 flex flex-wrap gap-2">@foreach($menfess->platforms as $platform)<span class="rounded-full bg-[#e7f1fa] px-3 py-1 text-sm">{{ strtoupper($platform) }}</span>@endforeach</div>
        @if($menfess->rejection_reason)<div class="mt-6 rounded-2xl bg-rose-50 p-4 text-rose-800"><strong>Alasan penolakan</strong><p class="mt-1">{{ $menfess->rejection_reason }}</p></div>@endif
        @foreach($menfess->attachments as $attachment)<img src="{{ route('menfess-attachments.show', $attachment) }}" alt="Lampiran menfess" class="mt-6 max-h-96 rounded-2xl object-contain">@endforeach
        <div class="mt-7 border-t border-slate-100 pt-5 text-sm text-slate-500">Dikirim {{ $menfess->created_at->format('d M Y, H:i') }} @if($menfess->submitted_at) · Masuk moderasi {{ $menfess->submitted_at->format('d M Y, H:i') }} @endif</div>
    </div>
    <div class="mt-7 rounded-3xl border border-[#ece7e2] bg-white p-6">
        <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-bold">Gambar publikasi</h2><p class="mt-1 text-sm text-slate-500">Render menggunakan Chromium dan template yang sama dengan preview.</p></div><form method="POST" action="{{ route('menfesses.images.store', $menfess) }}" class="flex gap-2">@csrf<select name="format" class="rounded-full border border-slate-200 px-3 py-2 text-sm"><option value="instagram_portrait">Instagram portrait</option><option value="instagram_square">Instagram square</option><option value="x_landscape">X landscape</option></select><button class="rounded-full bg-[#182a4b] px-4 py-2 text-sm font-semibold text-white">Buat PNG</button></form></div>
        @if($menfess->generatedImages->isNotEmpty())<div class="mt-5 grid gap-4 sm:grid-cols-2">@foreach($menfess->generatedImages as $image)<figure class="rounded-2xl bg-[#fbf8f4] p-3"><img src="{{ $image->delivery_url }}" alt="Gambar menfess {{ $image->metadata['slide'] ?? 1 }}" class="w-full rounded-xl"><figcaption class="mt-3 flex items-center justify-between text-sm"><span>{{ $image->width }} × {{ $image->height }} @if(($image->metadata['slides'] ?? 1) > 1) · Slide {{ $image->metadata['slide'] }}/{{ $image->metadata['slides'] }} @endif</span>@if(in_array($menfess->status, ['approved', 'published'], true))<a href="{{ route('generated-images.download', $image) }}" class="font-semibold text-[#a64e73]">Unduh</a>@endif</figcaption></figure>@endforeach</div>@endif
    </div>
</section>
@endsection
