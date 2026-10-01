// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import UserLink from 'components/user-link';
import BeatmapsetJson from 'interfaces/beatmapset-json';
import UserJson from 'interfaces/user-json';
import * as React from 'react';

// TomYum: someone who made or nominated a map: an account here (their profile and hover card), or an
// osu! user from the set's import (their osu! profile and avatar).
export interface Person {
  avatarUrl: string | null;
  id: number;
  local: boolean;
  username: string;
}

export function localPerson(user: Pick<UserJson, 'avatar_url' | 'id' | 'username'>): Person {
  return { avatarUrl: user.avatar_url ?? null, id: user.id, local: true, username: user.username };
}

export function osuPerson(user: { id: number; username: string }): Person {
  return { avatarUrl: `https://a.ppy.sh/${user.id}`, id: user.id, local: false, username: user.username };
}

// A difficulty's mappers: their accounts here, else who made it on osu! (the import).
export function beatmapMappers(
  beatmapId: number,
  owners: Pick<UserJson, 'avatar_url' | 'id' | 'username'>[],
  beatmapset: Pick<BeatmapsetJson, 'tomyum_mappers'>,
): Person[] {
  const local = owners.filter((user) => user.id > 0).map(localPerson);
  if (local.length > 0) return local;

  return (beatmapset.tomyum_mappers?.beatmaps[beatmapId] ?? []).map(osuPerson);
}

interface LinkProps {
  avatar?: boolean;
  className: string;
  person: Person;
}

// A person's name (and small avatar), linked to their profile here or on osu!.
export function PersonLink({ avatar = false, className, person }: LinkProps) {
  const content = (
    <>
      {avatar && (
        <span
          className={`${className}-avatar`}
          style={person.avatarUrl == null ? undefined : { backgroundImage: `url('${person.avatarUrl}')` }}
        />
      )}
      {person.username}
    </>
  );

  return person.local ? (
    <UserLink className={className} user={{ id: person.id, username: person.username }}>
      {content}
    </UserLink>
  ) : (
    <a className={className} href={`https://osu.ppy.sh/users/${person.id}`} rel='noreferrer' target='_blank'>
      {content}
    </a>
  );
}
