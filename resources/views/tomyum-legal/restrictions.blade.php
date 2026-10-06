{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: Restrictions & Appeals, as on example.com, with the appeal form. --}}
@php
    $discord = osu_url('dev');
    $accounts = App\Libraries\TomYumTickets::address('accounts');
    $appealReview = App\Libraries\TomYumTickets::address('appeal');
    $rulesUrl = route('legal', ['locale' => app()->getLocale(), 'path' => 'Rules']);
    $appealUrl = route('tomyum-appeal.show');
    $currentUser = Auth::user();
    $appealAfter = $currentUser === null ? null : App\Libraries\TomYumAppeals::appealAfter($currentUser);
@endphp
@extends('tomyum-legal.layout')

@section('legal-updated', 'Last updated: 6 October 2026')

@section('legal-body')
@if ($appealAfter !== null)
    <div class="tomyum-legal__notice">
        @if ($appealAfter->isFuture())
            Your account is restricted. You can appeal from <strong>{{ $appealAfter->format('j F Y') }}</strong>.
        @else
            Your account is restricted, and your appeal cooldown is over: you can <a href="{{ $appealUrl }}">send your appeal</a> now.
        @endif
    </div>
@endif

<h2>What is a restriction?</h2>
<p>
    Your account can be "restricted" if you break the <a href="{{ $rulesUrl }}">rules</a> badly enough to need a
    timeout from the community. Some restrictions happen automatically; if one turns out to be a mistake, it's
    lifted without any trouble.
</p>
<p>While your account is restricted:</p>
<ul>
    <li>you can't play multiplayer;</li>
    <li>you can't chat in the game or send private messages (except to BanchoBot);</li>
    <li>other players can't see your profile;</li>
    <li>you're left out of the global, country and score rankings.</li>
</ul>
<p>You can still log in and play, and your scores are kept (only you can see them).</p>

<h2>What should I do if I get restricted?</h2>
<p>
    First of all, don't panic! Some restrictions are mistakes. If you think yours is, email
    <a href="mailto:{{ $accounts }}">{{ $accounts }}</a> and we'll sort it out.
</p>
<p>
    Restrictions are handled by email and the appeal form only. Messaging staff directly about your restriction,
    over and over, can count against your appeal.
</p>
<p>
    If you're not sure why you were restricted, email us to ask. If it was a mistake, it will be fixed quickly.
    Otherwise, follow the steps below.
</p>

<h2>Why did I get restricted?</h2>
<p>The most common reasons are:</p>
<ul>
    <li>
        <strong>Multi-accounting:</strong> making more than one account isn't allowed. Your first account is
        restricted for a while, and any other accounts are disabled for good.
    </li>
    <li>
        <strong>Account sharing:</strong> letting someone else play on your account, or playing on someone else's,
        isn't allowed. If it happened by accident on a shared computer, please contact us.
    </li>
    <li>
        <strong>Cheating:</strong> using third-party software or anything else that gives you an unfair advantage
        means an immediate restriction.
    </li>
</ul>
<p>Other cases can lead to a restriction too, at staff's discretion.</p>

<h2>Appeal cooldowns</h2>
<p>
    If your restriction wasn't a mistake, you need to wait before you can appeal. This is time to think about
    what happened and to show good behaviour. When you're signed in, the date you can appeal from is shown on
    this page and on <a href="{{ $appealUrl }}">the appeal page</a>.
</p>
<table>
    <thead>
        <tr><th>Offense</th><th>Cooldown</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td>Cheating (first offense)</td><td>3 months</td><td>Full profile wipe</td></tr>
        <tr><td>Cheating (second offense)</td><td>12 months</td><td>Full profile wipe</td></tr>
        <tr><td>Multi-accounting</td><td>2 months</td><td>Other accounts are disabled for good</td></tr>
        <tr><td>Account sharing</td><td>2 months</td><td>Every account involved gets the same penalty</td></tr>
    </tbody>
</table>
<p>
    If you break more rules while restricted, your cooldown starts again from the date of the new offense. If an
    appeal is denied, you can appeal again 3 months later.
</p>

<h2>How do I appeal?</h2>
<p>
    Once your cooldown is over, sign in and send your appeal from <a href="{{ $appealUrl }}">the appeal page</a>.
    Appeals usually take 3 to 14 days to look at, and we answer by email. You can have one appeal waiting at a
    time.
</p>
<p>Your appeal should cover:</p>
<ol>
    <li><strong>What led to your restriction:</strong> a short explanation of the rule or rules you broke.</li>
    <li><strong>Why you broke the rules:</strong> your reasons, and anything else we should know.</li>
    <li><strong>How you broke the rules:</strong> mainly for cheating (for example, what tool you used).</li>
    <li><strong>Why you deserve another chance:</strong> what you think about it now, and what's changed.</li>
</ol>

<h2>What if my appeal is denied?</h2>
<p>An appeal can be denied because of:</p>
<ul>
    <li>dishonesty in the appeal;</li>
    <li>breaking more rules while restricted;</li>
    <li>a serious account history;</li>
    <li>appealing before the cooldown was over.</li>
</ul>
<p>
    If you think your appeal was denied unfairly, you can ask for a second review by emailing
    <a href="mailto:{{ $appealReview }}">{{ $appealReview }}</a> with:
</p>
<ul>
    <li>the date you sent your appeal (or its ticket number);</li>
    <li>the answer you got, if any;</li>
    <li>why you think the decision was unfair;</li>
    <li>any earlier appeals you sent.</li>
</ul>
<p>A second review doesn't promise an unrestriction, but it gives your case a second opinion.</p>

<h2>Final notes</h2>
<ul>
    <li>If you're restricted a second time, the next cooldown is longer.</li>
    <li>Serious or repeated offenses can lead to a permanent restriction.</li>
    <li>Follow the <a href="{{ $rulesUrl }}">rules</a> to stay out of trouble in the first place.</li>
</ul>
<p>
    Questions about restrictions, appeals or cooldowns? Email <a href="mailto:{{ $accounts }}">{{ $accounts }}</a>{!! $discord === null ? '' : ', or ask on our <a href="'.e($discord).'">Discord</a> (please keep appeals themselves to the appeal page)' !!}.
</p>
@endsection
