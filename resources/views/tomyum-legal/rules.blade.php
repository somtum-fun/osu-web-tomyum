{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: production's rules, as on example.com, with the in-game sign-up rule added. --}}
@php
    $serverName = $GLOBALS['cfg']['tomyum']['server_name'];
    $discord = osu_url('dev');
    $support = App\Libraries\TomYumTickets::address('support');
    $accounts = App\Libraries\TomYumTickets::address('accounts');
    $restrictionsUrl = route('legal', ['locale' => app()->getLocale(), 'path' => 'Restrictions']);
@endphp
@extends('tomyum-legal.layout')

@section('legal-updated', 'Last updated: 6 October 2026')

@section('legal-body')
<p>
    By playing on {{ $serverName }}, you agree to follow these rules. Breaking them can lead to anything from a
    warning to a permanent ban.
</p>

<h2>1. General rules</h2>
<ol>
    <li>
        <strong>Use common sense.</strong> Respect others, don't look for loopholes, and keep things fair and fun
        for everyone.
    </li>
    <li>
        <strong>No cheating, hacking or botting.</strong> Any cheat, client modification or third-party tool that
        gives you an unfair advantage is not allowed.
    </li>
    <li>
        <strong>One account per person.</strong> If you need a new account because of a problem, contact us at
        <a href="mailto:{{ $accounts }}">{{ $accounts }}</a> first.
    </li>
    <li>
        <strong>Make your account in the game, without a VPN or proxy.</strong> Accounts are made in
        lazer-fork or osu! stable, not on the website. Sign-ups through a VPN, proxy, Tor or hosting network are
        blocked, and each network (a home, school or phone) can make one account a day. Don't try to get around
        this; if it stops you from signing up honestly, email <a href="mailto:{{ $accounts }}">{{ $accounts }}</a>.
    </li>
    <li>
        <strong>No account sharing.</strong> Your account represents you. Don't share it, and don't play on
        someone else's.
    </li>
    <li>
        <strong>Report rule breakers.</strong> If you see someone breaking these rules, report them in the game
        or from their profile{!! $discord === null ? '' : ' (or on our <a href="'.e($discord).'">Discord</a>)' !!}.
        Helping rule breakers can get you punished too.
    </li>
    <li>
        <strong>No bug exploiting.</strong> If you find a bug, tell a developer instead of using it. Responsible
        reports may even be rewarded.
    </li>
</ol>

<h2>2. Community behaviour</h2>
<ol start="8">
    <li>
        <strong>Be respectful; no harassment.</strong> Treat everyone with kindness. Harassment, discrimination
        and toxic behaviour aren't tolerated.
    </li>
    <li>
        <strong>No hate speech.</strong> Racism, sexism, homophobia, transphobia or any other bigotry, including
        slurs, is strictly forbidden.
    </li>
    <li>
        <strong>No drama or public hackusations.</strong> If you have a problem with someone, report it
        privately. Public complaints about bans and accusations against other players aren't allowed.
    </li>
    <li>
        <strong>English in public chats.</strong> Public channels are English only. Other languages are fine in
        private messages and in channels made for them.
    </li>
    <li>
        <strong>No spamming or flooding.</strong> Avoid sending lots of messages, unnecessary links or anything
        else disruptive.
    </li>
</ol>

<h2>3. Content and communication</h2>
<ol start="13">
    <li>
        <strong>Keep it safe for work.</strong> No NSFW or offensive content, and nothing that could hurt
        someone (such as flashing images or jumpscares).
    </li>
    <li>
        <strong>No advertising or unwanted links.</strong> Don't promote other servers, pirated software or
        unrelated content.
    </li>
    <li>
        <strong>No talk about cheating or illegal activities.</strong> Encouraging cheating or discussing
        anything illegal isn't allowed.
    </li>
</ol>

<h2>4. Moderation</h2>
<ol start="16">
    <li>
        <strong>Follow staff instructions.</strong> If a staff member asks you to do something, do it.
    </li>
    <li>
        <strong>Don't impersonate staff.</strong> Pretending to be a staff member is a serious offense.
    </li>
    <li>
        <strong>Don't abuse support.</strong> Repeated pointless appeals, name change requests or disruptive
        behaviour in support channels will get you restricted.
    </li>
</ol>

<h2>5. Appeals and punishments</h2>
<ol start="19">
    <li>
        <strong>How to report a player.</strong> Use the report button in the game or on their profile, or
        email <a href="mailto:{{ $support }}">{{ $support }}</a>.
    </li>
    <li>
        <strong>How to appeal.</strong> If you think your punishment was unfair, you can appeal once your appeal
        cooldown is over. Every appeal is looked at case by case. See
        <a href="{{ $restrictionsUrl }}">Restrictions &amp; Appeals</a> for how it works.
    </li>
    <li>
        <strong>How bad it gets.</strong> Depending on how serious it was, punishments include warnings, chat
        silences, restrictions, and temporary or permanent bans.
    </li>
</ol>

<h2>6. Final notes</h2>
<ul>
    <li><strong>These rules can change.</strong> Staff may update them at any time.</li>
    <li><strong>Use common sense.</strong> If something feels wrong, it probably is.</li>
    <li>
        <strong>Serious offenses can end your account.</strong> Severe violations, such as trying to damage the
        server, may lead to a permanent ban with no chance to appeal.
    </li>
</ul>

<p><strong>By continuing to use our services, you agree to these rules.</strong></p>
@endsection
