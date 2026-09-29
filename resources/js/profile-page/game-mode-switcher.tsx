// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import PlaymodeTabs from 'components/playmode-tabs';
import StringWithComponent from 'components/string-with-component';
import Ruleset, { rulesets } from 'interfaces/ruleset';
import { route } from 'laroute';
import { action, observable, makeObservable } from 'mobx';
import { observer } from 'mobx-react';
import * as React from 'react';
import { onErrorWithCallback } from 'utils/ajax';
import { trans } from 'utils/lang';
import Controller from './controller';

interface Props {
  controller: Controller;
}

@observer
export default class GameModeSwitcher extends React.Component<Props> {
  @observable private settingDefault = false;

  constructor(props: Props) {
    super(props);

    makeObservable(this);
  }

  render() {
    if (this.props.controller.state.user.is_bot) {
      return null;
    }

    return (
      <>
        {this.renderSetDefault()}
        {/* TomYum: the Relax/Autopilot pills sit with the mode icons, on their left. */}
        <div style={{ alignItems: 'center', display: 'flex', gap: 14, marginLeft: 'auto' }}>
          {this.renderVariants()}
          <PlaymodeTabs
          currentMode={this.props.controller.currentMode}
          defaultMode={this.props.controller.state.user.playmode}
          entries={rulesets.map((mode) => ({
            disabled: false,
            href: route('users.show', { mode, user: this.props.controller.state.user.id }),
            mode,
          }))}
          />
        </div>
      </>
    );
  }

  // TomYum: Relax and Autopilot have their own stats and plays (lazer shows them as modes too).
  private renderVariants() {
    const mode = this.props.controller.currentMode;
    const variants: Partial<Record<Ruleset, [string, string, string][]>> = {
      fruits: [['RX', 'fruitsrx', 'Relax']],
      osu: [['RX', 'osurx', 'Relax'], ['AP', 'osuap', 'Autopilot']],
      taiko: [['RX', 'taikorx', 'Relax']],
    };
    const list = variants[mode];
    if (list == null) {
      return null;
    }

    const shown = this.props.controller.variant ?? mode;
    const user = this.props.controller.state.user.id;

    return (
      <div style={{ alignItems: 'center', display: 'flex', gap: 6 }}>
        {[['Vanilla', mode], ...list.map(([, target, title]) => [title, target])].map(([label, target]) => {
          const active = shown === target;

          return (
            <a
              key={target}
              href={route('users.show', { mode: target, user })}
              style={{
                background: active ? 'hsl(var(--hsl-h1))' : 'hsl(var(--hsl-b4))',
                borderRadius: 999,
                color: active ? 'hsl(var(--hsl-b6))' : 'hsl(var(--hsl-c1))',
                fontSize: 12,
                fontWeight: 600,
                lineHeight: '20px',
                padding: '2px 12px',
                textDecoration: 'none',
                whiteSpace: 'nowrap',
              }}
            >
              {label}
            </a>
          );
        })}
      </div>
    );
  }

  private renderSetDefault() {
    if (!this.props.controller.withEdit || this.props.controller.state.user.playmode === this.props.controller.currentMode) {
      return null;
    }

    return (
      <div className='profile-page-button'>
        <button
          className='profile-page-button__button'
          disabled={this.settingDefault}
          onClick={this.setDefault}
          type='button'
        >
          <StringWithComponent
            mappings={{
              mode: <strong>{trans(`beatmaps.mode.${this.props.controller.currentMode}`)}</strong>,
            }}
            pattern={trans('users.show.edit.default_playmode.set')}
          />
        </button>
      </div>
    );
  }

  @action
  private readonly setDefault = () => {
    this.settingDefault = true;

    this.props.controller.apiSetDefaultGameMode()
      .always(action(() => {
        this.settingDefault = false;
      })).fail(onErrorWithCallback(this.setDefault));
  };
}
