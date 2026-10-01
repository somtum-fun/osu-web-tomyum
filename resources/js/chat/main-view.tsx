// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import HeaderV4 from 'components/header-v4';
import { autorun, makeObservable, runInAction } from 'mobx';
import { disposeOnUnmount, observer } from 'mobx-react';
import core from 'osu-core-singleton';
import * as React from 'react';
import { hideLoadingOverlay, showImmediateLoadingOverlay } from 'utils/loading-overlay';
import { ChatListFilter } from './chat-state-store';
import ConversationList from './conversation-list';
import ConversationPanel from './conversation-panel';

@observer
export default class MainView extends React.Component<Record<string, never>> {
  constructor(props: Record<string, never>) {
    super(props);

    makeObservable(this);

    disposeOnUnmount(
      this,
      autorun(() => {
        if (core.dataStore.chatState.isChatMounted) {
          // This keeps the body element (not the html element) from rubberbanding on mobile Safari
          // when scroll hits the end.
          document.documentElement.classList.add('u-chat');
        } else {
          document.documentElement.classList.remove('u-chat');
        }
      }),
    );

    disposeOnUnmount(
      this,
      autorun(() => {
        if (core.dataStore.chatState.isAddingChannel) {
          showImmediateLoadingOverlay();
        } else {
          hideLoadingOverlay();
        }
      }),
    );
  }

  componentDidMount() {
    runInAction(() => {
      core.dataStore.chatState.viewsMounted.add(this);
      if (core.dataStore.channelStore.channels.size === 0 && core.dataStore.chatState.selectedChannelOrType == null) {
        core.dataStore.chatState.selectChannel('join');
      }
    });
  }

  componentWillUnmount() {
    runInAction(() => {
      core.dataStore.chatState.viewsMounted.delete(this);
    });
  }

  render() {
    const chatState = core.dataStore.chatState;

    // TomYum: the chat design's tabs, which filter the conversation list (Groups only once there
    // are any), and its Join Chat button.
    const filters: [ChatListFilter, string][] = [
      ['all', 'All Chats'],
      ['channels', 'Channels'],
      ['pms', 'Direct Messages'],
    ];
    if (core.dataStore.channelStore.groupedChannels.GROUP.length > 0) {
      filters.push(['groups', 'Groups']);
    }

    const links = filters.map(([filter, title]) => ({
      active: chatState.listFilter === filter,
      data: { 'data-filter': filter },
      title,
      url: '#',
    }));

    return (
      <>
        <HeaderV4
          links={links}
          linksAppend={(
            <button className='chat-join-button' onClick={this.handleJoinClick} type='button'>
              Join Chat
            </button>
          )}
          onLinkClick={this.handleFilterClick}
          theme='chat'
        />
        <div className='chat osu-page osu-page--chat'>
          <ConversationList />
          <ConversationPanel />
        </div>
      </>
    );
  }

  private readonly handleFilterClick = (e: React.MouseEvent<HTMLAnchorElement>) => {
    e.preventDefault();
    const filter = e.currentTarget.dataset.filter;
    if (filter === 'all' || filter === 'channels' || filter === 'pms' || filter === 'groups') {
      runInAction(() => {
        core.dataStore.chatState.listFilter = filter;
      });
    }
  };

  private readonly handleJoinClick = () => {
    core.dataStore.chatState.selectChannel('join');
  };
}
