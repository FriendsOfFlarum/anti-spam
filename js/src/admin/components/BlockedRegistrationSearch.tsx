import app from 'flarum/admin/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

const PREFIX = 'fof-anti-spam.admin.blocked_registrations.filters';

export interface BlockedRegistrationFilter {
  key: string;
  example: string;
  description: string | null;
  values: string[];
  extension: string | null;
}

export interface BlockedRegistrationSearchAttrs extends ComponentAttrs {
  /** The current query, so the parent owns the state. */
  query: string;
  loading: boolean;
  onsubmit: (query: string) => void;
}

/**
 * A `key:value` search box for the blocked registrations list.
 *
 * Modelled on flarum/audit's AuditBrowser: one input rather than a row of dropdowns, with the
 * available filters advertised by the backend so the syntax is discoverable — clickable chips,
 * a help panel, and value autocomplete for the filters with a known value set.
 *
 * @see AuditBrowser in flarum/audit
 */
export default class BlockedRegistrationSearch extends Component<BlockedRegistrationSearchAttrs> {
  q: string = '';
  caret: number = 0;
  showHelp: boolean = false;
  autocompleteFocused: boolean = false;

  oninit(vnode: Mithril.Vnode<BlockedRegistrationSearchAttrs, this>) {
    super.oninit(vnode);

    this.q = this.attrs.query ?? '';
  }

  filters(): BlockedRegistrationFilter[] {
    return (app.forum.attribute('fof-anti-spam.filters') as BlockedRegistrationFilter[] | undefined) || [];
  }

  view(): Mithril.Children {
    return (
      <div className="BlockedRegistrationSearch">
        <div className="BlockedRegistrationSearch-row">
          <div className="BlockedRegistrationSearch-wrapper">
            <input
              className="FormControl"
              value={this.q}
              placeholder={extractText(app.translator.trans(`${PREFIX}.placeholder`))}
              oninput={(event: InputEvent) => {
                const el = event.target as HTMLInputElement;
                this.q = el.value;
                this.caret = el.selectionStart ?? el.value.length;
              }}
              onkeyup={(event: KeyboardEvent) => {
                this.caret = (event.target as HTMLInputElement).selectionStart ?? this.q.length;

                if (event.key === 'Enter') this.submit();
              }}
              onclick={(event: MouseEvent) => {
                this.caret = (event.target as HTMLInputElement).selectionStart ?? this.q.length;
              }}
              onfocus={() => {
                this.autocompleteFocused = true;
              }}
              onblur={() => {
                // Delayed so a click on a suggestion lands before the dropdown closes.
                setTimeout(() => {
                  this.autocompleteFocused = false;
                  m.redraw();
                }, 150);
              }}
            />
            {this.q ? (
              <Button
                className="Search-clear Button Button--icon Button--link"
                icon="fas fa-times-circle"
                aria-label={extractText(app.translator.trans(`${PREFIX}.clear`))}
                onclick={() => {
                  this.q = '';
                  this.submit();
                }}
              />
            ) : null}
            {this.autocomplete()}
          </div>

          <Button className="Button" loading={this.attrs.loading} onclick={() => this.submit()}>
            {app.translator.trans(`${PREFIX}.apply`)}
          </Button>
        </div>

        {this.filterHints()}
      </div>
    );
  }

  submit(): void {
    this.attrs.onsubmit(this.q.trim());
  }

  /**
   * The whitespace-delimited token the caret currently sits in.
   */
  activeToken(): { start: number; end: number; text: string } {
    const start = this.q.lastIndexOf(' ', this.caret - 1) + 1;
    let end = this.q.indexOf(' ', this.caret);

    if (end === -1) end = this.q.length;

    return { start, end, text: this.q.slice(start, end) };
  }

  /**
   * Value suggestions for the filter being typed, for those that advertise a value set.
   */
  autocompleteValues(): { key: string; values: string[] } | null {
    if (!this.autocompleteFocused) return null;

    const { text } = this.activeToken();
    // A leading '-' negates, and must not stop the key from being recognised.
    const match = text.match(/^-?([a-zA-Z]+):(.*)$/);

    if (!match) return null;

    const [, key, partial] = match;
    const filter = this.filters().find((f) => f.key === key);

    if (!filter?.values.length) return null;

    const needle = partial.toLowerCase();
    const values = filter.values.filter((value) => value.toLowerCase().includes(needle));

    return values.length ? { key, values } : null;
  }

  autocomplete(): Mithril.Children {
    const group = this.autocompleteValues();

    if (!group) return null;

    return (
      <ul className="BlockedRegistrationSearch-autocomplete">
        {group.values.map((value) => (
          <li>
            <button type="button" className="BlockedRegistrationSearch-suggestion" onclick={() => this.applySuggestion(group.key, value)}>
              <code>{value}</code>
            </button>
          </li>
        ))}
      </ul>
    );
  }

  applySuggestion(key: string, value: string): void {
    const { start, end, text } = this.activeToken();
    const negate = text.startsWith('-') ? '-' : '';
    const completed = `${negate}${key}:${value}`;

    this.q = this.q.slice(0, start) + completed + this.q.slice(end);
    this.caret = start + completed.length;

    this.focusInput();
    this.autocompleteFocused = true;
    m.redraw();
  }

  filterHints(): Mithril.Children {
    const filters = this.filters();

    if (!filters.length) return null;

    return (
      <div className="BlockedRegistrationSearch-filters">
        <div className="BlockedRegistrationSearch-quick">
          <span className="BlockedRegistrationSearch-label">{app.translator.trans(`${PREFIX}.hint`)}</span>
          {filters.map((filter) => this.filterChip(filter))}
          <Button
            className="Button Button--text BlockedRegistrationSearch-toggle"
            icon={this.showHelp ? 'fas fa-caret-down' : 'fas fa-caret-right'}
            onclick={() => (this.showHelp = !this.showHelp)}
          >
            {app.translator.trans(`${PREFIX}.help.toggle`)}
          </Button>
        </div>
        {this.showHelp ? this.filterHelp(filters) : null}
      </div>
    );
  }

  filterHelp(filters: BlockedRegistrationFilter[]): Mithril.Children {
    return (
      <div className="BlockedRegistrationSearch-help">
        <dl className="BlockedRegistrationSearch-list">
          {filters.map((filter) => [
            <dt>{this.filterChip(filter)}</dt>,
            <dd>
              {filter.description ? app.translator.trans(filter.description) : null}
              {filter.values.length ? (
                <span className="BlockedRegistrationSearch-values">
                  {filter.values.map((value) => (
                    <Button className="Button BlockedRegistrationSearch-filter" onclick={() => this.applyExample(`${filter.key}:${value}`)}>
                      <code>{value}</code>
                    </Button>
                  ))}
                </span>
              ) : null}
            </dd>,
          ])}
        </dl>
        <ul className="BlockedRegistrationSearch-syntax">
          <li>{app.translator.trans(`${PREFIX}.help.multiple`)}</li>
          <li>{app.translator.trans(`${PREFIX}.help.negate`)}</li>
        </ul>
      </div>
    );
  }

  /**
   * Filters advertising a value set open their suggestions; the rest drop their example
   * straight into the box.
   */
  filterChip(filter: BlockedRegistrationFilter): Mithril.Children {
    const autocompletes = filter.values.length > 0;
    const label = autocompletes ? `${filter.key}:` : filter.example;

    return (
      <Button
        className="Button BlockedRegistrationSearch-filter"
        icon={autocompletes ? 'fas fa-list-ul' : undefined}
        onclick={() => (autocompletes ? this.openAutocomplete(filter.key) : this.applyExample(filter.example))}
      >
        <code>{label}</code>
      </Button>
    );
  }

  applyExample(example: string): void {
    this.q = example;
    this.caret = example.length;

    this.focusInput();
    this.submit();
  }

  openAutocomplete(key: string): void {
    const prefix = `${key}:`;

    this.q = prefix;
    this.caret = prefix.length;
    this.autocompleteFocused = true;

    this.focusInput();
    m.redraw();
  }

  private focusInput(): void {
    const input = this.element?.querySelector<HTMLInputElement>('.FormControl');

    if (input) {
      input.focus();
      input.setSelectionRange(this.caret, this.caret);
    }
  }
}
