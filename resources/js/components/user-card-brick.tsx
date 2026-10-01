// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import UserJson from 'interfaces/user-json';
import UserRelationJson from 'interfaces/user-relation-json';
import { route } from 'laroute';
import { computed, makeObservable } from 'mobx';
import { observer } from 'mobx-react';
import core from 'osu-core-singleton';
import * as React from 'react';
import UserCardTypeContext from 'user-card-type-context';
import LevelHexagon from './level-hexagon';
import { classWithModifiers, groupColour, Modifiers } from 'utils/css';

interface Props {
  modifiers?: Modifiers;
  onRemoveClick?: (user: UserJson) => void;
  playerCard?: boolean; // TomYum: osu!'s player card design (the friends page)
  user: UserJson;
}

@observer
export default class UserCardBrick extends React.Component<Props> {
  static readonly contextType = UserCardTypeContext;

  declare context: React.ContextType<typeof UserCardTypeContext>;

  private readonly ref = React.createRef<HTMLDivElement>();

  @computed
  private get friendModifier() {
    if (core.currentUser?.friends == null) return;

    const friendState = core.currentUser.friends.find((friend: UserRelationJson) => friend.target_id === this.props.user.id);

    if (friendState != null) {
      if (friendState.mutual) return 'mutual';

      if (!this.context.isFriendsPage) return 'friend';
    }
  }

  // TomYum: the player card brick shows friend status everywhere, the friends page too: green when
  // they haven't added you back, pink when mutual (osu-web's colours).
  @computed
  private get friendState() {
    const friend = core.currentUser?.friends?.find((relation: UserRelationJson) => relation.target_id === this.props.user.id);

    return friend == null ? undefined : (friend.mutual ? 'mutual' : 'friend');
  }

  constructor(props: Props) {
    super(props);

    makeObservable(this);
  }

  componentWillUnmount() {
    $.publish('user-card:remove', this.ref.current);
  }

  render() {
    if (this.props.playerCard) {
      return this.renderPlayerCard();
    }

    const blockClass = classWithModifiers(
      'user-card-brick',
      this.props.modifiers,
      this.friendModifier,
    );

    const group = this.props.user.groups != null && this.props.user.groups.length > 0
      ? this.props.user.groups[0]
      : undefined;

    return (
      <div ref={this.ref} className={`js-usercard ${blockClass}`} data-user-id={this.props.user.id}>
        <a
          className='user-card-brick__link'
          href={route('users.show', { user: this.props.user.id })}
        >
          <div
            className='user-card-brick__group-bar'
            style={groupColour(group)}
            title={group?.name}
          />

          <div className='user-card-brick__username u-ellipsis-overflow'>
            {this.props.user.username}
          </div>
        </a>
        {this.props.onRemoveClick != null && (
          <button className='user-card-brick__remove' onClick={this.handleRemoveClick}>
            <span className='fas fa-times' />
          </button>
        )}
      </div>
    );
  }

  private readonly handleRemoveClick = () => {
    this.props.onRemoveClick?.(this.props.user);
  };

  // TomYum: osu!'s "Player Card, Bricks Normal" design (the friends page's brick view): the cover in
  // the player's colour, avatar, group colour, level, name, group and friend status, 40px high.
  private renderPlayerCard() {
    const user = this.props.user;
    const group = user.groups?.[0];
    const level = user.statistics?.level;
    const url = route('users.show', { user: user.id });
    const style = {
      ...groupColour(group),
      '--card-hue': user.profile_hue ?? undefined,
      '--cover': user.cover?.url == null ? undefined : `url('${user.cover.url}')`,
    } as React.CSSProperties;

    return (
      // The whole brick opens the hover card (like osu-web's brick), not only its avatar and name: the
      // gaps between them didn't, and the tooltip's cleanup on removal looks for it on this element.
      <div
        ref={this.ref}
        className={`js-usercard ${classWithModifiers('player-card', 'list', 'brick', this.props.modifiers)}`}
        data-user-id={user.id}
        style={style}
      >
        <div className='player-card__info'>
          <a className='u-contents' href={url}>
            <span className='player-card__avatar' style={{ backgroundImage: `url('${user.avatar_url}')` }} />
          </a>
          <div className='player-card__group-bar' title={group?.name} />
          {level != null && <LevelHexagon level={level.current} modifiers='small' progress={level.progress} />}
          <a className='player-card__username u-ellipsis-overflow' href={url}>
            {user.username}
          </a>
          {group != null && (
            <span className='player-card__pill player-card__pill--group' style={groupColour(group)} title={group.name}>
              {group.short_name}
            </span>
          )}
          {this.friendState != null && (
            <span
              className={classWithModifiers('player-card__pill', 'friend', { mutual: this.friendState === 'mutual' })}
              title={this.friendState === 'mutual' ? 'Mutual friend' : "Friend (hasn't added you back)"}
            >
              <span className={`fas ${this.friendState === 'mutual' ? 'fa-user-friends' : 'fa-user'}`} />
            </span>
          )}
          {this.props.onRemoveClick != null && (
            <button className='player-card__remove' onClick={this.handleRemoveClick} type='button'>
              <span className='fas fa-times' />
            </button>
          )}
        </div>
      </div>
    );
  }
}
