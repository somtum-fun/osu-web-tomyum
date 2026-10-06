<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

return [
    'index' => [
        'none_running' => 'There are no tournaments running at the moment, please check back later!',
        'registration_period' => 'Registration: :start to :end',

        'header' => [
            'title' => 'Community Tournaments',
        ],

        'item' => [
            'registered' => 'registered players',
        ],

        'state' => [
            'current' => 'Active Tournaments',
            'previous' => 'Past Tournaments',
        ],
    ],

    'show' => [
        'banner' => 'Support Your Team',
        // TomYum: solo registration; news comes on the information page.
        'entered' => 'You are registered for this tournament!<br><br>Keep an eye on the information page for what happens next.',
        'info_page' => 'Information Page',
        'login_to_register' => 'Please :login to view registration details!',
        'not_yet_entered' => 'You are not registered for this tournament.',
        'rank_too_low' => 'Sorry, you do not meet the rank requirements for this tournament!',
        'registration_ends' => 'Registrations close on :date',

        // TomYum: requirements, registered players and drafts.
        'cannot_register' => 'You can\'t register yet:',
        'full' => 'This tournament is full.',
        'hidden' => 'Only staff can see this tournament right now.',
        'none_registered' => 'Nobody has registered yet.',
        'registered_count' => ':count registered',
        'registered_count_max' => ':count of :max registered',
        'registrants' => 'Registered players',
        'registration_opens' => 'Registrations open on :date',
        'requirements' => 'Who can register',

        'button' => [
            'cancel' => 'Cancel Registration',
            'register' => 'Sign me up!',
        ],

        'period' => [
            'end' => 'End',
            'start' => 'Start',
        ],

        'state' => [
            'before_registration' => 'Registration for this tournament has not yet opened.',
            'ended' => 'This tournament has concluded. Check the information page for results.',
            'registration_closed' => 'Registration for this tournament has closed. Check the information page for latest updates.',
            'running' => 'This tournament is currently in progress. Check the information page for more details.',
        ],
    ],
    'tournament_period' => ':start to :end',

    // TomYum: who may register, and why someone can't.
    'requirements' => [
        'account_age' => 'An account at least :days old',
        'countries' => 'Playing for one of: :countries (your profile\'s country)',
        'discord' => 'A linked Discord account (Settings → Connections)',
        'rank' => 'Global :mode rank: :range',
        'restriction' => 'No restriction in the last :days',
        'silence' => 'No silence in the last :days',
        'standing' => 'Not restricted, and no tournament ban',

        'failed' => [
            'account_age' => 'Your account is :age old, and it needs to be at least :days old.',
            'closed' => 'Registration isn\'t open right now.',
            'country' => 'Your profile\'s country is :country, and this tournament is for players from :countries.',
            'country_none' => 'Your profile doesn\'t have a country set, and this tournament is for players from :countries.',
            'deleted' => 'This account doesn\'t exist any more.',
            'discord' => 'Link your Discord account first (Settings → Connections).',
            'full' => 'This tournament is full.',
            'rank' => 'Your global :mode rank is #:rank, and this tournament is for :range.',
            'rank_none' => 'You don\'t have a global :mode rank yet. Play some ranked maps first!',
            'restricted' => 'Your account is restricted.',
            'restriction' => 'Your account was restricted in the last :days.',
            'silence' => 'You were silenced in the last :days.',
            'tournament_ban' => 'You have a tournament ban.',
        ],

        'range' => [
            'between' => '#:min to #:max',
            'max' => '#:max or better',
            'min' => '#:min or lower',
        ],
    ],
];
