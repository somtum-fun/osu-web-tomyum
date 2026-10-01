// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import BeatmapsetCover from 'components/beatmapset-cover';
import BeatmapsetJson from 'interfaces/beatmapset-json';
import { route } from 'laroute';
import * as React from 'react';
import { getArtist, getTitle } from 'utils/beatmapset-helper';
import { classWithModifiers, Modifiers } from 'utils/css';
import { formatNumber } from 'utils/html';
import { trans } from 'utils/lang';

// TomYum: the search design's beatmap row: the cover, title and artist, its status and mapper, then
// its favourites, discussion and download.
export default function Beatmapset({ beatmapset, modifiers }: { beatmapset: BeatmapsetJson; modifiers?: Modifiers }) {
  const url = route('beatmapsets.show', { beatmapset: beatmapset.id });

  return (
    <div className={classWithModifiers('beatmapset-search-card', modifiers)}>
      <a className='beatmapset-search-card__link' href={url} />
      <BeatmapsetCover beatmapset={beatmapset} modifiers='search' size='list' />
      <div className='beatmapset-search-card__details'>
        <div className='beatmapset-search-card__title u-ellipsis-overflow'>
          {`${getTitle(beatmapset)} `}
          <span className='beatmapset-search-card__title-artist'>{getArtist(beatmapset)}</span>
        </div>

        <div className='beatmapset-search-card__extra'>
          <span
            className='beatmapset-search-card__status'
            style={{
              '--bg': `hsl(var(--beatmapset-${beatmapset.status}-bg-hsl))`,
              '--colour': `var(--beatmapset-${beatmapset.status}-colour)`,
            } as React.CSSProperties}
          >
            {trans(`beatmapsets.show.status.${beatmapset.status}`)}
          </span>
          {trans('beatmapsets.show.details.mapped_by', { mapper: beatmapset.creator })}
        </div>
      </div>

      <div className='beatmapset-search-card__actions'>
        <span className='beatmapset-search-card__action' title={trans('beatmapsets.show.stats.favourites')}>
          <span className='fas fa-heart' />
          {' '}
          {formatNumber(beatmapset.favourite_count)}
        </span>
        <a
          className='beatmapset-search-card__action'
          href={route('beatmapsets.discussion', { beatmapset: beatmapset.id })}
          title={trans('beatmapsets.show.discussion')}
        >
          <span className='fas fa-comment-alt' />
        </a>
        <a
          className='beatmapset-search-card__action'
          href={route('beatmapsets.download', { beatmapset: beatmapset.id })}
          title={trans('beatmapsets.show.details.download._')}
        >
          <span className='fas fa-file-download' />
        </a>
      </div>
    </div>
  );
}
