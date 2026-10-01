// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import { observer } from 'mobx-react';
import core from 'osu-core-singleton';
import * as React from 'react';
import { classWithModifiers, Modifiers } from 'utils/css';
import { trans } from 'utils/lang';

// TomYum: a player's level in osu!'s hexagon (player cards). The outline fills clockwise from the top
// as they get through the level, in their level's colours: a new metal or gem every 10 levels (and a
// shimmering prism from 100), or osu-web's level tiers (the profile's; Radiant and Lustrous shimmer).
// Which one is the viewer's preference (level_colours).

interface Colours {
  name: string;
  shimmer?: boolean;
  stops: string[];
}

const gems: Colours[] = [
  { name: 'Iron', stops: ['#c9c3bd', '#8f8984'] },
  { name: 'Copper', stops: ['#f2b48f', '#b8653f'] },
  { name: 'Bronze', stops: ['#d6a77f', '#8a5a3a'] },
  { name: 'Silver', stops: ['#eef0f7', '#9fa3bd'] },
  { name: 'Gold', stops: ['#fff0a8', '#e0b632'] },
  { name: 'Jade', stops: ['#a8f5d2', '#2fb88a'] },
  { name: 'Sapphire', stops: ['#a8ccff', '#3f6fe0'] },
  { name: 'Amethyst', stops: ['#dcb0ff', '#8a4fe0'] },
  { name: 'Ruby', stops: ['#ffb0c0', '#e03f5f'] },
  { name: 'Diamond', stops: ['#ffffff', '#9eeeff'] },
];
const prism: Colours = { name: 'Prism', shimmer: true, stops: ['#ff9be0', '#7ef0ff', '#fff27a', '#ff9be0'] };

// osu-web's tiers (layout.less --level-tier-*, user-level.tsx).
const tiers: [number, Colours][] = [
  [110, { name: 'Lustrous', shimmer: true, stops: ['#ffe600', '#ed82ff', '#ffe600'] }],
  [105, { name: 'Radiant', shimmer: true, stops: ['#97dcff', '#ed82ff', '#97dcff'] }],
  [100, { name: 'Rhodium', stops: ['#d9f8d3', '#a0cf96'] }],
  [80, { name: 'Platinum', stops: ['#a8f0ef', '#52e0df'] }],
  [60, { name: 'Gold', stops: ['#f0e4a8', '#e0c952'] }],
  [40, { name: 'Silver', stops: ['#e0e0eb', '#a3a3c2'] }],
  [20, { name: 'Bronze', stops: ['#b88f7a', '#855c47'] }],
  [0, { name: 'Iron', stops: ['#bab3ab', '#bab3ab'] }],
];

export function levelColours(level: number, scheme: 'gems' | 'tiers'): Colours {
  if (scheme === 'tiers') {
    return (tiers.find(([from]) => level >= from) ?? tiers[tiers.length - 1])[1];
  }

  return level >= 100 ? prism : gems[Math.max(0, Math.min(9, Math.floor(level / 10)))];
}

// The profile's rounded hexagon (user-level.less's clip path) as a line along the middle of its ring,
// in a 50 × 50 box: it starts at the top corner so the progress runs clockwise from there.
const radius = 10.65; // the corners' rounding
const toCorner = 13; // from the middle to the centre of a corner's rounding
const path = (() => {
  const at = (corner: number, offset: number) => {
    const angle = (Math.PI / 180) * (-90 + corner * 60);
    const turn = (Math.PI / 180) * (-90 + corner * 60 + offset);

    return `${(25 + toCorner * Math.cos(angle) + radius * Math.cos(turn)).toFixed(2)},${(25 + toCorner * Math.sin(angle) + radius * Math.sin(turn)).toFixed(2)}`;
  };
  const arc = `A ${radius} ${radius} 0 0 1`;

  let d = `M ${at(0, 0)} ${arc} ${at(0, 30)}`;
  for (let corner = 1; corner < 6; corner++) {
    d += ` L ${at(corner, -30)} ${arc} ${at(corner, 30)}`;
  }

  return `${d} L ${at(0, -30)} ${arc} ${at(0, 0)}`;
})();

interface Props {
  level: number;
  modifiers?: Modifiers;
  progress?: number;
}

// Each hexagon's gradient needs its own id on the page (React 17 has no useId).
let nextId = 0;

const LevelHexagon = observer(({ level, modifiers, progress }: Props) => {
  const [id] = React.useState(() => `level-hexagon-${nextId++}`);
  const colours = levelColours(level, core.userPreferences.get('level_colours'));
  const filled = Math.max(0, Math.min(100, progress ?? 100));

  return (
    <div className={classWithModifiers('level-hexagon', modifiers)} title={trans('users.show.stats.level', { level })}>
      <svg className='level-hexagon__shape' viewBox='0 0 50 50'>
        <defs>
          <linearGradient id={id} x1='0' x2='1' y1='0' y2='1'>
            {colours.stops.map((colour, i) => (
              <stop key={i} offset={i / Math.max(1, colours.stops.length - 1)} stopColor={colour} />
            ))}
            {colours.shimmer && (
              <animateTransform
                attributeName='gradientTransform'
                dur='6s'
                from='0 0.5 0.5'
                repeatCount='indefinite'
                to='360 0.5 0.5'
                type='rotate'
              />
            )}
          </linearGradient>
        </defs>
        <path className='level-hexagon__track' d={path} />
        <path
          className='level-hexagon__progress'
          d={path}
          pathLength={100}
          stroke={`url(#${id})`}
          strokeDasharray={`${filled} 100`}
        />
      </svg>
      <span className='level-hexagon__level'>{level}</span>
    </div>
  );
});

export default LevelHexagon;
