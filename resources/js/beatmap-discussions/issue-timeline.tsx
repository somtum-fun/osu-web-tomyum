// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import BeatmapsetDiscussionJson from 'interfaces/beatmapset-discussion-json';
import * as React from 'react';
import { formatTimestamp, makeUrl } from 'utils/beatmapset-discussion-helper';
import { classWithModifiers } from 'utils/css';
import { trans } from 'utils/lang';

interface Props {
  discussions: BeatmapsetDiscussionJson[];
  duration: number;
}

// TomYum: the design proposal's issues indicator: a line on a dark bar where each timestamped
// discussion is in the map (praise blue, problems red, suggestions yellow, notes purple, resolved
// lime), and under it a dot for each open problem (red) and resolved one (lime). Each jumps to its
// discussion.
export default function IssueTimeline({ discussions, duration }: Props) {
  if (duration <= 0) return null;

  const items = discussions.filter((discussion) => discussion.timestamp != null && discussion.deleted_at == null);
  const position = (timestamp: number) => `${100 * Math.min(1, timestamp / duration)}%`;
  const type = (discussion: BeatmapsetDiscussionJson) => discussion.resolved ? 'resolved' : discussion.message_type;

  return (
    <div className='beatmapset-issue-timeline'>
      <div className='beatmapset-issue-timeline__bar'>
        {items.map((discussion) => (
          <a
            key={discussion.id}
            className={`${classWithModifiers('beatmapset-issue-timeline__mark', type(discussion))} js-beatmap-discussion--jump`}
            data-tooltip-position='bottom center'
            href={makeUrl({ discussion })}
            style={{ left: position(discussion.timestamp ?? 0) }}
            title={`${formatTimestamp(discussion.timestamp ?? 0)} ${trans(`beatmaps.discussions.message_type.${discussion.message_type}`)}`}
          />
        ))}
      </div>

      <div className='beatmapset-issue-timeline__dots'>
        {items.filter((discussion) => discussion.message_type === 'problem' || discussion.resolved).map((discussion) => (
          <span
            key={discussion.id}
            className={classWithModifiers('beatmapset-issue-timeline__dot', discussion.resolved ? 'resolved' : 'problem')}
            style={{ left: position(discussion.timestamp ?? 0) }}
          />
        ))}
      </div>
    </div>
  );
}
