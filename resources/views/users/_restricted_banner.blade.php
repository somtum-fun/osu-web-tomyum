{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@if (auth()->check() && auth()->user()->isAdmin() && $user->isRestricted())
    @include('objects._notification_banner', [
        'type' => 'warning',
        'title' => osu_trans('admin.users.restricted_banner.title'),
        'message' => osu_trans('admin.users.restricted_banner.message'),
    ])
@endif
{{-- TomYum: on their own profile, a restricted player sees when they can appeal (TomYumAppeals). --}}
@if (auth()->check() && auth()->id() === $user->getKey() && $user->isRestricted())
    @php
        $tomyumAppealAfter = App\Libraries\TomYumAppeals::appealAfter($user);
        $tomyumOpenAppeal = App\Libraries\TomYumAppeals::openAppeal($user);
    @endphp
    @if ($tomyumAppealAfter !== null)
        @include('objects._notification_banner', [
            'type' => 'info',
            'label' => 'appeal',
            'title' => $tomyumOpenAppeal !== null
                ? 'Your appeal is waiting for an answer'
                : ($tomyumAppealAfter->isFuture() ? 'You can appeal from '.$tomyumAppealAfter->format('j F Y') : 'You can appeal now'),
            'message' => $tomyumOpenAppeal !== null
                ? e("We'll reply by email (ticket #{$tomyumOpenAppeal->id}).")
                : ($tomyumAppealAfter->isFuture()
                    ? e('Your appeal cooldown ends then. ').link_to(route('legal', ['locale' => app()->getLocale(), 'path' => 'Restrictions']), 'How appeals work')
                    : link_to(route('tomyum-appeal.show'), 'Send your appeal')),
        ])
    @endif
@endif
