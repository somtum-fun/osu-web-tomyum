# Security policy

## Reporting a security problem

Please **don't open a public issue** for a security problem. Email **[mind@somtum.fun](mailto:mind@somtum.fun)**
instead, with:

- what the problem is and what someone could do with it;
- how to reproduce it (a request, a packet, a few steps), and the version or commit you tested;
- whether it's already public or being used against a server you know of.

We'll reply within a few days, keep you posted while we fix it, and credit you in the fix's notes unless
you'd rather not be named. Please give us a reasonable time to fix it before you talk about it publicly,
and only test against your own server, never someone else's or their players' accounts.

## What counts

Problems in this repository's code (the TomYum website, osu-web with TomYum's changes): for example getting into
someone else's account, submitting scores as another player, reading or changing data you shouldn't,
running code on the server, or one player being able to take the server down.

A problem that is in ppy's upstream osu-web too should also go to ppy (see
[ppy/osu-web's security policy](https://github.com/ppy/osu-web/security/policy)); we'll fix it here. A server's own configuration (weak passwords, open ports) isn't
something we can fix here, but tell us if the docs led you there.

## Supported versions

Fixes go into `master`. Before `1.0.0` there are no fixes for older versions: update
to the latest one ([updating](https://docs.somtum.fun/guide/updating)).
