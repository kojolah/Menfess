@extends('layouts.app')
@section('content')
<section class="mx-auto grid min-h-[65vh] max-w-5xl place-items-center px-5 py-16"><div class="w-full max-w-lg rounded-[2rem] border border-[#ece7e2] bg-white p-8 shadow-xl shadow-slate-900/5 sm:p-10"><div class="mx-auto grid size-14 place-items-center rounded-2xl bg-[#f7dce9] text-2xl">✳</div><h1 class="mt-6 text-center text-3xl font-bold">Masuk ke ruang kampus</h1><p class="mt-3 text-center leading-7 text-slate-600">Gunakan akun Google untuk menjaga akun dan riwayat kirimanmu tetap terhubung.</p>
    @if ($errors->any())<div class="mt-6 rounded-2xl bg-rose-50 p-4 text-sm text-rose-800" role="alert">{{ $errors->first() }}</div>@endif
    <a href="{{ route('auth.google') }}" class="mt-7 flex w-full items-center justify-center gap-3 rounded-full border border-slate-200 px-5 py-3.5 font-semibold shadow-sm hover:bg-slate-50"><span class="text-lg font-bold text-[#4285f4]">G</span> Lanjutkan dengan Google</a>
    <p class="mt-6 text-center text-xs leading-5 text-slate-500">Dengan masuk, kamu menyetujui <a href="{{ route('rules') }}" class="underline">aturan penggunaan dan kebijakan privasi</a>.</p>
</div></section>
@endsection
