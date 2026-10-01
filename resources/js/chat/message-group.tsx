// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import UserLink from 'components/user-link';
import Reportable from 'interfaces/reportable';
import { observer } from 'mobx-react';
import Message from 'models/chat/message';
import * as moment from 'moment';
import core from 'osu-core-singleton';
import * as React from 'react';
import { classWithModifiers } from 'utils/css';
import MessageItem from './message-item';

interface Props {
  messages: Message[];
}

// TomYum: the colours osu!lazer gives chat usernames, picked by the user's id, so everyone keeps
// the same one.
const usernameColours = [
  '#588c7e', '#b2a367', '#c98f65', '#bc5151', '#5c8bd6', '#7f6ab7', '#a368ad', '#aa6880',
  '#6fad9b', '#f2e394', '#f2ae72', '#f98f8a', '#7daef4', '#a691f2', '#c894d3', '#d895b0',
  '#53c4a1', '#eace5c', '#ea8c47', '#fc4f4f', '#3d94ea', '#7760ea', '#af52c6', '#e25696',
  '#677c66', '#9b8732', '#8c5129', '#8c3030', '#1f5d91', '#4335a5', '#812a96', '#992861',
];

function usernameColour(userId: number) {
  return usernameColours[Math.abs(userId) % usernameColours.length];
}

function reportable(message: Message) {
  if (typeof message.messageId !== 'number') {
    return undefined;
  }

  return {
    id: message.messageId.toString(),
    type: 'message',
  } satisfies Reportable;
}

// TomYum: messages as in osu!'s chat design: one line each, the time, the sender's name in their
// colour (a bot's on a pill) and the message.
@observer
export default class MessageGroup extends React.Component<Props> {
  render(): React.ReactNode {
    const messages = this.props.messages;

    if (messages.length === 0) {
      return;
    }

    const sender = messages[0].sender;

    return (
      <div
        className={classWithModifiers('chat-message-group', {
          bot: sender.isBot,
          own: sender.id === core.currentUser?.id,
        })}
        style={{ '--username-colour': usernameColour(sender.id) } as React.CSSProperties}
      >
        {messages.map((message) => (
          <div key={message.uuid} className='chat-message-group__line'>
            <time
              className='chat-message-group__timestamp'
              dateTime={message.timestamp}
              title={moment(message.timestamp).format('LLL')}
            >
              {moment(message.timestamp).format('HH:mm:ss')}
            </time>
            <div className='chat-message-group__sender'>
              <UserLink
                cardSize='compact'
                className='chat-message-group__username u-ellipsis-overflow'
                reportable={reportable(message)}
                tooltipPosition='top center'
                user={sender}
              >
                {sender.username}
              </UserLink>
            </div>
            <div className='chat-message-group__message'>
              <MessageItem message={message} />
            </div>
          </div>
        ))}
      </div>
    );
  }
}
