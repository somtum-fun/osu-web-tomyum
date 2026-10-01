// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import { BeatmapsetCardSize } from 'beatmapset-panel';
import { computed, makeObservable } from 'mobx';
import { observer } from 'mobx-react';
import core from 'osu-core-singleton';
import * as React from 'react';
import { classWithModifiers } from 'utils/css';

// TomYum: the views by name, as in osu!'s search design proposal ("View Nano Mini Normal ...")
const names: Record<BeatmapsetCardSize, string> = {
  cover: 'Cover',
  extra: 'Extra',
  list: 'List',
  mini: 'Mini',
  nano: 'Nano',
  normal: 'Normal',
};

interface Props {
  classElement: string;
  size: BeatmapsetCardSize;
}

@observer
export default class BeatmapsetCardViewSelector extends React.Component<Props> {
  @computed
  private get isActive() {
    return this.props.size === core.userPreferences.get('beatmapset_card_size');
  }

  constructor(props: Props) {
    super(props);

    makeObservable(this);
  }

  render() {
    return (
      <button
        className={classWithModifiers(this.props.classElement, { active: this.isActive })}
        onClick={this.handleClick}
        type='button'
      >
        {names[this.props.size]}
      </button>
    );
  }

  private readonly handleClick = () => {
    void core.userPreferences.set('beatmapset_card_size', this.props.size);
  };
}
