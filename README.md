# osu-web for TomYum

This is [ppy/osu-web](https://github.com/ppy/osu-web), with ppy's history, plus the changes that make it
the website of a [TomYum](https://github.com/somtum-fun/TomYum-OSS) server: accounts shared with the game
server, staff panels (BN, GMT, admin), branding, downloads, legal pages and store checkout through
payments-service (Stripe). Changes to ppy's files are marked with a `TomYum:` comment.

Not affiliated with ppy Pty Ltd. osu! is a trademark of ppy Pty Ltd; the licence below (AGPL-3.0) covers
the code, not the osu! name, logo or artwork.

Known gaps: the store's prices and currency are still baht/US$ (`SupporterTag`, `store_currency()`).

## Community

Questions about TomYum and this website go to the **somtum.fun Discord server**, in its **tomyum-bancho**
category: [join the server](https://discord.gg/U6Ztb3mRyz), then ask in
[#discussion](https://discord.com/channels/1512525725255860425/1559643196995149824) or report bugs in
[#bug-problem](https://discord.com/channels/1512525725255860425/1559643294315843746). Please don't ask ppy's
Discord about this fork. Setting up a server is covered on [docs.somtum.fun](https://docs.somtum.fun).

Example TomYum plugins, all running on somtum.fun:

- [tomyum-plugin-template](https://github.com/somtum-fun/tomyum-plugin-template): the starting point, compiled or loose source.
- [tomyum-bpy-api](https://github.com/somtum-fun/tomyum-bpy-api): bancho.py's public v1 API on TomYum's data.
- [tomyum-votes-plugins](https://github.com/somtum-fun/tomyum-votes-plugins): osu-server-list.com votes, rewarded with osu!direct time.

---

# osu!web

[![Tests](https://github.com/ppy/osu-web/actions/workflows/tests.yml/badge.svg)](https://github.com/ppy/osu-web/actions/workflows/tests.yml)
[![Lint](https://github.com/ppy/osu-web/actions/workflows/lint.yml/badge.svg)](https://github.com/ppy/osu-web/actions/workflows/lint.yml)
[![Crowdin](https://d322cqt584bo4o.cloudfront.net/osu-web/localized.svg)](https://crowdin.com/project/osu-web)
[![dev chat](https://discordapp.com/api/guilds/188630481301012481/widget.png?style=shield)](https://discord.gg/ppy)

The browser-facing portion of [osu!](https://osu.ppy.sh/home).

## Getting Started

See the [setup guide](SETUP.md) for a rundown on how to get a development environment up.

## Contributing

We welcome all contributions, but keep in mind that we already have the full site designed (mock-ups). If you wish to work on a new section, please open a ticket and we will give you what you need from a design perspective to proceed. If you want to make *changes* to the design, we recommend you open an issue with your intentions before spending too much time, to ensure no effort is wasted.

If you wish to help with localisation efforts, head over to [crowdin](https://crowdin.com/project/osu-web). If you're unsure of what you can help with, check out the [list of open issues](https://github.com/ppy/osu-web/issues). (especially those with the ["good first issue"](https://github.com/ppy/osu-web/issues?q=is%3Aissue+is%3Aopen+sort%3Aupdated-desc+label%3A%22good+first+issue%22) label).

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for information about the code standards we expect from pull requests.

While we have standards in place, nothing is set in stone. If you have an issue with the way code is structured; with any libraries we are using; with any processes involved with contributing, *please* bring it up. We welcome all feedback so we can make contributing to this project as pain-free as possible.

Everyone taking part follows our [Code of Conduct](CODE_OF_CONDUCT.md) (the Contributor Covenant); report
problems to [mind@somtum.fun](mailto:mind@somtum.fun).

Our team believes in using AI as an assistant, always under human review. Low-effort AI-generated ("slop") pull requests or comments may be closed without notice.

## Security

Found a security problem? Please don't open an issue: email [mind@somtum.fun](mailto:mind@somtum.fun).
See [SECURITY.md](SECURITY.md).

## Seeking Help

If you need help with anything, you have two options:

### Create an Issue

If you have something you want to discuss in detail, or have hit an issue which you believe others will also have in deployment or development of the system, [opening an issue](https://github.com/somtum-fun/osu-web-tomyum/issues) is the best way to get help. It creates a permanent resource for others wishing to contribute to conversation. Please **make sure to search first** in case someone else has already addressed the same issue!

### Discord

Alternatively, ask in [#discussion](https://discord.com/channels/1512525725255860425/1559643196995149824) on the
[somtum.fun Discord server](https://discord.gg/U6Ztb3mRyz) (tomyum-bancho category). ppy's development
Discord is for ppy's osu-web, not this fork.

## Licence

osu!web is licensed under AGPL version 3 or later. Please see [the licence file](LICENCE) for more information. [tl;dr](https://tldrlegal.com/license/gnu-affero-general-public-license-v3-(agpl-3.0)) if you want to use any code, design or artwork from this project, attribute it and make your project open source under the same licence.

Please note that this *does not cover* the usage of the "osu!" or "ppy" branding in any software, resources, advertising or promotion, as this is protected by trademark law.
