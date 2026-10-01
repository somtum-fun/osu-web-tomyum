// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import UserJson from 'interfaces/user-json';
import { action, autorun, computed, makeObservable, observable } from 'mobx';
import { present } from 'utils/string';
import { v4 as uuidv4 } from 'uuid';
import { maxMessageLength } from './channel';

interface LocalStorageProps extends Record<InputKey, string> {
  validUsers: number[];
}

const inputKeys = ['description', 'message', 'name', 'users'] as const;
type InputKey = typeof inputKeys[number];

// TomYum: the form also makes group chats (the chat design's Groups): only the name and the players
// are needed there.
export type CreateChannelKind = 'announcement' | 'group';

const localStorageKeys: Record<CreateChannelKind, string> = {
  announcement: 'createAnnouncement',
  group: 'createGroup',
};

export const maxLengths = Object.freeze({
  description: 255,
  message: maxMessageLength,
  name: 50,
  users: undefined,
});

export function isInputKey(key: string): key is InputKey {
  return (inputKeys as readonly string[]).includes(key);
}

// This class is owned by ChatStateStore
export default class CreateAnnouncement {
  @observable inputs: Record<InputKey, string>;
  @observable showError: Record<InputKey, boolean>;
  @observable validUsers = new Map<number, UserJson>();

  private initialized = false;
  private readonly uuid = uuidv4();

  @computed
  get errors() {
    return {
      description: !this.isValidLength('description', true),
      message: !this.isValidLength('message', this.kind === 'group'),
      name: !this.isValidLength('name'),
      users: this.validUsers.size === 0
        || present(this.inputs.users.trim()), // implies invalid ids left
    };
  }

  @computed
  get isValid() {
    return !Object.values(this.errors).some(Boolean);
  }

  get propsForUsernameInput() {
    return {
      initialUsers: [...this.validUsers.values()],
      initialValue: this.inputs.users,
    };
  }

  private get localStorageKey() {
    return localStorageKeys[this.kind];
  }

  constructor(readonly kind: CreateChannelKind = 'announcement') {
    this.inputs = this.resetInputs();
    this.showError = this.resetErrors();

    makeObservable(this);
  }

  @action
  clear() {
    this.resetErrors();
    this.resetInputs();
    this.validUsers.clear();
    // localStorage key not removed because the currently the autorun will fill it again with empty values.
  }

  @action
  initialize() {
    if (this.initialized) return;

    const saved = localStorage.getItem(this.localStorageKey);
    if (saved != null) {
      try {
        // TODO: validate props
        const json = JSON.parse(saved) as LocalStorageProps;

        this.inputs.description = json.description;
        this.inputs.message = json.message;
        this.inputs.name = json.name;
        this.inputs.users = [...json.validUsers, json.users].join(',');
      } catch (_error) {
        console.error('invalid json in localStorage');
        localStorage.removeItem(this.localStorageKey);
      }
    }

    autorun(() => {
      const props: LocalStorageProps = {
        ...this.inputs,
        validUsers: [...this.validUsers.keys()],
      };

      // TODO: don't save if 'empty'?
      localStorage.setItem(this.localStorageKey, JSON.stringify(props));
    });

    this.initialized = true;
  }

  inputContainerPropsFor(name: InputKey) {
    return {
      hasError: this.errors[name],
      input: this.inputs[name],
      maxLength: maxLengths[name],
      showError: this.showError[name],
    };
  }

  toJson() {
    const { description, message, name } = this.inputs;

    return {
      channel: { description, name },
      message,
      target_ids: [...this.validUsers.keys()],
      type: this.kind === 'group' ? 'GROUP' as const : 'ANNOUNCE' as const,
      uuid: this.uuid,
    };
  }

  private isValidLength(key: Exclude<InputKey, 'users'>, allowEmpty = false) {
    return (allowEmpty || present(this.inputs[key])) && this.inputs[key].length <= maxLengths[key];
  }

  @action
  private resetErrors() {
    return this.showError = {
      description: false,
      message: false,
      name: false,
      users: false,
    };
  }

  @action
  private resetInputs() {
    return this.inputs = {
      description: '',
      message: '',
      name: '',
      users: '',
    };
  }
}
