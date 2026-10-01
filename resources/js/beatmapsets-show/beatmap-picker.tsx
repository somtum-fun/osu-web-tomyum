// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import DifficultyBadge from 'components/difficulty-badge';
import Portal from 'components/portal';
import BeatmapExtendedJson from 'interfaces/beatmap-extended-json';
import { action, makeObservable, observable } from 'mobx';
import { observer } from 'mobx-react';
import * as React from 'react';
import { classWithModifiers } from 'utils/css';
import BeatmapSelection from './beatmap-selection';

// What the picker shows and changes: the beatmap page's controller, or the discussion page's state.
export interface PickerState {
  readonly currentBeatmap: BeatmapExtendedJson;
  readonly currentBeatmaps: readonly BeatmapExtendedJson[];
  hoveredBeatmap: BeatmapExtendedJson | null;
  beatmapUrl(beatmap: BeatmapExtendedJson): string;
  setCurrentBeatmap(beatmap: BeatmapExtendedJson): void;
}

interface Props {
  controller: PickerState;
  // Who mapped a difficulty, for the list ("mapped by …"); from the header.
  renderMappers: (beatmap: BeatmapExtendedJson) => React.ReactNode;
}

// TomYum: the design proposal's difficulty picker: the four dots open the list of every difficulty
// (the whole page dims, the list pops up over the title, just right of the dots: ruleset, stars,
// name, mapper), then each difficulty's icon (hovering one shows it on the page, the selected one
// is marked).
@observer
export default class BeatmapPicker extends React.Component<Props> {
  private readonly buttonRef = React.createRef<HTMLButtonElement>();
  @observable private listOpen = false;
  @observable private listPosition = { left: 0, top: 0 };

  constructor(props: Props) {
    super(props);
    makeObservable(this);
  }

  componentDidUpdate() {
    if (this.listOpen) {
      document.addEventListener('keydown', this.handleKeyDown);
    } else {
      document.removeEventListener('keydown', this.handleKeyDown);
    }
  }

  componentWillUnmount() {
    document.removeEventListener('keydown', this.handleKeyDown);
  }

  render() {
    return (
      <div className='beatmapset-beatmap-picker'>
        <button
          ref={this.buttonRef}
          className='beatmapset-beatmap-picker__list-button'
          onClick={this.handleToggle}
          title='All difficulties'
          type='button'
        >
          <span className='beatmapset-beatmap-picker__list-dots' />
        </button>

        {this.props.controller.currentBeatmaps.map((beatmap) => (
          <BeatmapSelection
            key={beatmap.id}
            beatmap={beatmap}
            controller={this.props.controller}
          />
        ))}

        {this.listOpen && this.renderList()}
      </div>
    );
  }

  @action
  private readonly handleClose = () => {
    this.listOpen = false;
    this.props.controller.hoveredBeatmap = null;
  };

  private readonly handleKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') {
      this.handleClose();
    }
  };

  // Where the design has it: its left edge 29px right of the dots, its top 118px above them (over
  // the title), kept below the site's header and inside the window.
  @action
  private readonly handleToggle = () => {
    const button = this.buttonRef.current;
    if (button != null) {
      const rect = button.getBoundingClientRect();
      const width = Math.min(550, window.innerWidth - 40);
      this.listPosition = {
        left: Math.max(20, Math.min(rect.left + 29, window.innerWidth - width - 20)) + window.scrollX,
        top: Math.max(70, rect.top - 118) + window.scrollY,
      };
    }
    this.listOpen = !this.listOpen;
  };

  private renderList() {
    const controller = this.props.controller;

    return (
      <Portal>
        <div className='beatmapset-beatmap-picker__dim' onClick={this.handleClose} />
        <div className='beatmapset-beatmap-picker__list' style={{ left: this.listPosition.left, top: this.listPosition.top }}>
          {controller.currentBeatmaps.map((beatmap) => (
            <a
              key={beatmap.id}
              className={classWithModifiers('beatmapset-beatmap-picker__list-item', {
                active: beatmap.id === controller.currentBeatmap.id,
              })}
              href={controller.beatmapUrl(beatmap)}
              onClick={action((e: React.MouseEvent) => {
                e.preventDefault();
                controller.setCurrentBeatmap(beatmap);
                this.handleClose();
              })}
              onMouseEnter={action(() => {
                controller.hoveredBeatmap = beatmap;
              })}
              onMouseLeave={action(() => {
                controller.hoveredBeatmap = null;
              })}
            >
              <span className={`beatmapset-beatmap-picker__list-ruleset fal fa-extra-mode-${beatmap.mode}`} />
              <DifficultyBadge rating={beatmap.difficulty_rating} />
              <span className='beatmapset-beatmap-picker__list-name u-ellipsis-overflow'>
                <span className='beatmapset-beatmap-picker__list-version'>{beatmap.version}</span>
                {' '}
                {this.props.renderMappers(beatmap)}
              </span>
            </a>
          ))}
        </div>
      </Portal>
    );
  }
}
