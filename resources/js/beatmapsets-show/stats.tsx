// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import { computed, makeObservable } from 'mobx';
import { observer } from 'mobx-react';
import * as React from 'react';
import { formatNumber, formatStarRating } from 'utils/html';
import { trans, transChoice } from 'utils/lang';
import Controller from './controller';

interface Props {
  controller: Controller;
  part: 'numbers' | 'ratings';
}

// TomYum: the design proposal's middle and right columns: the difficulty's object counts and its
// settings as bars (part 'numbers'), then how many plays pass, the user rating and its spread
// (part 'ratings').
@observer
export default class Stats extends React.Component<Props> {
  // the one in beatmapset has invalid rating 0 data in it
  @computed
  private get ratings() {
    return this.props.controller.beatmapset.ratings.slice(1);
  }

  private get statKeys() {
    switch (this.props.controller.currentBeatmap.mode) {
      case 'mania':
        return ['cs', 'drain', 'accuracy', 'difficulty_rating'] as const;
      case 'taiko':
        return ['drain', 'accuracy', 'difficulty_rating'] as const;
      default:
        return ['cs', 'drain', 'accuracy', 'ar', 'difficulty_rating'] as const;
    }
  }

  constructor(props: Props) {
    super(props);
    makeObservable(this);
  }

  render() {
    if (this.props.part === 'ratings') {
      return (
        <div className='beatmapset-header-v2__ratings'>
          {this.renderSuccessRate()}
          {this.props.controller.beatmapset.is_scoreable && this.renderRating()}
        </div>
      );
    }

    const beatmap = this.props.controller.currentBeatmap;

    return (
      <div className='beatmapset-header-v2__stats'>
        <div className='beatmapset-header-v2__counts'>
          {this.renderCount(trans('beatmapsets.show.stats.count_circles'), beatmap.count_circles)}
          {this.renderCount(trans('beatmapsets.show.stats.count_sliders'), beatmap.count_sliders)}
          {this.renderCount('Spinner Count', beatmap.count_spinners)}
        </div>

        <div className='beatmapset-header-v2__stat-rows'>
          {this.statKeys.map(this.renderStat)}
        </div>
      </div>
    );
  }

  private renderBar(value: number, modifier?: string) {
    return (
      <div className={`beatmapset-header-v2__bar ${modifier == null ? '' : `beatmapset-header-v2__bar--${modifier}`}`}>
        <div className='beatmapset-header-v2__bar-fill' style={{ width: `${Math.max(0, Math.min(1, value)) * 100}%` }} />
      </div>
    );
  }

  private renderRating() {
    const ratings = this.ratings;
    let negative = 0;
    let positive = 0;
    ratings.forEach((count, i) => {
      if (i < 5) {
        negative += count;
      } else {
        positive += count;
      }
    });
    const total = negative + positive;
    const maxValue = Math.max(1, ...ratings);

    return (
      <>
        <div className='beatmapset-header-v2__rating-block'>
          <div className='beatmapset-header-v2__rating-label'>
            {trans('beatmapsets.show.stats.user-rating')}
            {total > 0 && ` (${formatNumber(this.props.controller.beatmapset.rating, 2)})`}
          </div>
          <div className='beatmapset-header-v2__rating-values'>
            <span>{formatNumber(negative)}</span>
            <span>{formatNumber(positive)}</span>
          </div>
          {this.renderBar(total === 0 ? 0 : positive / total, 'rating')}
        </div>

        <div className='beatmapset-header-v2__rating-block'>
          <div className='beatmapset-header-v2__rating-label'>{trans('beatmapsets.show.stats.rating-spread')}</div>
          <div className='beatmapset-header-v2__spread'>
            {ratings.map((count, i) => (
              <div
                key={i}
                className={`beatmapset-header-v2__spread-bar beatmapset-header-v2__spread-bar--${i + 1}`}
                style={{ height: `${Math.max(6, 100 * count / maxValue)}%` }}
                title={formatNumber(count)}
              />
            ))}
          </div>
        </div>
      </>
    );
  }

  private renderSuccessRate() {
    const beatmap = this.props.controller.currentBeatmap;

    if (beatmap.playcount === 0) {
      return (
        <div className='beatmapset-header-v2__rating-block'>
          <div className='beatmapset-header-v2__rating-label'>{trans('beatmapsets.show.info.success-rate')}</div>
          <div className='beatmapset-header-v2__rating-empty'>{trans('beatmapsets.show.info.no_scores')}</div>
        </div>
      );
    }

    const successPercent = beatmap.passcount / Math.max(1, beatmap.playcount);

    return (
      <div className='beatmapset-header-v2__rating-block'>
        <div className='beatmapset-header-v2__rating-label'>{trans('beatmapsets.show.info.success-rate')}</div>
        <div
          className='beatmapset-header-v2__rating-values'
          title={transChoice('beatmapsets.show.info.success_rate_plays', beatmap.playcount, {
            passes: formatNumber(beatmap.passcount),
          })}
        >
          <span>{formatNumber(beatmap.passcount)}/{formatNumber(beatmap.playcount)}</span>
          <span>{formatNumber(successPercent, 2, { style: 'percent' })}</span>
        </div>
        {this.renderBar(successPercent, 'rating')}
      </div>
    );
  }

  // A count as the design's number block: its label over the number (three fit side by side).
  private renderCount(label: string, count: number) {
    return (
      <div className='beatmapset-header-v2__count'>
        <div className='beatmapset-header-v2__count-label'>{label}</div>
        <div className='beatmapset-header-v2__count-value'>{formatNumber(count)}</div>
      </div>
    );
  }

  private readonly renderStat = (key: typeof this.statKeys[number]) => {
    const rawValue = this.props.controller.currentBeatmap[key];
    let label: string = key;
    let value: string;

    switch (key) {
      case 'difficulty_rating':
        label = 'stars';
        value = formatStarRating(rawValue);
        break;
      case 'cs':
        if (this.props.controller.currentBeatmap.mode === 'mania') {
          label += '-mania';
        }
        break;
    }

    value ??= formatNumber(rawValue);

    return (
      <div key={key} className='beatmapset-header-v2__stat'>
        <span className='beatmapset-header-v2__stat-label'>{trans(`beatmapsets.show.stats.${label}`)}</span>
        <span className='beatmapset-header-v2__stat-value'>{value}</span>
        {this.renderBar(rawValue / 10, key === 'difficulty_rating' ? 'stars' : undefined)}
      </div>
    );
  };
}
