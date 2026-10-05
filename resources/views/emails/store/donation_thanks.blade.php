{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: our own thanks, from the production team (osu!'s was peppy's letter). English only: the
     translated versions speak for osu!. --}}

Hi {{ $donor->username }},

Thanks a lot for choosing to {{ $continued ? 'keep ': '' }}supporting production!

{{ $isGift ? 'Your giftee(s)' : 'You' }} will now have osu!direct and the other production supporter perks{{ !$isGift ? ' for '.\App\Models\SupporterTag::getDurationText($duration, 'en') : '' }}.

production is a free, unofficial osu! server run by a few volunteers. Your support pays for the server, the domain and the bandwidth that keep it online. Supporter perks are only cosmetic and handy extras, never pay-to-win.

If you have any questions, just reply to this email.

Thank you ❤️
The production team

production is not affiliated with ppy Pty Ltd or osu!. To support osu! itself, see https://osu.ppy.sh/home/support
