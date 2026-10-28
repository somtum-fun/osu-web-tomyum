// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import Links from 'profile-page/links';
import * as React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import testCurrentUserJson from '../test-current-user-json';

describe('profile-page/links join_date', () => {
  afterEach(() => {
    delete window.firstMembersBefore;
  });

  it('shows normal join month when firstMembersBefore is unset', () => {
    window.firstMembersBefore = null;
    const html = renderToStaticMarkup(
      <Links user={{ ...testCurrentUserJson, id: 2, join_date: '2024-07-15T12:00:00Z' }} />,
    );

    expect(html).not.toContain('Here since the beginning');
    expect(html).toContain('Joined');
  });

  it('shows "Here since the beginning" only for users joined before firstMembersBefore', () => {
    window.firstMembersBefore = '2024-08-01';

    const beforeHtml = renderToStaticMarkup(
      <Links user={{ ...testCurrentUserJson, id: 2, join_date: '2024-07-31T23:59:59Z' }} />,
    );
    const onOrAfterHtml = renderToStaticMarkup(
      <Links user={{ ...testCurrentUserJson, id: 3, join_date: '2024-08-01T00:00:00Z' }} />,
    );

    expect(beforeHtml).toContain('Here since the beginning');
    expect(beforeHtml).toContain('title="2024-07-31T23:59:59.000Z"');
    expect(onOrAfterHtml).not.toContain('Here since the beginning');
    expect(onOrAfterHtml).toContain('Joined');
  });
});

