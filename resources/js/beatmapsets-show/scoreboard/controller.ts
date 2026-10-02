// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import BeatmapExtendedJson from 'interfaces/beatmap-extended-json';
import { ScoreJsonForBeatmap } from 'interfaces/score-json';
import { route } from 'laroute';
import { action, computed, makeObservable, observable, reaction, runInAction } from 'mobx';
import core from 'osu-core-singleton';
import ScoreboardType, { supporterTypes } from './scoreboard-type';

interface SetOptions {
  forceReload?: boolean;
  resetMods?: boolean;
  sort?: ScoreboardSort;
  toggleMod?: string | null;
  type?: ScoreboardType;
  variant?: ScoreboardVariant;
}

// TomYum: Relax/Autopilot leaderboards (the game server's, by pp) next to the website's own.
export type ScoreboardVariant = 'vn' | 'rx' | 'ap';

// TomYum: a board ordered by pp or by score. Relax/Autopilot start by pp, Vanilla by score.
export type ScoreboardSort = 'pp' | 'score';

export const defaultSort = (variant: ScoreboardVariant): ScoreboardSort => variant === 'vn' ? 'score' : 'pp';

export const variantsByRuleset: Partial<Record<string, ScoreboardVariant[]>> = {
  fruits: ['vn', 'rx'],
  osu: ['vn', 'rx', 'ap'],
  taiko: ['vn', 'rx'],
};

export type ScoreLoadingState = null | 'error' | 'loading' | 'supporter_only' | 'unranked';

interface UserScore {
  position: number;
  score: ScoreJsonForBeatmap;
}

interface BeatmapScoresJson {
  blank?: true;
  scores: ScoreJsonForBeatmap[];
  user_score?: UserScore;
}

interface StoredState {
  allData: Partial<Record<string, BeatmapScoresJson>>;
  currentType: ScoreboardType;
  enabledMods: string[];
  sort?: ScoreboardSort;
  variant?: ScoreboardVariant;
}

export default class Controller {
  @observable currentType: ScoreboardType = 'global';
  @observable enabledMods = new Set<string>();
  @observable sort: ScoreboardSort = 'score';
  @observable variant: ScoreboardVariant = 'vn';

  @observable private allData: Partial<Record<string, BeatmapScoresJson>> = {};
  private readonly disposers = new Set<(() => void) | undefined>();
  private xhr: JQuery.jqXHR<BeatmapScoresJson> | null = null;
  @observable private xhrState: 'error' | 'loading' | null = null;

  get beatmap() {
    return this.getBeatmap();
  }

  @computed
  get currentDataKey() {
    const beatmap = this.beatmap;

    return `${beatmap.id}-${beatmap.mode}-${[...this.enabledMods].sort().join(':')}-${this.currentType}-${this.variant}-${this.sort}`;
  }

  @computed
  get data() {
    return this.allData[this.currentDataKey] ?? { blank: true, scores: [] };
  }

  @computed
  get loadingState(): ScoreLoadingState {
    if (!this.beatmap.is_scoreable) {
      return 'unranked';
    }

    if (!core.currentUser?.is_supporter && this.requiresSupporter) {
      return 'supporter_only';
    }

    return this.xhrState;
  }

  get requiresSupporter() {
    // Relax/Autopilot: country and friends need supporter as on Vanilla; the mod filter is open to everyone.
    if (this.variant !== 'vn') return supporterTypes.has(this.currentType);

    return supporterTypes.has(this.currentType) || this.enabledMods.size > 0;
  }

  constructor(private readonly container: HTMLElement, private readonly getBeatmap: () => BeatmapExtendedJson) {
    let storedState: StoredState | null = null;
    try {
      storedState = JSON.parse(this.container.dataset.scoreboardState ?? 'null') as (StoredState | null);
    } catch {
      // Do nothing if failed parsing.
    }

    if (storedState != null) {
      this.currentType = storedState.currentType;
      this.allData = storedState.allData;
      this.enabledMods = new Set(storedState.enabledMods);
      this.variant = storedState.variant ?? 'vn';
      this.sort = storedState.sort ?? defaultSort(this.variant);
    }

    makeObservable(this);

    $(document).on('turbo:before-cache', this.storeState);

    // fetch score data if needed
    this.setCurrent({});

    this.disposers.add(reaction(
      () => `${this.beatmap.mode}:${this.beatmap.id}`,
      () => this.setCurrent({
        resetMods: true,
        type: 'global',
        // Keep Relax/Autopilot when the new ruleset has it (osu! → taiko keeps RX, loses AP).
        variant: variantsByRuleset[this.beatmap.mode]?.includes(this.variant) ? this.variant : 'vn',
      }),
    ));
  }

  destroy() {
    this.xhr?.abort();
    this.disposers.forEach((d) => d?.());
    this.storeState();
    $(document).off('turbo:before-cache', this.storeState);
  }

  @action
  readonly setCurrent = (options: SetOptions) => {
    const toggleMod = options.toggleMod ?? null;
    const forceReload = options.forceReload ?? false;
    const resetMods = options.resetMods ?? false;

    this.xhr?.abort();

    if (resetMods) {
      this.enabledMods.clear();
    } else {
      if (toggleMod != null) {
        if (this.enabledMods.has(toggleMod)) {
          this.enabledMods.delete(toggleMod);
        } else {
          this.enabledMods.add(toggleMod);
        }
      }
    }

    if (options.type != null) {
      this.currentType = options.type;
    }

    if (options.variant != null) {
      // Each board starts in its own order (pp for Relax/Autopilot, score for Vanilla).
      if (options.variant !== this.variant) {
        this.sort = defaultSort(options.variant);
      }
      this.variant = options.variant;
    }

    if (options.sort != null) {
      this.sort = options.sort;
    }

    const beatmap = this.beatmap;

    if (!forceReload && !this.data.blank) {
      this.xhrState = null;
      return;
    }

    this.xhrState = 'loading';
    const dataKey = this.currentDataKey;
    this.xhr = $.ajax(route('beatmaps.scores', { beatmap: beatmap.id }), {
      data: {
        mode: beatmap.mode,
        mods: [...this.enabledMods],
        sort: this.sort,
        type: this.currentType,
        variant: this.variant === 'vn' ? undefined : this.variant,
      },
      dataType: 'JSON',
      method: 'GET',
    });
    this.xhr.done((data) => runInAction(() => {
      this.allData[dataKey] = data;
      this.xhrState = null;
    })).fail((_xhr, status) => runInAction(() => {
      this.xhrState = status === 'abort' ? null : 'error';
    }));
  };

  private readonly storeState = () => {
    const state: StoredState = {
      allData: this.allData,
      currentType: this.currentType,
      enabledMods: [...this.enabledMods],
      sort: this.sort,
      variant: this.variant,
    };

    this.container.dataset.scoreboardState = JSON.stringify(state);
  };
}
