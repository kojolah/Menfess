@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-6xl px-5 py-10">
    <p class="text-sm font-bold uppercase tracking-[.18em] text-[#a64e73]">PENGATURAN KEAMANAN</p>
    <h1 class="mt-2 text-3xl font-bold">Filter konten otomatis</h1>
    <p class="mt-2 max-w-3xl text-slate-600">Aturan bertindak sebagai kata/frasa utuh, tidak membedakan kapital, dan mengenali tanda baca atau variasi angka umum. Aksi “Tinjau” menahan kiriman untuk pemeriksaan manusia.</p>

    <div class="mt-8 grid gap-6 lg:grid-cols-[.8fr_1.2fr]">
        <form method="POST" action="{{ route('admin.content-filters.rules.store') }}" class="h-fit rounded-3xl border border-[#ece7e2] bg-white p-6">@csrf
            <h2 class="text-lg font-bold">Tambah aturan</h2>
            <label class="mt-4 block text-sm font-semibold">Kata atau frasa<input name="phrase" required maxlength="160" class="mt-2 w-full rounded-xl border-slate-300" value="{{ old('phrase') }}"></label>
            @error('phrase')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            <label class="mt-4 block text-sm font-semibold">Kategori<select name="category" class="mt-2 w-full rounded-xl border-slate-300"><option value="profanity">Kata kotor / umpatan</option><option value="insult">Penghinaan</option><option value="sexual_18">Eksplisit 18+</option><option value="other">Lainnya</option></select></label>
            <label class="mt-4 block text-sm font-semibold">Tindakan<select name="action" class="mt-2 w-full rounded-xl border-slate-300"><option value="censor">Sensor menjadi ****</option><option value="review">Tahan untuk review</option></select></label>
            <button class="mt-5 rounded-full bg-[#182a4b] px-5 py-3 text-sm font-bold text-white">Simpan aturan</button>
        </form>

        <div class="rounded-3xl border border-[#ece7e2] bg-white p-6">
            <h2 class="text-lg font-bold">Aturan aktif dan nonaktif</h2>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse($rules as $rule)
                    <form method="POST" action="{{ route('admin.content-filters.rules.update', $rule) }}" class="grid gap-3 py-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">@csrf @method('PUT')
                        <label class="text-xs font-semibold text-slate-500">Frasa<input name="phrase" value="{{ $rule->phrase }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm" maxlength="160"></label>
                        <div class="grid grid-cols-2 gap-2"><label class="text-xs font-semibold text-slate-500">Kategori<select name="category" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@foreach(['profanity'=>'Umpatan','insult'=>'Penghinaan','sexual_18'=>'18+','other'=>'Lainnya'] as $key=>$label)<option value="{{ $key }}" @selected($rule->category === $key)>{{ $label }}</option>@endforeach</select></label><label class="text-xs font-semibold text-slate-500">Tindakan<select name="action" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="censor" @selected($rule->action === 'censor')>Sensor</option><option value="review" @selected($rule->action === 'review')>Review</option></select></label></div>
                        <div class="flex items-center gap-2"><label class="text-xs"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($rule->is_active)> Aktif</label><button class="rounded-lg bg-[#e7f1fa] px-3 py-2 text-xs font-bold">Simpan</button></div>
                    </form>
                    <form method="POST" action="{{ route('admin.content-filters.rules.destroy', $rule) }}" class="-mt-3 pb-3 text-right">@csrf @method('DELETE')<button class="text-xs font-semibold text-rose-700">Hapus aturan</button></form>
                @empty<p class="py-8 text-sm text-slate-500">Belum ada aturan.</p>@endforelse
            </div>
            {{ $rules->links() }}
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[.8fr_1.2fr]">
        <form method="POST" action="{{ route('admin.content-filters.exceptions.store') }}" class="h-fit rounded-3xl border border-[#ece7e2] bg-white p-6">@csrf
            <h2 class="text-lg font-bold">Tambah pengecualian</h2><p class="mt-1 text-sm text-slate-500">Frasa cocok yang memuat aturan filter tidak akan disensor.</p>
            <label class="mt-4 block text-sm font-semibold">Frasa<input name="phrase" required maxlength="160" class="mt-2 w-full rounded-xl border-slate-300"></label>
            <label class="mt-4 block text-sm font-semibold">Alasan<input name="reason" maxlength="500" class="mt-2 w-full rounded-xl border-slate-300"></label>
            <button class="mt-5 rounded-full bg-[#182a4b] px-5 py-3 text-sm font-bold text-white">Simpan pengecualian</button>
        </form>
        <div class="rounded-3xl border border-[#ece7e2] bg-white p-6"><h2 class="text-lg font-bold">Pengecualian</h2><div class="mt-4 divide-y divide-slate-100">
            @forelse($exceptions as $exception)<form method="POST" action="{{ route('admin.content-filters.exceptions.update', $exception) }}" class="flex flex-wrap items-end gap-3 py-3">@csrf @method('PUT')<label class="min-w-40 flex-1 text-xs font-semibold text-slate-500">Frasa<input name="phrase" value="{{ $exception->phrase }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label><label class="min-w-40 flex-1 text-xs font-semibold text-slate-500">Alasan<input name="reason" value="{{ $exception->reason }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label><label class="text-xs"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($exception->is_active)> Aktif</label><button class="rounded-lg bg-[#e7f1fa] px-3 py-2 text-xs font-bold">Simpan</button></form><form method="POST" action="{{ route('admin.content-filters.exceptions.destroy', $exception) }}" class="-mt-3 pb-3 text-right">@csrf @method('DELETE')<button class="text-xs font-semibold text-rose-700">Hapus</button></form>@empty<p class="py-8 text-sm text-slate-500">Belum ada pengecualian.</p>@endforelse
        </div>{{ $exceptions->links() }}</div>
    </div>
</section>
@endsection
