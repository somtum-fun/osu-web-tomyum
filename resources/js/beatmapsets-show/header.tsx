// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import BeatmapsetBadge from 'components/beatmapset-badge';
import BeatmapsetCover from 'components/beatmapset-cover';
import DifficultyBadge from 'components/difficulty-badge';
import PopupMenu from 'components/popup-menu';
import PopupMenuState from 'components/popup-menu-state';
import { ReportReportable } from 'components/report-reportable';
import StringWithComponent from 'components/string-with-component';
import UserLink from 'components/user-link';
import BeatmapExtendedJson from 'interfaces/beatmap-extended-json';
import UserJson from 'interfaces/user-json';
import { route } from 'laroute';
import { action, computed, makeObservable, observable } from 'mobx';
import { observer } from 'mobx-react';
import * as moment from 'moment';
import core from 'osu-core-singleton';
import * as React from 'react';
import { getDiffColour } from 'utils/beatmap-helper';
import { downloadLimited, getArtist, getTitle, makeSearchQueryOption, toggleFavourite } from 'utils/beatmapset-helper';
import { classWithModifiers } from 'utils/css';
import { formatDuration, formatNumber } from 'utils/html';
import { trans } from 'utils/lang';
import { present } from 'utils/string';
import { beatmapDownloadDirect, wikiUrl } from 'utils/url';
import BeatmapPicker from './beatmap-picker';
import Controller from './controller';
import Stats from './stats';

// TomYum: the beatmap page after osu!'s "Beatmap Info" design proposal (flyte): a cover that folds
// away (remembered), the title and difficulty picker, the difficulty's line (ruleset, stars, name,
// mapper, length and BPM), its points of failure, three columns (the set's details, the
// difficulty's stats, success rate and ratings) and a bar with the play counts and the buttons.

const favouritesToShow = 8; // avatars beside the play counts
const coverExpandedKey = 'beatmapset-cover-expanded';

function readCoverExpanded() {
  try {
    return localStorage.getItem(coverExpandedKey) === '1';
  } catch {
    return false;
  }
}

function statusIcon(type: 'storyboard' | 'video') {
  const iconClass = type === 'video' ? 'fas fa-film' : 'fas fa-image';

  return (
    <div
      className='beatmapset-status beatmapset-status--show-icon'
      title={trans(`beatmapsets.show.info.${type}`)}
    >
      <span className={iconClass} />
    </div>
  );
}

interface Props {
  controller: Controller;
}

// A mapper: someone with an account here (their profile and hover card), or an osu! user from the
// set's import (their osu! profile and avatar).
interface Person {
  avatarUrl: string | null;
  id: number;
  local: boolean;
  username: string;
}

@observer
export default class Header extends React.Component<Props> {
  @observable private coverExpanded = readCoverExpanded();
  private readonly downloadMenu = new PopupMenuState();
  private readonly moreMenu = new PopupMenuState();
  @observable private tagsExpanded = false;

  private get beatmapset() {
    return this.controller.beatmapset;
  }

  private get controller() {
    return this.props.controller;
  }

  @computed
  private get filteredFavourites() {
    let ret = this.beatmapset.recent_favourites;

    const user = core.currentUser;
    if (user != null) {
      ret = ret.filter((f) => f.id !== user.id);

      if (this.beatmapset.has_favourited) {
        ret.unshift(user);
      }
    }

    return ret.slice(0, favouritesToShow);
  }

  @computed
  private get nominators() {
    const ret: UserJson[] = [];
    const usersById = this.controller.usersById;
    for (const nomination of this.beatmapset.current_nominations) {
      const user = usersById[nomination.user_id];
      if (user != null) {
        ret.push(user);
      }
    }

    return ret;
  }

  @computed
  private get nominatorPeople(): Person[] {
    if (this.nominators.length > 0) {
      return this.nominators.map((user) => ({ avatarUrl: user.avatar_url ?? null, id: user.id, local: true, username: user.username }));
    }

    // TomYum: nominators known only from osu!, with their osu! profiles and avatars.
    return (this.beatmapset.tomyum_nominators ?? []).map((user) => ({
      avatarUrl: `https://a.ppy.sh/${user.id}`,
      id: user.id,
      local: false,
      username: user.username,
    }));
  }

  @computed
  private get tags() {
    return [...this.controller.tags.userTags.map((tag) => tag.name), ...this.controller.tags.mapperTags];
  }

  constructor(props: Props) {
    super(props);

    makeObservable(this);
  }


  render() {
    const beatmap = this.controller.hoveredBeatmap ?? this.controller.currentBeatmap;

    return (
      <div
        className='beatmapset-header-v2'
        style={{ '--diff': getDiffColour(beatmap.difficulty_rating) } as React.CSSProperties}
      >
        {this.renderCover()}

        <div className='beatmapset-header-v2__head'>
          {this.renderTitle()}
        </div>

        {this.renderAvailabilityInfo()}

        <div className='beatmapset-header-v2__difficulty-band'>
          {this.renderDifficulty()}
          <BeatmapPicker controller={this.controller} renderMappers={this.renderMappedBy} />
        </div>

        <div className='beatmapset-header-v2__body'>
          {this.renderFailStrip()}

          <div className='beatmapset-header-v2__columns'>
            {this.renderDetails()}
            <div className='beatmapset-header-v2__divider' />
            <Stats controller={this.controller} part='numbers' />
            <Stats controller={this.controller} part='ratings' />
          </div>

          {this.renderCredits()}
        </div>

        {this.renderFooter()}
      </div>
    );
  }

  @action
  private readonly handleCoverToggle = () => {
    this.coverExpanded = !this.coverExpanded;

    try {
      localStorage.setItem(coverExpandedKey, this.coverExpanded ? '1' : '0');
    } catch {
      // the cover just won't be remembered
    }
  };

  private readonly handleFavouriteClick = () => {
    toggleFavourite(this.beatmapset);
  };

  @action
  private readonly handleTagsToggle = () => {
    this.tagsExpanded = !this.tagsExpanded;
  };

  private renderAvailabilityInfo() {
    if (!downloadLimited(this.beatmapset)) return;

    let label: string;
    let href: string | null;

    if (this.beatmapset.availability.download_disabled) {
      label = trans('beatmapsets.availability.disabled');
    } else {
      if (this.beatmapset.availability.more_information === 'rule_violation') {
        label = trans('beatmapsets.availability.rule_violation');
        href = `${wikiUrl('Rules')}#beatmap-submission-rules`;
      } else {
        label = trans('beatmapsets.availability.parts-removed');
      }
    }

    href ??= this.beatmapset.availability.more_information;

    return (
      <div className='beatmapset-header-v2__availability'>
        {label}
        {href != null && (
          <>
            {' '}
            <a href={href} rel='noreferrer' target='_blank'>
              {trans('beatmapsets.availability.more-info')}
            </a>
          </>
        )}
      </div>
    );
  }

  // The cover, folded to a strip by default: the status, explicit and video / storyboard pills on the
  // left, the song preview and the fold button on the right.
  private renderCover() {
    return (
      <div className={classWithModifiers('beatmapset-header-v2__cover', { expanded: this.coverExpanded })}>
        <BeatmapsetCover
          beatmapset={this.beatmapset}
          forceShowNsfw // check already covered by parent component
          modifiers='full'
          size='cover'
        />

        <div className='beatmapset-header-v2__cover-pills'>
          <a
            className='beatmapset-header-v2__status'
            href={this.statusToWikiLink(this.controller.currentBeatmap.status)}
            style={{
              '--bg': `hsl(var(--beatmapset-${this.controller.currentBeatmap.status}-bg-hsl))`,
              '--colour': `var(--beatmapset-${this.controller.currentBeatmap.status}-colour)`,
            } as React.CSSProperties}
          >
            {trans(`beatmapsets.show.status.${this.controller.currentBeatmap.status}`)}
          </a>
          {this.beatmapset.nsfw && (
            <span className='beatmapset-header-v2__explicit'>{trans('beatmapsets.nsfw_badge.label')}</span>
          )}
          {this.beatmapset.video && statusIcon('video')}
          {this.beatmapset.storyboard && statusIcon('storyboard')}
        </div>

        <div className='beatmapset-header-v2__cover-buttons'>
          <button
            className='beatmapset-header-v2__preview js-audio--play js-audio--player'
            data-audio-url={this.beatmapset.preview_url}
            type='button'
          >
            <span className='play-button' />
            <span className='beatmapset-header-v2__preview-elapsed' />
          </button>

          <button
            className='beatmapset-header-v2__cover-toggle'
            onClick={this.handleCoverToggle}
            title={this.coverExpanded ? 'Fold the cover' : 'Show the cover'}
            type='button'
          >
            <span className={this.coverExpanded ? 'fas fa-chevron-up' : 'fas fa-chevron-down'} />
          </button>
        </div>
      </div>
    );
  }

  // The set's creator: their account here, else who made it on osu! (the import), else their name
  // linked to an osu! profile search.
  @computed
  private get creator(): Person | string {
    const user = this.beatmapset.user;
    if (user?.id != null && user.id > 0) {
      return { avatarUrl: user.avatar_url ?? null, id: user.id, local: true, username: user.username };
    }

    const osuCreator = this.beatmapset.tomyum_mappers?.creator;
    if (osuCreator != null) {
      return { avatarUrl: `https://a.ppy.sh/${osuCreator.id}`, id: osuCreator.id, local: false, username: osuCreator.username };
    }

    return this.beatmapset.creator;
  }

  // A difficulty's mappers: their accounts here, else who made it on osu! (the import).
  private mappers(beatmap: Controller['currentBeatmap']): Person[] {
    const local = this.controller.owners(beatmap)
      .filter((user) => user.id > 0)
      .map((user) => ({ avatarUrl: user.avatar_url, id: user.id, local: true, username: user.username }));
    if (local.length > 0) return local;

    return (this.beatmapset.tomyum_mappers?.beatmaps[beatmap.id] ?? [])
      .map((user) => ({ avatarUrl: `https://a.ppy.sh/${user.id}`, id: user.id, local: false, username: user.username }));
  }

  private renderCreator() {
    const creator = this.creator;

    return typeof creator === 'string'
      ? (
        <a className='beatmapset-header-v2__link' href={`https://osu.ppy.sh/users/${encodeURIComponent(creator)}`}>
          {creator}
        </a>
      ) : this.renderPerson(creator);
  }

  // "mapped by" a difficulty's mappers (names only), for the difficulty list.
  private readonly renderMappedBy = (beatmap: BeatmapExtendedJson) => {
    const mappers = this.mappers(beatmap as Controller['currentBeatmap']);
    if (mappers.length === 0) return null;

    return (
      <StringWithComponent
        mappings={{
          mapper: mappers.map((person, i) => (
            <React.Fragment key={person.id}>
              {i > 0 && ', '}
              {this.renderPersonLink(person, person.username)}
            </React.Fragment>
          )),
        }}
        pattern={trans('beatmapsets.show.details.mapped_by')}
      />
    );
  };

  private renderPersonLink(person: Person, content: React.ReactNode) {
    return person.local ? (
      <UserLink className='beatmapset-header-v2__link' user={{ id: person.id, username: person.username }}>
        {content}
      </UserLink>
    ) : (
      <a className='beatmapset-header-v2__link' href={`https://osu.ppy.sh/users/${person.id}`} rel='noreferrer' target='_blank'>
        {content}
      </a>
    );
  }

  // A mapper's avatar and name, linked to their profile here or on osu!.
  private renderPerson(person: Person) {
    const content = (
      <>
        <span
          className='beatmapset-header-v2__avatar'
          style={person.avatarUrl == null ? undefined : { backgroundImage: `url('${person.avatarUrl}')` }}
        />
        {person.username}
      </>
    );

    return person.local ? (
      <UserLink className='beatmapset-header-v2__person' user={{ id: person.id, username: person.username }}>
        {content}
      </UserLink>
    ) : (
      <a className='beatmapset-header-v2__person' href={`https://osu.ppy.sh/users/${person.id}`} rel='noreferrer' target='_blank'>
        {content}
      </a>
    );
  }

  private renderDate(date: string | null) {
    return date == null ? '-' : moment(date).format('DD MMM YYYY');
  }

  // The set's details: where the song is from, its language and tags.
  private renderDetails() {
    return (
      <div className='beatmapset-header-v2__details'>
        <div className='beatmapset-header-v2__details-group'>
          {present(this.beatmapset.source) && this.renderDetail(trans('beatmapsets.show.info.source'), (
            <a
              className='beatmapset-header-v2__link'
              href={route('beatmapsets.index', { q: makeSearchQueryOption('source', this.beatmapset.source) })}
            >
              {this.beatmapset.source}
            </a>
          ))}
          {this.renderDetail(trans('beatmapsets.show.info.genre'), (
            <a className='beatmapset-header-v2__link' href={route('beatmapsets.index', { g: this.beatmapset.genre.id })}>
              {this.beatmapset.genre.name}
            </a>
          ))}
        </div>

        <div className='beatmapset-header-v2__details-group'>
          {this.renderDetail(trans('beatmapsets.show.info.language'), (
            <a className='beatmapset-header-v2__link' href={route('beatmapsets.index', { l: this.beatmapset.language.id })}>
              {this.beatmapset.language.name}
            </a>
          ))}
          {this.tags.length > 0 && this.renderDetail('Tag', this.renderTags())}
          {this.beatmapset.pack_tags.length > 0 && this.renderDetail(trans('beatmapsets.show.info.pack_tags'), (
            this.beatmapset.pack_tags.map((pack, i) => (
              <React.Fragment key={pack}>
                {i > 0 && ', '}
                <a className='beatmapset-header-v2__link' href={route('packs.show', pack)}>{pack}</a>
              </React.Fragment>
            ))
          ))}
        </div>
      </div>
    );
  }

  private renderDetail(label: string, value: React.ReactNode, plain = false) {
    return (
      <>
        <div className='beatmapset-header-v2__detail-label'>{label}</div>
        <div className={classWithModifiers('beatmapset-header-v2__detail-value', { plain })}>{value}</div>
      </>
    );
  }

  // The difficulty being looked at (or hovered in the picker): its stars, name and mappers, its
  // length and BPM on the right.
  private renderDifficulty() {
    const beatmap = this.controller.hoveredBeatmap ?? this.controller.currentBeatmap;
    const mappers = this.mappers(beatmap);

    return (
      <div className='beatmapset-header-v2__difficulty'>
        <DifficultyBadge modifiers='beatmapset' rating={beatmap.difficulty_rating} />
        <span className='beatmapset-header-v2__version u-ellipsis-overflow'>{beatmap.version}</span>
        {mappers.length > 0 && (
          <span className='beatmapset-header-v2__mapped-by'>
            <StringWithComponent
              mappings={{
                mapper: (
                  <span className='beatmapset-header-v2__people'>
                    {mappers.map((person) => <React.Fragment key={person.id}>{this.renderPerson(person)}</React.Fragment>)}
                  </span>
                ),
              }}
              pattern={trans('beatmapsets.show.details.mapped_by')}
            />
          </span>
        )}

        {this.renderLength()}
      </div>
    );
  }

  // Where players fail (red) or quit (dark red) along the map, brighter where more of them do; the
  // calmer stretches in osu!'s yellows. Only once there's something to show.
  private renderFailStrip() {
    const { exit, fail } = this.controller.currentBeatmap.failtimes;
    const totals = fail.map((value, i) => value + (exit[i] ?? 0));
    const max = Math.max(0, ...totals);
    if (max === 0) return null;

    return (
      <div className='beatmapset-header-v2__fail-strip' title={trans('beatmapsets.show.info.points-of-failure')}>
        {totals.map((total, i) => {
          const share = total / max;
          const modifier = share > 0.75 ? 'fail' : share > 0.5 ? 'exit' : share > 0.25 ? 'high' : share > 0.1 ? 'mid' : 'low';

          return <span key={i} className={`beatmapset-header-v2__fail-cell beatmapset-header-v2__fail-cell--${modifier}`} />;
        })}
      </div>
    );
  }

  // Who favourited it (the latest, as avatars with their player cards on hover), beside the play
  // counts; the heart button only favourites.
  private renderFavourites() {
    const count = this.beatmapset.favourite_count;
    const shown = this.filteredFavourites.slice(0, favouritesToShow);

    return (
      <div className='beatmapset-header-v2__playcount'>
        <div className='beatmapset-header-v2__playcount-label'>{trans('beatmapsets.show.stats.favourites')}</div>
        <div className='beatmapset-header-v2__favourites'>
          {count === 0 ? (
            <span className='beatmapset-header-v2__playcount-value'>{trans('beatmapsets.show.stats.no_favourites')}</span>
          ) : (
            <>
              {shown.map((user) => (
                <a
                  key={user.id}
                  className='beatmapset-header-v2__favourite js-usercard'
                  data-user-id={user.id}
                  href={route('users.show', { user: user.id })}
                  style={{ backgroundImage: `url('${user.avatar_url}')` }}
                />
              ))}
              {count > shown.length && (
                <span className='beatmapset-header-v2__playcount-value'>+{formatNumber(count - shown.length)}</span>
              )}
            </>
          )}
        </div>
      </div>
    );
  }

  // The play counts, then the buttons: favourite, download (and its options), osu!direct, more.
  private renderFooter() {
    const currentUser = core.currentUser;
    const canDownload = currentUser != null && !this.beatmapset.availability.download_disabled;

    return (
      <div className='beatmapset-header-v2__footer'>
        <div className='beatmapset-header-v2__playcount'>
          <div className='beatmapset-header-v2__playcount-label'>Total Play Count</div>
          <div className='beatmapset-header-v2__playcount-value'>
            <span className='fas fa-globe-americas' /> {formatNumber(this.beatmapset.play_count)}
          </div>
        </div>
        <div className='beatmapset-header-v2__playcount'>
          <div className='beatmapset-header-v2__playcount-label'>Difficulty Play Count</div>
          <div className='beatmapset-header-v2__playcount-value'>
            <span className='fas fa-globe-americas' /> {formatNumber(this.controller.currentBeatmap.playcount)}
          </div>
        </div>
        {this.renderFavourites()}

        <div className='beatmapset-header-v2__buttons'>
          {/* No title (osu-web turns titles into tooltips); guests clicking it sign in. */}
          <button
            aria-label={currentUser == null
              ? trans('beatmapsets.show.details.favourite_login')
              : trans(`beatmapsets.show.details.${this.beatmapset.has_favourited ? 'unfavourite' : 'favourite'}`)}
            className={`${classWithModifiers('beatmapset-header-v2__button', 'favourite', { active: this.beatmapset.has_favourited })}${currentUser == null ? ' js-user-link' : ''}`}
            onClick={currentUser == null ? undefined : this.handleFavouriteClick}
            type='button'
          >
            <span className={this.beatmapset.has_favourited ? 'fas fa-heart' : 'far fa-heart'} />
            {' '}
            {formatNumber(this.beatmapset.favourite_count)}
          </button>

          {currentUser == null ? (
            <button className='beatmapset-header-v2__button beatmapset-header-v2__button--wide js-user-link' type='button'>
              <span className='fas fa-lock' /> {trans('beatmapsets.show.details.login_required.top')}
            </button>
          ) : canDownload && (
            <>
              <button
                ref={this.downloadMenu.setButtonRef}
                className='beatmapset-header-v2__button beatmapset-header-v2__button--wide'
                onClick={this.downloadMenu.toggle}
                type='button'
              >
                {trans('beatmapsets.show.details.download._')}
                {' '}
                <span className='fas fa-angle-down' />
              </button>
              <PopupMenu direction='right' skipButton state={this.downloadMenu}>
                {this.renderDownloadMenu}
              </PopupMenu>

              <a
                className='beatmapset-header-v2__button beatmapset-header-v2__button--wide'
                href={currentUser.is_supporter ? beatmapDownloadDirect(this.controller.currentBeatmap.id) : route('support-the-game')}
              >
                {trans('beatmapsets.show.details.download.direct')}
              </a>
            </>
          )}

          <button
            ref={this.moreMenu.setButtonRef}
            className='beatmapset-header-v2__button beatmapset-header-v2__button--more'
            onClick={this.moreMenu.toggle}
            type='button'
          >
            <span className='fas fa-ellipsis-h' />
          </button>
          <PopupMenu direction='left' skipButton state={this.moreMenu}>
            {this.renderMoreMenu}
          </PopupMenu>
        </div>
      </div>
    );
  }

  private readonly renderDownloadMenu = () => {
    const id = this.beatmapset.id;

    return (
      <div className='simple-menu'>
        <a className='simple-menu__item' href={route('beatmapsets.download', { beatmapset: id })} onClick={this.downloadMenu.dismiss}>
          <span className='fas fa-download' />
          {` ${trans('beatmapsets.show.details.download._')}${this.beatmapset.video ? ` ${trans('beatmapsets.show.details.download.video')}` : ''}`}
        </a>
        {this.beatmapset.video && (
          <a className='simple-menu__item' href={route('beatmapsets.download', { beatmapset: id, noVideo: 1 })} onClick={this.downloadMenu.dismiss}>
            <span className='fas fa-download' />
            {` ${trans('beatmapsets.show.details.download._')} ${trans('beatmapsets.show.details.download.no-video')}`}
          </a>
        )}
      </div>
    );
  };

  private readonly renderMoreMenu = () => (
    <div className='simple-menu'>
      <a className='simple-menu__item' href={route('beatmapsets.discussion', { beatmapset: this.beatmapset.id })}>
        <span className='fas fa-comments' />
        {` ${trans('beatmapsets.show.discussion')}`}
      </a>
      {core.currentUser != null && core.currentUser.id !== this.beatmapset.user_id && (
        <ReportReportable
          className='simple-menu__item'
          icon
          onFormOpen={this.moreMenu.dismiss}
          reportableId={this.beatmapset.id.toString()}
          reportableType='beatmapset'
          user={{ username: this.beatmapset.creator }}
        />
      )}
    </div>
  );

  // The title and artist, and who created the set.
  private renderTitle() {
    return (
      <>
        <h1 className='beatmapset-header-v2__title'>
          <a
            className='beatmapset-header-v2__title-link'
            href={route('beatmapsets.index', { q: makeSearchQueryOption('title', getTitle(this.beatmapset)) })}
          >
            {getTitle(this.beatmapset)}
          </a>
          {' '}
          <a
            className='beatmapset-header-v2__artist'
            href={route('beatmapsets.index', { q: makeSearchQueryOption('artist', getArtist(this.beatmapset)) })}
          >
            {getArtist(this.beatmapset)}
          </a>
          <BeatmapsetBadge beatmapset={this.beatmapset} type='featured_artist' />
          <BeatmapsetBadge beatmapset={this.beatmapset} type='spotlight' />
        </h1>
        <div className='beatmapset-header-v2__created-by'>
          created by {this.renderCreator()}
        </div>
      </>
    );
  }

  // Length and BPM of the difficulty being looked at, at the end of its line.
  private renderLength() {
    const beatmap = this.controller.hoveredBeatmap ?? this.controller.currentBeatmap;

    return (
      <span className='beatmapset-header-v2__length'>
        <span title={trans('beatmapsets.show.stats.total_length', { hit_length: formatDuration(beatmap.hit_length) })}>
          Length <span className='beatmapset-header-v2__length-value'>{formatDuration(beatmap.total_length)}</span>
        </span>
        <span>
          {trans('beatmapsets.show.stats.bpm')}
          {' '}
          <span className='beatmapset-header-v2__length-value'>{formatNumber(beatmap.bpm)}</span>
        </span>
      </span>
    );
  }

  // Who made the set and who nominated it (avatars and names), and when it was submitted and ranked.
  private renderCredits() {
    const ranked = this.beatmapset.ranked > 0;
    const lastDateLabel = ranked
      ? `${this.beatmapset.status.charAt(0).toUpperCase()}${this.beatmapset.status.slice(1)} on`
      : 'Updated on';
    const nominators = this.nominatorPeople;

    return (
      <div className='beatmapset-header-v2__credits'>
        {this.renderCredit('mapped by', typeof this.creator === 'string' ? [] : [this.creator], this.renderCreator())}
        {nominators.length > 0 && this.renderCredit('Nominated by', nominators, (
          nominators.map((person, i) => (
            <React.Fragment key={person.id}>
              {i > 0 && ', '}
              {this.renderPersonLink(person, person.username)}
            </React.Fragment>
          ))
        ))}

        <div className='beatmapset-header-v2__dates'>
          <div>Submitted on <strong>{this.renderDate(this.beatmapset.submitted_date)}</strong></div>
          <div>
            {lastDateLabel}
            {' '}
            <strong>{this.renderDate(ranked ? this.beatmapset.ranked_date : this.beatmapset.last_updated)}</strong>
          </div>
        </div>
      </div>
    );
  }

  private renderCredit(label: string, people: Person[], names: React.ReactNode) {
    return (
      <div className='beatmapset-header-v2__credit'>
        {people.length > 0 && (
          <span className='beatmapset-header-v2__credit-avatars'>
            {people.map((person) => (
              <span
                key={person.id}
                className='beatmapset-header-v2__credit-avatar'
                style={person.avatarUrl == null ? undefined : { backgroundImage: `url('${person.avatarUrl}')` }}
              />
            ))}
          </span>
        )}
        <span className='beatmapset-header-v2__credit-text'>
          <span className='beatmapset-header-v2__credit-label'>{label}</span>
          <span className='beatmapset-header-v2__credit-names'>{names}</span>
        </span>
      </div>
    );
  }

  // Up to three lines of tags; the rest behind "…".
  private renderTags() {
    return (
      <div className={classWithModifiers('beatmapset-header-v2__tags', { expanded: this.tagsExpanded })}>
        <span className='beatmapset-header-v2__tags-list'>
          {this.tags.map((tag, i) => (
            <React.Fragment key={`${tag}-${i}`}>
              <a className='beatmapset-header-v2__link' href={route('beatmapsets.index', { q: makeSearchQueryOption('tag', tag) })}>
                {tag}
              </a>
              {' '}
            </React.Fragment>
          ))}
        </span>
        {this.tags.length > 8 && (
          <button className='beatmapset-header-v2__tags-toggle' onClick={this.handleTagsToggle} type='button'>
            {this.tagsExpanded ? <span className='fas fa-angle-up' /> : '…'}
          </button>
        )}
      </div>
    );
  }

  private statusToWikiLink(status: string): string {
    let fragment: string;
    if (status === 'wip' || status === 'pending') {
      fragment = 'wip-and-pending';
    } else {
      fragment = status;
    }
    return wikiUrl(`Beatmap/Category#${fragment}`);
  }
}
