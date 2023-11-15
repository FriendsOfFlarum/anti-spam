import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Link from 'flarum/common/components/Link';

export default class AntiSpamSettingsPage extends ExtensionPage {
  content() {
    const apiRegions = ['closest', 'europe', 'us'];

    return (
      <div className="FoFAntiSpamSettings">
        <div className="container">
          <div className="Form">
            <div className="Introduction">
              <p className="helpText">
                {app.translator.trans('fof-anti-spam.admin.settings.introduction', {
                  a: <Link href="https://stopforumspam.com" target="_blank" external={true} />,
                })}
              </p>
            </div>
            <hr />
            {this.buildSettingComponent({
              type: 'select',
              setting: 'fof-anti-spam.regionalEndpoint',
              options: apiRegions.reduce((o, p) => {
                o[p] = app.translator.trans(`fof-anti-spam.admin.settings.region_${p}_label`);

                return o;
              }, {}),
              label: app.translator.trans('fof-anti-spam.admin.settings.regional_endpoint_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.regional_endpoint_help'),
              default: 'closest',
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.username',
              label: app.translator.trans('fof-anti-spam.admin.settings.username_label'),
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.ip',
              label: app.translator.trans('fof-anti-spam.admin.settings.ip_label'),
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.email',
              label: app.translator.trans('fof-anti-spam.admin.settings.email_label'),
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.emailhash',
              label: app.translator.trans('fof-anti-spam.admin.settings.email_hash_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.email_hash_help'),
            })}
            {this.buildSettingComponent({
              type: 'number',
              setting: 'fof-anti-spam.frequency',
              label: app.translator.trans('fof-anti-spam.admin.settings.frequency_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.frequency_help'),
              placeholder: '5',
              required: true,
            })}
            {this.buildSettingComponent({
              type: 'number',
              setting: 'fof-anti-spam.confidence',
              label: app.translator.trans('fof-anti-spam.admin.settings.confidence_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.confidence_help'),
              min: 0,
              max: 100,
              placeholder: '50.0',
              required: true,
            })}
            <hr />
            <p className="helpText">{app.translator.trans('fof-anti-spam.admin.settings.api_key_text')}</p>
            {this.buildSettingComponent({
              type: 'string',
              setting: 'fof-anti-spam.api_key',
              label: app.translator.trans('fof-anti-spam.admin.settings.api_key_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.api_key_instructions_text', {
                register: <a href="https://www.stopforumspam.com/forum/register.php" />,
                key: <a href="https://www.stopforumspam.com/keys" />,
              }),
            })}
            <hr />
            {this.submitButton()}
          </div>
        </div>
      </div>
    );
  }
}
