// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

// TomYum: 'create-group' makes a group chat (the chat design's Groups).
export type AddChannelType = 'create' | 'create-group' | 'join';
type ChannelId = number | AddChannelType | null;

export default ChannelId;
