{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: a legal page staff wrote themselves (Admin panel > Branding), in markdown. --}}
@extends('tomyum-legal.layout')

@php
    $updatedAt = App\Libraries\TomYumBranding::legalUpdatedAt($page);
@endphp

@if ($updatedAt !== null)
    @section('legal-updated', 'Last updated: '.i18n_date(Carbon\Carbon::parse($updatedAt)))
@endif

@section('legal-body')
    {!! markdown_plain($text) !!}
@endsection
