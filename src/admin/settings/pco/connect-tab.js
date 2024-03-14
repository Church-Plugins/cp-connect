import { __ } from '@wordpress/i18n'
import Typography from '@mui/material/Typography'
import TextField from '@mui/material/TextField'

export default function ConnectTab({ data, updateField }) {
	return (
		<div>
			<Typography variant="h5">{ __( 'PCO API Configuration', 'cp-connect' ) }</Typography>
			<Typography variant="body2">{ __( 'The following parameters are required to authenticate to the API and then execute API calls to Planning Center Online.', 'cp-connect' ) }</Typography>
			<Typography variant="body2">
				{ __( 'You can get your authentication credentials by ', 'cp-connect' ) }

				<a href="https://api.planningcenteronline.com/oauth/applications" target="_blank" rel="noreferrer noopener">
					{ __( 'clicking here' )}
				</a>

				{ __( ' and scrolling down to "Personal Access Tokens"', 'cp-connect' ) }
			</Typography>
			<div style={{ marginTop: '1rem' }}>
				<TextField sx={{ width: '400px' }} label={__( 'Application ID', 'cp-connect' )} value={data.app_id} onChange={(e) => updateField('app_id', e.target.value)} variant="outlined" />
			</div>
			<div style={{ marginTop: '1rem' }}>
				<TextField sx={{ width: '400px' }} label={__( 'Application Secret', 'cp-connect' )} value={data.secret} onChange={(e) => updateField('secret', e.target.value)} variant="outlined" />
			</div>
		</div>
	)
}

