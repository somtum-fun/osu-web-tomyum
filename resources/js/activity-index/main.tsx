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

// TomYum: Community → Activity, the whole server's recent activity in the profile's "recent activity"
// rows (osu-web's profile-extra-entries), asked again every 10 seconds while the page is in view. The
// filter and mode are the page header's tabs and icons.

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

const emptyText: Record<FilterType, string> = {
  beatmaps: 'No beatmaps ranked, loved or uploaded yet.',
  firsts: 'Nobody has set a #1 yet.',
  highlights: 'Nothing has happened yet.',
  medals: 'Nobody has unlocked a medal yet.',
  names: 'Nobody has changed their name yet.',
  ranks: 'Nobody has made the top 50 on a map yet.',
  supporters: 'Nobody has become a supporter yet.',
};

const refreshInterval = 10000;

function url(type: FilterType, mode: Ruleset | null, extra: Record<string, number>) {
  const params = new URLSearchParams();
  if (type !== 'highlights') params.set('type', type);
  if (mode != null) params.set('mode', mode);
  for (const [key, value] of Object.entries(extra)) params.set(key, String(value));

  return `/community/activity?${params.toString()}`;
}

async function get(path: string) {
  const response = await fetch(path, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error(`HTTP ${response.status}`);

  return await response.json() as ActivityJson;
}

export default function Main({ events: initialEvents, has_more: initialHasMore, mode, type }: ActivityJson) {
  const [events, setEvents] = React.useState(initialEvents);
  const [hasMore, setHasMore] = React.useState(initialHasMore);
  const [loading, setLoading] = React.useState(false);
  // Entries that came in while the page was open, lit up for a moment.
  const [fresh, setFresh] = React.useState(new Set<number>());
  const newest = events[0]?.id ?? 0;

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

  if (events.length === 0) {
    return (
      <p className='profile-extra-entries'>
        {emptyText[type]} New entries show up here by themselves.
      </p>
    );
  }

  return (
    <ul className='profile-extra-entries profile-extra-entries--activity'>
      {events.map((event) => <Entry key={event.id} event={event} fresh={fresh.has(event.id)} />)}
      <li className='profile-extra-entries__item u-contents'>
        <ShowMoreLink callback={showMore} hasMore={hasMore} loading={loading} modifiers='profile-page' />
      </li>
    </ul>
  );
}

function Entry({ event, fresh }: { event: ActivityEventJson; fresh: boolean }) {
  const { badge, iconModifiers, mappings } = parseEvent(event, 'recent-activity');
  if (mappings == null) return null;
  const user = 'user' in event ? event.user : null;

  return (
    <li className={classWithModifiers('profile-extra-entries__item', { activity: true, fresh })}>
      <div className='profile-extra-entries__detail'>
        <a className='profile-extra-entries__avatar' href={user?.url}>
          {user != null && <UserAvatar modifiers='full-rounded' user={{ avatar_url: event.avatar_url }} />}
        </a>
        <div className={classWithModifiers('profile-extra-entries__icon', iconModifiers)}>
          {badge}
        </div>
        <div className='profile-extra-entries__text'>
          <StringWithComponent
            mappings={mappings}
            pattern={stripTags(trans(`events.${snakeCase(event.type)}`))}
          />
          {event.legacy_score_event != null && (
            <span className='profile-extra-entries__client'>{event.legacy_score_event ? 'stable' : 'lazer'}</span>
          )}
        </div>
      </div>
      <div className='profile-extra-entries__time'>
        <TimeWithTooltip dateTime={event.created_at} relative />
      </div>
    </li>
  );
}
