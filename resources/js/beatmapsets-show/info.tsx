// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import BbcodeEditor, { OnChangeProps } from 'components/bbcode-editor';
import Modal from 'components/modal';
import { BeatmapsetJsonForShow } from 'interfaces/beatmapset-extended-json';
import { route } from 'laroute';
import { action, makeObservable, observable, runInAction } from 'mobx';
import { observer } from 'mobx-react';
import * as React from 'react';
import { onErrorWithClick } from 'utils/ajax';
import Controller from './controller';
import MetadataEditor from './metadata-editor';

interface Props {
  controller: Controller;
}

@observer
export default class Info extends React.Component<Props> {
  private readonly descriptionEditorRef = React.createRef<BbcodeEditor>();
  @observable private isEditingDescription = false;
  @observable private isEditingMetadata = false;
  @observable private saveDescriptionXhr: JQuery.jqXHR<BeatmapsetJsonForShow> | null = null;

  private get controller() {
    return this.props.controller;
  }


  private get withEditDescription() {
    return this.controller.beatmapset.description.bbcode != null;
  }

  private get withEditMetadata() {
    return this.controller.beatmapset.current_user_attributes.can_edit_metadata;
  }

  constructor(props: Props) {
    super(props);

    makeObservable(this);
  }

  componentWillUnmount() {
    this.saveDescriptionXhr?.abort();
  }

  render() {
    return (
      <div className='beatmapset-info u-fancy-scrollbar'>
        {this.isEditingDescription &&
          <Modal onClose={this.handleCloseDescriptionEditor}>
            <div className='osu-page'>
              <BbcodeEditor
                key={this.controller.beatmapset.id /* ensure component is reset if beatmapset changes */}
                ref={this.descriptionEditorRef}
                disabled={this.saveDescriptionXhr != null}
                ignoreEsc
                modifiers='beatmapset-description-editor'
                onChange={this.handleEditorChange}
                rawValue={this.controller.beatmapset.description.bbcode ?? ''}
              />
            </div>
          </Modal>
        }

        {this.isEditingMetadata &&
          <Modal onClose={this.handleCloseMetadataEditor}>
            <MetadataEditor controller={this.props.controller} onClose={this.handleCloseMetadataEditor} />
          </Modal>
        }

        {/* TomYum: the design proposal's description card; the details, success rate and points of
            failure are in the header now. */}
        <div className='beatmapset-info__box beatmapset-info__box--description'>
          <div
            className='beatmapset-info__description'
            dangerouslySetInnerHTML={{
              // without the blank lines a post starts or ends with, so the card's spacing stays even
              __html: (this.controller.beatmapset.description.description ?? '')
                .replace(/^((?:<div[^>]*>\s*)*)(?:\s|<br\s*\/?>)+/, '$1')
                .replace(/(?:\s|<br\s*\/?>)+((?:<\/div>\s*)*)$/, '$1'),
            }}
          />
          <div className='beatmapset-info__edit-buttons'>
            {this.withEditMetadata && this.renderEditMetadataButton()}
            {this.withEditDescription && this.renderEditDescriptionButton()}
          </div>
        </div>
      </div>
    );
  }

  @action
  private readonly handleCloseDescriptionEditor = () => {
    if (this.descriptionEditorRef.current == null) {
      this.isEditingDescription = false;
    } else {
      this.descriptionEditorRef.current.cancel();
    }
  };

  @action
  private readonly handleCloseMetadataEditor = () => {
    this.isEditingMetadata = false;
  };

  @action
  private readonly handleEditorChange = (change: OnChangeProps) => {
    switch (change.type) {
      case 'cancel':
        this.isEditingDescription = false;
        break;
      case 'save':
        if (change.hasChanged) {
          this.saveDescription(change);
        } else {
          this.isEditingDescription = false;
        }
        break;
    }
  };

  @action
  private readonly onEditDescriptionClick = () => {
    this.isEditingDescription = !this.isEditingDescription;
  };

  @action
  private readonly onEditMetadataClick = () => {
    this.isEditingMetadata = !this.isEditingMetadata;
  };

  private renderEditDescriptionButton() {
    return (
      <div className='beatmapset-info__edit-button'>
        <button
          className='btn-circle'
          onClick={this.onEditDescriptionClick}
          type='button'
        >
          <span className='btn-circle__content'>
            <span className='fas fa-pencil-alt' />
          </span>
        </button>
      </div>
    );
  }

  private renderEditMetadataButton() {
    return (
      <div className='beatmapset-info__edit-button'>
        <button
          className='btn-circle'
          onClick={this.onEditMetadataClick}
          type='button'
        >
          <span className='btn-circle__content'>
            <span className='fas fa-pencil-alt' />
          </span>
        </button>
      </div>
    );
  }

  @action
  private saveDescription({ event, value }: OnChangeProps) {
    if (this.saveDescriptionXhr != null) return;

    this.saveDescriptionXhr = $.ajax(route('beatmapsets.update', { beatmapset: this.controller.beatmapset.id }), {
      data: { description: value },
      method: 'PATCH',
    });

    this.saveDescriptionXhr.done((beatmapset) => runInAction(() => {
      this.isEditingDescription = false;
      this.controller.state.beatmapset = beatmapset;
    })).fail(
      onErrorWithClick(event?.target),
    ).always(action(() => {
      this.saveDescriptionXhr = null;
    }));
  }
}
