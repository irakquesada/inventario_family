@extends('layouts.public', ['title' => $title])

@section('content')
    <section class="bg-stone-950 text-white"><div class="mx-auto max-w-4xl px-4 py-16 sm:px-6"><p class="text-sm font-bold uppercase tracking-[.24em] text-orange-300">Información legal</p><h1 class="mt-3 text-4xl font-black sm:text-5xl">{{ $title }}</h1></div></section>
    <article class="mx-auto max-w-4xl px-4 py-12 sm:px-6"><div class="whitespace-pre-line rounded-3xl border border-stone-200 bg-white p-6 leading-8 text-stone-700 shadow-sm sm:p-10">{{ $content }}</div><p class="mt-6 text-sm text-stone-500">Última actualización: {{ now()->format('d/m/Y') }}</p></article>
@endsection
