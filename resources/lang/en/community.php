<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

return [
    'support' => [
        'convinced' => [
            'title' => 'I\'m convinced! :D',
            'support' => 'support :brand',
            'gift' => 'or gift supporter to other players',
            'instructions' => 'click the heart button to go to the store',
        ],
        'why-support' => [
            'title' => 'Why should I support :brand? Where does the money go?',

            'team' => [
                'title' => 'Run by Volunteers',
                'description' => ':brand is a free, unofficial server run in our spare time by a couple of volunteers in Thailand. Nobody here gets paid.',
            ],
            'infra' => [
                'title' => 'Server Infrastructure',
                'description' => 'Every bit goes to the server, the domain and the bandwidth that keep the website, multiplayer and leaderboards online.',
            ],
            'featured-artists' => [
                'title' => 'Featured Artists',
                'description' => 'With your support, we can approach even more awesome artists and license more great music for use in osu!',
                'link_text' => 'View the current roster &raquo;',
            ],
            'ads' => [
                'title' => 'No Ads, No Pay-to-Win',
                'description' => ':brand stays free of ads. Supporter perks are cosmetic or handy extras and never give a competitive advantage.',
            ],
            'tournaments' => [
                'title' => 'Official Tournaments',
                'description' => 'Help fund the running of (and the prizes for) the official osu! World Cup tournaments.',
                'link_text' => 'Explore tournaments &raquo;',
            ],
            // TomYum
            'vote' => [
                'title' => 'Can\'t Pay? Vote!',
                'description' => 'Every vote for :brand on a server list gives you supporter time for free, and helps new players find us.',
                'link_text' => 'Vote for us &raquo;',
            ],
            'bounty-program' => [
                'title' => 'Open Source Bounty Program',
                'description' => 'Support the community contributors that have given their time and effort to help make osu! better.',
                'link_text' => 'Find out more &raquo;',
            ],
        ],
        'perks' => [
            'title' => 'Cool! What perks do I get?',
            // TomYum: this server's own perks (:brand is its name, TomYumBranding).
            'discord_role' => [
                'title' => 'Supporter Role on Discord',
                'description' => 'Get an exclusive supporter role on our Discord server! Make sure to link your Discord account with :brand first.',
            ],

            'unlimited_name_changes' => [
                'title' => 'Unlimited Name Changes',
                'description' => 'Free players get 5 name changes per month, but supporters enjoy unlimited changes!',
            ],

            'fresh_start' => [
                'title' => 'Start Fresh',
                'description' => 'Want a fresh start? Contact support and we\'ll wipe your scores for you!',
            ],

            'dev_team' => [
                'title' => 'Suggest Features or Chat with the Dev Team',
                'description' => 'Have a cool idea or want to talk to the developers? Supporters get exclusive roles on our Discord server to connect directly with the team!',
            ],

            'much_more' => [
                'title' => 'And Much More!',
                'description' => "We're keeping some surprises for later… stay tuned!",
            ],

            'osu_direct' => [
                'title' => 'osu!direct',
                'description' => 'Gain quick and easy access to search for and download beatmaps without having to leave the game.',
            ],

            'friend_ranking' => [
                'title' => 'Friend Ranking',
                'description' => "See how you stack up against your friends on a beatmap's leaderboard, both in-game and on the website.",
            ],

            'country_ranking' => [
                'title' => 'Country Ranking',
                'description' => 'Conquer your country before you conquer the world.',
            ],

            'mod_filtering' => [
                'title' => 'Filter by Mods',
                'description' => 'Associate only with people who play HDHR? No problem!',
            ],

            'auto_downloads' => [
                'title' => 'Automatic Downloads',
                'description' => 'Beatmaps will automatically download in multiplayer games, while spectating others, or when clicking relevant links in chat!',
            ],

            'upload_more' => [
                'title' => 'Upload More',
                'description' => 'Additional pending beatmap slots (per ranked beatmap) up to a max of 10.',
            ],

            'early_access' => [
                'title' => 'Early Access',
                'description' => 'Gain early access to new features before they go public!',
            ],

            'customisation' => [
                'title' => 'Customisation',
                'description' => "Stand out by uploading a custom cover image, creating a fully customizable 'me!' section, or even change the colour to any of your liking within your user profile.",
            ],

            'beatmap_filters' => [
                'title' => 'Beatmap Filters',
                'description' => 'Filter beatmap searches by played and unplayed maps, or by rank achieved.',
            ],

            'yellow_fellow' => [
                'title' => 'Yellow Fellow',
                'description' => 'Be recognised in-game with your new bright yellow chat username colour.',
            ],

            'speedy_downloads' => [
                'title' => 'Speedy Downloads',
                'description' => 'More lenient download restrictions, especially when using osu!direct.',
            ],

            'change_username' => [
                'title' => 'Change Username',
                'description' => 'Name changes are free for everyone; supporters can change their name as often as they like.',
            ],

            'skinnables' => [
                'title' => 'Skinnables',
                'description' => 'Extra in-game skinnables, like the main menu background.',
            ],

            'feature_votes' => [
                'title' => 'Feature Votes',
                'description' => 'Votes for feature requests.',
            ],

            'sort_options' => [
                'title' => 'Sort Options',
                'description' => 'The ability to view beatmap country / friend / mod-specific rankings in-game.',
            ],

            'more_favourites' => [
                'title' => 'More Favourites',
                'description' => 'The maximum number of beatmaps you can favourite is increased from :normally &rarr; :supporter',
            ],
            'more_friends' => [
                'title' => 'More Friends',
                'description' => 'The maximum number of friends you can have is increased from :normally &rarr; :supporter',
            ],
            'more_beatmaps' => [
                'title' => 'Upload More Beatmaps',
                'description' => 'How many pending beatmaps you can have at once is calculated from a base value plus an additional bonus for each ranked beatmap you currently have (up to a limit).<br/><br/>Normally this is :base plus :bonus per ranked beatmap (up to :bonus_max). With supporter, this increases to :supporter_base plus :supporter_bonus per ranked beatmap (up to :supporter_bonus_max).',
            ],
            'friend_filtering' => [
                'title' => 'Friend Leaderboards',
                'description' => 'Compete with your friends and see how you rank up against them!',
            ],

        ],
        'supporter_status' => [
            'contribution_with_duration' => 'Thank you for your ongoing support! So far, you\'ve contributed a total of :dollars, earning you the "Supporter" tag for :duration.',
            'not_yet' => "You haven't ever had an :brand supporter tag :(",
            'valid_until' => 'Your current supporter tag is valid until :date!',
            'was_valid_until' => 'Your supporter tag was valid until :date.',

            'gifted' => [
                '_' => 'Out of your total contributions, you’ve gifted :dollars worth of tags to :users covering :duration. That’s incredibly generous!',
                'users' => ':count_delimited other user|:count_delimited other users',
            ],
        ],
    ],
];
