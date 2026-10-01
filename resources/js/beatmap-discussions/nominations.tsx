// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import BeatmapsOwnerEditor from 'beatmap-discussions/beatmaps-owner-editor';
import LoveBeatmapDialog from 'beatmap-discussions/love-beatmap-dialog';
import { Nominator } from 'beatmap-discussions/nominator';
import PlainTextPreview from 'beatmap-discussions/plain-text-preview';
import BigButton from 'components/big-button';
import Modal from 'components/modal';
import StringWithComponent from 'components/string-with-component';
import TimeWithTooltip from 'components/time-with-tooltip';
import UserLink from 'components/user-link';
import BeatmapsetDiscussionsStore from 'interfaces/beatmapset-discussions-store';
import BeatmapsetEventJson from 'interfaces/beatmapset-event-json';
import BeatmapsetWithDiscussionsJson from 'interfaces/beatmapset-with-discussions-json';
import { route } from 'laroute';
import { action, makeObservable, observable, runInAction } from 'mobx';
import { observer } from 'mobx-react';
import { deletedUser, deletedUserJson } from 'models/user';
import moment from 'moment';
import core from 'osu-core-singleton';
import * as React from 'react';
import { onError } from 'utils/ajax';
import { canModeratePosts, makeUrl, startingPost } from 'utils/beatmapset-discussion-helper';
import { classWithModifiers } from 'utils/css';
import { formatNumber } from 'utils/html';
import { joinComponents, trans, transExists } from 'utils/lang';
import { presence } from 'utils/string';
import { wikiUrl } from 'utils/url';
import DiscussionsState from './discussions-state';

const bn = 'beatmap-discussion-nominations';
const flashClass = 'js-flash-border--on';
export const hypeExplanationClass = 'js-hype--explanation';
interface Props {
  discussionsState: DiscussionsState;
  // TomYum: more buttons at the end of the button bar (subscribe, the beatmap's info page).
  endButtons?: React.ReactNode;
  // TomYum: and at its start (the favourite).
  startButtons?: React.ReactNode;
  store: BeatmapsetDiscussionsStore;
}

// TomYum: the statuses on the design proposal's path to ranking (hype train, nominations,
// qualified, ranked); others (graveyard, loved) have no path.
const pathStatuses = new Set(['wip', 'pending', 'qualified', 'approved', 'ranked']);

type XhrType = 'delete' | 'discussionLock' | 'removeFromLoved';

function discussionIdFromEvent(event: BeatmapsetEventJson) {
  return event.comment != null
    && typeof event.comment === 'object'
    && 'beatmap_discussion_id' in event.comment
    ? event.comment.beatmap_discussion_id
    : null;
}

function formatDate(date: string | null) {
  return moment(date).format('LL');
}

@observer
export class Nominations extends React.Component<Props> {
  private hypeFocusTimeout: number | undefined;
  @observable private showBeatmapsOwnerEditor = false;
  @observable private showLoveBeatmapDialog = false;
  @observable private readonly xhr: Partial<Record<XhrType, JQuery.jqXHR<BeatmapsetWithDiscussionsJson>>> = {};

  private get beatmapset() {
    return this.props.discussionsState.beatmapset;
  }

  private get discussions() {
    return this.props.store.discussions;
  }

  private get events() {
    return this.beatmapset.events;
  }

  private get isQualified() {
    return this.beatmapset.status === 'qualified';
  }

  private get userCanDisqualify() {
    return core.currentUser != null && (core.currentUser.is_admin || core.currentUser.is_moderator || core.currentUser.is_full_bn);
  }

  private get userIsOwner() {
    return core.currentUser != null && (core.currentUser.id === this.beatmapset.user_id);
  }

  private get users() {
    return this.props.store.users;
  }

  constructor(props: Props) {
    super(props);

    makeObservable(this);
  }

  componentWillUnmount() {
    Object.values(this.xhr).forEach((xhr) => xhr?.abort());
    window.clearTimeout(this.hypeFocusTimeout);
  }

  // TomYum: the design proposal's layout: the path to ranking (hype train, nominations, qualified,
  // ranked, with who hyped and nominated), the status messages, then the button bar.
  render() {
    return (
      <div className={classWithModifiers(bn, 'v2')}>
        {this.renderBeatmapsOwnerEditor()}
        {this.renderLoveBeatmapDialog()}
        {this.renderPathToRanking()}
        <div className={`${bn}__items ${bn}__items--messages`}>
          <div className={`${bn}__item`}>{this.renderStatusMessage()}</div>
          <div className={`${bn}__item`}>{this.renderDisqualificationMessage()}</div>
          <div className={`${bn}__item`}>{this.renderNominationResetMessage()}</div>
          <div className={`${bn}__item`}>{this.renderDiscussionLockMessage()}</div>
        </div>
        <div className={`${bn}__actions`}>
          <div className={`${bn}__actions-group`}>
            {this.props.startButtons}
            {this.renderFeedbackButton()}
            {this.renderDisqualifyButton()}
            <Nominator
              discussionsState={this.props.discussionsState}
              store={this.props.store}
            />
            {this.renderDiscussionLockButton()}
            {this.renderLoveButton()}
            {this.renderRemoveFromLovedButton()}
            {this.renderDeleteButton()}
            {this.renderBeatmapsOwnerEditorButton()}
          </div>
          {this.props.endButtons != null && (
            <div className={`${bn}__actions-group ${bn}__actions-group--end`}>{this.props.endButtons}</div>
          )}
        </div>
      </div>
    );
  }

  @action
  private readonly delete = () => {
    if (this.xhr.delete != null) return;

    const message = this.userIsOwner
      ? trans('beatmaps.nominations.delete_own_confirm')
      : trans('beatmaps.nominations.delete_other_confirm');

    if (!confirm(message)) return;

    this.xhr.delete = $.ajax(
      route('beatmapsets.destroy', { beatmapset: this.beatmapset.id }),
      { method: 'DELETE' },
    )
      .done(() => Turbo.visit(route('users.show', { user: this.beatmapset.user_id })))
      .fail(onError)
      .always(action(() => {
        this.xhr.delete = undefined;
      }));
  };

  @action
  private readonly discussionLock = () => {
    if (this.xhr.discussionLock != null) return;

    const reason = presence(prompt(trans('beatmaps.discussions.lock.prompt.lock')));

    if (reason == null) return;

    this.xhr.discussionLock = $.ajax(
      route('beatmapsets.discussion-lock', { beatmapset: this.beatmapset.id }),
      { data: { reason }, method: 'POST' },
    );

    this.xhr.discussionLock
      .done((beatmapset) => runInAction(() => {
        this.props.discussionsState.update({ beatmapset });
      }))
      .fail(onError)
      .always(action(() => {
        this.xhr.discussionLock = undefined;
      }));
  };

  @action
  private readonly discussionUnlock = () => {
    if (this.xhr.discussionLock != null) return;

    if (!confirm(trans('beatmaps.discussions.lock.prompt.unlock'))) return;

    this.xhr.discussionLock = $.ajax(
      route('beatmapsets.discussion-unlock', { beatmapset: this.beatmapset.id }),
      { method: 'POST' },
    );

    this.xhr.discussionLock
      .done((beatmapset) => runInAction(() => {
        this.props.discussionsState.update({ beatmapset });
      }))
      .fail(onError)
      .always(action(() => {
        this.xhr.discussionLock = undefined;
      }));
  };

  @action
  private readonly focusHypeInput = () => {
    // switch to generalAll tab, set current filter to praises
    this.props.discussionsState.changeFilter('praises');
    this.props.discussionsState.changeDiscussionPage('generalAll');

    this.hypeFocusTimeout = window.setTimeout(() => {
      this.focusNewDiscussion(() => {
        const selector = `.${hypeExplanationClass}`;
        document.querySelector(selector)?.classList.add(flashClass);
        // flash border of hype description to emphasize input is required
        this.hypeFocusTimeout = window.setTimeout(() => {
          document.querySelector(selector)?.classList.remove(flashClass);
        }, 1000);
      });
    }, 0);
  };

  private focusNewDiscussion(this: void, callback?: () => void) {
    const inputBox = $('.js-hype--input');
    inputBox.trigger('focus');

    // ensure input box is in view and focus it
    $.scrollTo(inputBox, 200, {
      interrupt: true,
      offset: -100,
      onAfter: callback,
    });
  }

  @action
  private readonly focusNewDiscussionWithModeSwitch = () => {
    // Switch to generalAll tab just in case currently in event tab
    // and thus new discussion box isn't visible.
    if (this.props.discussionsState.currentPage === 'events' || this.props.discussionsState.currentPage === 'reviews') {
      this.props.discussionsState.changeDiscussionPage('generalAll');
    }

    window.setTimeout(() => {
      this.focusNewDiscussion();
    }, 0);
  };

  private readonly handlePendingProblemsClick = (event: React.SyntheticEvent<HTMLElement>) => {
    event.preventDefault();
    this.props.discussionsState.changeFilter('pending');
  };

  @action
  private readonly handleToggleBeatmapsOwnerEditor = () => {
    this.showBeatmapsOwnerEditor = !this.showBeatmapsOwnerEditor;
  };

  @action
  private readonly handleToggleLoveBeatmapDialog = () => {
    this.showLoveBeatmapDialog = !this.showLoveBeatmapDialog;
  };

  private parseEventData(event: BeatmapsetEventJson) {
    const user = this.users.get(event.user_id) ?? deletedUserJson;
    const discussionId = discussionIdFromEvent(event);
    const discussion = this.discussions.get(discussionId);
    const post = discussion != null ? startingPost(discussion) : null;

    let link: React.ReactNode;
    let message: React.ReactNode;

    // extra conditionals are for typing.
    if (discussion != null && post != null && !post.system) {
      link = <a className='js-beatmap-discussion--jump' href={makeUrl({ discussion })}>#{discussion.id}</a>;
      message = <PlainTextPreview markdown={post.message} />;
    } else {
      link = discussionId != null ? `#${discussionId}` : null;
      message = trans('beatmaps.nominations.reset_message_deleted');
    }

    return { discussion, link, message, user };
  }

  @action
  private readonly removeFromLoved = () => {
    if (this.xhr.removeFromLoved != null) return;

    const reason = presence(prompt(trans('beatmaps.nominations.remove_from_loved_prompt')));

    if (reason == null) return;

    this.xhr.removeFromLoved = $.ajax(
      route('beatmapsets.remove-from-loved', { beatmapset: this.beatmapset.id }),
      { data: { reason }, method: 'DELETE' },
    );

    this.xhr.removeFromLoved
      .done((beatmapset) => runInAction(() => {
        this.props.discussionsState.update({ beatmapset });
      }))
      .fail(onError)
      .always(action(() => {
        this.xhr.removeFromLoved = undefined;
      }));
  };

  private renderBeatmapsOwnerEditor() {
    if (!this.showBeatmapsOwnerEditor) return;

    return (
      <Modal>
        <BeatmapsOwnerEditor
          beatmapset={this.beatmapset}
          discussionsState={this.props.discussionsState}
          onClose={this.handleToggleBeatmapsOwnerEditor}
          users={this.props.store.users}
        />
      </Modal>
    );
  }

  private renderBeatmapsOwnerEditorButton() {
    if (!this.beatmapset.current_user_attributes.can_beatmap_update_owner) return;

    return (
      <BigButton
        icon='fas fa-pen'
        props={{
          onClick: this.handleToggleBeatmapsOwnerEditor,
        }}
        text={trans('beatmap_discussions.owner_editor.button')}
      />
    );
  }

  private renderDeleteButton() {
    if (!this.beatmapset.current_user_attributes.can_delete) return;

    return (
      <BigButton
        icon='fas fa-trash'
        isBusy={this.xhr.delete != null}
        modifiers='danger'
        props={{
          onClick: this.delete,
        }}
        text={trans('beatmaps.nominations.delete')}
      />
    );
  }

  private renderDiscussionLockButton() {
    if (!canModeratePosts()) return;

    const { buttonProps, lockAction } = this.beatmapset.discussion_locked
      ? {
        buttonProps: {
          icon: 'fas fa-unlock',
          props: {
            onClick: this.discussionUnlock,
          },
        },
        lockAction: 'unlock',
      } : {
        buttonProps: {
          icon: 'fas fa-lock',
          props: {
            onClick: this.discussionLock,
          },
        },
        lockAction: 'lock',
      };

    return (
      <BigButton
        {...buttonProps}
        isBusy={this.xhr.discussionLock != null}
        modifiers='warning'
        text={trans(`beatmaps.discussions.lock.button.${lockAction}`)}
      />
    );
  }

  private renderDiscussionLockMessage() {
    if (!this.beatmapset.discussion_locked) return;

    for (let i = this.events.length - 1; i >= 0; i--) {
      const event = this.events[i];
      if (event.type === 'discussion_lock') {
        return trans('beatmapset_events.event.discussion_lock', { text: event.comment.reason });
      }
    }
  }

  private renderDisqualificationMessage() {
    const showHype = this.beatmapset.can_be_hyped;
    const disqualification = this.beatmapset.nominations.disqualification;

    if (!showHype || this.isQualified || disqualification == null) return;

    return <div>{this.renderResetReason(disqualification)}</div>;
  }

  private renderDisqualifyButton() {
    if (!(this.isQualified && this.userCanDisqualify)) return;

    return (
      <BigButton
        icon='fas fa-thumbs-down'
        modifiers='warning'
        props={{
          onClick: this.focusNewDiscussionWithModeSwitch,
        }}
        text={trans('beatmaps.nominations.disqualify')}
      />
    );
  }

  private renderFeedbackButton() {
    if (core.currentUser == null || this.userIsOwner || this.beatmapset.can_be_hyped || this.beatmapset.discussion_locked) {
      return null;
    }

    return (
      <BigButton
        icon='fas fa-bullhorn'
        props={{
          onClick: this.focusNewDiscussionWithModeSwitch,
        }}
        text={trans('beatmaps.feedback.button')}
      />
    );
  }

  private renderLoveBeatmapDialog() {
    if (!this.showLoveBeatmapDialog) return;

    return (
      <Modal>
        <LoveBeatmapDialog
          beatmapset={this.beatmapset}
          discussionsState={this.props.discussionsState}
          onClose={this.handleToggleLoveBeatmapDialog}
        />
      </Modal>
    );
  }

  private renderLoveButton() {
    if (!this.beatmapset.current_user_attributes.can_love) return;

    return (
      <BigButton
        icon='fas fa-heart'
        modifiers='pink'
        props={{
          onClick: this.handleToggleLoveBeatmapDialog,
        }}
        text={trans('beatmaps.nominations.love')}
      />
    );
  }

  private renderNominationResetMessage() {
    const nominationReset = this.beatmapset.nominations.nomination_reset;

    if (!this.beatmapset.can_be_hyped || this.isQualified || nominationReset == null) return;

    return <div>{this.renderResetReason(nominationReset)}</div>;
  }

  // TomYum: the design proposal's path to ranking: the Hype! button, then the hype train (its
  // carriages fill with hypes), the nominations, qualified and ranked, each lit once reached, with
  // who hyped and who nominated under them.
  private renderPathToRanking() {
    const beatmapset = this.beatmapset;
    if (!pathStatuses.has(beatmapset.status)) return null;

    const state = this.props.discussionsState;
    const qualified = beatmapset.status === 'qualified' || beatmapset.status === 'ranked' || beatmapset.status === 'approved';
    const ranked = beatmapset.status === 'ranked' || beatmapset.status === 'approved';

    const hypeRequired = beatmapset.hype?.required ?? 0;
    const hypeCount = state.totalHypeCount;
    const hypeSegments = Math.max(1, Math.min(10, hypeRequired));
    const hypeFilled = qualified || hypeRequired === 0
      ? hypeSegments
      : Math.min(hypeSegments, Math.floor(hypeSegments * hypeCount / hypeRequired));

    const nominationsRequired = state.nominationsCount('required');
    const nominationsCurrent = state.nominationsCount('current');
    const nominationSegments = Math.max(1, nominationsRequired);
    const nominationsFilled = qualified ? nominationSegments : Math.min(nominationSegments, nominationsCurrent);

    const hypers = [...this.discussions.values()]
      .filter((discussion) => discussion != null && discussion.message_type === 'hype' && discussion.deleted_at == null)
      .map((discussion) => this.users.get(discussion?.user_id))
      .filter((user) => user != null);
    const nominators = state.nominators.length > 0
      ? state.nominators.map((user) => <UserLink key={user.id} user={user} />)
      // TomYum: nominators known only from osu!, linked to their osu! profiles.
      : (beatmapset.tomyum_nominators ?? []).map((user) => (
        <a key={user.id} href={`https://osu.ppy.sh/users/${user.id}`} rel='noreferrer' target='_blank'>{user.username}</a>
      ));

    const canHype = beatmapset.can_be_hyped && core.currentUser != null && !this.userIsOwner;

    return (
      <div className={`${bn}__path`}>
        {canHype && (
          <button
            className={`${bn}__hype-button`}
            disabled={!beatmapset.current_user_attributes.can_hype}
            onClick={this.focusHypeInput}
            title={beatmapset.current_user_attributes.can_hype_reason ?? undefined}
            type='button'
          >
            {state.hasCurrentUserHyped ? trans('beatmaps.hype.button_done') : 'Hype!'}
          </button>
        )}

        <div className={`${bn}__stages`}>
          {beatmapset.hype != null && this.renderStage(
            'hype',
            trans('beatmaps.hype.section_title'),
            // the count only while it's being hyped (osu!'s hypes aren't imported)
            qualified ? null : `${formatNumber(hypeCount)}/${formatNumber(hypeRequired)}`,
            hypeSegments,
            hypeFilled,
            hypers.length > 0 ? joinComponents(hypers.map((user) => <UserLink key={user?.id} user={user ?? deletedUserJson} />)) : null,
          )}
          {this.renderStage(
            'nominations',
            trans('beatmaps.nominations.title'),
            // the count only while it's being nominated (osu! doesn't tell older nominations)
            qualified ? null : `${formatNumber(nominationsCurrent)}/${formatNumber(nominationsRequired)}`,
            nominationSegments,
            nominationsFilled,
            nominators.length > 0 ? joinComponents(nominators) : null,
          )}
          {this.renderStage('qualified', trans('beatmapsets.show.status.qualified'), null, 1, qualified ? 1 : 0, null)}
          {this.renderStage('ranked', trans('beatmapsets.show.status.ranked'), null, 1, ranked ? 1 : 0, null)}
        </div>
      </div>
    );
  }

  private renderStage(key: string, label: string, value: string | null, segments: number, filled: number, names: React.ReactNode) {
    return (
      <div className={classWithModifiers(`${bn}__stage`, key, { reached: filled >= segments })} style={{ '--segments': segments } as React.CSSProperties}>
        <div className={`${bn}__stage-label`}>
          {label}
          {value != null && <span className={`${bn}__stage-value`}>{value}</span>}
        </div>
        <div className={`${bn}__stage-segments`}>
          {Array.from({ length: segments }, (_, i) => (
            <span key={i} className={classWithModifiers(`${bn}__segment`, { filled: i < filled })} />
          ))}
        </div>
        {names != null && <div className={`${bn}__stage-names`}>{names}</div>}
      </div>
    );
  }

  private renderRemoveFromLovedButton() {
    if (!this.beatmapset.current_user_attributes.can_remove_from_loved) return;

    return (
      <BigButton
        icon='fas fa-heart-broken'
        isBusy={this.xhr.removeFromLoved != null}
        modifiers='danger'
        props={{
          onClick: this.removeFromLoved,
        }}
        text={trans('beatmaps.nominations.remove_from_loved')}
      />
    );
  }

  private renderResetReason(event: BeatmapsetEventJson) {
    if (event.type === 'disqualify' && typeof event.comment !== 'object') {
      const reason = event.comment != null
        ? <PlainTextPreview markdown={event.comment} />
        : trans('beatmaps.nominations.disqualified_no_reason');

      return (
        <StringWithComponent
          mappings={{
            reason,
            time_ago: <TimeWithTooltip dateTime={event.created_at} relative />,
          }}
          pattern={trans('beatmaps.nominations.disqualified_at')}
        />
      );
    }

    const parsedEvent = this.parseEventData(event);

    return (
      <StringWithComponent
        mappings={{
          discussion: parsedEvent.link,
          message: parsedEvent.message,
          time_ago: <TimeWithTooltip dateTime={event.created_at} relative />,
          user: <UserLink user={parsedEvent.user ?? deletedUser} />,
        }}
        pattern={trans(`beatmaps.nominations.reset_at.${event.type}`)}
      />
    );
  }

  private renderStatusMessage() {
    switch (this.beatmapset.status) {
      case 'approved':
      case 'loved':
      case 'ranked':
        return trans(`beatmaps.discussions.status-messages.${this.beatmapset.status}`, { date: formatDate(this.beatmapset.ranked_date) });
      case 'graveyard':
        return trans('beatmaps.discussions.status-messages.graveyard', { date: formatDate(this.beatmapset.last_updated) });
      case 'wip':
        return trans('beatmaps.discussions.status-messages.wip');
      case 'qualified': {
        const rankingEta = this.beatmapset.nominations.ranking_eta;
        const date = rankingEta != null
          // TODO: remove after translations are updated
          ? transExists('beatmaps.nominations.rank_estimate.on')
            ? trans('beatmaps.nominations.rank_estimate.on', { date: formatDate(rankingEta) })
            : formatDate(rankingEta)
          : trans('beatmaps.nominations.rank_estimate.soon');

        const firstUnresolvedDiscussion = this.props.discussionsState.firstUnresolvedDiscussion;

        return (
          <>
            <StringWithComponent
              mappings={{
                date,
                // TODO: ranking_queue_position should not be nullable when status is qualified.
                position: formatNumber(this.beatmapset.nominations.ranking_queue_position ?? 0),
                queue: (
                  <a
                    href={wikiUrl('Beatmap_ranking_procedure/Ranking_queue')}
                    rel='noreferrer'
                    target='_blank'
                  >
                    {trans('beatmaps.nominations.rank_estimate.queue')}
                  </a>
                ),
              }}
              pattern={trans('beatmaps.nominations.rank_estimate._')}
            />
            {firstUnresolvedDiscussion != null &&
              <div className={`${bn}__problems`}>
                <StringWithComponent
                  mappings={{
                    problems: <a
                      className='js-beatmap-discussion--jump'
                      href={makeUrl({
                        discussion: firstUnresolvedDiscussion,
                        filter: 'pending',
                      })}
                      onClick={this.handlePendingProblemsClick}
                    >
                      {trans('beatmaps.nominations.rank_estimate.problems')}
                    </a>,
                  }}
                  pattern={trans('beatmaps.nominations.rank_estimate.unresolved_problems')}
                />
              </div>
            }
          </>
        );
      }
    }

    return null;
  }
}
