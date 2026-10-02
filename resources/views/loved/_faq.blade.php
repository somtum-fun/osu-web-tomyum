{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- Project Loved's questions, on the overview. $latest: the latest round (for its pass rule), if any. --}}
@php
    use App\Libraries\TomYumLoved;

    $threshold = $latest?->threshold ?? 70;
    $minPlays = $GLOBALS['cfg']['osu']['forum']['minimum_plays'];
    $admins = app('groups')->byIdentifier('admin');

    $questions = [
        'What\'s the difference between Ranked and Loved?' => 'Ranked maps go through the Beatmap Nominators and award pp. Loved maps skip that: the community votes them in. They get leaderboards and keep everyone\'s scores, but give no pp.',
        'Why would a map be Loved instead of ranked?' => 'Some maps can\'t or won\'t ever be ranked. They might be too long, too hard, use something the ranking rules don\'t allow, or their mapper stopped updating them. If people love playing them anyway, Loved gives them a leaderboard.',
        'Which maps can be Loved?' => 'Graveyard, WIP and pending maps. Ranked, qualified and already-Loved maps can\'t be.',
        'How do I suggest a map?' => 'On the <a href="'.e(route('loved.requests')).'">requests page</a>: paste the set\'s link (from here or osu.ppy.sh), pick the mode and say why. If someone asked for it already, you support their request instead. You can make up to '.TomYumLoved::REQUESTS_PER_DAY.' requests a day.',
        'Who decides which maps go into a round?' => 'The Loved team below. Each member picks for their own modes, mostly from the most-supported requests. A declined request shows the team\'s reason and can\'t be asked for again for 90 days.',
        'How does voting work?' => 'Each map gets a Yes/No poll, on the round\'s page and in its forum topic. You get one vote per map and can change it until voting ends. A map is Loved if it reaches the round\'s share of yes votes (like '.$threshold.'%) from at least the round\'s fewest votes. Both are shown on the round\'s page.',
        'Why can\'t I vote?' => 'Voting follows the forum\'s rules: you need to be signed in, not restricted'.($minPlays > 0 ? ', and have played at least '.i18n_number_format($minPlays).' times' : '').'.',
        'Can a Loved map be changed or unloved?' => 'Its mapper can\'t update it any more. Only an admin can take a map out of Loved.',
        'I made a map and don\'t want it Loved.' => 'Tell someone on the Loved team and they\'ll leave it out of the rounds.',
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
