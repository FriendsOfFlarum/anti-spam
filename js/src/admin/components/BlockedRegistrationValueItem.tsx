import { NestedStringArray } from '@askvortsov/rich-icu-message-formatter';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';

interface CustomAttrs extends ComponentAttrs {
  label: string | NestedStringArray;
  value: string | Mithril.Children | null;
}

export default class BlockedRegistrationValueItem extends Component<CustomAttrs> {
  label!: string | NestedStringArray;
  value!: string | Mithril.Children | null;

  oninit(vnode: Mithril.Vnode<CustomAttrs, this>) {
    super.oninit(vnode);

    this.label = this.attrs.label;
    this.value = this.attrs.value;
  }

  view() {
    return (
      <div className="BlockedRegistrations-item--details">
        <span className="BlockedRegistrations-label">{this.label}</span>
        <span className="BlockedRegistrations-value">{this.value}</span>
      </div>
    );
  }
}
