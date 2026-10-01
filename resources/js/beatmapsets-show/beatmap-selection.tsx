// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import { BeatmapIcon } from 'components/beatmap-icon';
import { action, computed, makeObservable } from 'mobx';
import { observer } from 'mobx-react';
import * as React from 'react';
import { getDiffColour } from 'utils/beatmap-helper';
import BeatmapExtendedJson from 'interfaces/beatmap-extended-json';
import { classWithModifiers } from 'utils/css';
import { PickerState } from './beatmap-picker';

interface Props {
  beatmap: BeatmapExtendedJson;
  controller: PickerState;
}

@observer
export default class BeatmapSelection extends React.Component<Props> {
  @computed
  get active() {
    return this.props.controller.currentBeatmap.id === this.props.beatmap.id;
  }

  constructor(props: Props) {
    super(props);
    makeObservable(this);
  }

  render() {
    const className = classWithModifiers('beatmapset-beatmap-picker__beatmap', { active: this.active });

    // TomYum: its colour for the underline (the design proposal's picker).
    return (
      <a
        className={className}
        href={this.props.controller.beatmapUrl(this.props.beatmap)}
        onClick={this.onClick}
        onMouseEnter={this.onMouseEnter}
        onMouseLeave={this.onMouseLeave}
        style={{ '--diff': getDiffColour(this.props.beatmap.difficulty_rating) } as React.CSSProperties}
      >
        <BeatmapIcon beatmap={this.props.beatmap} modifiers='beatmapset' />
      </a>
    );
  }

  private readonly onClick = (e: React.SyntheticEvent) => {
    e.preventDefault();

    this.props.controller.setCurrentBeatmap(this.props.beatmap);
  };

  @action
  private readonly onMouseEnter = () => {
    this.props.controller.hoveredBeatmap = this.props.beatmap;
  };

  @action
  private readonly onMouseLeave = () => {
    this.props.controller.hoveredBeatmap = null;
  };
}
