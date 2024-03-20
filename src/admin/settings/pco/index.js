
import { __ } from '@wordpress/i18n';
import SettingsTab from './settings-tab';
import ConnectTab from './connect-tab';

// Ministry platform data
export default {
	name: 'Planning Center Online',
	tabs: [
		{
			name: __( 'Connect', 'cp-connect' ),
			component: (props) => <ConnectTab {...props} />,
			optionGroup: 'connect',
			defaultData: {
				app_id: '',
				secret: '',
				step: 0,
				authorized: false,
			}
		},
		{
			name: __( 'Settings', 'cp-connect' ),
			component: (props) => <SettingsTab {...props} />,
			optionGroup: 'settings',
			defaultData: {
				events_enabled: 0,
				events_register_button_enabled: 0,
				groups_enabled: 1
			}
		}
	]
}
