{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: production's copyright and takedown page. --}}
@extends('tomyum-legal.layout')

@section('legal-body')
<p>
    We respect the rights of artists, mappers and other creators. If you think something on production uses
    your work without permission, tell us and we'll look into it quickly.
</p>

<h2>What's here</h2>
<p>
    Most beatmaps on production are mirrored from osu! so players can download them. Players also upload their
    own beatmaps, replays, skins, avatars, profile pages and posts. We don't check everything that's uploaded
    before it goes up, so we rely on reports.
</p>

<h2>Sending a takedown notice</h2>
<p>
    Email <a href="mailto:{{ App\Libraries\TomYumTickets::address('dmca') }}">{{ App\Libraries\TomYumTickets::address('dmca') }}</a> with:
</p>
<ol>
    <li>Your name and how to contact you (email, and a phone number if you like).</li>
    <li>The work you own (or represent), and how to find it (a link, or a description).</li>
    <li>The link to each thing on production you want removed, for example the beatmap page
        ({{ parse_url($GLOBALS['cfg']['app']['url'], PHP_URL_HOST) }}/beatmapsets/&hellip;), user profile or file.</li>
    <li>A statement that you believe in good faith that this use isn't allowed by you, your agent or the
        law.</li>
    <li>A statement that the information in your notice is accurate, and that you are the owner or allowed
        to act for the owner.</li>
    <li>Your signature (typing your full name is fine).</li>
</ol>
<p>
    We'll reply as soon as we can, usually within a few days. When a notice is complete, we remove or disable
    the content and let the person who uploaded it know. For a beatmap, that usually means the download (and
    its audio) is taken down.
</p>

<h2>Counter-notice</h2>
<p>
    If your content was removed and you believe it was a mistake, or that you have the right to use it, email
    <a href="mailto:{{ App\Libraries\TomYumTickets::address('dmca') }}">{{ App\Libraries\TomYumTickets::address('dmca') }}</a> with: your name and contact details, what was removed and
    where it was, why you believe it was removed by mistake, a statement that this is true to the best of your
    knowledge, and your signature. We'll pass it on to the person who sent the original notice and may put the
    content back if they don't take further action.
</p>

<h2>Repeat infringement</h2>
<p>
    Accounts that keep uploading content they don't have the rights to may lose the ability to upload, or be
    restricted or banned.
</p>

<h2>False notices</h2>
<p>
    Please only send a notice for your own work, or work you represent. Notices that are knowingly false may
    have legal consequences.
</p>

<h2>About osu!</h2>
<p>
    production is an independent server and is not run by ppy Pty Ltd. For anything about osu! itself, contact
    ppy; for anything on production, contact us.
</p>
@endsection
