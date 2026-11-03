## Ruleset

Available rulesets:

Name   | Description
------ | ---------------
fruits | osu!catch
mania  | osu!mania
osu    | osu!standard
taiko  | osu!taiko

<a id="ruleset-relax-and-autopilot"></a>

### Relax and Autopilot

Relax and Autopilot plays have their own leaderboards, rankings and pp on this server, kept by the game server rather than the website. Ask for them with these names in place of a [Ruleset](#ruleset) (they are what osu!lazer sends):

Name     | Board
-------- | ---------------
osurx    | osu! Relax
osuap    | osu! Autopilot
taikorx  | osu!taiko Relax
fruitsrx | osu!catch Relax

Endpoint                                                      | Relax / Autopilot
------------------------------------------------------------- | ---------------------
[Get User](#get-user) `/users/{user}/osurx`                   | That board's statistics.
[Get User Scores](#get-user-scores) `?mode=osurx`             | That board's best, firsts or recent plays (or `?mode=osu&variant=osurx`).
[Get Beatmap scores](#get-beatmap-scores) `?mode=osurx`       | The beatmap's board, by pp (or `?mode=osu&variant=rx`, `variant=ap`).
[Get Ranking](#get-ranking) `/rankings/osu/performance?variant=rx` | Its players (`variant=rx` or `ap`; `performance` or `score`).

<a id="ruleset-their-score-ids"></a>

#### Their score ids

A Relax/Autopilot play isn't one of the website's scores, so it has the game server's id, counted separately: the same number is usually also a different, vanilla score. These endpoints tell them apart:

What you ask for                               | Answer
---------------------------------------------- | ---------------------
`/scores/{id}`                                 | The website's (vanilla) score `id`. Never a Relax/Autopilot play.
`/scores/rx/{id}` (or `/scores/ap/{id}`)       | The Relax/Autopilot play with the game server's id `id`: the number on its page, `/scores/rx/{id}` on the website.
`/scores/{id + 1000000000000}`                 | The same play: this is the `id` the endpoints above give it.

Add `/download` for its replay. A Relax/Autopilot play's [Score](#score) has `type` `tomyum_score`, `id` = the game server's id + 1000000000000, and `legacy_score_id` = the game server's id. One played in osu!lazer keeps its website score id instead (osu!lazer matches its replay by it). Don't strip the offset: a bare number always means a vanilla score.
