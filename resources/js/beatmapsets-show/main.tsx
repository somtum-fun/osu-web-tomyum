// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import Comments from 'components/comments';
import HeaderV4 from 'components/header-v4';
import NotificationBanner from 'components/notification-banner';
import PlaymodeTabs from 'components/playmode-tabs';
import StringWithComponent from 'components/string-with-component';
import Ruleset, { rulesets } from 'interfaces/ruleset';
import { action, autorun, computed, IReactionDisposer, makeObservable, observable, untracked } from 'mobx';
import { observer } from 'mobx-react';
import core from 'osu-core-singleton';
import * as React from 'react';
import { generate, setHash } from 'utils/beatmapset-page-hash';
import { trans } from 'utils/lang';
import { reloadPage } from 'utils/turbolinks';
import Controller from './controller';
import Header from './header';
import headerLinks from './header-links';
import Info from './info';
import NsfwWarning from './nsfw-warning';
import ScoreboardMain from './scoreboard/main';

interface Props {
  container: HTMLElement;
}

@observer
export default class Main extends React.Component<Props> {
  @observable private readonly controller: Controller;
  private setHashDisposer?: IReactionDisposer;

  @computed
  private get showEnableLazerModeLink() {
    // enabling lazer mode requires full page reload.
    return this.controller.currentBeatmap.lazer_only && untracked(() => core.userPreferences.get('legacy_score_only'));
  }

  @computed
  private get headerLinksAppend() {
    if (this.controller.state.showingNsfwWarning) return null;

    const entries = rulesets.map((ruleset) => {
      const beatmaps = this.controller.beatmaps.get(ruleset) ?? [];
      const mainCount = beatmaps.filter((b) => !b.convert).length;

      return {
        count: mainCount > 0 ? mainCount : undefined,
        disabled: beatmaps.length === 0,
        href: generate({ ruleset }),
        mode: ruleset,
      };
    });

    return (
      <PlaymodeTabs
        currentMode={this.controller.currentBeatmap.mode}
        entries={entries}
        modifiers='beatmapset'
        onClick={this.onClickPlaymode}
      />
    );
  }

  // TomYum: opened from the Comments tab (#comments): scrolled to them once the page is up (the
  // hash is the difficulty's afterwards).
  private readonly openComments = window.location.hash === '#comments';
  private readonly commentsRef = React.createRef<HTMLDivElement>();

  constructor(props: Props) {
    super(props);

    this.controller = new Controller(this.props.container);

    makeObservable(this);
  }

  componentDidMount() {
    if (this.openComments) {
      // again once the scoreboard and images above them have loaded and moved them down
      core.reactTurbolinks.runAfterPageLoad(() => {
        window.setTimeout(this.scrollToComments, 100);
        window.setTimeout(this.scrollToComments, 1200);
      });
    }
    this.setHashDisposer = autorun(this.setHash);
    $(document).one('turbo:before-cache', () => this.setHashDisposer?.());
  }

  componentWillUnmount() {
    this.setHashDisposer?.();
    this.controller.destroy();
  }

  render() {
    return (
      <div className='osu-layout osu-layout--full'>
        {this.renderDeletedNotification()}
        {this.renderPageHeader()}
        {this.controller.state.showingNsfwWarning
          ? <NsfwWarning onClose={this.onCloseNsfwWarning} />
          : this.renderPage()
        }
      </div>
    );
  }


  private handleEnableLazerMode(this: void, event: React.SyntheticEvent) {
    event.preventDefault();
    const request = core.userPreferences.set('legacy_score_only', false);

    if (request == null) {
      // the page is in weird state
      reloadPage();
    } else {
      request.done(reloadPage);
    }
  }

  @action
  private readonly onClickPlaymode = (e: React.MouseEvent, mode: Ruleset) => {
    e.preventDefault();

    this.controller.state.playmode = mode;
  };

  @action
  private readonly onCloseNsfwWarning = () => {
    this.controller.state.showingNsfwWarning = false;
  };

  private renderDeletedNotification() {
    if (this.controller.beatmapset.deleted_at == null) {
      return;
    }

    return (
      <NotificationBanner
        message={trans('beatmapsets.show.deleted_banner.message')}
        title={trans('beatmapsets.show.deleted_banner.title')}
        type='info'
      />
    );
  }

  private renderLazerOnlyMessage() {
    return (
      <div className='beatmapset-hype'>
        <div className='beatmapset-hype__box beatmapset-hype__box--description'>
          <div className='beatmapset-hype__description-row beatmapset-hype__description-row--status'>
            <div className='beatmapset-status beatmapset-status--lazer-only'>
              {trans('beatmapsets.show.lazer_only.title')}
            </div>
          </div>
          <p className='beatmapset-hype__description-row beatmapset-hype__description-row--current'>
            {trans('beatmapsets.show.lazer_only.description')}
          </p>
          {this.showEnableLazerModeLink && (
            <p className='beatmapset-hype__description-row'>
              <StringWithComponent
                mappings={{
                  enable_link: (
                    <a href='#' onClick={this.handleEnableLazerMode}>
                      {trans('beatmapsets.show.lazer_only.scoreboard_switch_mode.enable_link')}
                    </a>
                  ),
                }}
                pattern={trans('beatmapsets.show.lazer_only.scoreboard_switch_mode._')}
              />
            </p>
          )}
        </div>
      </div>
    );
  }

  private renderPage() {
    return (
      <>
        <div className='osu-page osu-page--generic-compact'>
          <Header controller={this.controller} />
          <Info controller={this.controller} />

          <div className='user-profile-pages user-profile-pages--no-tabs'>
            {/* TomYum: hyping and disqualifying are on the discussion page's path to ranking now
                (the design proposal has no box for them here). */}
            {this.controller.currentBeatmap.lazer_only && (
              <div className='page-extra page-extra--compact'>
                {this.renderLazerOnlyMessage()}
              </div>
            )}

            {this.controller.currentBeatmap.is_scoreable && !this.showEnableLazerModeLink &&
              <div className='page-extra'>
                <ScoreboardMain
                  beatmap={this.controller.currentBeatmap}
                  container={this.props.container}
                />
              </div>
            }

            <div ref={this.commentsRef} className='page-extra page-extra--compact' id='comments'>
              <Comments
                baseCommentableMeta={{
                  id: this.controller.beatmapset.id,
                  type: 'beatmapset',
                }}
                controllerStateSelector='#json-comments'
                modifiers='page-extra'
              />
            </div>
          </div>
        </div>
      </>
    );
  }

  // The Comments tab on this page just scrolls down to them.
  private readonly handleHeaderLinkClick = (e: React.MouseEvent<HTMLAnchorElement>) => {
    if (!e.currentTarget.href.endsWith('#comments')) return;

    e.preventDefault();
    this.scrollToComments();
  };

  private renderPageHeader() {
    return (
      <HeaderV4
        links={headerLinks('show', this.controller.beatmapset)}
        linksAppend={this.headerLinksAppend}
        onLinkClick={this.handleHeaderLinkClick}
        theme='beatmapset'
      />
    );
  }

  private readonly scrollToComments = () => {
    this.commentsRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  private readonly setHash = () => {
    setHash(generate({ beatmap: this.controller.currentBeatmap }));
  };
}
