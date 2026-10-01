// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import BeatmapsetPanel, { Props } from 'beatmapset-panel';
import { observer } from 'mobx-react';
import core from 'osu-core-singleton';
import * as React from 'react';
import BeatmapsetCard, { DesignCardSize } from './beatmapset-card';

// TomYum: a beatmap in the view picked on the beatmap listing (osu!'s search design proposal), for
// the pages outside it (profile, search): "extra" is osu-web's panel, the others the design's cards.
export default observer(function BeatmapsetCardAuto({ beatmapset }: Props) {
  const size = core.userPreferences.get('beatmapset_card_size');

  return size === 'extra'
    ? <BeatmapsetPanel beatmapset={beatmapset} />
    : <BeatmapsetCard beatmapset={beatmapset} size={size as DesignCardSize} />;
});
