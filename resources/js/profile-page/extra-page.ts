// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import Ruleset from 'interfaces/ruleset';
import { ProfileExtraPage } from 'interfaces/user-extended-json';
import UserJson from 'interfaces/user-json';
import { route } from 'laroute';
import { OffsetPaginationJson } from 'utils/offset-paginator';

export interface PageSectionJson<T> {
  count: number;
  items: T[];
  pagination: OffsetPaginationJson;
}

// TODO: basically OffsetPaginatorJson now
export interface PageSectionWithoutCountJson<T> {
  items: T[];
  pagination: OffsetPaginationJson;
}

// TODO: how to require mode conditionally based on page?
export default function getPage<T>(user: Pick<UserJson, 'id'>, page: ProfileExtraPage, mode?: Ruleset, variant?: string | null) {
  // TomYum: a Relax/Autopilot profile asks for its variant's lists.
  const params = variant == null ? { mode, page, user: user.id } : { mode, page, user: user.id, variant };
  return $.ajax(route('users.extra-page', params)) as JQuery.jqXHR<T>;
}
