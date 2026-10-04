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
        {/* TomYum: the Relax/Autopilot pills sit with the mode icons, on their left: one pinned group (the
            icons are pinned to the right on their own otherwise, and would overlap anything next to them). */}
        <div className='game-mode'>
          {this.renderVariants()}
          <PlaymodeTabs
            currentMode={this.props.controller.currentMode}
            defaultMode={this.props.controller.state.user.playmode}
            entries={rulesets.map((mode) => ({
              disabled: false,
              href: route('users.show', { mode, user: this.props.controller.state.user.id }),
              mode,
            }))}
            modifiers='inline'
          />
        </div>
      </>
    );
  }

  // TomYum: Relax and Autopilot have their own stats and plays (lazer shows them as modes too).
  // Each pill also carries a star you can click (your own profile only) to pin that mode as a
  // favourite; favourites get a gold border so other players see which modes a user cares about.
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
    const favourites = this.props.controller.state.user.tomyum_favorite_modes ?? [];
    const canEdit = this.props.controller.withEdit;

    return (
      <div style={{ alignItems: 'center', display: 'flex', gap: 6 }}>
        {[['Vanilla', mode], ...list.map(([, target, title]) => [title, target])].map(([label, target]) => {
          const active = shown === target;
          const favourite = favourites.includes(target);

          return (
            <span
              key={target}
              style={{
                alignItems: 'center',
                background: active ? 'hsl(var(--hsl-h1))' : 'hsl(var(--hsl-b4))',
                border: favourite ? '1px solid hsl(var(--hsl-h1))' : '1px solid transparent',
                borderRadius: 999,
                color: active ? 'hsl(var(--hsl-b6))' : 'hsl(var(--hsl-c1))',
                display: 'inline-flex',
                fontSize: 12,
                fontWeight: 600,
                gap: 4,
                lineHeight: '20px',
                padding: '1px 10px',
                whiteSpace: 'nowrap',
              }}
            >
              <a
                href={route('users.show', { mode: target, user })}
                style={{ color: 'inherit', textDecoration: 'none' }}
              >
                {label}
              </a>
              {(canEdit || favourite) && (
                <button
                  aria-label={favourite ? trans('users.show.edit.favourite_mode.unstar') : trans('users.show.edit.favourite_mode.star')}
                  disabled={!canEdit || this.favouriting === target}
                  onClick={canEdit ? () => this.toggleFavourite(target) : undefined}
                  style={{
                    background: 'transparent',
                    border: 0,
                    color: favourite ? 'hsl(var(--hsl-y1, 44 100% 60%))' : 'inherit',
                    cursor: canEdit ? 'pointer' : 'default',
                    fontSize: 11,
                    opacity: favourite ? 1 : 0.6,
                    padding: 0,
                    pointerEvents: canEdit ? 'auto' : 'none',
                  }}
                  title={favourite ? trans('users.show.edit.favourite_mode.unstar') : trans('users.show.edit.favourite_mode.star')}
                  type='button'
                >
                  <i className={favourite ? 'fas fa-star' : 'far fa-star'} />
                </button>
              )}
            </span>
          );
        })}
      </div>
    );
  }

  @observable private favouriting: string | null = null;

  @action
  private readonly toggleFavourite = (mode: string) => {
    this.favouriting = mode;
    this.props.controller.apiToggleFavoriteMode(mode)
      .always(action(() => { this.favouriting = null; }))
      .fail(onErrorWithCallback(() => this.toggleFavourite(mode)));
  };

  private renderSetDefault() {
    const { controller } = this.props;
    // TomYum: Relax/Autopilot can be the default too.
    const isDefault = controller.state.user.playmode === controller.currentMode
      && (controller.state.user.tomyum_default_variant ?? null) === controller.variant;

    if (!controller.withEdit || isDefault) {
      return null;
    }

    const variantTitle = controller.variant == null ? '' : ` ${controller.variant.endsWith('ap') ? 'Autopilot' : 'Relax'}`;

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
              mode: <strong>{trans(`beatmaps.mode.${controller.currentMode}`)}{variantTitle}</strong>,
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
