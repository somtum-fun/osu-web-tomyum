// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import BlockButton from 'components/block-button';
import FriendButton from 'components/friend-button';
import Reportable from 'interfaces/reportable';
import UserJson from 'interfaces/user-json';
import { route } from 'laroute';
import { observer } from 'mobx-react';
import core from 'osu-core-singleton';
import * as React from 'react';
import { grades } from 'interfaces/user-statistics-json';
import { playTimeStrings } from 'profile-page/play-time';
import { globalTier } from 'profile-page/rank';
import { classWithModifiers, groupColour, Modifiers } from 'utils/css';
import { formatNumber } from 'utils/html';
import { trans } from 'utils/lang';
import { present } from 'utils/string';
import { giftSupporterTagUrl } from 'utils/url';
import { apiLookupUsers } from 'utils/user';
import FlagCountry from './flag-country';
import FlagTeam from './flag-team';
import LevelHexagon from './level-hexagon';
import { PopupMenuPersistent } from './popup-menu-persistent';
import PopupMenuState from './popup-menu-state';
import { ReportReportable } from './report-reportable';
import StringWithComponent from './string-with-component';
import TimeWithTooltip from './time-with-tooltip';
import UserCardBrick from './user-card-brick';

export type ViewMode = 'brick' | 'card' | 'list';
export const viewModes: ViewMode[] = ['card', 'list', 'brick'];

interface Props {
  activated: boolean;
  dropdown?: boolean; // TomYum: card view in a grid (friends, user lists) drops down on hover
  expanded?: boolean; // TomYum: the hover card, with their ranks and numbers (osu!'s "Player Card" expansion)
  mode: ViewMode;
  modifiers?: Modifiers;
  reportable?: Reportable;
  user?: UserJson | null;
}

interface State {
  cardStatistics?: UserJson['card_statistics'];
}

// TomYum: the card and list views follow osu!'s player card design ("Player Card" 375 × 90 and
// "Player Card, List" 1000 × 42): the player's cover in their profile colour, their avatar beside
// their group's colour, name, default ruleset, group pills, friend status and supporter pill, flags,
// level, and whether they're online. The brick view is UserCardBrick.
@observer
export class UserCard extends React.PureComponent<Props, State> {
  static defaultProps = {
    activated: false,
    mode: 'card',
  };

  static userLoading: UserJson = {
    avatar_url: '',
    country_code: '',
    cover: { custom_url: null, id: null, url: null },
    default_group: '',
    id: 0,
    is_active: false,
    is_bot: false,
    is_deleted: false,
    is_online: false,
    is_supporter: false,
    last_visit: '',
    pm_friends_only: true,
    profile_colour: '',
    username: trans('users.card.loading'),
  };

  readonly state: State = {};
  private readonly popupMenuState = new PopupMenuState();
  private url?: string;
  private xhr?: JQuery.jqXHR;

  // TomYum: in card view the card drops down on hover into the hover card's expansion (ranks and
  // numbers), fetched the first time.
  private get dropdown() {
    return this.props.dropdown === true && this.props.mode === 'card' && !this.props.expanded && this.isUserVisible;
  }

  private get cardStatistics() {
    return this.user.card_statistics ?? this.state.cardStatistics;
  }

  private get canMessage() {
    return !this.isSelf
      && !core.currentUserModel.blocks.has(this.user.id);
  }

  private get isSelf() {
    return core.currentUser != null && core.currentUser.id === this.user.id;
  }

  private get isUserLoaded() {
    return Number.isFinite(this.user.id) && this.user.id > 0;
  }

  private get isUserNotFound() {
    return this.user.id === -1;
  }

  private get isUserVisible() {
    return this.isUserLoaded && !this.user.is_deleted;
  }

  private get user() {
    return this.props.user || UserCard.userLoading;
  }

  componentWillUnmount() {
    this.xhr?.abort();
  }

  render() {
    if (this.props.mode === 'brick') {
      if (this.props.user == null) {
        return null;
      }

      return <UserCardBrick {...this.props} playerCard user={this.props.user} />;
    }

    const list = this.props.mode === 'list';
    const blockClass = classWithModifiers(
      'player-card',
      this.props.modifiers,
      this.props.mode,
      // Setting the active modifiers from the parent causes unwanted renders unless deep comparison is used.
      this.popupMenuState.active || this.props.activated ? 'active' : 'highlightable',
      { dropdown: this.dropdown, 'has-expansion': this.cardStatistics != null },
    );
    const style = {
      ...groupColour(this.user.groups?.[0]),
      '--card-hue': this.user.profile_hue ?? undefined,
      '--cover': this.user.cover?.url == null ? undefined : `url('${this.user.cover.url}')`,
    } as React.CSSProperties;

    this.url = this.isUserVisible ? route('users.show', { user: this.user.id }) : undefined;

    const card = (
      <div className={blockClass} style={style}>
        <div className='player-card__info'>
          {this.renderAvatar()}
          <div className='player-card__group-bar' title={this.user.groups?.[0]?.name} />
          {list && this.renderLevel()}
          <div className='player-card__details'>
            {this.renderUsername()}
            {this.isUserVisible && (
              <div className='player-card__badges'>
                {!list && this.renderRuleset()}
                {this.renderGroups()}
                {this.renderFriend()}
                {this.renderSupporter()}
                {list && this.renderFlags()}
              </div>
            )}
          </div>
          {!list && this.renderFlags()}
          {!list && this.renderLevel()}
          {list && this.renderStatus()}
          {this.renderMenuButton()}
        </div>
        {!list && !this.props.expanded && this.renderStatus()}
        {(this.props.expanded || this.dropdown) && this.renderExpansion()}
      </div>
    );

    // The dropdown card hangs over the cards below it, keeping its place in the grid.
    return this.dropdown ? (
      <div className='player-card-slot' onMouseEnter={this.handleMouseEnter}>{card}</div>
    ) : card;
  }

  private readonly handleMouseEnter = () => {
    if (this.cardStatistics != null || this.xhr != null) return;

    this.xhr = apiLookupUsers([this.user.id.toString()], undefined, true)
      .done((response) => {
        const cardStatistics = response.users[0]?.card_statistics;
        if (cardStatistics != null) {
          this.setState({ cardStatistics });
        }
      })
      .fail(() => {
        this.xhr = undefined; // try again next time
      });
  };

  private renderAvatar() {
    const hasAvatar = present(this.user.avatar_url) && !this.isUserNotFound && this.isUserLoaded;
    const avatar = <span className='player-card__avatar' style={hasAvatar ? { backgroundImage: `url('${this.user.avatar_url}')` } : undefined} />;

    return this.url == null ? avatar : <a className='u-contents' href={this.url}>{avatar}</a>;
  }

  // The hover card's expansion: global and country rank, medals, pp, play time and grade counts in
  // their default ruleset. Nothing until the numbers have loaded.
  private renderExpansion() {
    const stats = this.cardStatistics;
    if (stats == null || !this.isUserVisible) return null;

    // The ranks as on the profile, in the player's rank tier's colours (rank-value): the country one too.
    const rank = (value: number | null | undefined, tier?: string | null) => (
      <div
        className={classWithModifiers('rank-value', tier ?? 'base')}
        style={{ '--colour': tier == null ? '' : `var(--level-tier-${tier})` } as React.CSSProperties}
      >
        {value == null ? '-' : `#${formatNumber(value)}`}
      </div>
    );
    const country = this.user.country?.name;
    const tier = globalTier(stats);

    return (
      <div className='player-card__expansion'>
        <div className='player-card__stat player-card__stat--rank'>
          <div className='player-card__stat-label'>{trans('users.show.rank.global_simple')}</div>
          <div className='player-card__stat-value'>{rank(stats.global_rank, tier)}</div>
        </div>
        <div className='player-card__stat player-card__stat--rank'>
          <div className='player-card__stat-label u-ellipsis-overflow'>
            {trans('users.show.rank.country_simple')}{country == null ? '' : `, ${country}`}
          </div>
          <div className='player-card__stat-value'>{rank(stats.country_rank, tier)}</div>
        </div>
        <div className='player-card__stat player-card__stat--small'>
          <div className='player-card__stat-label'>{trans('users.show.stats.medals')}</div>
          <div className='player-card__stat-value'>{formatNumber(stats.medal_count)}</div>
        </div>
        <div className='player-card__stat player-card__stat--small'>
          <div className='player-card__stat-label'>pp</div>
          <div className='player-card__stat-value'>{formatNumber(Math.round(stats.pp))}</div>
        </div>
        <div className='player-card__stat'>
          <div className='player-card__stat-label'>{trans('users.show.stats.play_time')}</div>
          <div className='player-card__stat-value'>{playTimeStrings(stats.play_time).value}</div>
        </div>
        <div className='player-card__grades'>
          {grades.map((grade) => (
            <div key={grade} className='player-card__grade'>
              <div className={`score-rank score-rank--rank-${grade}`} />
              {formatNumber(stats.grade_counts[grade])}
            </div>
          ))}
        </div>
      </div>
    );
  }

  private renderFlags() {
    if (!this.isUserVisible) return null;

    return (
      <div className='player-card__flags'>
        <a href={route('rankings', { country: this.user.country_code, mode: 'osu', type: 'performance' })}>
          <FlagCountry country={this.user.country} />
        </a>
        {this.user.team != null && (
          <a href={route('teams.show', { team: this.user.team.id })}>
            <FlagTeam team={this.user.team} />
          </a>
        )}
      </div>
    );
  }

  private renderFriend() {
    if (this.isSelf) return null;

    return <FriendButton modifiers='player-card' userId={this.user.id} />;
  }

  private renderGroups() {
    const groups = this.user.groups ?? [];
    if (groups.length === 0) return null;
    const [main, ...others] = groups;

    return (
      <>
        <a
          className='player-card__pill player-card__pill--group'
          href={main.has_listing ? route('groups.show', { group: main.id }) : undefined}
          style={groupColour(main)}
          title={main.name}
        >
          {main.short_name}
        </a>
        {others.length > 0 && (
          <span className='player-card__pill player-card__pill--dots' title={others.map((group) => group.name).join(', ')}>
            {others.map((group) => <span key={group.id} className='player-card__dot' style={groupColour(group)} />)}
          </span>
        )}
      </>
    );
  }

  private renderLevel() {
    const level = (this.user.statistics ?? this.cardStatistics)?.level;
    if (level == null || !this.isUserVisible) return null;

    return (
      <LevelHexagon
        level={level.current}
        modifiers={this.props.mode === 'list' ? 'small' : undefined}
        progress={level.progress}
      />
    );
  }

  private renderMenuButton() {
    if (this.isSelf || !this.isUserVisible) return null;

    return (
      <div className='player-card__menu'>
        <PopupMenuPersistent state={this.popupMenuState}>
          {this.renderMenuItems}
        </PopupMenuPersistent>
      </div>
    );
  }

  private readonly renderMenuItems = () => (
    <div className='simple-menu'>
      {this.canMessage && (
        <a
          className='simple-menu__item js-login-required--click'
          href={route('messages.users.show', { user: this.user.id })}
          onClick={this.popupMenuState.dismiss}
        >
          <span className='fas fa-envelope' />
          {` ${trans('users.card.send_message')}`}
        </a>
      )}

      <a
        className='simple-menu__item'
        href={giftSupporterTagUrl(this.user)}
        onClick={this.popupMenuState.dismiss}
      >
        <span className='fas fa-gift' />
        {` ${trans('users.card.gift_supporter')}`}
      </a>

      <BlockButton
        modifiers='inline'
        onClick={this.popupMenuState.dismiss}
        userId={this.user.id}
        wrapperClass='simple-menu__item'
      />

      <ReportReportable
        className='simple-menu__item'
        icon
        onFormOpen={this.popupMenuState.dismiss}
        reportableId={this.props.reportable?.id ?? this.user.id.toString()}
        reportableType={this.props.reportable?.type ?? 'user'}
        user={this.user}
      />
    </div>
  );

  private renderRuleset() {
    if (this.user.playmode == null) return null;

    return (
      <span
        className={`player-card__ruleset fal fa-extra-mode-${this.user.playmode}`}
        title={trans(`beatmaps.mode.${this.user.playmode}`)}
      />
    );
  }

  private renderStatus() {
    if (!this.isUserVisible) return null;

    const online = this.user.is_online;

    return (
      <div className='player-card__status'>
        <span className={classWithModifiers('player-card__status-ring', { online })} />
        <span className='player-card__status-text u-ellipsis-overflow'>
          {online || this.user.last_visit == null
            ? trans(online ? 'users.status.online' : 'users.status.offline')
            : (
              <StringWithComponent
                mappings={{ date: <TimeWithTooltip dateTime={this.user.last_visit} relative /> }}
                pattern={trans('users.show.lastvisit')}
              />
            )}
        </span>
      </div>
    );
  }

  private renderSupporter() {
    if (!this.user.is_supporter) return null;
    const level = Math.max(1, this.user.support_level ?? 1);

    return (
      <a className='player-card__pill player-card__pill--supporter' href={route('support-the-game')} title={trans('users.show.is_supporter')}>
        {Array.from({ length: level }, (_, i) => <span key={i} className='fas fa-heart' />)}
      </a>
    );
  }

  private renderUsername() {
    const displayName = this.user.is_deleted ? trans('users.deleted') : this.user.username;

    return this.url == null ? (
      <div className='player-card__username u-ellipsis-pre-overflow'>{displayName}</div>
    ) : (
      <a className='player-card__username u-ellipsis-pre-overflow' href={this.url}>
        {displayName}
      </a>
    );
  }
}
