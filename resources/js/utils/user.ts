// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import UserJson from 'interfaces/user-json';
import { route } from 'laroute';

// TomYum: card: the hover card's numbers too (card_statistics; looking up one player).
export function apiLookupUsers(idsOrUsernames: (string | null | undefined)[], excludeBots?: boolean, card?: boolean) {
  return $.ajax(route('users.lookup'), {
    data: { card, exclude_bots: excludeBots, ids: idsOrUsernames },
    dataType: 'json',
  }) as JQuery.jqXHR<{ users: UserJson[] }>;
}
