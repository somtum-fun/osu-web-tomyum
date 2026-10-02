{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- Project Loved's questions, on the requests page. --}}
@php
    use App\Libraries\TomYumLoved;

    $admins = app('groups')->byIdentifier('admin');

    $questions = [
        'What\'s the difference between Ranked and Loved?' => 'Ranked maps go through the Beatmap Nominators and award pp. Loved maps skip that: players ask for them and the Loved team loves them. They get leaderboards and keep everyone\'s scores, but give no pp.',
        'Which maps can be Loved?' => 'Graveyard, WIP and pending maps. Ranked, qualified and already-Loved maps can\'t be, and neither can maps on the <a href="'.e(route('loved.blacklist')).'">blacklist</a>.',
        'How do I ask for a map?' => 'Paste the set\'s link (from here or osu.ppy.sh) above, pick the mode and say why. One request per map and mode: if someone asked for it already, review their request instead. You can ask for up to '.TomYumLoved::REQUESTS_PER_DAY.' maps a day.',
        'How do reviews work?' => 'Open a request and say whether you\'re for or against it, and why. You can change or remove your review while the request is open. A request\'s score is its fors minus its againsts (the person who asked counts as a for), and the best-scored requests are listed first.',
        'Who decides?' => 'The Loved team below, each for the modes on their badge. They read the requests, best-scored first, and love the map or reject the request, always with a reason. Loving a map makes its difficulties in that mode Loved straight away.',
        'What happens to rejected or removed requests?' => 'A rejected map can\'t be asked for again in that mode (the team can open the request again). A removed request is taken off the list and can be asked for again later. Blacklisted maps can\'t be asked for at all.',
        'Can a Loved map be changed or unloved?' => 'Its mapper can\'t update it any more. Only an admin can take a map out of Loved.',
        'I made a map and don\'t want it Loved.' => 'Tell someone on the Loved team and they\'ll blacklist it.',
        'How do I join the Loved team?' => $admins === null
            ? 'Ask an admin.'
            : 'Ask one of the <a href="'.e(route('groups.show', ['group' => $admins->getKey()])).'">admins</a>.',
    ];
@endphp
<div class="tomyum-loved__faq">
    @foreach ($questions as $question => $answer)
        <details class="tomyum-loved__question">
            <summary>{{ $question }}</summary>
            <p>{!! $answer !!}</p>
        </details>
    @endforeach
</div>
