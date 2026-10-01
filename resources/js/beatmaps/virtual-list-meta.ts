// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import { BeatmapsetCardSize } from 'beatmapset-panel';
import { computed, makeObservable } from 'mobx';
import core from 'osu-core-singleton';

// TomYum: each view of the search design proposal: its card height, the gap under it, and how
// many cards a row has on desktop.
const sizes: Record<BeatmapsetCardSize, { columns: number; gap: number; height: number }> = {
  cover: { columns: 2, gap: 10, height: 200 },
  extra: { columns: 2, gap: 10, height: 140 },
  list: { columns: 1, gap: 5, height: 40 },
  mini: { columns: 2, gap: 8, height: 60 },
  nano: { columns: 2, gap: 4, height: 32 },
  normal: { columns: 2, gap: 10, height: 100 },
};

export default class VirtualListMeta {
  get size() {
    return core.userPreferences.get('beatmapset_card_size');
  }

  get cardHeight() {
    if (core.windowSize.isDesktop) {
      return sizes[this.size]?.height ?? 100;
    }

    switch (this.size) {
      case 'nano':
      case 'mini':
      case 'cover':
      case 'list':
        return sizes[this.size].height;
    }

    return 120;
  }

  @computed
  get itemHeight() {
    return this.cardHeight + (sizes[this.size]?.gap ?? 10);
  }

  @computed
  get numberOfColumns() {
    return core.windowSize.isDesktop ? sizes[this.size]?.columns ?? 2 : 1;
  }

  constructor() {
    makeObservable(this);
  }
}
