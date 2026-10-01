// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import BeatmapsetCover from 'components/beatmapset-cover';
import StringWithComponent from 'components/string-with-component';
import TimeWithTooltip from 'components/time-with-tooltip';
import UserLink from 'components/user-link';
import BeatmapExtendedJson from 'interfaces/beatmap-extended-json';
import BeatmapJson from 'interfaces/beatmap-json';
import BeatmapsetExtendedJson from 'interfaces/beatmapset-extended-json';
import { BeatmapsetStatus } from 'interfaces/beatmapset-json';
import Ruleset from 'interfaces/ruleset';
import { route } from 'laroute';
import { observer } from 'mobx-react';
import core from 'osu-core-singleton';
import * as React from 'react';
import { getDiffColour, group as groupBeatmaps } from 'utils/beatmap-helper';
import { getArtist, getTitle, showAudio, toggleFavourite } from 'utils/beatmapset-helper';
import { classWithModifiers } from 'utils/css';
import { formatNumber, formatNumberSuffixed } from 'utils/html';
import { trans } from 'utils/lang';
import { beatmapsetDownloadDirect } from 'utils/url';

// TomYum: the beatmap cards of osu!'s search design proposal; "extra" stays osu-web's own panel.
export const designCardSizes = ['nano', 'mini', 'normal', 'cover', 'list'] as const;
export type DesignCardSize = typeof designCardSizes[number];

interface Props {
  beatmapset: BeatmapsetExtendedJson;
  size: DesignCardSize;
}

const displayDateMap: Record<BeatmapsetStatus, 'last_updated' | 'ranked_date'> = {
  approved: 'ranked_date',
  graveyard: 'last_updated',
  loved: 'ranked_date',
  pending: 'last_updated',
  qualified: 'ranked_date',
  ranked: 'ranked_date',
  wip: 'last_updated',
};

// how many bars a mode shows before a "+"
const maxBars: Record<DesignCardSize, number> = {
  cover: 20,
  list: 13,
  mini: 13,
  nano: 8,
  normal: 16,
};

interface BeatmapGroup {
  beatmaps: BeatmapJson[];
  keys?: number;
  mode: Ruleset;
}

// a mode's difficulties, osu!mania's split by key count (the "4" and "7" after its icon)
function beatmapGroups(beatmapset: BeatmapsetExtendedJson) {
  const ret: BeatmapGroup[] = [];

  for (const [mode, beatmaps] of groupBeatmaps(beatmapset.beatmaps)) {
    if (beatmaps.length === 0) continue;

    if (mode !== 'mania') {
      ret.push({ beatmaps, mode });
      continue;
    }

    const byKeys = new Map<number, BeatmapJson[]>();
    for (const beatmap of beatmaps) {
      const keys = Math.round((beatmap as Partial<BeatmapExtendedJson>).cs ?? 0);
      let list = byKeys.get(keys);
      if (list == null) {
        list = [];
        byKeys.set(keys, list);
      }
      list.push(beatmap);
    }

    [...byKeys.keys()].sort((a, b) => a - b).forEach((keys) => {
      ret.push({ beatmaps: byKeys.get(keys) ?? [], keys: keys > 0 ? keys : undefined, mode });
    });
  }

  return ret;
}

const Difficulties = observer(({ beatmapset, size }: Props) => (
  <div className='beatmapset-card__diffs'>
    {beatmapGroups(beatmapset).map((group) => {
      const shown = group.beatmaps.slice(0, maxBars[size]);

      return (
        <div key={`${group.mode}-${group.keys ?? 0}`} className='beatmapset-card__diff-group'>
          <span className={`beatmapset-card__mode fal fa-extra-mode-${group.mode}`} title={trans(`beatmaps.mode.${group.mode}`)} />
          {group.keys != null && <span className='beatmapset-card__keys'>{group.keys}</span>}
          {shown.map((beatmap) => (
            <span
              key={beatmap.id}
              className='beatmapset-card__diff'
              style={{ '--bg': getDiffColour(beatmap.difficulty_rating) } as React.CSSProperties}
              title={`${beatmap.version} ${formatNumber(beatmap.difficulty_rating, 2)}★`}
            />
          ))}
          {group.beatmaps.length > shown.length && (
            <span className='beatmapset-card__diff-more' title={formatNumber(group.beatmaps.length)}>+</span>
          )}
        </div>
      );
    })}
  </div>
));

const Status = ({ beatmapset }: { beatmapset: BeatmapsetExtendedJson }) => (
  <span
    className='beatmapset-card__status'
    style={{
      '--bg': `hsl(var(--beatmapset-${beatmapset.status}-bg-hsl))`,
      '--colour': `var(--beatmapset-${beatmapset.status}-colour)`,
    } as React.CSSProperties}
  >
    {trans(`beatmapsets.show.status.${beatmapset.status}`)}
  </span>
);

const Mapper = ({ beatmapset, nameOnly = false }: { beatmapset: BeatmapsetExtendedJson; nameOnly?: boolean }) => (
  <span className='beatmapset-card__mapper u-ellipsis-overflow'>
    {nameOnly ? (
      <UserLink
        className='beatmapset-card__mapper-link'
        user={{ id: beatmapset.user_id, username: beatmapset.creator }}
      />
    ) : <StringWithComponent
      mappings={{
        mapper: (
          <UserLink
            className='beatmapset-card__mapper-link'
            user={{ id: beatmapset.user_id, username: beatmapset.creator }}
          />
        ),
      }}
      pattern={trans('beatmapsets.show.details.mapped_by')}
    />}
  </span>
);

// the video and storyboard markers, black circles over the cover
const Icons = ({ beatmapset }: { beatmapset: BeatmapsetExtendedJson }) => (
  <>
    {beatmapset.video && (
      <span className='beatmapset-card__icon' title={trans('beatmapsets.show.info.video')}>
        <span className='fas fa-film' />
      </span>
    )}
    {beatmapset.storyboard && (
      <span className='beatmapset-card__icon' title={trans('beatmapsets.show.info.storyboard')}>
        <span className='fas fa-image' />
      </span>
    )}
  </>
);

function downloadLink(beatmapset: BeatmapsetExtendedJson) {
  if (core.currentUser == null) {
    return { title: trans('beatmapsets.show.details.logged-out') };
  }

  if (beatmapset.availability?.download_disabled) {
    return { title: trans('beatmapsets.availability.disabled') };
  }

  let type = core.userPreferences.get('beatmapset_download');
  if (type === 'direct' && !core.currentUser.is_supporter) {
    type = 'all';
  }

  if (type === 'direct') {
    return { title: trans('beatmapsets.panel.download.direct'), url: beatmapsetDownloadDirect(beatmapset.id) };
  }

  const params: Record<string, string | number> = { beatmapset: beatmapset.id };
  let titleVariant = 'all';
  if (beatmapset.video) {
    if (type === 'no_video') {
      params.noVideo = 1;
      titleVariant = 'no_video';
    } else {
      titleVariant = 'video';
    }
  }

  return { title: trans(`beatmapsets.panel.download.${titleVariant}`), url: route('beatmapsets.download', params) };
}

@observer
export default class BeatmapsetCard extends React.Component<Props> {
  private get beatmapset() {
    return this.props.beatmapset;
  }

  private get displayDate() {
    return this.beatmapset[displayDateMap[this.beatmapset.status]];
  }

  private get url() {
    return route('beatmapsets.show', { beatmapset: this.beatmapset.id });
  }

  render() {
    const size = this.props.size;
    let className = classWithModifiers('beatmapset-card', size);
    if (showAudio(this.beatmapset)) {
      className += ' js-audio--player';
    }

    return (
      <div className={className} data-audio-url={this.beatmapset.preview_url}>
        {size === 'cover' ? this.renderCoverCard() : this.renderRow()}
      </div>
    );
  }

  private renderActions() {
    const download = downloadLink(this.beatmapset);
    const favouriteIcon = this.beatmapset.has_favourited ? 'fas fa-heart' : 'far fa-heart';

    return (
      <div className='beatmapset-card__actions'>
        <button
          className='beatmapset-card__action'
          onClick={this.toggleFavourite}
          title={trans(`beatmapsets.show.details.${this.beatmapset.has_favourited ? 'unfavourite' : 'favourite'}`)}
          type='button'
        >
          <span className={favouriteIcon} />
        </button>
        <a
          className='beatmapset-card__action'
          href={route('beatmapsets.discussion', { beatmapset: this.beatmapset.id })}
          title={trans('beatmapsets.show.discussion')}
        >
          <span className='fas fa-comment-alt' />
        </a>
        {download.url == null ? (
          <span className='beatmapset-card__action beatmapset-card__action--disabled' title={download.title}>
            <span className='fas fa-file-download' />
          </span>
        ) : (
          <a className='beatmapset-card__action' href={download.url} title={download.title}>
            <span className='fas fa-file-download' />
          </a>
        )}
      </div>
    );
  }

  private renderCover(coverSize: 'card' | 'list') {
    return (
      <div className='beatmapset-card__cover'>
        <BeatmapsetCover beatmapset={this.beatmapset} modifiers='full' size={coverSize} />
        <a className='beatmapset-card__cover-link' href={this.url} />
        {showAudio(this.beatmapset) && (
          <button className='beatmapset-card__play js-audio--play' type='button'>
            <span className='play-button' />
          </button>
        )}
      </div>
    );
  }

  // "Cover": the big cover with the status and numbers on it, the mapper and source under it
  private renderCoverCard() {
    return (
      <>
        <div className='beatmapset-card__top'>
          {this.renderCover('card')}
          <div className='beatmapset-card__top-start'>
            <Icons beatmapset={this.beatmapset} />
            <Status beatmapset={this.beatmapset} />
          </div>
          <div className='beatmapset-card__top-end'>
            {this.displayDate != null && (
              <span className='beatmapset-card__stat'>
                <TimeWithTooltip dateTime={this.displayDate} format='L' />
                <span className='fas fa-check-circle' />
              </span>
            )}
            {this.renderStats()}
          </div>
          <a className='beatmapset-card__titles' href={this.url}>
            <span className='beatmapset-card__title'>{getTitle(this.beatmapset)}</span>
            <span className='beatmapset-card__artist u-ellipsis-overflow'>{getArtist(this.beatmapset)}</span>
          </a>
        </div>
        <div className='beatmapset-card__bottom'>
          <div className='beatmapset-card__bottom-info'>
            <Mapper beatmapset={this.beatmapset} />
            {this.beatmapset.source !== '' && (
              <span className='beatmapset-card__source u-ellipsis-overflow'>{this.beatmapset.source}</span>
            )}
            <Difficulties {...this.props} />
          </div>
          {this.renderActions()}
        </div>
      </>
    );
  }

  // Nano, Mini, Normal and List: the cover at the start, the rest over the faded cover
  private renderRow() {
    const size = this.props.size;
    const titleInline = size !== 'normal';

    return (
      <>
        {this.renderCover('list')}
        {size !== 'nano' && size !== 'list' && (
          <div className='beatmapset-card__icons'>
            <Icons beatmapset={this.beatmapset} />
          </div>
        )}
        <div className='beatmapset-card__info'>
          <div className='beatmapset-card__info-bg'>
            {core.windowSize.isDesktop && (
              <BeatmapsetCover beatmapset={this.beatmapset} modifiers='full' size='card' />
            )}
          </div>
          <div className='beatmapset-card__main'>
            <a className='beatmapset-card__titles' href={this.url}>
              <span className='beatmapset-card__title u-ellipsis-overflow'>
                {getTitle(this.beatmapset)}
                {titleInline && (
                  <span className='beatmapset-card__artist'>{` ${getArtist(this.beatmapset)}`}</span>
                )}
              </span>
              {!titleInline && (
                <span className='beatmapset-card__artist u-ellipsis-overflow'>{getArtist(this.beatmapset)}</span>
              )}
            </a>
            {size === 'nano' || size === 'list' ? (
              <div className='beatmapset-card__line'>
                <Status beatmapset={this.beatmapset} />
                <Mapper beatmapset={this.beatmapset} nameOnly={size === 'nano'} />
              </div>
            ) : (
              <>
                <Mapper beatmapset={this.beatmapset} />
                <div className='beatmapset-card__line'>
                  <Status beatmapset={this.beatmapset} />
                  <Difficulties {...this.props} />
                </div>
              </>
            )}
          </div>
          {size === 'list' && (
            <>
              <div className='beatmapset-card__explicit'>
                {this.beatmapset.nsfw && <span className='beatmapset-card__explicit-pill'>{trans('beatmapsets.nsfw_badge.label')}</span>}
              </div>
              <div className='beatmapset-card__list-stats'>
                <Difficulties {...this.props} />
                <div className='beatmapset-card__line'>
                  {this.renderStats()}
                  {this.displayDate != null && (
                    <span className='beatmapset-card__stat'>
                      <span className='fas fa-check-circle' />
                      <TimeWithTooltip dateTime={this.displayDate} format='ll' />
                    </span>
                  )}
                </div>
              </div>
            </>
          )}
          {size !== 'nano' && this.renderActions()}
        </div>
      </>
    );
  }

  private renderStats() {
    return (
      <>
        <span
          className='beatmapset-card__stat'
          title={trans('beatmaps.panel.favourites', { count: formatNumber(this.beatmapset.favourite_count) })}
        >
          <span className='fas fa-heart' />
          {formatNumberSuffixed(this.beatmapset.favourite_count)}
        </span>
        <span
          className='beatmapset-card__stat'
          title={trans('beatmaps.panel.playcount', { count: formatNumber(this.beatmapset.play_count) })}
        >
          <span className='fas fa-play' />
          {formatNumberSuffixed(this.beatmapset.play_count)}
        </span>
      </>
    );
  }

  private readonly toggleFavourite = () => {
    toggleFavourite(this.beatmapset);
  };
}
