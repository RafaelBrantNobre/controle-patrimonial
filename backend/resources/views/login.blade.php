@extends('layouts.app')

@section('content')
@php
    $campo = 'mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-teal-700 focus:ring-4 focus:ring-teal-700/10';
@endphp
    <main class="min-h-dvh bg-[#f6f8f7] text-slate-900 lg:grid lg:grid-cols-2">
      <section class="relative flex flex-col justify-between overflow-hidden bg-[#103e39] px-7 py-10 text-white sm:px-12 lg:min-h-dvh lg:px-16 lg:py-12">
        <div aria-hidden="true" class="pointer-events-none absolute -right-36 top-1/4 size-[520px] rounded-full border border-white/10 shadow-[0_0_0_65px_#ffffff04,0_0_0_130px_#ffffff04]"></div>
        <a href="{{ route('login') }}" class="relative flex w-fit items-center gap-3 rounded-md focus-visible:outline-2 focus-visible:outline-offset-8 focus-visible:outline-white">
          <span aria-hidden="true" class="grid size-11 place-items-center rounded-xl bg-white/10 ring-1 ring-white/20">
            <svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m12 3 9 5-9 5-9-5 9-5Z" /><path d="M3 8v9l9 5 9-5V8M12 13v9" /></svg>
          </span>
          <span class="text-sm font-semibold tracking-wide">CEAD <span class="font-normal text-teal-100/60">/ Unimontes</span></span>
        </a>
        <div class="relative max-w-lg py-16 lg:py-24">
          <p class="mb-5 text-xs font-semibold uppercase tracking-[0.22em] text-teal-200">Controle patrimonial</p>
          <h1 class="text-4xl font-semibold leading-[1.15] tracking-tight sm:text-5xl xl:text-6xl">Cada bem tem<br>seu lugar.<br><span class="text-teal-200">Tudo sob controle.</span></h1>
          <p class="mt-7 max-w-sm text-base leading-7 text-teal-50/70">Organize os bens, acompanhe as movimentações e cuide do patrimônio da nossa instituição.</p>
          <div class="mt-10 flex items-center gap-3 text-sm text-teal-50/80"><span aria-hidden="true" class="h-px w-9 bg-teal-200/60"></span>Gestão que faz parte do cuidado.</div>
        </div>
        <p class="relative text-xs leading-5 text-teal-100/50">Centro de Educação a Distância<br>Universidade Estadual de Montes Claros</p>
      </section>
      <section class="flex flex-col justify-between px-6 py-10 sm:px-12 lg:px-16 lg:py-12">
        <p class="text-right text-xs text-slate-500">Sistema de gestão patrimonial</p>
        <div class="mx-auto w-full max-w-sm py-14 lg:py-16">
          <span class="mb-6 inline-flex rounded-full border border-teal-800/15 bg-teal-800/5 px-3 py-1 text-xs font-medium text-teal-800">Acesso institucional</span>
          <h2 class="text-3xl font-semibold tracking-tight">Boas-vindas de volta</h2>
          <p class="mt-3 text-sm leading-6 text-slate-500">Entre com suas credenciais para acessar o sistema.</p>
          <form method="POST" action="{{ route('login.exemplo') }}" class="mt-9 space-y-6">
            @csrf
            @if ($errors->any())
                <p role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</p>
            @endif
            <div>
              <label for="email" class="text-sm font-medium">E-mail institucional</label>
              <input id="email" name="email" value="{{ old('email') }}" type="email" autocomplete="username" placeholder="seu.nome@unimontes.br" required class="{{ $campo }}">
            </div>
            <div>
              <label for="senha" class="text-sm font-medium">Senha</label>
              <div class="relative">
                <input id="senha" name="password" type="password" autocomplete="current-password" placeholder="Digite sua senha" required class="{{ $campo }} pr-24">
                <button type="button" aria-controls="senha" aria-pressed="false" id="mostrar-senha" class="absolute inset-y-0 right-1 my-1 rounded-lg px-3 text-xs font-medium text-teal-800 hover:bg-teal-50 focus-visible:outline-2 focus-visible:outline-teal-700">Mostrar</button>
              </div>
            </div>
            <button type="submit" class="flex w-full items-center justify-center gap-3 rounded-xl bg-teal-800 px-4 py-3.5 text-sm font-semibold text-white transition hover:bg-teal-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700">Entrar no sistema <span aria-hidden="true">→</span></button>
            @if (session('status'))
            <p role="status" class="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm leading-6 text-teal-900">{{ session('status') }}</p>
            @endif
          </form>
          <div class="mt-8 border-t border-slate-200 pt-6">
            <p class="text-xs leading-5 text-slate-500">Página de exemplo. A autenticação ainda não está habilitada.</p>
            <a href="{{ url('/api/status') }}" class="mt-4 inline-block text-xs text-teal-800 underline underline-offset-4">Verificar conexão com o banco</a>
          </div>
        </div>
        <p class="text-center text-xs text-slate-400">CEAD / Unimontes · Controle Patrimonial</p>
      </section>
    </main>
@endsection
