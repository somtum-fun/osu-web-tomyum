// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import ShowMoreLink from 'components/show-more-link';
import StringWithComponent from 'components/string-with-component';
import TimeWithTooltip from 'components/time-with-tooltip';
import UserAvatar from 'components/user-avatar';
import EventJson from 'interfaces/event-json';
import Ruleset from 'interfaces/ruleset';
import { snakeCase } from 'lodash';
import parseEvent from 'profile-page/parse-event';
import * as React from 'react';
import { classWithModifiers } from 'utils/css';
import { stripTags } from 'utils/html';
import { trans } from 'utils/lang';

// TomYum: Community → Activity, the whole server's recent activity (the entries players get on
// their profiles), asked again every 10 seconds while the page is in view.

type ActivityEventJson = EventJson & {
  avatar_url?: string | null;
  legacy_score_event?: boolean | null;
};

type FilterType = 'highlights' | 'firsts' | 'ranks' | 'medals' | 'beatmaps' | 'supporters' | 'names';

interface ActivityJson {
  events: ActivityEventJson[];
  has_more: boolean;
  mode: Ruleset | null;
  type: FilterType;
}

const types: [FilterType, string][] = [
  ['highlights', 'Highlights'],
  ['firsts', '#1s'],
  ['ranks', 'Top 50 ranks'],
  ['medals', 'Medals'],
  ['beatmaps', 'Beatmaps'],
  ['supporters', 'Supporters'],
  ['names', 'Name changes'],
];

const modes: [Ruleset | null, string][] = [
  [null, 'All modes'],
  ['osu', 'osu!'],
  ['taiko', 'osu!taiko'],
  ['fruits', 'osu!catch'],
  ['mania', 'osu!mania'],
];

// Types whose entries are plays, where a mode can be picked.
const scoreTypes = new Set<FilterType>(['firsts', 'ranks']);

const refreshInterval = 10000;

function url(type: FilterType, mode: Ruleset | null, extra: Record<string, number> = {}) {
  const params = new URLSearchParams();
  if (type !== 'highlights') params.set('type', type);
  if (mode != null) params.set('mode', mode);
  for (const [key, value] of Object.entries(extra)) params.set(key, String(value));
  const query = params.toString();

  return `/community/activity${query === '' ? '' : `?${query}`}`;
}

async function get(path: string) {
  const response = await fetch(path, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error(`HTTP ${response.status}`);

  return await response.json() as ActivityJson;
}

export default function Main(props: ActivityJson) {
  const [events, setEvents] = React.useState(props.events);
  const [hasMore, setHasMore] = React.useState(props.has_more);
  const [loading, setLoading] = React.useState(false);
  const [type, setType] = React.useState(props.type);
  const [mode, setMode] = React.useState(props.mode);
  // Entries that came in while the page was open, lit up for a moment.
  const [fresh, setFresh] = React.useState(new Set<number>());
  const newest = events[0]?.id ?? 0;

  const pick = (newType: FilterType, newMode: Ruleset | null) => {
    setLoading(true);
    const path = url(newType, newMode);
    get(path).then((json) => {
      setType(newType);
      setMode(newMode);
      setEvents(json.events);
      setHasMore(json.has_more);
      setFresh(new Set());
      window.history.replaceState(window.history.state, '', path);
    }).catch(() => undefined).finally(() => setLoading(false));
  };

  const showMore = () => {
    const oldest = events[events.length - 1]?.id;
    if (oldest == null) return;
    setLoading(true);
    get(url(type, mode, { before: oldest })).then((json) => {
      setEvents((current) => [...current, ...json.events]);
      setHasMore(json.has_more);
    }).catch(() => undefined).finally(() => setLoading(false));
  };

  React.useEffect(() => {
    const refresh = () => {
      if (document.hidden) return;
      get(url(type, mode, { after: newest })).then((json) => {
        if (json.events.length === 0) return;
        setEvents((current) => {
          const known = new Set(current.map((event) => event.id));
          return [...json.events.filter((event) => !known.has(event.id)), ...current];
        });
        setFresh(new Set(json.events.map((event) => event.id)));
      }).catch(() => undefined);
    };
    const timer = window.setInterval(refresh, refreshInterval);
    document.addEventListener('visibilitychange', refresh);

    return () => {
      window.clearInterval(timer);
      document.removeEventListener('visibilitychange', refresh);
    };
  }, [type, mode, newest]);

  return (
    <div className='activity-feed'>
      <div className='activity-feed__filters'>
        <div className='activity-feed__tabs'>
          {types.map(([value, label]) => (
            <button
              key={value}
              className={classWithModifiers('activity-feed__tab', { active: value === type })}
              disabled={loading}
              onClick={() => pick(value, scoreTypes.has(value) || value === 'highlights' ? mode : null)}
              type='button'
            >
              {label}
            </button>
          ))}
        </div>
        {(scoreTypes.has(type) || type === 'highlights') && (
          <select
            className='activity-feed__mode'
            disabled={loading}
            onChange={(e) => pick(type, e.currentTarget.value === '' ? null : e.currentTarget.value as Ruleset)}
            value={mode ?? ''}
          >
            {modes.map(([value, label]) => <option key={label} value={value ?? ''}>{label}</option>)}
          </select>
        )}
      </div>

      {type === 'highlights' && mode != null && (
        <p className='activity-feed__hint'>The mode picks which #1s show; medals, beatmaps and the rest show for every mode.</p>
      )}

      {events.length === 0 ? (
        <p className='activity-feed__empty'>{trans('events.empty')}</p>
      ) : (
        <ul className='activity-feed__entries'>
          {events.map((event) => <Entry key={event.id} event={event} fresh={fresh.has(event.id)} />)}
        </ul>
      )}

      <ShowMoreLink callback={showMore} hasMore={hasMore} loading={loading} modifiers='t-greysky' />
    </div>
  );
}

function Entry({ event, fresh }: { event: ActivityEventJson; fresh: boolean }) {
  const { badge, iconModifiers, mappings } = parseEvent(event, 'recent-activity');
  if (mappings == null) return null;
  const user = 'user' in event ? event.user : null;

  return (
    <li className={classWithModifiers('activity-feed__entry', { fresh })}>
      <div className='activity-feed__avatar'>
        {user != null && (
          <a href={user.url}>
            <UserAvatar modifiers='full-rounded' user={{ avatar_url: event.avatar_url }} />
          </a>
        )}
      </div>
      <div className={classWithModifiers('profile-extra-entries__icon', iconModifiers)}>
        {badge}
      </div>
      <div className='activity-feed__text'>
        <StringWithComponent
          mappings={mappings}
          pattern={stripTags(trans(`events.${snakeCase(event.type)}`))}
        />
        {event.legacy_score_event != null && (
          <span className='activity-feed__client'>{event.legacy_score_event ? 'stable' : 'lazer'}</span>
        )}
      </div>
      <div className='activity-feed__time'>
        <TimeWithTooltip dateTime={event.created_at} relative />
      </div>
    </li>
  );
}
