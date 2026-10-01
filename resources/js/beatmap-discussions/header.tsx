// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import BeatmapPicker, { PickerState } from 'beatmapsets-show/beatmap-picker';
import headerLinks from 'beatmapsets-show/header-links';
import { beatmapMappers, localPerson, osuPerson, Person, PersonLink } from 'beatmapsets-show/people';
import BeatmapsetBadge from 'components/beatmapset-badge';
import DifficultyBadge from 'components/difficulty-badge';
import HeaderV4 from 'components/header-v4';
import NotificationBanner from 'components/notification-banner';
import PlaymodeTabs from 'components/playmode-tabs';
import StringWithComponent from 'components/string-with-component';
import BeatmapExtendedJson from 'interfaces/beatmap-extended-json';
import BeatmapsetDiscussionsStore from 'interfaces/beatmapset-discussions-store';
import Ruleset, { rulesets } from 'interfaces/ruleset';
import WithBeatmapOwners from 'interfaces/with-beatmap-owners';
import { route } from 'laroute';
import { action, computed, makeObservable, observable } from 'mobx';
import { observer } from 'mobx-react';
import * as moment from 'moment';
import * as React from 'react';
import { getDiffColour } from 'utils/beatmap-helper';
import { getArtist, getTitle, toggleFavourite } from 'utils/beatmapset-helper';
import { makeUrl } from 'utils/beatmapset-discussion-helper';
import { classWithModifiers } from 'utils/css';
import { formatDuration, formatNumber } from 'utils/html';
import { trans, transChoice } from 'utils/lang';
import DiscussionsState from './discussions-state';
import IssueTimeline from './issue-timeline';
import ModdingPeople from './modding-people';
import { Nominations } from './nominations';
import { Subscribe } from './subscribe';
import TypeFilters from './type-filters';

interface Props {
  discussionsState: DiscussionsState;
  store: BeatmapsetDiscussionsStore;
}

// What the difficulty picker shows and changes on this page.
class DiscussionsPickerState implements PickerState {
  @observable hoveredBeatmap: BeatmapExtendedJson | null = null;

  get currentBeatmap() {
    return this.discussionsState.currentBeatmap;
  }

  get currentBeatmaps() {
    return this.discussionsState.groupedBeatmaps.get(this.discussionsState.currentBeatmap.mode) ?? [];
  }

  constructor(private readonly discussionsState: DiscussionsState) {
    makeObservable(this);
  }

  beatmapUrl(beatmap: BeatmapExtendedJson) {
    return makeUrl({ beatmap, filter: this.discussionsState.currentFilter });
  }

  @action
  setCurrentBeatmap(beatmap: BeatmapExtendedJson) {
    this.discussionsState.currentBeatmapId = beatmap.id;
    this.discussionsState.changeDiscussionPage('timeline');
  }
}

// TomYum: the discussion page's header after osu!'s "Beatmap Modding" design proposal (flyte), like
// the beatmap page's: the title, the difficulty band in the difficulty's colour with the picker, the
// issues on the map's timeline, who made it and when beside the discussion counters (they filter),
// then the path to ranking and the buttons.
@observer
export class Header extends React.Component<Props> {
  private readonly picker: DiscussionsPickerState;

  private get beatmapset() {
    return this.discussionsState.beatmapset;
  }

  private get currentBeatmap() {
    return this.discussionsState.currentBeatmap;
  }

  private get discussionsState() {
    return this.props.discussionsState;
  }

  // The set's creator: their account here, else who made it on osu! (the import).
  @computed
  private get creator(): Person | null {
    const user = this.users.get(this.beatmapset.user_id);
    if (user != null && user.id > 0) return localPerson(user);

    const osuCreator = this.beatmapset.tomyum_mappers?.creator;
    return osuCreator == null ? null : osuPerson(osuCreator);
  }

  @computed
  private get timelineDiscussions() {
    return this.discussionsState.discussionsForSelectedUserByMode.timeline;
  }

  private get users() {
    return this.props.store.users;
  }

  constructor(props: Props) {
    super(props);
    this.picker = new DiscussionsPickerState(props.discussionsState);
    makeObservable(this);
  }

  render() {
    const beatmap = this.picker.hoveredBeatmap ?? this.currentBeatmap;

    return (
      <>
        {this.beatmapset.deleted_at != null && (
          <NotificationBanner
            message={trans('beatmapsets.show.deleted_banner.message')}
            title={trans('beatmapsets.show.deleted_banner.title')}
            type='info'
          />
        )}
        <HeaderV4
          links={headerLinks('discussions', this.beatmapset)}
          linksAppend={(
            <PlaymodeTabs
              currentMode={this.currentBeatmap.mode}
              entries={rulesets.map((mode) => ({
                count: this.discussionsState.unresolvedDiscussionCounts.byMode[mode],
                countTooltip: transChoice('beatmaps.discussions.unresolved_count', this.discussionsState.unresolvedDiscussionCounts.byMode[mode] ?? 0),
                disabled: (this.discussionsState.groupedBeatmaps.get(mode)?.length ?? 0) === 0,
                mode,
              }))}
              modifiers='beatmapset'
              onClick={this.onClickMode}
            />
          )}
          theme='beatmapset'
        />
        <div className='osu-page'>
          <div
            className='beatmapset-header-v2 beatmapset-header-v2--modding'
            style={{ '--diff': getDiffColour(beatmap.difficulty_rating) } as React.CSSProperties}
          >
            <div className='beatmapset-header-v2__head'>
              <h1 className='beatmapset-header-v2__title'>
                <a className='beatmapset-header-v2__title-link' href={route('beatmapsets.show', { beatmapset: this.beatmapset.id })}>
                  {getTitle(this.beatmapset)}
                </a>
                {' '}
                <span className='beatmapset-header-v2__artist'>
                  {trans('beatmapsets.show.details.by_artist', { artist: getArtist(this.beatmapset) })}
                </span>
                <BeatmapsetBadge beatmapset={this.beatmapset} type='nsfw' />
                <BeatmapsetBadge beatmapset={this.beatmapset} type='spotlight' />
                <BeatmapsetBadge beatmapset={this.beatmapset} type='featured_artist' />
              </h1>
            </div>

            <div className='beatmapset-header-v2__difficulty-band'>
              {this.renderDifficulty(beatmap)}
              <BeatmapPicker controller={this.picker} renderMappers={this.renderMappedBy} />
            </div>

            <div className='beatmapset-header-v2__body'>
              <IssueTimeline discussions={this.timelineDiscussions} duration={this.currentBeatmap.total_length * 1000} />

              <div className='beatmapset-header-v2__modding-row'>
                {this.renderMapping()}
                <div className='beatmapset-header-v2__counters'>
                  <TypeFilters discussionsState={this.discussionsState} />
                </div>
              </div>
            </div>

            <Nominations
              discussionsState={this.discussionsState}
              startButtons={(
                <button
                  aria-label={trans(`beatmapsets.show.details.${this.beatmapset.has_favourited ? 'unfavourite' : 'favourite'}`)}
                  className={classWithModifiers('beatmapset-header-v2__button', 'favourite', { active: this.beatmapset.has_favourited })}
                  onClick={this.handleFavourite}
                  type='button'
                >
                  <span className={this.beatmapset.has_favourited ? 'fas fa-heart' : 'far fa-heart'} />
                  {` ${formatNumber(this.beatmapset.favourite_count)}`}
                </button>
              )}
              endButtons={(
                <>
                  <Subscribe beatmapset={this.beatmapset} discussionsState={this.discussionsState} />
                  <a className='beatmapset-header-v2__button beatmapset-header-v2__button--info' href={route('beatmapsets.show', { beatmapset: this.beatmapset.id })}>
                    <span className='fas fa-info-circle' />
                    {` ${trans('beatmaps.discussions.beatmap_information')}`}
                  </a>
                </>
              )}
              store={this.props.store}
            />

            <ModdingPeople discussionsState={this.discussionsState} store={this.props.store} />
          </div>
        </div>
      </>
    );
  }

  private readonly handleFavourite = () => {
    toggleFavourite(this.beatmapset);
  };

  private mappers(beatmap: BeatmapExtendedJson) {
    // The beatmaps here all have their owners.
    const owners = this.discussionsState.beatmapOwners(beatmap as WithBeatmapOwners<BeatmapExtendedJson>);

    return beatmapMappers(beatmap.id, owners, this.beatmapset);
  }

  @action
  private readonly onClickMode = (event: React.MouseEvent<HTMLAnchorElement>, mode: Ruleset) => {
    event.preventDefault();
    this.discussionsState.changeGameMode(mode);
  };

  // The difficulty being looked at (or hovered in the picker): its stars, name and mappers, its
  // length and BPM on the right.
  private renderDifficulty(beatmap: BeatmapExtendedJson) {
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
                    {mappers.map((person) => (
                      <PersonLink key={person.id} avatar className='beatmapset-header-v2__person' person={person} />
                    ))}
                  </span>
                ),
              }}
              pattern={trans('beatmapsets.show.details.mapped_by')}
            />
          </span>
        )}

        <span className='beatmapset-header-v2__length'>
          <span>
            Length <span className='beatmapset-header-v2__length-value'>{formatDuration(beatmap.total_length)}</span>
          </span>
          <span>
            {trans('beatmapsets.show.stats.bpm')}
            {' '}
            <span className='beatmapset-header-v2__length-value'>{formatNumber(beatmap.bpm)}</span>
          </span>
        </span>
      </div>
    );
  }

  // Who made the set and when it was submitted and ranked (the design's left column).
  private renderMapping() {
    const ranked = this.beatmapset.ranked > 0;
    const date = (value: string | null | undefined) => value == null ? '-' : moment(value).format('ll');

    return (
      <div className='beatmapset-header-v2__details-group beatmapset-header-v2__details-group--mapping'>
        <div className='beatmapset-header-v2__detail-label'>Mapped by</div>
        <div className='beatmapset-header-v2__detail-value'>
          {this.creator == null
            ? this.beatmapset.creator
            : <PersonLink avatar className='beatmapset-header-v2__person' person={this.creator} />}
        </div>
        <div className='beatmapset-header-v2__detail-label'>Submitted on</div>
        <div className='beatmapset-header-v2__detail-value beatmapset-header-v2__detail-value--plain'>{date(this.beatmapset.submitted_date)}</div>
        <div className='beatmapset-header-v2__detail-label'>{ranked ? `${this.beatmapset.status.charAt(0).toUpperCase()}${this.beatmapset.status.slice(1)} on` : 'Updated on'}</div>
        <div className='beatmapset-header-v2__detail-value beatmapset-header-v2__detail-value--plain'>
          {date(ranked ? this.beatmapset.ranked_date : this.beatmapset.last_updated)}
        </div>
      </div>
    );
  }

  // "mapped by" a difficulty's mappers (names only), for the difficulty list.
  private readonly renderMappedBy = (beatmap: BeatmapExtendedJson) => {
    const mappers = this.mappers(beatmap);
    if (mappers.length === 0) return null;

    return (
      <StringWithComponent
        mappings={{
          mapper: mappers.map((person, i) => (
            <React.Fragment key={person.id}>
              {i > 0 && ', '}
              <PersonLink className='beatmapset-header-v2__link' person={person} />
            </React.Fragment>
          )),
        }}
        pattern={trans('beatmapsets.show.details.mapped_by')}
      />
    );
  };
}
