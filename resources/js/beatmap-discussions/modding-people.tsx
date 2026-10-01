// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import { beatmapMappers, localPerson, osuPerson, Person } from 'beatmapsets-show/people';
import BeatmapsetDiscussionsStore from 'interfaces/beatmapset-discussions-store';
import UserJson from 'interfaces/user-json';
import { action, computed, makeObservable } from 'mobx';
import { observer } from 'mobx-react';
import * as React from 'react';
import { classWithModifiers } from 'utils/css';
import { formatNumber } from 'utils/html';
import DiscussionsState from './discussions-state';

interface Props {
  discussionsState: DiscussionsState;
  store: BeatmapsetDiscussionsStore;
}

interface Entry {
  colour: string;
  pending: number;
  person: Person;
  resolved: number;
}

const issueTypes = new Set(['problem', 'suggestion']);

// TomYum: the design proposal's mappers and modders: everyone who made the map, then everyone who
// modded it, as avatars in a row that scrolls sideways, each over their group's colour with how many
// issues they resolved (mappers: on their difficulties; modders: of theirs) and how many of theirs
// are still pending. Clicking someone with an account here shows only their discussions.
@observer
export default class ModdingPeople extends React.Component<Props> {
  private readonly resizeObserver = new ResizeObserver(() => this.updateArrows());
  private readonly scrollerRef = React.createRef<HTMLDivElement>();
  private readonly arrows = { canScrollLeft: false, canScrollRight: false };

  private get beatmapset() {
    return this.props.discussionsState.beatmapset;
  }

  @computed
  private get issues() {
    return [...this.props.store.discussions.values()].filter((discussion) => (
      discussion != null && discussion.deleted_at == null && issueTypes.has(discussion.message_type)
    ));
  }

  // Who made the set and each difficulty, with the issues resolved on what they made.
  @computed
  private get mappers(): Entry[] {
    const users = this.props.store.users;
    const entries = new Map<string, Entry>();
    const add = (person: Person) => {
      const key = `${person.local ? 'local' : 'osu'}-${person.id}`;
      let entry = entries.get(key);
      if (entry == null) {
        entry = { colour: this.colour(person.local ? users.get(person.id) : undefined), pending: 0, person, resolved: 0 };
        entries.set(key, entry);
      }
      return entry;
    };

    const creatorUser = users.get(this.beatmapset.user_id);
    const creatorOsu = this.beatmapset.tomyum_mappers?.creator;
    const creator = creatorUser != null && creatorUser.id > 0
      ? add(localPerson(creatorUser))
      : creatorOsu == null ? null : add(osuPerson(creatorOsu));

    const beatmapMapperEntries = new Map<number, Entry[]>();
    for (const beatmap of this.beatmapset.beatmaps) {
      if (beatmap.deleted_at != null) continue;
      const owners = beatmap.owners.map((owner) => users.get(owner.id) ?? owner);
      beatmapMapperEntries.set(beatmap.id, beatmapMappers(beatmap.id, owners as UserJson[], this.beatmapset).map(add));
    }

    for (const issue of this.issues) {
      if (!issue.resolved) continue;
      const owners = issue.beatmap_id == null ? (creator == null ? [] : [creator]) : (beatmapMapperEntries.get(issue.beatmap_id) ?? []);
      for (const entry of owners) {
        entry.resolved++;
      }
    }

    return [...entries.values()];
  }

  // Who started discussions here (not the mappers), with their issues resolved and pending.
  @computed
  private get modders(): Entry[] {
    const users = this.props.store.users;
    const mapperIds = new Set(this.mappers.filter((entry) => entry.person.local).map((entry) => entry.person.id));
    const entries = new Map<number, Entry>();

    for (const discussion of this.props.store.discussions.values()) {
      if (discussion == null || discussion.deleted_at != null || mapperIds.has(discussion.user_id)) continue;
      const user = users.get(discussion.user_id);
      if (user == null || user.id <= 0) continue;

      let entry = entries.get(user.id);
      if (entry == null) {
        entry = { colour: this.colour(user), pending: 0, person: localPerson(user), resolved: 0 };
        entries.set(user.id, entry);
      }
      if (issueTypes.has(discussion.message_type)) {
        if (discussion.resolved) {
          entry.resolved++;
        } else {
          entry.pending++;
        }
      }
    }

    return [...entries.values()].sort((a, b) => (b.resolved + b.pending) - (a.resolved + a.pending));
  }

  constructor(props: Props) {
    super(props);
    makeObservable(this);
  }

  componentDidMount() {
    if (this.scrollerRef.current != null) {
      this.resizeObserver.observe(this.scrollerRef.current);
    }
    this.updateArrows();
  }

  componentDidUpdate() {
    this.updateArrows();
  }

  componentWillUnmount() {
    this.resizeObserver.disconnect();
  }

  render() {
    const mappers = this.mappers;
    const modders = this.modders;
    if (mappers.length === 0 && modders.length === 0) return null;

    return (
      <div className='beatmapset-modding-people'>
        <button
          className={classWithModifiers('beatmapset-modding-people__arrow', 'left', { hidden: !this.arrows.canScrollLeft })}
          onClick={this.handleScrollLeft}
          type='button'
        >
          <span className='fas fa-chevron-left' />
        </button>

        <div ref={this.scrollerRef} className='beatmapset-modding-people__scroller' onScroll={this.updateArrows}>
          {mappers.length > 0 && this.renderGroup('Mappers', mappers, false)}
          {modders.length > 0 && this.renderGroup('Modders', modders, true)}
        </div>

        <button
          className={classWithModifiers('beatmapset-modding-people__arrow', 'right', { hidden: !this.arrows.canScrollRight })}
          onClick={this.handleScrollRight}
          type='button'
        >
          <span className='fas fa-chevron-right' />
        </button>
      </div>
    );
  }

  // The group's colour under their avatar: their main group's, else supporter pink, else blue.
  private colour(user: UserJson | undefined) {
    const group = user?.groups?.[0];
    if (group?.colour != null) return group.colour;

    return user?.is_supporter ? '#ff66ab' : '#0087ca';
  }

  private readonly handleScrollLeft = () => this.scrollBy(-1);

  private readonly handleScrollRight = () => this.scrollBy(1);

  @action
  private readonly handleSelect = (e: React.MouseEvent<HTMLButtonElement>) => {
    const userId = Number(e.currentTarget.dataset.userId);
    const selected = this.props.discussionsState.selectedUserIds;

    if (selected.has(userId)) {
      selected.delete(userId);
    } else {
      selected.add(userId);
    }
  };

  private renderEntry(entry: Entry, withPending: boolean) {
    const { person } = entry;
    const selected = person.local && this.props.discussionsState.selectedUserIds.has(person.id);
    const avatar = (
      <span
        className='beatmapset-modding-people__avatar'
        style={person.avatarUrl == null ? undefined : { backgroundImage: `url('${person.avatarUrl}')` }}
      />
    );

    return (
      <div key={`${person.local ? 'l' : 'o'}${person.id}`} className={classWithModifiers('beatmapset-modding-people__person', { selected })}>
        {person.local ? (
          <button
            className='beatmapset-modding-people__avatar-button js-usercard'
            data-user-id={person.id}
            onClick={this.handleSelect}
            type='button'
          >
            {avatar}
          </button>
        ) : (
          <a
            className='beatmapset-modding-people__avatar-button'
            href={`https://osu.ppy.sh/users/${person.id}`}
            rel='noreferrer'
            target='_blank'
            title={person.username}
          >
            {avatar}
          </a>
        )}
        <span className='beatmapset-modding-people__group' style={{ backgroundColor: entry.colour }} />
        <span className='beatmapset-modding-people__counts'>
          <span className='beatmapset-modding-people__resolved'>{formatNumber(entry.resolved)}</span>
          {withPending && (
            <>
              {' • '}
              <span className='beatmapset-modding-people__pending'>{formatNumber(entry.pending)}</span>
            </>
          )}
        </span>
      </div>
    );
  }

  private renderGroup(label: string, entries: Entry[], withPending: boolean) {
    return (
      <div className='beatmapset-modding-people__group-box'>
        <div className='beatmapset-modding-people__label'>{label}</div>
        <div className='beatmapset-modding-people__row'>
          {entries.map((entry) => this.renderEntry(entry, withPending))}
        </div>
      </div>
    );
  }

  private scrollBy(direction: number) {
    const scroller = this.scrollerRef.current;
    if (scroller == null) return;

    scroller.scrollBy({ behavior: 'smooth', left: direction * scroller.clientWidth * 0.8 });
  }

  private readonly updateArrows = () => {
    const scroller = this.scrollerRef.current;
    if (scroller == null) return;

    const canScrollLeft = scroller.scrollLeft > 1;
    const canScrollRight = scroller.scrollLeft + scroller.clientWidth < scroller.scrollWidth - 1;

    if (canScrollLeft !== this.arrows.canScrollLeft || canScrollRight !== this.arrows.canScrollRight) {
      this.arrows.canScrollLeft = canScrollLeft;
      this.arrows.canScrollRight = canScrollRight;
      this.forceUpdate();
    }
  };
}
