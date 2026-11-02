// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import UserBadgeJson from 'interfaces/user-badge-json';
import { observer } from 'mobx-react';
import core from 'osu-core-singleton';
import * as React from 'react';
import { Modifiers, classWithModifiers } from 'utils/css';
import { formatNumber } from 'utils/html';
import Badge from './badge';
import Controller from './controller';

interface Props {
  badges: UserBadgeJson[];
  controller?: Controller;
  modifiers?: Modifiers;
}

interface State {
  canScrollLeft: boolean;
  canScrollRight: boolean;
}

// TomYum: the profile page proposal's badge row: how many badges, fixed at the start, then the
// badges in one row that scrolls sideways, with arrows at the ends while there's more that way.
@observer
export default class Badges extends React.PureComponent<Props, State> {
  static defaultProps = { badges: [] };

  readonly state: State = { canScrollLeft: false, canScrollRight: false };
  private readonly resizeObserver = new ResizeObserver(() => this.updateArrows());
  private readonly scrollerRef = React.createRef<HTMLDivElement>();

  componentDidMount() {
    if (this.scrollerRef.current != null) {
      this.resizeObserver.observe(this.scrollerRef.current);
    }
    this.updateArrows();
  }

  componentDidUpdate() {
    this.updateArrows();
  }

  componentWillUnmount() {
    this.resizeObserver.disconnect();
  }

  render() {
    if (this.props.badges.length === 0) return null;

    const controller = (this.props.controller?.withEdit ?? false) && core.userPreferences.get('profile_detail_v2')
      ? this.props.controller
      : undefined;

    return (
      <div className={classWithModifiers('profile-badges', this.props.modifiers)}>
        <div className='profile-badges__count'>
          <div className='profile-badges__count-label'>Badges</div>
          <div className='profile-badges__count-value'>{formatNumber(this.props.badges.length)}</div>
        </div>

        <div className='profile-badges__row'>
          <div ref={this.scrollerRef} className='profile-badges__scroller' onScroll={this.updateArrows}>
            {this.props.badges.map((badge) => (
              <span key={badge.image_url} className='profile-badges__item'>
                <Badge badge={badge} controller={controller} />
              </span>
            ))}
          </div>

          {this.state.canScrollLeft && (
            <button className='profile-badges__arrow profile-badges__arrow--left' onClick={this.handleScrollLeft} type='button'>
              <span className='fas fa-chevron-left' />
            </button>
          )}
          {this.state.canScrollRight && (
            <button className='profile-badges__arrow profile-badges__arrow--right' onClick={this.handleScrollRight} type='button'>
              <span className='fas fa-chevron-right' />
            </button>
          )}
        </div>
      </div>
    );
  }

  private readonly handleScrollLeft = () => this.scrollBy(-1);

  private readonly handleScrollRight = () => this.scrollBy(1);

  // Most of a row's width at a time, so the badge cut off at the edge comes fully into view.
  private scrollBy(direction: number) {
    const scroller = this.scrollerRef.current;
    if (scroller == null) return;

    scroller.scrollBy({ behavior: 'smooth', left: direction * scroller.clientWidth * 0.8 });
  }

  private readonly updateArrows = () => {
    const scroller = this.scrollerRef.current;
    if (scroller == null) return;

    const canScrollLeft = scroller.scrollLeft > 1;
    const canScrollRight = scroller.scrollLeft + scroller.clientWidth < scroller.scrollWidth - 1;

    if (canScrollLeft !== this.state.canScrollLeft || canScrollRight !== this.state.canScrollRight) {
      this.setState({ canScrollLeft, canScrollRight });
    }
  };
}
