// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import Main from 'activity-index/main';
import core from 'osu-core-singleton';
import * as React from 'react';
import { parseJson } from 'utils/json';

core.reactTurbolinks.register('activity-index', () => (
  <Main {...parseJson('json-activity')} />
));
