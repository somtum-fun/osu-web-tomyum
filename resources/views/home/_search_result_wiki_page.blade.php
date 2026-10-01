{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    TomYum: a wiki result as in osu!'s search design proposal, like the forum results: a line down
    its side, the title (section / page) and the page's text with the words searched for marked,
    then its link. The whole result is a link. (No "last updated": wiki pages only know when they
    were last indexed, not when they were last edited.)
--}}
@foreach ($search->data() as $entry)
    @php
        $url = wiki_url($entry->path, $entry->locale);
    @endphp
    <div class="search-entry">
        <div class="search-wiki-page">
            <a class="search-wiki-page__link" href="{{ $url }}"></a>
            <div class="search-wiki-page__title">
                <span class="search-highlight">
                    {!! $entry->highlightedTitle() !!}
                </span>
            </div>

            <div class="search-wiki-page__excerpt">
                <span class="search-highlight">
                    {!! $entry->highlights() !!}
                </span>
            </div>

            <div class="search-wiki-page__footer">
                <a class="search-wiki-page__url" href="{{ $url }}">{{ $url }}</a>
            </div>
        </div>
    </div>
@endforeach
