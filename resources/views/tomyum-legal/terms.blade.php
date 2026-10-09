{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: this server's Terms of Service. --}}
@extends('tomyum-legal.layout')

@section('legal-body')
<p>
    Welcome to {{ App\Libraries\TomYumBranding::name() }}! These terms apply when you use our website ({{ parse_url($GLOBALS['cfg']['app']['url'], PHP_URL_HOST) }} and our other
    sites), our game servers, chat and IRC, and our clients and tools. By making an account or
    playing here, you agree to them. If you don't agree, please don't use the service.
</p>

<h2>Who we are</h2>
<p>
    {{-- TomYum: who runs it and where (Admin panel > Branding; either may be left out). --}}
    @php($operator = App\Libraries\TomYumBranding::operator())
    @php($country = App\Libraries\TomYumBranding::country())
    {{ App\Libraries\TomYumBranding::name() }} is a community osu! private server run by {{ present($operator) ? $operator : 'its team' }}{{ present($country) ? " in {$country}" : '' }}, with the help of our
    staff. When these terms say "we" or "us", they mean the people who run {{ App\Libraries\TomYumBranding::name() }}. You can reach us at
    <a href="mailto:{{ App\Libraries\TomYumTickets::address('support') }}">{{ App\Libraries\TomYumTickets::address('support') }}</a>.
</p>

<h2>Your account</h2>
<ul>
    <li>You must be at least 13 years old to make an account. If you're under the age where you can agree to
        this by yourself where you live, a parent or guardian must agree for you.</li>
    <li>One account per person. Don't share your account or use someone else's.</li>
    <li>Keep your password safe. Please don't use the same password as your official osu! account.</li>
    <li>Your username must follow the <a href="{{ route('legal', ['locale' => app()->getLocale(), 'path' => 'Rules']) }}">rules</a>. We may change a name that
        doesn't.</li>
</ul>

<h2>Play fair and be nice</h2>
<p>
    Follow the <a href="{{ route('legal', ['locale' => app()->getLocale(), 'path' => 'Rules']) }}">community rules</a>. In short: be kind to other players, don't
    harass, spam or share anything illegal or hateful, and don't cheat.
</p>
<p>
    Cheating means anything that gives you an unfair advantage: cheat clients, aim or timing assistance,
    replay editing or stealing, speed changes, multi-accounting, or getting someone else to play for you. We
    run anti-cheat checks on the game client and on submitted scores and replays.
</p>
<p>
    If you break the rules, we may warn you, silence you in chat, remove scores (including rolling back
    scores after a date), restrict your account, or ban you, depending on what happened. Restricted accounts
    are hidden from rankings and other players. If you think we made a mistake, write to
    <a href="mailto:{{ App\Libraries\TomYumTickets::address('support') }}">{{ App\Libraries\TomYumTickets::address('support') }}</a> and we'll take another look.
</p>

<h2>What you share</h2>
<p>
    You keep the rights to what you make, like beatmaps, replays, skins, forum posts and chat messages. By
    posting it here, you let us store it, show it to other players, and use it to run the service (for
    example, showing your beatmap in listings or your replay to spectators). Only post things you have the
    right to share. See our <a href="{{ route('legal', ['locale' => app()->getLocale(), 'path' => 'Copyright']) }}">copyright page</a>
    for how we handle takedowns.
</p>
<p>
    We may remove content or hide it from others when it breaks these terms or the rules.
</p>

<h2>Supporter and donations</h2>
<ul>
    <li>Buying supporter is a voluntary donation that helps us pay for the servers. It isn't payment for an
        official osu!supporter tag, and none of it goes to ppy.</li>
    <li>Supporter gives cosmetic and convenience perks only. It never gives an advantage in gameplay or
        rankings.</li>
    <li>Payments are handled by Stripe. Donations are non-refundable, except where the law says
        otherwise. If something went wrong with a payment, write to
        <a href="mailto:{{ App\Libraries\TomYumTickets::address('support') }}">{{ App\Libraries\TomYumTickets::address('support') }}</a>.</li>
    <li>Supporter time you get by voting for us on server lists works the same way and has no cash value.</li>
    <li>If your account is banned for breaking the rules, you lose any supporter time left on it.</li>
</ul>

<h2>The service is as-is</h2>
<p>
    {{ App\Libraries\TomYumBranding::name() }} is run by volunteers on a best-effort basis. We provide it "as is", without warranties of
    any kind. It may be down, lose data, or change at any time, and we may shut it down. As far as the law
    allows, we aren't liable for any loss that comes from using it.
</p>

<h2>Not official</h2>
<p>
    {{ App\Libraries\TomYumBranding::name() }} is not osu!. It is not affiliated with, endorsed by or connected to ppy Pty Ltd. osu! is a
    trademark of ppy Pty Ltd. Please don't contact ppy about anything here; contact us instead.
</p>

<h2>Changes</h2>
<p>
    We may update these terms. When we make a big change, we'll tell you on the website. Using the service
    after a change means you accept the new terms.
</p>

@if (present(App\Libraries\TomYumBranding::country()))
<h2>Law</h2>
<p>
    These terms are governed by the laws of {{ App\Libraries\TomYumBranding::country() }}.
</p>
@endif
@endsection
